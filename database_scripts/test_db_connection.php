<?php
// ملف اختبار الاتصال بقاعدة البيانات
header('Content-Type: application/json; charset=utf-8');

try {
    // إعدادات الاتصال
    $servername = "127.0.0.1";
    $username   = "u497700233_medhatomar5555";
    $password   = "BelalOmar49988155$";
    $dbname     = "u497700233_System";
    
    // إنشاء الاتصال
    $conn = new mysqli($servername, $username, $password, $dbname, 3306);
    
    // التحقق من الاتصال
    if ($conn->connect_error) {
        throw new Exception("فشل الاتصال: " . $conn->connect_error);
    }
    
    // تعيين الترميز
    $conn->set_charset("utf8mb4");
    
    // اختبار استعلام بسيط
    $result = $conn->query("SELECT 1 as test");
    if (!$result) {
        throw new Exception("فشل الاستعلام: " . $conn->error);
    }
    
    // اختبار جدول المدن
    $cities_result = $conn->query("SELECT COUNT(*) as count FROM shipping_cities");
    $cities_count = 0;
    if ($cities_result) {
        $row = $cities_result->fetch_assoc();
        $cities_count = $row['count'];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'الاتصال بقاعدة البيانات ناجح',
        'database' => $dbname,
        'cities_count' => $cities_count
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
