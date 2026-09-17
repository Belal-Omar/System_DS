<?php 
$conn = new mysqli('localhost', 'root', '', 'System');
$conn->set_charset("utf8mb4");

$res = $conn->query("SELECT SUM(pieces) as total_pieces FROM support_orders WHERE product_code = 'Etala001' AND order_status NOT IN ('cancelled', 'order_cancelled', 'returned')"); 
$row = $res->fetch_assoc();
echo "Total active pieces for Etala001 in support_orders: " . $row['total_pieces'] . "\n";

$res = $conn->query("SELECT stock_quantity FROM shipping_inventory_products WHERE product_code = 'Etala001'");
$row = $res->fetch_assoc();
echo "Current stock_quantity in shipping_inventory_products: " . $row['stock_quantity'] . "\n";
