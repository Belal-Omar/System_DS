<?php
include(__DIR__ . '/core/config.php");
header("Content-Type: text/plain");

echo "Checking orders table structure...\n";
$res = $conn->query("SHOW COLUMNS FROM orders LIKE 'total_special_commission'");
if ($res->num_rows > 0) {
    echo "OK: total_special_commission exists in orders.\n";
} else {
    echo "ERROR: total_special_commission MISSING in orders.\n";
}

$res = $conn->query("SHOW COLUMNS FROM order_items LIKE 'special_commission'");
if ($res->num_rows > 0) {
    echo "OK: special_commission exists in order_items.\n";
} else {
    echo "ERROR: special_commission MISSING in order_items.\n";
}

echo "\nChecking last 5 orders for commission data:\n";
$sql = "SELECT id, commission_total, total_special_commission FROM orders ORDER BY id DESC LIMIT 5";
$result = $conn->query($sql);
while($row = $result->fetch_assoc()) {
    echo "Order #{$row['id']}: Net={$row['commission_total']}, Special={$row['total_special_commission']}\n";
    
    // Check items for this order
    $itemSql = "SELECT product_id, quantity, commission, special_commission FROM order_items WHERE order_id = {$row['id']}";
    $itemRes = $conn->query($itemSql);
    while($item = $itemRes->fetch_assoc()) {
        echo "  -> Product #{$item['product_id']}: Qty={$item['quantity']}, UnitNet={$item['commission']}, UnitSpecial={$item['special_commission']}\n";
    }
}
?>
