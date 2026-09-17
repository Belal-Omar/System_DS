-- Update products table to use shipping_company_id instead of shipping_company name
ALTER TABLE products 
ADD COLUMN shipping_company_id INT NULL AFTER shipping_company;

-- Create foreign key relationship
ALTER TABLE products 
ADD CONSTRAINT fk_products_shipping_company 
FOREIGN KEY (shipping_company_id) 
REFERENCES shipping_companies(id) 
ON DELETE SET NULL;

-- Update existing products to use shipping_company_id based on their current shipping_company name
UPDATE products p 
SET shipping_company_id = (
    SELECT id FROM shipping_companies sc 
    WHERE sc.name = p.shipping_company
) 
WHERE shipping_company IS NOT NULL AND shipping_company != '';

-- After verification, you can drop the old column
-- ALTER TABLE products DROP COLUMN shipping_company;
