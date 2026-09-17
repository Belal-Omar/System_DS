<?php
// اختبار config.php فقط
header("Content-Type: application/json; charset=UTF-8");

try {
    include(__DIR__ . '/core/config.php");
    
    if (!$conn || $conn->connect_error) {
        echo json_encode([
            "success" => false, 
            "message" => "فشل الاتصال: " . ($conn->connect_error ?? "لا يوجد اتصال")
        ]);
        exit();
    }
    
    echo json_encode([
        "success" => true, 
        "message" => "Config يعمل",
        "connection" => "موجود"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "خطأ: " . $e->getMessage()
    ]);
}
?>
