<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// تعديل error reporting لتجنب أي output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// التأكد من أن الجلسة لا تسبب مشاكل
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/core/config.php");

$response = ['success' => false, 'cities' => [], 'message' => ''];

try {
    // التحقق من الاتصال بقاعدة البيانات
    if (!$conn || $conn->connect_error) {
        $response['message'] = "فشل الاتصال بقاعدة البيانات";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        exit();
    }
    
    // التحقق من وجود الجدول
    $conn->query("CREATE TABLE IF NOT EXISTS shipping_cities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        city_name VARCHAR(100) NOT NULL UNIQUE,
        shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $result = $conn->query("SELECT * FROM shipping_cities ORDER BY city_name ASC");
    $cities = [];
    
    if ($result) {
        while($city = $result->fetch_assoc()) {
            $cities[] = $city;
        }
    }
    
    $response['success'] = true;
    $response['cities'] = $cities;
    $response['message'] = 'تم جلب المدن بنجاح';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in get_shipping_cities.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
?>
