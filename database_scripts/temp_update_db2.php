<?php
require_once 'config.php';
if (!$conn) {
    echo "Connection failed.\n";
    exit;
}
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS force_locked TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS unlocked_at DATETIME NULL");
echo "Columns force_locked and unlocked_at added successfully.";
