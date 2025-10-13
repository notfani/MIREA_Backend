# W7 - Web Application с PDF управлением и персонализацией контента

## Описание проекта

Веб-приложение для управления PDF файлами с системой аутентификации, персонализацией контента и кешированием. Проект
демонстрирует современные практики разработки на PHP с использованием микросервисной архитектуры, PostgreSQL, Redis и
Docker.

## 🏗️ Архитектура

Проект построен по принципам **Clean Architecture** с разделением на слои:

### Структура проекта

```
W7/
├── docker-compose.yaml          # Конфигурация Docker
├── readme.md                    # Документация
├── composer.json                # PHP зависимости
├── nginx/                       # Конфигурация Nginx
│   ├── default.conf
│   └── static/                  # Статические файлы (SVG баннеры)
├── php/                         
│   ├── composer.json
│   ├── dockerfile
│   └── src/                     # Исходный код приложения
│       ├── index.php            # Front Controller
│       ├── config/              # Конфигурация
│       │   └── config.php       # Основной конфиг
│       ├── core/                # Ядро фреймворка
│       │   ├── Request.php      # Обработка HTTP запросов
│       │   ├── Response.php     # Формирование HTTP ответов
│       │   └── Logger.php       # Логирование
│       ├── repositories/        # Слой данных (Data Access Layer)
│       │   ├── UserRepository.php
│       │   └── PdfRepository.php
│       ├── services/            # Бизнес-логика
│       │   ├── Auth.php         # Аутентификация
│       │   ├── Content.php      # Контент и персонализация
│       │   └── PdfService.php   # Работа с PDF
│       ├── DB.php               # Подключение к PostgreSQL
│       ├── RedisClient.php      # Подключение к Redis
│       ├── api/                 # API endpoints
│       │   └── router.php       # API роутер
│       ├── public/              # Публичные страницы
│       │   └── index.php        # Страница входа/регистрации
│       ├── private/             # Приватные страницы
│       │   └── index.php        # Личный кабинет
│       ├── css/                 # Темы оформления
│       │   ├── light.css
│       │   ├── dark.css
│       │   └── colorblind.css
│       ├── uploads/             # Загруженные PDF файлы
│       └── logs/                # Логи приложения
└── postgres/
    └── init.sql                 # Инициализация БД

```

## 🚀 Технологии

- **Backend**: PHP 8.2 (Apache)
- **Database**: PostgreSQL 15
- **Cache**: Redis 7
- **Web Server**: Nginx (reverse proxy)
- **Containerization**: Docker & Docker Compose

## 📦 Основные возможности

### 1. Аутентификация и авторизация

- Регистрация новых пользователей
- Безопасная аутентификация (bcrypt хеширование паролей)
- Сессии на базе cookies
- Middleware для защиты приватных маршрутов

### 2. Управление PDF файлами

- Загрузка PDF файлов (до 10MB)
- Просмотр списка загруженных файлов
- Скачивание PDF файлов
- Удаление файлов (с удалением из БД и файловой системы)
- Хранение оригинальных названий файлов

### 3. Персонализация контента

- Поддержка тем оформления (light, dark, colorblind)
- Мультиязычность (русский, английский, испанский, французский, немецкий)
- Кеширование персонализированного контента в Redis
- Автоматическая инвалидация кеша

### 4. Безопасность и производительность

- Prepared statements для защиты от SQL injection
- XSS защита через htmlspecialchars
- Валидация входных данных
- Логирование всех операций
- Redis кеширование для ускорения отдачи контента

## 🛠️ Установка и запуск

### Предварительные требования

- Docker
- Docker Compose

### Шаги установки

1. **Клонируйте репозиторий** (или перейдите в директорию W7)
   ```bash
   cd W7
   ```

2. **Запустите Docker контейнеры**
   ```bash
   docker-compose up -d --build
   ```

3. **Дождитесь инициализации** (первый запуск может занять 1-2 минуты)

4. **Откройте в браузере**
   ```
   http://localhost
   ```

### Проверка работоспособности

```bash
# Проверить статус контейнеров
docker-compose ps

# Просмотреть логи
docker-compose logs -f php
docker-compose logs -f postgres
docker-compose logs -f redis
```

## 📖 Использование

### Регистрация и вход

1. Откройте `http://localhost`
2. Введите логин (минимум 3 символа) и пароль (минимум 4 символа)
3. Нажмите **"Создать аккаунт"** для регистрации
4. После успешной регистрации нажмите **"Войти"**
5. Вы будете перенаправлены в личный кабинет `/private`

### Работа с PDF файлами

В личном кабинете:

1. **Загрузка**: выберите PDF файл и нажмите "OK"
2. **Просмотр**: нажмите на название файла в списке
3. **Удаление**: нажмите кнопку "Удалить" рядом с файлом

