<?php
header("Content-Type: application/json; charset=UTF-8");

// الاتصال المباشر بقاعدة البيانات
$servername = "localhost";
$username = "root"; 
$password = "BelalOmar499881";
$dbname = "system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        "success" => false, 
        "message" => "فشل الاتصال بقاعدة البيانات"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn->set_charset("utf8mb4");

// جلب آخر 3 طلبات فقط للاختبار
$result = $conn->query("
    SELECT 
        id,
        customer_name as name,
        shipping_city_id,
        shipping_city_name,
        shipping_cost,
        total,
        status,
        created_at as date
    FROM orders 
    ORDER BY id DESC 
    LIMIT 3
");

$orders = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $orders[] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'shipping_city_id' => $row['shipping_city_id'],
            'shipping_city_name' => $row['shipping_city_name'],
            'shipping_cost' => floatval($row['shipping_cost']),
            'total' => floatval($row['total']),
            'status' => $row['status'],
            'date' => $row['date']
        ];
    }
}

echo json_encode([
    'success' => true,
    'orders' => $orders,
    'message' => 'تم جلب ' . count($orders) . ' طلب للاختبار'
], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

$conn->close();
?>
