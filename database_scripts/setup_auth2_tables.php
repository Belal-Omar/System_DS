<?php
declare(strict_types=1);

require_once __DIR__ . '/auth2_db.php';

$db = auth2_db();

$sql = "CREATE TABLE IF NOT EXISTS auth2_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Manager', 'User') NOT NULL,
    twofa_secret VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$db->query($sql)) {
    http_response_code(500);
    echo 'Table creation failed: ' . $db->error;
    exit;
}

echo "auth2_users table is ready.";
?>
