# MIREA W5 – Минимальный full‑stack пример (PHP + PostgreSQL + Redis + Nginx + Docker)

Демонстрационное приложение: регистрация и вход пользователя, персонализированный контент (тема + язык), загрузка и
отдача PDF, кеширование части ответа через Redis, реверс‑прокси nginx перед Apache (php:8.2). Фронтенд – одна
SPA‑страница (`public/index.html`), работающая поверх JSON API.

## 1. Ключевая идея

Показать компактный, но реальный стек и базовые практики:

- Слой API (чистый PHP без фреймворка) с роутером.
- Персонализация (куки theme/lang + кеш Redis).
- Хранение пользователей и загруженных файлов в PostgreSQL.
- Загрузка и выдача PDF (с проверкой типа, хранение имени в БД, физические файлы в volume).
- Темы интерфейса и локализация минимальными средствами.

## 2. Текущий функционал

- POST /api/register – регистрация (login + pwd, пароль хэшируется `password_hash`).
- POST /api/login – вход (устанавливаются куки: `uid`, `theme`, `lang`).
- GET /api/content – персонализированный JSON (greeting, theme, banner). Результат кешируется в Redis на 60 секунд.
- POST /api/upload – загрузка PDF (только с авторизацией; файл кладётся в `uploads/`, запись в таблицу `pdfs`).
- GET /api/pdf/{id} – отдача PDF по id (простая выдача; проверка владельца пока не реализована – см. TODO).
- Клиентская SPA (корень `/`) – формы регистрации/логина, выбор темы/языка, загрузка PDF, просмотр текущего контента.

## 3. Архитектура (упрощённо)

```
Browser (SPA) --> nginx (reverse proxy, /static/* отдает сам) --> php-apache --> PostgreSQL & Redis
                                   |                           |               |
                                   |-- /static/*.svg, css      |-- PDO         |-- кеш JSON /content
                                   |                           |-- file uploads
```

## 4. Структура каталога `W5`

```
W5/
├── docker-compose.yaml          # Оркестрация: postgres, redis, php (Apache), nginx
├── readme.md                    # Этот файл
├── nginx/
│   ├── default.conf             # Reverse proxy (proxy_pass на php, статика /static)
│   └── static/                  # Баннеры и прочая статика
│       ├── light.svg
│       ├── dark.svg
│       └── cb.svg
├── php/
│   ├── Dockerfile               # Сборка php:8.2-apache + pdo_pgsql + redis
│   └── src/
│       ├── api/
│       │   └── router.php       # Простой роутер по PATH (эндпоинты /api/*)
│       ├── DB.php               # Подключение к PostgreSQL (PDO singleton)
│       ├── RedisClient.php      # Обёртка над Redis
│       ├── Auth.php             # Регистрация / логин
│       ├── Content.php          # Персонализированный контент + кеш Redis
│       ├── PdfService.php       # Загрузка / отдача PDF
│       ├── index.php            # Фронт-контроллер (вызов роутера для не-статических запросов)
│       ├── .htaccess            # Переписывает: /api/* -> router, остальное -> /public/index.html
│       ├── css/                 # Темы
│       │   ├── light.css
│       │   ├── dark.css
│       │   └── colorblind.css
│       ├── public/              # Клиентская SPA
│       │   ├── index.html       # Главная страница (формы + JS)
│       │   └── index.php        # Редирект на /
│       ├── private/             # Пример приватной HTML‑страницы (доступ по cookie uid)
│       │   └── index.php
│       ├── db/
│       │   └── init.sql         # Схема БД (users, pdfs)
│       └── uploads/             # (volume) – контейнерная директория; смонтирована из ../uploads (создаётся docker-compose)
├── postgres/
│   └── init.sql                 # Дубликат схемы для entrypoint Postgres (используется volumes)
└── composer.json (опционально)  # Зависимости расширений (ext-pdo, ext-redis)
```

## 5. Работа запросов

1. Браузер открывает `/` – nginx возвращает `public/index.html`.
2. JS вызывает `/api/content` – nginx проксирует в Apache/PHP – PHP формирует JSON (или берёт из Redis).
3. Регистрация / логин – POST JSON, пароль хэшируется, при логине ставятся куки.
4. Загрузка PDF – multipart POST `/api/upload`, файл перемещается в `uploads/`, имя сохраняется в БД.
5. Запрос PDF `/api/pdf/{id}` – прямое чтение файла и вывод с `Content-Type: application/pdf`.

## 6. Модель данных (PostgreSQL)

```
users(id, login UNIQUE, pwd_hash, theme DEFAULT 'light', lang DEFAULT 'en')
pdfs(id, user_id REFERENCES users(id), filename, uploaded_at DEFAULT now())
```

