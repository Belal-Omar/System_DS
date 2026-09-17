<?php
// ملف: test_connection.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include(__DIR__ . '/core/config.php");

$response = [];

try {
    // اختبار الاتصال بقاعدة البيانات
    if ($conn->connect_error) {
        throw new Exception("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
    }
    
    $response['database'] = "✅ الاتصال بقاعدة البيانات ناجح";
    
    // اختبار الجداول
    $tables = ['users', 'products', 'orders', 'order_items', 'product_inventory', 'withdrawals'];
    $existing_tables = [];
    
    $result = $conn->query("SHOW TABLES");
    while($row = $result->fetch_array()) {
        $existing_tables[] = $row[0];
    }
    
    $response['tables'] = [
        'existing' => $existing_tables,
        'required' => $tables
    ];
    
    // اختبار إضافة طلب تجريبي
    $test_order = $conn->query("INSERT INTO orders (customer_name, customer_phone, region, address, total, commission_total) 
                               VALUES ('اختبار', '0912345678', 'منطقة اختبار', 'عنوان اختبار', 100.00, 10.00)");
    
    if ($test_order) {
        $order_id = $conn->insert_id;
        $response['test_order'] = "✅ إضافة طلب اختبار ناجحة - رقم الطلب: " . $order_id;
        
        // تنظيف الطلب التجريبي
        $conn->query("DELETE FROM orders WHERE id = $order_id");
    } else {
        $response['test_order'] = "❌ فشل إضافة طلب اختبار: " . $conn->error;
    }
    
    $response['success'] = true;
    $response['message'] = "جميع الاختبارات مكتملة";
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>