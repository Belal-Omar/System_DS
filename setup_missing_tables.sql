-- Create shipping companies table if not exists
CREATE TABLE IF NOT EXISTS `shipping_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Add shipping_company column to orders table if not exists
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS `shipping_company` varchar(100) DEFAULT NULL AFTER `reason_type`,
ADD COLUMN IF NOT EXISTS `shipping_company_id` int DEFAULT NULL AFTER `shipping_company`;

-- Add shipping_company column to products table if not exists
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS `shipping_company` varchar(100) DEFAULT NULL AFTER `stock`,
ADD COLUMN IF NOT EXISTS `shipping_company_id` int DEFAULT NULL AFTER `shipping_company`;

-- Insert sample shipping companies
INSERT IGNORE INTO `shipping_companies` (`id`, `name`, `phone`, `email`, `address`) VALUES
(1, 'أرامكس', '16166', 'info@aramex.com', 'القاهرة، مصر'),
(2, 'فدكس', '16788', 'info@fedex.com', 'القاهرة، مصر'),
(3, 'DHL', '16789', 'info@dhl.com', 'القاهرة، مصر'),
(4, 'شركة الشحن السريع', '1234567890', 'info@fastshipping.com', 'الإسكندرية، مصر');
