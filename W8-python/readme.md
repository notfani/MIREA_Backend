# PDF Upload & Charts Platform

Веб-приложение для загрузки PDF-файлов с визуализацией данных и системой авторизации.

## Возможности

✅ **Регистрация и авторизация пользователей**
- Регистрация с email и паролем (минимум 6 символов)
- Вход/выход из системы
- Защита данных с использованием хэширования паролей

✅ **Загрузка PDF-файлов**
- Загрузка PDF только для авторизованных пользователей
- Привязка файлов к пользователю в БД
- Скачивание только своих файлов
- Список файлов с датой загрузки

✅ **Визуализация данных**
- Автоматическая генерация графиков из фикстур
- 3 типа графиков: scatter, bar, histogram
- Доступ к графикам только для авторизованных пользователей

✅ **Темы оформления**
- Переключение между светлой и тёмной темой
- Сохранение выбранной темы

## Технологический стек

**Backend:**
- Flask 3.0.0
- Flask-SQLAlchemy 3.1.1
- PostgreSQL (psycopg2-binary)
- Gunicorn 21.2.0

**Data visualization:**
- Pandas 2.1.3
- Matplotlib 3.8.2
- Pillow 10.1.0

**Frontend:**
- Bootstrap 5.3.3
- Vanilla JavaScript

**Infrastructure:**
- Docker & Docker Compose
- Nginx (reverse proxy)

## Структура проекта

```
PythonProject/
├── app/
│   ├── api/                    # API endpoints
│   │   ├── auth.py            # Авторизация
│   │   ├── pdf.py             # Загрузка PDF
│   │   ├── theme.py           # Темы оформления
│   │   ├── charts.py          # Генерация графиков
│   │   └── charts_api.py      # API графиков
│   ├── static/                # Статические файлы
│   │   ├── index.html         # Главная страница
│   │   ├── main.js            # Frontend логика
│   │   ├── uploads/           # Загруженные PDF
│   │   └── charts/            # Сгенерированные графики
│   ├── config.py              # Конфигурация
│   ├── models.py              # SQLAlchemy модели
│   ├── crud.py                # Репозитории для БД
│   ├── fixtures.py            # Тестовые данные
│   ├── migrate_db.py          # Автоматические миграции
│   ├── run.py                 # Flask приложение
│   ├── wsgi.py                # WSGI entry point
│   ├── Dockerfile             # Docker образ
│   └── requirements.txt       # Python зависимости
├── nginx/
│   └── default.conf           # Nginx конфигурация
├── docker-compose.yaml        # Orchestration
└── readme.md                  # Документация

```

## Быстрый старт

### Запуск с Docker (рекомендуется)

```bash
# Клонировать репозиторий
git clone <repo-url>
cd PythonProject

# Запустить сервисы
docker-compose up -d --build

# Проверить логи
docker-compose logs -f apache
```

Приложение доступно по адресу: **http://localhost/**

### Локальный запуск (для разработки)

```bash
cd app

# Создать виртуальное окружение
python -m venv .venv
source .venv/bin/activate  # Linux/Mac
.venv\Scripts\activate     # Windows

# Установить зависимости
pip install -r requirements.txt

# Запустить приложение
python run.py
```

Приложение доступно по адресу: **localhost/**

## API Endpoints

### Авторизация

- `POST /api/register` - Регистрация
  ```json
  {"email": "user@example.com", "password": "password123"}
  ```

- `POST /api/login` - Вход
  ```json
  {"email": "user@example.com", "password": "password123"}
  ```

- `POST /api/logout` - Выход
- `GET /api/me` - Текущий пользователь

### PDF

- `POST /api/pdf/upload` - Загрузить PDF (требует авторизации)
- `GET /api/pdf/list` - Список файлов пользователя
- `GET /api/pdf/download/<id>` - Скачать файл

### Графики

- `GET /api/charts/list` - Список доступных графиков (требует авторизации)
- `POST /api/charts/generate` - Принудительная генерация графиков

### Темы

- `POST /api/theme/set` - Установить тему
  ```json
  {"theme": "dark"}
  ```
- `GET /api/theme/get` - Получить текущую тему

## Переменные окружения

```bash
# База данных (по умолчанию SQLite)
DATABASE_URL=postgresql+psycopg2://user:pass@host:5432/db

# Секретный ключ для сессий
SECRET_KEY=your-secret-key

# Папка для загрузок
UPLOAD_FOLDER=/path/to/uploads

# Пропустить инициализацию (для тестов)
SKIP_INIT=1
```

## Модели данных

### User
- `id` - Уникальный идентификатор
- `email` - Email (уникальный)
- `password_hash` - Хэш пароля
- `created_at` - Дата регистрации

### PDF
- `id` - Уникальный идентификатор
- `name` - Имя файла
- `path` - Путь к файлу
- `user_id` - Владелец файла (FK)
- `created_at` - Дата загрузки

### Fixture
- `id` - Уникальный идентификатор
- `f1, f2, f3` - Числовые поля
- `f4` - Строковое поле
- `f5` - Булево поле

### Theme
- `id` - Уникальный идентификатор
- `name` - Название темы (light/dark)
- `user` - Пользователь

## Команды Docker

```bash
# Запуск
docker-compose up -d

# Остановка
docker-compose down

# Перезапуск
docker-compose restart apache

# Логи
docker-compose logs -f apache

# Пересборка
docker-compose up -d --build

# Очистка
docker-compose down -v  # Удалит volumes (БД)
```

## Безопасность

✅ Хэширование паролей (Werkzeug)
✅ Проверка авторизации на уровне API
✅ Привязка файлов к пользователям
✅ Защита от доступа к чужим файлам
✅ Валидация email и пароля
✅ CSRF защита через сессии Flask

## Производительность

- Gunicorn для production
- Nginx для статических файлов
- PostgreSQL для надёжности
- Ленивая загрузка графиков
- Кэширование статики через Nginx

## Лицензия

MIT License

## Автор

Проект создан для демонстрации работы с Flask, SQLAlchemy, Docker и визуализацией данных.

