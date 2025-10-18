use rocket::fairing::AdHoc;
use rocket::fs::NamedFile;
use rocket::http::{Cookie, CookieJar, Status, Accept, ContentType};
use rocket::response::status::Created;
use rocket::response::Redirect;
use rocket::serde::{Deserialize, Serialize, json::Json};
use rocket::tokio::fs;
use rocket::{form::Form, fs::TempFile, State};
use rocket::{get, post, delete, launch, routes};
use rocket::FromForm;
use rocket::Either;
use rocket_dyn_templates::{Template, context};
use sqlx::{postgres::PgPoolOptions, PgPool};
use std::path::{Path, PathBuf};
use bcrypt::{hash, verify, DEFAULT_COST};
use chrono::Utc;
use uuid::Uuid;
use serde_json::json;
use redis::AsyncCommands;

type Result<T, E = rocket::response::Debug<sqlx::Error>> = std::result::Result<T, E>;

#[derive(Deserialize)]
struct AuthReq<'r> {
    login: &'r str,
    pwd: &'r str,
}

#[derive(Serialize)]
struct OkReply {
    ok: bool,
}

#[derive(Serialize)]
struct PrivateContext {
    login: String,
    uid: i32,
    files: Vec<PdfRow>,
    theme: String,
}

#[derive(Serialize)]
struct PdfRow {
    id: i32,
    filename: String,
    original_name: String,
    uploaded_at: String,
}

async fn init_pool() -> PgPool {
    let url = std::env::var("ROCKET_DATABASES")
        .ok()
        .and_then(|s| serde_json::from_str::<serde_json::Value>(&s).ok())
        .and_then(|v| v["postgres"]["url"].as_str().map(String::from))
        .unwrap_or_else(|| "postgres://postgres:postgres@db:5432/postgres".into());

    PgPoolOptions::new()
        .max_connections(5)
        .connect(&url)
        .await
        .expect("Cannot connect to PG")
}

async fn init_redis() -> redis::aio::ConnectionManager {
    let url = std::env::var("REDIS_URL")
        .unwrap_or_else(|_| "redis://redis:6379".into());
    let client = redis::Client::open(url)
        .expect("Cannot create Redis client");
    redis::aio::ConnectionManager::new(client)
        .await
        .expect("Cannot connect to Redis")
}

#[post("/login", data = "<body>")]
async fn login(
    cookies: &CookieJar<'_>,
    db: &State<PgPool>,
    redis: &State<redis::aio::ConnectionManager>,
    accept: &Accept,
    content_type: &ContentType,
    body: Json<AuthReq<'_>>
) -> Either<Redirect, Json<OkReply>> {
    let cache_key = format!("user:{}:hash", body.login);
    let mut redis_conn = redis.inner().clone();

    let cached_hash: Option<String> = redis_conn.get(&cache_key).await.ok();

    let (id, pwd_hash) = if let Some(hash) = cached_hash {
        let rec = sqlx::query_as::<_, (i32,)>("SELECT id FROM users WHERE login = $1")
            .bind(body.login)
            .fetch_optional(db.inner())
            .await
            .unwrap();
        if let Some((id,)) = rec {
            (Some(id), hash)
        } else {
            return Either::Right(Json(OkReply { ok: false }));
        }
    } else {
        let rec = sqlx::query_as::<_, (i32, String)>("SELECT id, pwd_hash FROM users WHERE login = $1")
            .bind(body.login)
            .fetch_optional(db.inner())
            .await
            .unwrap();
        if let Some((id, hash)) = rec {
            let _: Result<(), _> = redis_conn.set_ex(&cache_key, &hash, 3600).await;
            (Some(id), hash)
        } else {
            return Either::Right(Json(OkReply { ok: false }));
        }
    };

    if let Some(id) = id {
        if verify(body.pwd, &pwd_hash).unwrap_or(false) {
            let mut cookie = Cookie::new("uid", id.to_string());
            cookie.set_path("/");
            cookies.add_private(cookie);

            let is_json = content_type.is_json();
            let prefers_html = accept.to_string().contains("html");

            if is_json {
                return Either::Right(Json(OkReply { ok: true }));
            }

            if prefers_html {
                return Either::Left(Redirect::to("/private"));
            } else {
                return Either::Right(Json(OkReply { ok: true }));
            }
        }
    }
    Either::Right(Json(OkReply { ok: false }))
}

