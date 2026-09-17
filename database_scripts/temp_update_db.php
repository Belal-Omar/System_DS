<?php
require_once 'config.php';
if (!$conn) {
    echo "Connection failed.\n";
    exit;
}
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS is_unlocked TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS unlock_request TINYINT(1) DEFAULT 0");
echo "Done.\n";
