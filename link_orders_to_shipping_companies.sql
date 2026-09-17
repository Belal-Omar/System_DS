-- Update all orders to have shipping companies based on their products
UPDATE orders o 
SET o.shipping_company = (
    SELECT p.shipping_company 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = o.id 
    AND p.shipping_company IS NOT NULL
    LIMIT 1
)
WHERE o.shipping_company IS NULL
AND EXISTS (
    SELECT 1 FROM order_items oi2 
    JOIN products p2 ON oi2.product_id = p2.id 
    WHERE oi2.order_id = o.id 
    AND p2.shipping_company IS NOT NULL
);

-- Update products to have shipping companies if they don't have any
UPDATE products SET shipping_company = 'أرامكس' 
WHERE shipping_company IS NULL 
AND id IN (SELECT product_id FROM order_items LIMIT 20);

UPDATE products SET shipping_company = 'فدكس' 
WHERE shipping_company IS NULL 
AND id IN (SELECT product_id FROM order_items LIMIT 20 OFFSET 20);

UPDATE products SET shipping_company = 'DHL' 
WHERE shipping_company IS NULL 
AND id IN (SELECT product_id FROM order_items LIMIT 20 OFFSET 40);

-- Check the results
SELECT 
    'Orders with shipping company' as type,
    COUNT(*) as count
FROM orders 
WHERE shipping_company IS NOT NULL

UNION ALL

SELECT 
    'Orders without shipping company' as type,
    COUNT(*) as count
FROM orders 
WHERE shipping_company IS NULL

UNION ALL

SELECT 
    'Products with shipping company' as type,
    COUNT(*) as count
FROM products 
WHERE shipping_company IS NOT NULL

UNION ALL

SELECT 
    'Products without shipping company' as type,
    COUNT(*) as count
FROM products 
WHERE shipping_company IS NULL;
