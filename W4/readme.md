    CREATE (создать клиента)

```mutation {
createClient(name: "Alice", email: "alice@demo.ru") {
id
name
email
}
}
Ожидаемый ответ (id может быть 1, 2 …):
{"data":{"createClient":{"id":1,"name":"Alice","email":"alice@demo.ru"}}}
```

    READ-ALL (получить список)

```
query {
clients {
id
name
email
}
}
```

    READ-ONE (получить одного по id)

```
query(id: Int!) {
client(id: id) {
id
name
email
}
}
В нижней колонке «Variables»:
{"id": 1}
```

    UPDATE (изменить имя)

```
mutation {
updateClient(id: 1, name: "Bob") {
id
name
email
}
}
```

    DELETE (удалить)

```
mutation {
deleteClient(id: 1)
}
Ответ: {"data":{"deleteClient":true}}
```

    Проверить, что удалили

Повторите запрос №2 – список должен быть пустым:

```
{"data":{"clients":[]}}
```