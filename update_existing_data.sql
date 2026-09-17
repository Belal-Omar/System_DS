-- Update existing products to have shipping companies
UPDATE products SET shipping_company = 'أرامكس' WHERE shipping_company IS NULL LIMIT 10;
UPDATE products SET shipping_company = 'فدكس' WHERE shipping_company IS NULL LIMIT 10;
UPDATE products SET shipping_company = 'DHL' WHERE shipping_company IS NULL LIMIT 10;

-- Update existing orders to have shipping companies based on their products
UPDATE orders o 
SET o.shipping_company = (
    SELECT p.shipping_company 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = o.id 
    LIMIT 1
)
WHERE o.shipping_company IS NULL;

-- Check data
SELECT 'Products with shipping companies' as info, COUNT(*) as count FROM products WHERE shipping_company IS NOT NULL
UNION ALL
SELECT 'Orders with shipping companies' as info, COUNT(*) as count FROM orders WHERE shipping_company IS NOT NULL
UNION ALL
SELECT 'Total products' as info, COUNT(*) as count FROM products
UNION ALL
SELECT 'Total orders' as info, COUNT(*) as count FROM orders;
