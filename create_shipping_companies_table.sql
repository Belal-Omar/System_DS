-- Create shipping_companies table
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

-- Insert some default shipping companies
INSERT INTO `shipping_companies` (`name`, `phone`, `email`, `is_active`) VALUES
('شركة الأمل للشحن', '01234567890', 'info@amal-shipping.com', 1),
('شركة النور السريع', '01123456789', 'contact@noor-express.com', 1),
('شركة البرق للتوصيل', '01098765432', 'support@barq-delivery.com', 1);
