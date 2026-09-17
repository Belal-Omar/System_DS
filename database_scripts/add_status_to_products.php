<?php
// ملف: add_status_to_products.php - إضافة عمود status إلى جدول products
include(__DIR__ . '/core/config.php");

// التحقق من وجود العمود أولاً
$check_column = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");

if ($check_column->num_rows == 0) {
    // إضافة العمود
    $result = $conn->query("ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
    
    if ($result) {
        // تحديث جميع المنتجات الموجودة لتكون active
        $conn->query("UPDATE products SET status = 'active' WHERE status IS NULL");
        echo "تم إضافة عمود status بنجاح!";
    } else {
        echo "خطأ في إضافة العمود: " . $conn->error;
    }
} else {
    echo "العمود status موجود بالفعل!";
}
?>

