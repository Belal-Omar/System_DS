<?php
require 'config.php';
$sql = "CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('admin', 'user') NOT NULL,
    recipient_id INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_type, recipient_id, is_read)
)";
if ($conn->query($sql)) echo "Table created";
else echo "Error: " . $conn->error;
?>
