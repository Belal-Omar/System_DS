-- إضافة عمود user_id إلى جدول products
ALTER TABLE products ADD COLUMN IF NOT EXISTS user_id INT NULL;
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_user_id (user_id);

-- إضافة foreign key (اختياري)
-- ALTER TABLE products ADD CONSTRAINT fk_products_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

