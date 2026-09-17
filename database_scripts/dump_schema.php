<?php
require __DIR__ . '/config.php';
/** @var mysqli $conn */
if (!isset($conn) || !$conn instanceof mysqli) {
    die("Connection failed");
}
$res = $conn->query("DESCRIBE support_orders");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
