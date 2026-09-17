<?php
// ملف: debug_orders.php
header("Content-Type: application/json; charset=UTF-8");
include(__DIR__ . '/core/config.php");

// جلب جميع الطلبات مع تفاصيل أكثر
$debug_query = $conn->query("
    SELECT 
        o.id,
        o.customer_name,
        o.customer_phone, 
        o.region,
        o.total,
        o.commission_total,
        o.status,
        o.created_at,
        COUNT(oi.id) as items_count,
        GROUP_CONCAT(p.name) as product_names
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

$orders_debug = [];
while($order = $debug_query->fetch_assoc()) {
    $orders_debug[] = $order;
}

// جرد order_items
$items_query = $conn->query("SELECT * FROM order_items ORDER BY order_id");
$items_debug = [];
while($item = $items_query->fetch_assoc()) {
    $items_debug[] = $item;
}

echo json_encode([
    'success' => true,
    'orders_count' => count($orders_debug),
    'items_count' => count($items_debug),
    'orders' => $orders_debug,
    'items_sample' => array_slice($items_debug, 0, 5) // أول 5 عناصر فقط
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>