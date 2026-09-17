<?php
// Add shipping_city_name column to orders table
require_once 'config.php';

if ($conn) {
    // Check if shipping_city_name column exists
    $check_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_city_name'");
    
    if ($check_column->num_rows == 0) {
        // Add the column
        $sql = "ALTER TABLE orders ADD COLUMN shipping_city_name VARCHAR(255) NULL AFTER shipping_city_id";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ تم إضافة عمود shipping_city_name بنجاح</p>";
            
            // Update existing orders with city names
            $update_sql = "UPDATE orders o 
                          LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id 
                          SET o.shipping_city_name = sc.city_name 
                          WHERE o.shipping_city_id IS NOT NULL AND o.shipping_city_name IS NULL";
            
            if ($conn->query($update_sql)) {
                $affected_rows = $conn->affected_rows;
                echo "<p style='color: green;'>✓ تم تحديث $affected_rows طلب بأسماء المدن</p>";
            } else {
                echo "<p style='color: red;'>✗ خطأ في تحديث أسماء المدن: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ خطأ في إضافة العمود: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>- عمود shipping_city_name موجود بالفعل</p>";
    }
} else {
    echo "<p style='color: red;'>✗ خطأ في الاتصال بقاعدة البيانات</p>";
}
?>
