<?php
$conn = new mysqli("127.0.0.1", "u497700233_medhatomar5555", "BelalOmar49988155$", "u497700233_System", 3306);
$conn->set_charset("utf8mb4");

echo "All distinct statuses:\n";
$res = $conn->query("SELECT DISTINCT order_status FROM support_orders");
while ($r = $res->fetch_assoc()) {
    echo $r['order_status'] . "\n";
}

echo "\nTotal orders count by status:\n";
$res = $conn->query("SELECT order_status, COUNT(*) as c FROM support_orders GROUP BY order_status");
while ($r = $res->fetch_assoc()) {
    echo $r['order_status'] . ": " . $r['c'] . "\n";
}
