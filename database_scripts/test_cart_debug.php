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

$input = file_get_contents('php://input');
$data = json_decode($input, true);

echo json_encode([
    "success" => true,
    "message" => "بيانات الطلب المستلمة",
    "order_data" => $data,
    "debug_info" => [
        "shipping_city_id" => $data['shipping_city_id'] ?? 'NULL',
        "shipping_city_name" => $data['shipping_city_name'] ?? 'NULL',
        "shipping_cost" => $data['shipping_cost'] ?? 'NULL',
        "selectedShippingCity" => $data['selectedShippingCity'] ?? 'NOT_SENT'
    ]
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
