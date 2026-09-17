<?php
/** @var mysqli $conn */
require __DIR__ . '/config.php';
/** @var mysqli $conn */
if (!isset($conn) || !$conn instanceof mysqli) {
    die("Connection failed");
}
$res = $conn->query("SELECT id, role, shipping_company_id FROM admins WHERE shipping_company_id IS NOT NULL");
while($r = $res->fetch_assoc()) print_r($r);
echo "\n====\n";
$res2 = $conn->query("SELECT id, name FROM shipping_accounts");
while($r = $res2->fetch_assoc()) print_r($r);
