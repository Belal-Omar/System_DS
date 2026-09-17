<?php
/** @var mysqli $conn */
require __DIR__ . '/config.php';
/** @var mysqli $conn */
if (!isset($conn) || !$conn instanceof mysqli) {
    die("Connection failed");
}
$conn->query("
CREATE TABLE IF NOT EXISTS system_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_role VARCHAR(50) NOT NULL,
    sender_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    type ENUM('complaint', 'suggestion') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");
echo "Table created";
