<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'system');
$res = $conn->query("SHOW CREATE TABLE support_orders");
$row = $res->fetch_array();
echo $row[1] . "\n\n";

$res2 = $conn->query("SHOW CREATE TABLE orders");
$row2 = $res2->fetch_array();
echo $row2[1];