### Выход из системы

Нажмите кнопку **"Выйти"** в правом верхнем углу

## 🔌 API Endpoints

### Аутентификация

| Метод | Endpoint        | Описание    | Тело запроса                     |
|-------|-----------------|-------------|----------------------------------|
| POST  | `/api/register` | Регистрация | `{"login": "...", "pwd": "..."}` |
| POST  | `/api/login`    | Вход        | `{"login": "...", "pwd": "..."}` |
| POST  | `/api/logout`   | Выход       | -                                |

### Контент

| Метод | Endpoint       | Описание                      | Требует авторизацию |
|-------|----------------|-------------------------------|---------------------|
| GET   | `/api/content` | Получить персональный контент | Нет                 |

### PDF файлы

| Метод  | Endpoint        | Описание      | Требует авторизацию |
|--------|-----------------|---------------|---------------------|
| POST   | `/api/upload`   | Загрузить PDF | Да                  |
| GET    | `/api/pdf/{id}` | Скачать PDF   | Нет                 |
| DELETE | `/api/pdf/{id}` | Удалить PDF   | Да                  |

### Примеры запросов

```bash
# Регистрация
curl -X POST http://localhost/api/register \
  -H "Content-Type: application/json" \
  -d '{"login": "testuser", "pwd": "testpass123"}'

# Вход
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"login": "testuser", "pwd": "testpass123"}' \
  -c cookies.txt

# Загрузка PDF
curl -X POST http://localhost/api/upload \
  -F "pdf=@document.pdf" \
  -b cookies.txt

# Получение контента
curl -X GET http://localhost/api/content \
  -b cookies.txt
```

## 🗄️ База данных

### Схема PostgreSQL

```sql
-- Таблица пользователей
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    login VARCHAR(255) UNIQUE NOT NULL,
    pwd_hash VARCHAR(255) NOT NULL,
    theme VARCHAR(50) DEFAULT 'light',
    lang VARCHAR(10) DEFAULT 'ru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица PDF файлов
CREATE TABLE pdfs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Индексы
CREATE INDEX idx_pdfs_user_id ON pdfs(user_id);
CREATE INDEX idx_users_login ON users(login);
```

## 🔧 Конфигурация

Основные настройки находятся в `php/src/config/config.php`:

```php
return [
    'db' => [
        'host' => 'postgres',
        'port' => 5432,
        'database' => 'contentgate',
        // ...
    ],
    'uploads' => [
        'max_size' => 10 * 1024 * 1024, // 10 MB
        // ...
    ],
    'cache' => [
        'ttl' => 60, // секунды
    ],
];
```

## 📝 Логирование

Все операции логируются в `php/src/logs/app.log`:

```
[2025-10-09 12:34:56] [INFO] User registered successfully {"login":"testuser"}
[2025-10-09 12:35:12] [INFO] User logged in {"userId":1,"login":"testuser"}
[2025-10-09 12:35:45] [INFO] PDF uploaded successfully {"fileId":1,"userId":1}
```

## 🧪 Тестирование

### Проверка подключения к БД

```bash
docker-compose exec postgres psql -U gate -d contentgate -c "\dt"
```

### Проверка Redis

```bash
docker-compose exec redis redis-cli ping
# Ответ: PONG
```

## 🛡️ Безопасность

- ✅ Хеширование паролей (bcrypt)
- ✅ Prepared statements против SQL injection
- ✅ Валидация входных данных
- ✅ Проверка типов файлов
- ✅ Ограничение размера файлов
- ✅ Логирование подозрительных действий
- ✅ HTTPOnly cookies

## 🐛 Отладка

### Просмотр логов приложения

```bash
docker-compose exec php cat /var/www/html/logs/app.log
```

### Просмотр логов Apache

```bash
docker-compose logs -f php
```

### Очистка кеша Redis

```bash
docker-compose exec redis redis-cli FLUSHALL
```

## 📈 Производительность

- **Redis кеширование**: персональный контент кешируется на 60 секунд
- **Prepared statements**: переиспользование SQL запросов
- **Lazy connections**: подключение к БД/Redis только при необходимости
- **Connection pooling**: использование persistent connections

## 🔄 Остановка и очистка

```bash
# Остановить контейнеры
docker-compose down

# Остановить и удалить volumes (БД будет очищена)
docker-compose down -v

# Пересборка с нуля
docker-compose down -v
docker-compose up -d --build
```

## 🤝 Вклад в разработку

Проект создан в учебных целях для демонстрации современных практик веб-разработки на PHP.

## 📄 Лицензия

MIT License

---

**Разработано для MIREA - Российский технологический университет**
