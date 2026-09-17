<?php
// ملف: add_user_id_to_products.php - إضافة عمود user_id إلى جدول products
include(__DIR__ . '/core/config.php");

// التحقق من وجود العمود أولاً
$check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'user_id'");

if ($check_column->num_rows == 0) {
    // إضافة العمود
    $result = $conn->query("ALTER TABLE products ADD COLUMN user_id INT NULL");
    
    if ($result) {
        // إضافة index
        $conn->query("ALTER TABLE products ADD INDEX idx_user_id (user_id)");
        echo "تم إضافة عمود user_id بنجاح!";
    } else {
        echo "خطأ في إضافة العمود: " . $conn->error;
    }
} else {
    echo "العمود user_id موجود بالفعل!";
}
?>

