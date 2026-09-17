<?php
session_start();
include(__DIR__ . '/core/config.php");

$user_id = $_SESSION['user_id'] ?? null;

// Debug calculation for profit
echo "<h3>Debug Profit Calculation</h3>";

// Current calculation
$current_query = $conn->query("
    SELECT COALESCE(SUM((oi.price - oi.commission) * oi.quantity), 0) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");

if ($current_query) {
    $row = $current_query->fetch_assoc();
    echo "Current calculation (price - commission): " . $row['total'] . "<br>";
}

// Show individual order items
$items_query = $conn->query("
    SELECT oi.price, oi.commission, oi.quantity, p.name
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");

echo "<h4>Individual Items:</h4>";
while ($item = $items_query->fetch_assoc()) {
    $net_profit = ($item['price'] - $item['commission']) * $item['quantity'];
    echo "Product: " . $item['name'] . "<br>";
    echo "Price: " . $item['price'] . ", Commission: " . $item['commission'] . ", Quantity: " . $item['quantity'] . "<br>";
    echo "Net Profit: " . $net_profit . "<br><br>";
}
?>