#[post("/register", data = "<body>")]
async fn register(
    db: &State<PgPool>,
    redis: &State<redis::aio::ConnectionManager>,
    body: Json<AuthReq<'_>>
) -> Result<Created<Json<OkReply>>, Status> {
    let pwd_hash = hash(body.pwd, DEFAULT_COST).map_err(|_| Status::InternalServerError)?;
    let _result = sqlx::query_as::<_, (i32,)>(
        "INSERT INTO users (login, pwd_hash) VALUES ($1, $2) RETURNING id"
    )
    .bind(body.login)
    .bind(&pwd_hash)
    .fetch_one(db.inner())
    .await
    .map_err(|_| Status::Conflict)?;

    let cache_key = format!("user:{}:hash", body.login);
    let mut redis_conn = redis.inner().clone();
    let _: Result<(), _> = redis_conn.set_ex(&cache_key, &pwd_hash, 3600).await;

    Ok(Created::new("/").body(Json(OkReply { ok: true })))
}

#[post("/logout")]
fn logout(cookies: &CookieJar<'_>) -> Json<OkReply> {
    cookies.remove_private(Cookie::from("uid"));
    Json(OkReply { ok: true })
}

#[derive(FromForm)]
struct Upload<'r> {
    pdf: TempFile<'r>,
}

#[post("/upload", data = "<form>")]
async fn upload(
    cookies: &CookieJar<'_>,
    db: &State<PgPool>,
    mut form: Form<Upload<'_>>,
) -> Result<Json<OkReply>, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let temp = &mut form.pdf;

    if temp.len() == 0 {
        eprintln!("ERROR: No file uploaded or file is empty");
        return Err(Status::BadRequest);
    }

    let orig = temp
        .raw_name()
        .as_ref()
        .ok_or_else(|| {
            eprintln!("ERROR: No filename provided");
            Status::BadRequest
        })?
        .dangerous_unsafe_unsanitized_raw()
        .to_string();

    eprintln!("INFO: Uploading file: {} (size: {} bytes) for user {}", orig, temp.len(), uid);

    let ext = Path::new(&orig)
        .extension()
        .and_then(|s| s.to_str())
        .unwrap_or("pdf");
    let fname = format!("{}.{}", Uuid::new_v4(), ext);
    let dest = PathBuf::from("uploads").join(&fname);

    fs::create_dir_all("uploads").await.map_err(|e| {
        eprintln!("ERROR: Failed to create uploads directory: {}", e);
        Status::InternalServerError
    })?;

    temp.copy_to(&dest).await.map_err(|e| {
        eprintln!("ERROR: Failed to copy file to {:?}: {}", dest, e);
        Status::InternalServerError
    })?;

    eprintln!("INFO: File saved to {:?}", dest);

    sqlx::query("INSERT INTO pdfs (user_id, filename, original_name) VALUES ($1, $2, $3)")
        .bind(uid)
        .bind(&fname)
        .bind(&orig)
        .execute(db.inner())
        .await
        .map_err(|e| {
            eprintln!("ERROR: Failed to insert into database: {}", e);
            Status::InternalServerError
        })?;

    eprintln!("INFO: File {} successfully uploaded by user {}", fname, uid);
    Ok(Json(OkReply { ok: true }))
}

#[delete("/delete-pdf/<id>")]
async fn delete_pdf(
    cookies: &CookieJar<'_>,
    db: &State<PgPool>,
    id: i32,
) -> Result<Json<OkReply>, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let rec = sqlx::query_as::<_, (String,)>(
        "DELETE FROM pdfs WHERE id = $1 AND user_id = $2 RETURNING filename"
    )
    .bind(id)
    .bind(uid)
    .fetch_optional(db.inner())
    .await
    .map_err(|_| Status::InternalServerError)?;

    if let Some((filename,)) = rec {
        let path = PathBuf::from("uploads").join(&filename);
        let _ = fs::remove_file(path).await;
        return Ok(Json(OkReply { ok: true }));
    }
    Err(Status::NotFound)
}

#[get("/pdf/<id>")]
async fn get_pdf(
    cookies: &CookieJar<'_>,
    db: &State<PgPool>,
    id: i32,
) -> Result<NamedFile, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let rec = sqlx::query_as::<_, (String,)>(
        "SELECT filename FROM pdfs WHERE id = $1 AND user_id = $2"
    )
    .bind(id)
    .bind(uid)
    .fetch_optional(db.inner())
    .await
    .map_err(|_| Status::InternalServerError)?;

    if let Some((filename,)) = rec {
        let path = PathBuf::from("uploads").join(&filename);
        return NamedFile::open(path).await.map_err(|_| Status::NotFound);
    }
    Err(Status::NotFound)
}

