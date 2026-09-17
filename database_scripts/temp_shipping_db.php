<?php
require_once 'config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed"); }

$conn->query("CREATE TABLE IF NOT EXISTS shipping_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Table shipping_companies ensured.\n";

$conn->query("ALTER TABLE admins ADD COLUMN shipping_company_id INT NULL");
$conn->query("ALTER TABLE support_orders ADD COLUMN shipping_company_id INT NULL");
echo "Done.";
