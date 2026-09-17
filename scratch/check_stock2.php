<?php
require 'config.php';
/** @var mysqli $conn */
$res = $conn->query("SELECT product_code, COUNT(*) as c, SUM(pieces) as p FROM support_orders WHERE product_code = 'Etala001'");
$row = $res->fetch_assoc();
echo "Etala001 in support_orders: count={$row['c']}, sum_pieces={$row['p']}\n";

$res = $conn->query("SELECT product_code, COUNT(*) as c, SUM(pieces) as p FROM support_orders WHERE product_code = 'Etala001' AND order_status = 'handed_to_rep'");
$row = $res->fetch_assoc();
echo "Etala001 handed_to_rep: count={$row['c']}, sum_pieces={$row['p']}\n";

$res = $conn->query("SELECT product_code, COUNT(*) as c, SUM(pieces) as p FROM support_orders WHERE product_code = 'Etala001' AND order_status IN ('cancelled', 'order_cancelled', 'returned')");
$row = $res->fetch_assoc();
echo "Etala001 cancelled: count={$row['c']}, sum_pieces={$row['p']}\n";

$res = $conn->query("SELECT * FROM shipping_inventory WHERE product_code = 'Etala001'");
while($row = $res->fetch_assoc()) {
    echo "Shipping Inv for {$row['shipping_company_id']}: {$row['quantity']}\n";
}
