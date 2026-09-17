<?php
// ملف: test_orders.php
header("Content-Type: application/json; charset=UTF-8");
include(__DIR__ . '/core/config.php");

// اختبار بسيط للتحقق من وجود الطلبات
$test_query = $conn->query("SELECT COUNT(*) as total FROM orders");
$result = $test_query->fetch_assoc();

echo json_encode([
    'success' => true,
    'total_orders' => $result['total'],
    'tables_exist' => [
        'orders' => $conn->query("SHOW TABLES LIKE 'orders'")->num_rows > 0,
        'order_items' => $conn->query("SHOW TABLES LIKE 'order_items'")->num_rows > 0
    ],
    'connection' => $conn->connect_error ? $conn->connect_error : 'success'
], JSON_UNESCAPED_UNICODE);
?>