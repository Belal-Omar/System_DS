<?php
// ملف تصحيح لأخطاء get_shipping_cities.php
header('Content-Type: application/json; charset=utf-8');

try {
    // إعدادات الاتصال
    $servername = "127.0.0.1";
    $username   = "u497700233_medhatomar5555";
    $password   = "BelalOmar49988155$";
    $dbname     = "u497700233_System";
    
    // إنشاء الاتصال
    $conn = new mysqli($servername, $username, $password, $dbname, 3306);
    
    if ($conn->connect_error) {
        throw new Exception("فشل الاتصال: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // اختبار جلب المدن بدون Prepared Statement
    $result = $conn->query("SELECT * FROM shipping_cities ORDER BY city_name ASC LIMIT 5");
    $cities = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cities[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'تم جلب المدن بنجاح (بدون Prepared Statement)',
            'cities' => $cities,
            'total_test' => count($cities)
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("فشل الاستعلام: " . $conn->error);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}
?>
