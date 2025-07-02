# Загрузка документов
POST /documents
```
[
    {
        "id": 1,
        "productId": 1,
        "reportDate": 1751390251,
        "type": "inventory",
        "value": 10
    },
    {
        "id": 2,
        "productId": 1,
        "reportDate": 1751370251,
        "type": "income",
        "value": 15,
        "price": 9.90
    },
    ...
]
```
список полей:

id - Идентификатор документа в пакете - int

productId - Идентификатор продукта - int

reportDate - Дата-время на которое актуальны данные документа - timestamp

type - тип документа (incoming, outcoming, inventory) - varchar

value - Количество продукта - int

price - цена (используется только при type = 'incoming') - float


Ошибка инвентаризации и баланс считается при загрузке программно.

P.S. При перерасчете документов сначала хотел использовать update запрос, но все оказалось сложнее, так что реализовал на php. Скорее всего не лучшее решение с точки зрения highload.

# Получение истории

История отдаётся полностью за всё время в разрезе продуктов 

GET /documents/history

# Получение аналитики

GET /documents/analytic?timestamp=\<int\>
