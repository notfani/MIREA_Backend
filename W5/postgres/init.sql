CREATE TABLE IF NOT EXISTS users
(
    id       SERIAL PRIMARY KEY,
    login    VARCHAR(50) UNIQUE NOT NULL,
    pwd_hash TEXT               NOT NULL,
    theme    VARCHAR(20) DEFAULT 'light',
    lang     VARCHAR(10) DEFAULT 'en'
);

CREATE TABLE IF NOT EXISTS pdfs
(
    id            SERIAL PRIMARY KEY,
    user_id       INT REFERENCES users (id),
    filename      TEXT NOT NULL,          -- хешированное имя файла на диске
    original_name TEXT NOT NULL,          -- оригинальное имя файла
    uploaded_at   TIMESTAMP DEFAULT NOW()
);

-- Добавляем колонку original_name если её нет
ALTER TABLE pdfs ADD COLUMN IF NOT EXISTS original_name TEXT;
-- Заполняем existing records значениями из filename для обратной совместимости
UPDATE pdfs SET original_name = filename WHERE original_name IS NULL;
-- Делаем поле обязательным
ALTER TABLE pdfs ALTER COLUMN original_name SET NOT NULL;
