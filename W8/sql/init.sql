-- Таблица пользователей
CREATE TABLE users (
    id         SERIAL PRIMARY KEY,
    login      TEXT UNIQUE NOT NULL,
    pwd_hash   TEXT        NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    theme      TEXT        DEFAULT 'light'
);

-- Таблица загруженных PDF
CREATE TABLE pdfs (
    id            SERIAL PRIMARY KEY,
    user_id       INT REFERENCES users(id) ON DELETE CASCADE,
    filename      TEXT NOT NULL,          -- как сохранили на диске
    original_name TEXT NOT NULL,          -- оригинальное имя
    uploaded_at   TIMESTAMPTZ DEFAULT NOW()
);