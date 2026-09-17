<?php
require_once 'config.php';
/** @var mysqli $conn */

$conn->query("ALTER TABLE admins ADD COLUMN allowed_device_id VARCHAR(255) NULL");
$conn->query("ALTER TABLE users ADD COLUMN allowed_device_id VARCHAR(255) NULL");

$sql = "CREATE TABLE IF NOT EXISTS device_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('admin', 'user') NOT NULL,
    account_id INT NOT NULL,
    requested_device_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "Success";
} else {
    echo "Error: " . $conn->error;
}

$conn->query("ALTER TABLE shipping_inventory_products ADD COLUMN IF NOT EXISTS stock_quantity INT NOT NULL DEFAULT 0");
echo "<br>Stock column ensured.";

