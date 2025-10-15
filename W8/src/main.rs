#[macro_use] extern crate rocket;

use rocket::fs::NamedFile;
use rocket::http::{Cookie, CookieJar, Status};
use rocket::response::status::Created;
use rocket::serde::{Deserialize, Serialize};
use rocket::tokio::fs;
use rocket::tokio::io::AsyncWriteExt;
use rocket::{form::Form, fs::TempFile, http::uri::Origin};
use rocket_dyn_templates::{context, Template};
use sqlx::{postgres::PgPoolOptions, PgPool};
use std::path::{Path, PathBuf};
use bcrypt::{hash, verify, DEFAULT_COST};
use chrono::{DateTime, Utc};
use uuid::Uuid;

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
}

#[derive(Serialize)]
struct PdfRow {
    id: i32,
    filename: String,
    original_name: String,
    uploaded_at: DateTime<Utc>,
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

#[post("/login", data = "<body>")]
async fn login(cookies: &CookieJar<'_>, db: &PgPool, body: Json<AuthReq<'_>>) -> Json<OkReply> {
    let rec = sqlx::query!("SELECT id, pwd_hash FROM users WHERE login = $1", body.login)
        .fetch_optional(db)
        .await
        .unwrap();
    if let Some(u) = rec {
        if verify(body.pwd, &u.pwd_hash).unwrap_or(false) {
            cookies.add_private(Cookie::new("uid", u.id.to_string()));
            return Json(OkReply { ok: true });
        }
    }
    Json(OkReply { ok: false })
}

#[post("/register", data = "<body>")]
async fn register(db: &PgPool, body: Json<AuthReq<'_>>) -> Result<Created<Json<OkReply>>, Status> {
    let pwd_hash = hash(body.pwd, DEFAULT_COST).map_err(|_| Status::InternalServerError)?;
    let id = sqlx::query!(
        "INSERT INTO users (login, pwd_hash) VALUES ($1, $2) RETURNING id",
        body.login,
        pwd_hash
    )
    .fetch_one(db)
    .await
    .map_err(|_| Status::Conflict)?; // логин занят
    Ok(Created::new("/").body(Json(OkReply { ok: true })))
}

#[post("/logout")]
fn logout(cookies: &CookieJar<'_>) -> Json<OkReply> {
    cookies.remove_private(Cookie::named("uid"));
    Json(OkReply { ok: true })
}

#[derive(FromForm)]
struct Upload<'r> {
    pdf: TempFile<'r>,
}

#[post("/upload", data = "<form>")]
async fn upload(
    cookies: &CookieJar<'_>,
    db: &PgPool,
    mut form: Form<Upload<'_>>,
) -> Result<Json<OkReply>, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let temp = form.pdf.as_mut().unwrap();
    let orig = temp
        .raw_name()
        .as_ref()
        .ok_or(Status::BadRequest)?
        .dangerous_unsafe_unsanitized_raw()
        .to_string();

    let ext = Path::new(&orig)
        .extension()
        .and_then(|s| s.to_str())
        .unwrap_or("pdf");
    let fname = format!("{}.{}", Uuid::new_v4(), ext);
    let dest = PathBuf::from("uploads").join(&fname);

    // создаём каталог uploads, если его нет
    fs::create_dir_all("uploads").await.map_err(|_| Status::InternalServerError)?;
    temp.persist_to(&dest).await.map_err(|_| Status::InternalServerError)?;

    sqlx::query!(
        "INSERT INTO pdfs (user_id, filename, original_name) VALUES ($1, $2, $3)",
        uid,
        fname,
        orig
    )
    .execute(db)
    .await
    .map_err(|_| Status::InternalServerError)?;

    Ok(Json(OkReply { ok: true }))
}

#[delete("/delete-pdf/<id>")]
async fn delete_pdf(
    cookies: &CookieJar<'_>,
    db: &PgPool,
    id: i32,
) -> Result<Json<OkReply>, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let rec = sqlx::query!(
        "DELETE FROM pdfs WHERE id = $1 AND user_id = $2 RETURNING filename",
        id,
        uid
    )
    .fetch_optional(db)
    .await
    .map_err(|_| Status::InternalServerError)?;

    if let Some(r) = rec {
        let path = PathBuf::from("uploads").join(&r.filename);
        let _ = fs::remove_file(path).await; // не критично, если не получилось
        return Ok(Json(OkReply { ok: true }));
    }
    Err(Status::NotFound)
}

#[get("/pdf/<id>")]
async fn get_pdf(
    cookies: &CookieJar<'_>,
    db: &PgPool,
    id: i32,
) -> Result<NamedFile, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;

    let rec = sqlx::query!("SELECT filename FROM pdfs WHERE id = $1 AND user_id = $2", id, uid)
        .fetch_optional(db)
        .await
        .map_err(|_| Status::InternalServerError)?;

    if let Some(r) = rec {
        let path = PathBuf::from("uploads").join(&r.filename);
        return NamedFile::open(path).await.map_err(|_| Status::NotFound);
    }
    Err(Status::NotFound)
}

#[get("/")]
async fn index() -> Template {
    Template::render("auth", &())
}

#[get("/private")]
async fn private(cookies: &CookieJar<'_>, db: &PgPool) -> Result<Template, Status> {
    let uid = cookies
        .get_private("uid")
        .and_then(|c| c.value().parse::<i32>().ok())
        .ok_or(Status::Unauthorized)?;
    let user = sqlx::query!("SELECT login FROM users WHERE id = $1", uid)
        .fetch_one(db)
        .await
        .map_err(|_| Status::InternalServerError)?;
    let files = sqlx::query!(
        "SELECT id, filename, original_name, uploaded_at FROM pdfs WHERE user_id = $1 ORDER BY uploaded_at DESC",
        uid
    )
    .fetch_all(db)
    .await
    .unwrap_or_default();

    let ctx = PrivateContext {
        login: user.login,
        uid,
        files: files
            .into_iter()
            .map(|r| PdfRow {
                id: r.id,
                filename: r.filename,
                original_name: r.original_name,
                uploaded_at: r.uploaded_at,
            })
            .collect(),
    };
    Ok(Template::render("private", &ctx))
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
fn rocket() -> _ {
    let pool_rt = rocket::tokio::runtime::Handle::current();
    let pool = pool_rt.block_on(init_pool());

    rocket::build()
        .manage(pool)
        .attach(Template::fairing())
        .mount(
            "/api",
            routes![login, register, logout, upload, delete_pdf, get_pdf],
        )
        .mount("/", routes![index, private, css, static_files])
}  