-- Add shipping_company column to orders table
ALTER TABLE orders
ADD COLUMN shipping_company_id INT NULL,
ADD CONSTRAINT fk_shipping_company
    FOREIGN KEY (shipping_company_id)
    REFERENCES shipping_companies(id)
    ON DELETE SET NULL;
