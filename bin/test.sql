SELECT
    d.balance,
    d.balance * last_documents.avg_price,
    d.inventory_error,
    d.inventory_error * last_documents.avg_price,
    last_documents.avg_price
FROM
    document AS d
INNER JOIN
    (
        SELECT
            d.id,
            (
                SELECT
                    IF(COUNT(id) > 0, ROUND(SUM(price*value)/SUM(value), 2), (
                        SELECT
                            price
                        FROM
                            document
                        WHERE
                            type = 'incoming' AND
                            product_id = d.product_id AND
                            report_date < d.report_date
                        ORDER BY
                            report_date DESC
                        LIMIT 1
                        ))
                FROM
                    document
                WHERE
                    product_id = d.product_id AND
                    DATE(report_date) >= DATE(DATE_ADD(d.report_date, INTERVAL -20 DAY)) AND
                    report_date < d.report_date AND
                    type = 'incoming'
            ) AS avg_price
        FROM
            document AS d
        LEFT JOIN
            document AS d2
        ON
            d2.product_id = d.product_id AND
            d2.type = d.type AND
            DATE(d2.report_date) = DATE(d.report_date) AND
            d2.report_date > d.report_date
        WHERE
            d.type = 'inventory' AND
            DATE(d.report_date) = '2025-06-20' AND
            d2.id IS NULL
    ) AS last_documents
ON
    d.id = last_documents.id
