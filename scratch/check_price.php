<?php 
$conn = new mysqli('localhost', 'root', '', 'System');
$conn->set_charset("utf8mb4");
$res = $conn->query('SELECT id, phone, pieces, total_price, product_code, bundle_type, created_at FROM support_orders WHERE phone IN ("928871451", "944175079", "913889478")'); 
print_r($res->fetch_all(MYSQLI_ASSOC));
