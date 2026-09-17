<?php
$content = file_get_contents('config.php');
preg_match('/\$servername\s*=\s*"([^"]+)";/', $content, $m1);
preg_match('/\$username\s*=\s*"([^"]+)";/', $content, $m2);
preg_match('/\$password\s*=\s*"([^"]+)";/', $content, $m3);
preg_match('/\$dbname\s*=\s*"([^"]+)";/', $content, $m4);

$conn = new mysqli($m1[1], $m2[1], $m3[1], $m4[1], 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$queries = [
    "CREATE TABLE IF NOT EXISTS shipping_companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "ALTER TABLE admins ADD COLUMN shipping_company_id INT DEFAULT NULL AFTER role",
    "ALTER TABLE support_orders ADD COLUMN shipping_company_id INT DEFAULT NULL AFTER sheet_id"
];

foreach ($queries as $sql) {
    echo "Running: $sql\n";
    if ($conn->query($sql)) {
        echo "Success.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
