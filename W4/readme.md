## REST API для клиентов

Сервис предоставляет простые endpoints для демонстрации CRUD-операций над клиентской базой.
Дата хранится в файле `storage/clients.json` (без БД), поэтому изменения сохраняются между запросами.

### Базовый URL

```
http://localhost:8080/api/clients
```

### Методы

| Метод | Путь | Описание | Пример тела |
|-------|------|----------|-------------|
| GET | `/api/clients` | Список всех клиентов | — |
| GET | `/api/clients/{id}` | Найти клиента по ID | — |
| POST | `/api/clients` | Создать клиента | `{ "name": "Alice", "email": "alice@demo.ru" }` |
| PATCH | `/api/clients/{id}` | Обновить имя/email | `{ "name": "Bob" }`
| DELETE | `/api/clients/{id}` | Удалить клиента | — |

### Примеры запросов (curl)

```bash
# Создать клиента
curl -X POST http://localhost:8080/api/clients \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@demo.ru"}'

# Получить всех клиентов
curl http://localhost:8080/api/clients

# Обновить email
curl -X PATCH http://localhost:8080/api/clients/1 \
  -H "Content-Type: application/json" \
  -d '{"email":"alice+new@demo.ru"}'

# Удалить клиента
curl -X DELETE http://localhost:8080/api/clients/1
```

### Playground

Страница `public/playground.html` содержит удобную форму для выполнения запросов прямо из браузера.
Она автоматически выводит список клиентов и показывает последний ответ сервера.