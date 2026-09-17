<?php
$queries = [
    "CREATE TABLE IF NOT EXISTS shipping_inventory_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_code VARCHAR(100) NOT NULL UNIQUE,
        product_name VARCHAR(255) NOT NULL,
        status ENUM('active', 'disabled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "ALTER TABLE shipping_inventory_products ADD COLUMN IF NOT EXISTS status ENUM('active', 'disabled') DEFAULT 'active'",
    "CREATE TABLE IF NOT EXISTS shipping_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shipping_company_id INT NOT NULL,
        product_code VARCHAR(100) NOT NULL,
        quantity INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_company_product (shipping_company_id, product_code)
    )"
];

foreach ($queries as $q) {
    @$conn->query($q);
}

// Migrate existing products from old tables if not already inserted
@$conn->query("INSERT IGNORE INTO shipping_inventory_products (product_code, product_name) SELECT DISTINCT product_code, product_code FROM support_orders WHERE product_code IS NOT NULL AND product_code != ''");
@$conn->query("INSERT IGNORE INTO shipping_inventory_products (product_code, product_name) SELECT DISTINCT product_code, product_code FROM support_product_monthly WHERE product_code IS NOT NULL AND product_code != ''");

$conn->query("
CREATE TABLE IF NOT EXISTS system_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_role VARCHAR(50) NOT NULL,
    sender_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    type ENUM('complaint', 'suggestion') NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT NULL,
    status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");
$conn->query("ALTER TABLE system_complaints MODIFY COLUMN status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending'");
$conn->query("ALTER TABLE system_complaints ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL AFTER message");
?>
