<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "system_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    try {
        $dbname = "system"; // Try another common name
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$sql = "CREATE TABLE IF NOT EXISTS landing_page_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    governorate VARCHAR(100) NOT NULL,
    product_package VARCHAR(255) NOT NULL,
    product_name VARCHAR(100) DEFAULT 'Miske',
    marketer_code VARCHAR(100) NOT NULL,
    status ENUM('new', 'processed', 'cancelled') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$pdo->exec($sql);
echo "Table landing_page_leads created successfully";
?>