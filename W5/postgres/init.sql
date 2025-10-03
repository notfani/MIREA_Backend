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
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES users (id),
    filename    TEXT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT NOW()
);

