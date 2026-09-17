-- Add shipping_company column to products table
ALTER TABLE products ADD COLUMN shipping_company VARCHAR(100) DEFAULT NULL AFTER stock;
