<?php
require __DIR__ . '/config.php';
/** @var mysqli $conn */
$conn->query("ALTER TABLE admins MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin'");
$conn->query("UPDATE admins SET role = 'shipping_company' WHERE (role = '' OR role = 'admin') AND shipping_company_id IS NOT NULL AND shipping_company_id > 0");
echo "Updated rows: " . $conn->affected_rows;
?>
