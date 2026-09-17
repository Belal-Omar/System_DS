<?php
// ملف اختبار بسيط للتحقق من المشكلة
ob_clean(); // تنظيف أي output سابق
header("Content-Type: application/json; charset=UTF-8");

// تجربة include config.php فقط
try {
    include(__DIR__ . '/core/config.php");
    
    if (!$conn || $conn->connect_error) {
        echo json_encode([
            "success" => false, 
            "message" => "فشل الاتصال بقاعدة البيانات: " . ($conn->connect_error ?? "اتصال غير موجود")
        ]);
        exit();
    }
    
    // اختبار simple query
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        $row = $result->fetch_assoc();
        echo json_encode([
            "success" => true, 
            "message" => "الاتصال بقاعدة البيانات يعمل",
            "test" => $row['test'],
            "debug_info" => [
                "php_version" => PHP_VERSION,
                "memory_usage" => memory_get_usage(true),
                "included_files" => count(get_included_files())
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "خطأ في الاستعلام: " . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "استثناء: " . $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>