## 7. API эндпоинты

| Метод | Путь          | Описание                              | Тело / Ответ                 |
|-------|---------------|---------------------------------------|------------------------------|
| POST  | /api/register | Регистрация                           | JSON {login,pwd} → {ok:true} |
| POST  | /api/login    | Вход / куки                           | JSON {login,pwd} → {ok:true} |
| GET   | /api/content  | Персонализированный контент (кеш 60s) | {greeting, theme, banner}    |
| POST  | /api/upload   | Загрузка PDF (требует cookie uid)     | multipart (pdf) → {ok:true}  |
| GET   | /api/pdf/{id} | Отдача PDF                            | application/pdf              |

Планы (не реализовано): /api/logout, /api/list (список файлов только владельца), /api/prefs (серверное сохранение
темы/языка).

## 8. Темы и локализация

- Тема и язык хранятся в куках `theme`, `lang` (SPA выставляет вручную).
- `Content::personal()` читает куки, подставляет приветствие (ru/en) и баннер (light/dark/colorblind).
- Ответ кешируется в Redis по ключу `content:{uid}:{theme}:{lang}`.

## 9. Загрузка файлов

- Проверяется тип `application/pdf`.
- Имя генерируется `uniqid('', true) . '.pdf'`.
- Файл помещается в `php/src/uploads` (volume), запись – в `pdfs`.
- Выдача: прямое чтение `readfile()`. (TODO: ограничить доступ только владельцу.)

## 10. Кеширование

| Слой    | Инструмент | Что кешируется             | TTL    |
|---------|------------|----------------------------|--------|
| Контент | Redis      | JSON ответа `/api/content` | 60 сек |

## 11. Переменные окружения (опционально)

`DB.php`/`RedisClient.php` читают ENV, иначе дефолты:

```
PG_HOST=postgres
PG_PORT=5432
PG_DB=contentgate
PG_USER=gate
PG_PASSWORD=gatepass
REDIS_HOST=redis
REDIS_PORT=6379
```

Задаются через `environment:` или `.env` (сейчас не требуется – дефолты совпадают с docker-compose).

## 12. Сборка и запуск

Перейдите в папку `W5` и выполните:

```cmd
cd W5
docker compose up -d --build
```

После запуска:

- Nginx слушает порт 80 → открывайте http://localhost
- Postgres инициализирует таблицы через `postgres/init.sql`.
- Redis сразу готов.

Остановка и удаление контейнеров:

```cmd
docker compose down
```

Полная пересборка без кэша:

```cmd
docker compose build --no-cache php
```

Просмотр логов PHP:

```cmd
docker compose logs --tail=200 php
```

## 13. Проверка работы (быстрый сценарий)

1. Открыть http://localhost – SPA.
2. Зарегистрироваться (форма «Регистрация»).
3. Войти (форма «Вход») – появятся куки.
4. Выбрать тему и язык → «Сохранить предпочтения» → обновится баннер.
5. Нажать «Обновить контент» – лог покажет JSON.
6. Загрузить PDF → ответ `{ok:true}`.
7. (Опционально) GET `/api/pdf/1` в новой вкладке (если id 1 существует) – откроется PDF.

## 14. Типичные проблемы и решения

| Симптом                                  | Причина                                          | Решение                                               |
|------------------------------------------|--------------------------------------------------|-------------------------------------------------------|
| 500 + `.htaccess: <?php> was not closed` | В .htaccess попал PHP                            | Заменить содержимое на правила из репо                |
| `Could not find driver`                  | Не установлено расширение pdo_pgsql              | Пересобрать образ (Dockerfile уже содержит установку) |
| `Class "Redis" not found`                | Не собралось расширение redis                    | Проверить логи сборки, пересобрать без кэша           |
| Пустой баннер                            | Файл `light.svg`/`dark.svg`/`cb.svg` отсутствует | Проверить `nginx/static/`                             |
| /api/upload 401                          | Нет cookie `uid`                                 | Сначала выполнить /api/login                          |

## 15. Расширение (ROADMAP / TODO)

- Серверный эндпоинт сохранения предпочтений (`/api/prefs`) c записью в таблицу users.
- Защита скачивания PDF: проверка владельца + X-Accel-Redirect через nginx (сейчас прямой readfile()).
- Логаут: очистка куки + отдельный /api/logout.
- Админский эндпоинт: список пользователей, кол-во их файлов.
- Rate limiting / защита от чрезмерных загрузок.
- Тесты (PHPUnit) для Auth / Content / PdfService.

## 16. Лицензия / использование

Учебный пример. Используйте и модифицируйте свободно в учебных целях.