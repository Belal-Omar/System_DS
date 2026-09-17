<?php
header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/core/config.php';

echo "<h1>تحديث قاعدة البيانات (Force Update)</h1>";

if ($conn->connect_error) {
    die("<span style='color:red'>فشل الاتصال: " . $conn->connect_error . "</span>");
}

function executeQuery($conn, $sql, $description) {
    echo "<div style='margin-bottom:10px; padding:10px; border:1px solid #ddd;'>";
    echo "<strong>$description</strong><br>";
    echo "<code>$sql</code><br>";
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<span style='color:green; font-weight:bold;'>تم التنفيذ بنجاح!</span>";
        } else {
            // Check if error is "Duplicate column name" which is fine
            if (strpos($conn->error, "Duplicate column name") !== false) {
                 echo "<span style='color:orange;'>العمود موجود بالفعل (لا داعي للقلق).</span>";
            } else {
                 echo "<span style='color:red; font-weight:bold;'>خطأ: " . $conn->error . "</span>";
            }
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>Exception: " . $e->getMessage() . "</span>";
    }
    echo "</div>";
}

// 1. Add special_commission to products
executeQuery($conn, 
    "ALTER TABLE products ADD COLUMN special_commission DECIMAL(10,2) DEFAULT 0.00", 
    "إضافة عمود special_commission لجدول products");

// 2. Add special_commission to order_items
executeQuery($conn, 
    "ALTER TABLE order_items ADD COLUMN special_commission DECIMAL(10,2) DEFAULT 0.00", 
    "إضافة عمود special_commission لجدول order_items");

// 3. Add total_special_commission to orders
executeQuery($conn, 
    "ALTER TABLE orders ADD COLUMN total_special_commission DECIMAL(10,2) DEFAULT 0.00", 
    "إضافة عمود total_special_commission لجدول orders");

echo "<h3>التحقق النهائي من الأعمدة:</h3>";
// Check products again
$cols = $conn->query("SHOW COLUMNS FROM products LIKE 'special_commission'");
if ($cols && $cols->num_rows > 0) {
    echo "<span style='color:green; font-size:1.2em;'>✓ عمود special_commission موجود في جدول products.</span><br>";
} else {
    echo "<span style='color:red; font-size:1.2em;'>✗ عمود special_commission غير موجود في جدول products!</span><br>";
}

$conn->close();
?>
