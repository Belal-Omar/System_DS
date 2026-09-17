<?php
require __DIR__ . '/config.php';
/** @var mysqli $conn */
if (!isset($conn) || !$conn instanceof mysqli) {
    die("Connection failed");
}
$res = $conn->query("SELECT id, username, role, shipping_company_id FROM admins LIMIT 20");
if (!$res) die($conn->error);
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
