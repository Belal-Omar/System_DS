<?php
// تشغيل migrations مرة واحدة عند أول دخول للوحة الإدارة

$conn->query("CREATE TABLE IF NOT EXISTS shipping_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$check_color_col = $conn->query("SHOW COLUMNS FROM product_images LIKE 'color_name'");
if ($check_color_col && $check_color_col->num_rows == 0) {
    $conn->query("ALTER TABLE product_images ADD COLUMN color_name VARCHAR(100) NULL AFTER is_main");
}

$conn->query("CREATE TABLE IF NOT EXISTS product_shippers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    shipping_company_id INT NOT NULL,
    stock INT DEFAULT 0,
    UNIQUE KEY uq_product_shipper (product_id, shipping_company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS shipping_company_cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipping_company_id INT NOT NULL,
    city_id INT NOT NULL,
    UNIQUE KEY uq_shipper_city (shipping_company_id, city_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS product_marketers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    marketer_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_marketer (product_id, marketer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS support_monthly_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month VARCHAR(7) NOT NULL UNIQUE,
    stock_quantity INT DEFAULT 0,
    product_sale_price DECIMAL(10,2) DEFAULT 0,
    product_cost DECIMAL(10,2) DEFAULT 0,
    intl_shipping DECIMAL(10,2) DEFAULT 0,
    dom_shipping DECIMAL(10,2) DEFAULT 0,
    ops_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS support_daily_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    product_code VARCHAR(100) NOT NULL DEFAULT 'all',
    lead_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_date_product (date, product_code)
)");

// Add product_code column if it doesn't exist
$conn->query("ALTER TABLE support_daily_leads ADD COLUMN product_code VARCHAR(100) NOT NULL DEFAULT 'all' AFTER date");
// Drop the old unique key on date if it exists
$conn->query("ALTER TABLE support_daily_leads DROP INDEX date");
// Add the new unique key
$conn->query("ALTER TABLE support_daily_leads ADD UNIQUE KEY uq_date_product (date, product_code)");

$conn->query("CREATE TABLE IF NOT EXISTS support_financial_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    type ENUM('collected', 'creditor', 'debtor') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note TEXT,
    period_month VARCHAR(7) NULL,
    shipping_company_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$check_sc_col = $conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'shipping_company_id'");
if ($check_sc_col && $check_sc_col->num_rows == 0) {
    $conn->query("ALTER TABLE support_financial_records ADD COLUMN shipping_company_id INT NULL AFTER period_month");
    $conn->query("ALTER TABLE support_financial_records ADD INDEX (shipping_company_id)");
}

$check_transfer = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'transfer_method'");
if ($check_transfer && $check_transfer->num_rows == 0) {
    $conn->query("ALTER TABLE withdrawals ADD COLUMN transfer_method VARCHAR(100) DEFAULT NULL AFTER processed_at");
}

$conn->query("CREATE TABLE IF NOT EXISTS support_product_monthly (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month VARCHAR(7) NOT NULL,
    product_code VARCHAR(100) NOT NULL DEFAULT 'Etala001',
    product_name VARCHAR(255) DEFAULT NULL,
    stock_quantity INT DEFAULT 0,
    product_sale_price DECIMAL(10,2) DEFAULT 0,
    bundle_sale_price DECIMAL(10,2) DEFAULT 0,
    product_cost DECIMAL(10,2) DEFAULT 0,
    bundle_cost DECIMAL(10,2) DEFAULT 0,
    intl_shipping DECIMAL(10,2) DEFAULT 0,
    dom_shipping DECIMAL(10,2) DEFAULT 0,
    ops_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_month_product (month, product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS shipping_company_reps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$check_rep_col = $conn->query("SHOW COLUMNS FROM support_orders LIKE 'shipping_rep_id'");
if ($check_rep_col && $check_rep_col->num_rows == 0) {
    $conn->query("ALTER TABLE support_orders ADD COLUMN shipping_rep_id INT NULL AFTER shipping_company_id");
    $conn->query("ALTER TABLE support_orders ADD INDEX (shipping_rep_id)");
}

$check_handed = $conn->query("SHOW COLUMNS FROM support_orders LIKE 'handed_to_rep_at'");
if ($check_handed && $check_handed->num_rows == 0) {
    $conn->query("ALTER TABLE support_orders ADD COLUMN handed_to_rep_at TIMESTAMP NULL DEFAULT NULL AFTER order_status");
}

$check_received = $conn->query("SHOW COLUMNS FROM support_orders LIKE 'received_at'");
if ($check_received && $check_received->num_rows == 0) {
    $conn->query("ALTER TABLE support_orders ADD COLUMN received_at TIMESTAMP NULL DEFAULT NULL AFTER handed_to_rep_at");
}