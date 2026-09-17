-- جدول أوردرات الدعم الفني
CREATE TABLE IF NOT EXISTS support_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    support_name VARCHAR(100) NOT NULL,
    product_code VARCHAR(50) DEFAULT 'Etala001',
    customer_name VARCHAR(200) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    bundle_type ENUM('bundle', 'single') DEFAULT 'single',
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 150.00,
    total_price DECIMAL(10,2) NOT NULL,
    order_status ENUM('pending', 'confirmed', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_support_id (support_id),
    INDEX idx_status (order_status),
    INDEX idx_created (created_at)
);

-- جدول ملخص الإحصائيات اليومية للدعم
CREATE TABLE IF NOT EXISTS support_daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    stat_date DATE NOT NULL,
    total_orders INT DEFAULT 0,
    confirmed_orders INT DEFAULT 0,
    delivered_orders INT DEFAULT 0,
    cancelled_orders INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    UNIQUE KEY unique_support_date (support_id, stat_date),
    INDEX idx_date (stat_date)
);