#[get("/")]
async fn index(cookies: &CookieJar<'_>, db: &State<PgPool>) -> Either<Redirect, Template> {
    if let Some(uid_cookie) = cookies.get_private("uid") {
        if let Ok(id) = uid_cookie.value().parse::<i32>() {
            let exists = match sqlx::query_as::<_, (i32,)>("SELECT id FROM users WHERE id = $1")
                .bind(id)
                .fetch_optional(db.inner())
                .await
            {
                Ok(Some(_)) => true,
                _ => false,
            };

            if exists {
                return Either::Left(Redirect::to("/private"));
            } else {
                cookies.remove_private(Cookie::from("uid"));
            }
        } else {
            cookies.remove_private(Cookie::from("uid"));
        }
    }
    Either::Right(Template::render("auth", context! {}))
}

#[derive(FromForm)]
struct ThemeForm<'r> {
    theme: &'r str,
}

#[post("/theme", data = "<form>")]
async fn set_theme(
    cookies: &CookieJar<'_>,
    db: &State<PgPool>,
    form: Form<ThemeForm<'_>>,
) -> Result<Json<serde_json::Value>, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let theme = form.theme;
    match theme {
        "light" | "dark" | "colorblind" => {}
        _ => return Err(Status::BadRequest),
    }

    sqlx::query("UPDATE users SET theme = $1 WHERE id = $2")
        .bind(theme)
        .bind(uid)
        .execute(db.inner())
        .await
        .map_err(|_| Status::InternalServerError)?;

    Ok(Json(json!({"ok": true, "theme": theme})))
}

#[get("/theme")]
async fn get_theme(
    cookies: &CookieJar<'_>,
    db: &State<PgPool>,
) -> Result<Json<serde_json::Value>, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let rec = sqlx::query_as::<_, (Option<String>,)>(
        "SELECT theme FROM users WHERE id = $1"
    )
    .bind(uid)
    .fetch_optional(db.inner())
    .await
    .map_err(|_| Status::InternalServerError)?;

    if let Some((theme_opt,)) = rec {
        let theme = theme_opt.unwrap_or_else(|| "light".into());
        Ok(Json(json!({"ok": true, "theme": theme})))
    } else {
        Err(Status::NotFound)
    }
}

#[get("/private")]
async fn private(cookies: &CookieJar<'_>, db: &State<PgPool>) -> Either<Redirect, Template> {
    let uid = match cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
    {
        Some(id) => id,
        None => return Either::Left(Redirect::to("/")),
    };

    let user = match sqlx::query_as::<_, (String, String)>(
        "SELECT login, COALESCE(theme, 'light')::text as theme FROM users WHERE id = $1"
    )
        .bind(uid)
        .fetch_optional(db.inner())
        .await
    {
        Ok(Some(row)) => row,
        Ok(None) => {
            cookies.remove_private(Cookie::from("uid"));
            return Either::Right(Template::render("auth", context! {}));
        }
        Err(_) => {
            return Either::Right(Template::render("auth", context! {}));
        }
    };

    let files = sqlx::query_as::<_, (i32, String, String, chrono::DateTime<Utc>)>(
        "SELECT id, filename, original_name, uploaded_at FROM pdfs WHERE user_id = $1 ORDER BY uploaded_at DESC"
    )
    .bind(uid)
    .fetch_all(db.inner())
    .await
    .unwrap_or_default();

    let ctx = PrivateContext {
        login: user.0,
        uid,
        files: files
            .into_iter()
            .map(|(id, filename, original_name, uploaded_at)| PdfRow {
                id,
                filename,
                original_name,
                uploaded_at: uploaded_at.to_rfc3339(),
            })
            .collect(),
        theme: user.1,
    };

    eprintln!("DEBUG: Rendering private page for user {} with theme: {}", uid, ctx.theme);

    Either::Right(Template::render("private", &ctx))
}

#[get("/css/<file..>")]
async fn css(file: PathBuf) -> Option<NamedFile> {
    NamedFile::open(Path::new("static/").join(file)).await.ok()
}

#[get("/static/<file..>")]
async fn static_files(file: PathBuf) -> Option<NamedFile> {
    NamedFile::open(Path::new("static/").join(file)).await.ok()
}

#[launch]
fn rocket() -> rocket::Rocket<rocket::Build> {
    rocket::build()
        .attach(Template::fairing())
        .attach(AdHoc::try_on_ignite("DB", |rocket| async {
            let pool = init_pool().await;
            Ok(rocket.manage(pool))
        }))
        .attach(AdHoc::try_on_ignite("Redis", |rocket| async {
            let redis = init_redis().await;
            Ok(rocket.manage(redis))
        }))
        .mount(
            "/api",
            routes![login, register, logout, upload, delete_pdf, get_pdf, set_theme, get_theme],
        )
        .mount("/", routes![index, private, css, static_files])
}
