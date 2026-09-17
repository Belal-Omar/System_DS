<?php
require 'c:\xampp\htdocs\System\db_connect.php';
$q = $conn->query("SHOW CREATE TABLE shipping_inventory");
if ($r = $q->fetch_row()) {
    echo $r[1] . "\n\n";
}
$q = $conn->query("SHOW CREATE TABLE shipping_inventory_products");
if ($r = $q->fetch_row()) {
    echo $r[1] . "\n\n";
}
