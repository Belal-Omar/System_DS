<?php
// auto_fix_orders.php

// إعدادات الاتصال بقاعدة البيانات
$servername = "127.0.0.1";
$username   = "u497700233_medhatomar5555";
$password   = "BelalOmar49988155$";
$dbname     = "u497700233_System";
$port       = 3306;

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// فحص الاتصال
if ($conn->connect_error) {
    error_log("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
    exit();
}

// تعيين الترميز
$conn->set_charset("utf8mb4");

// تنفيذ تحديث المدن وتكلفة الشحن
$sql = "
UPDATE orders o
LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
SET 
    o.shipping_city_name = CASE 
        WHEN o.shipping_city_id IS NULL OR o.shipping_city_id = 0 THEN 'مدينة غير محددة'
        WHEN sc.city_name IS NOT NULL AND sc.city_name != '' THEN sc.city_name
        ELSE 'مدينة غير معروفة'
    END,
    o.shipping_cost = CASE 
        WHEN o.shipping_cost IS NULL OR o.shipping_cost = 0 THEN 
            CASE 
                WHEN sc.shipping_cost IS NOT NULL AND sc.shipping_cost > 0 THEN sc.shipping_cost
                ELSE 30.00
            END
        ELSE o.shipping_cost
    END
WHERE o.shipping_city_name IS NULL OR o.shipping_city_name = '' OR o.shipping_cost IS NULL OR o.shipping_cost = 0
";

if ($conn->query($sql) === TRUE) {
    echo "تم تحديث الطلبات بنجاح!\n";
} else {
    error_log("خطأ أثناء تحديث الطلبات: " . $conn->error);
}

$conn->close();
?>
