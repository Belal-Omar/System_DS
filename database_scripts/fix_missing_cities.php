<?php
header("Content-Type: text/html; charset=UTF-8");

// الاتصال المباشر بقاعدة البيانات
$servername = "localhost";
$username = "root"; 
$password = "BelalOmar499881";
$dbname = "system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "<h2>🔧 إصلاح بيانات مدينة الشحن المفقودة</h2>";

// التحقق من وجود الأعمدة
echo "<h3>1. التحقق من الأعمدة المطلوبة:</h3>";

$required_columns = ['shipping_city_id', 'shipping_city_name', 'shipping_cost'];
foreach ($required_columns as $column) {
    $check = $conn->query("SHOW COLUMNS FROM orders LIKE '$column'");
    if ($check->num_rows == 0) {
        echo "- ❌ العمود '$column' غير موجود - يتم إضافته...<br>";
        if ($column == 'shipping_city_id') {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_id INT NULL");
        } elseif ($column == 'shipping_city_name') {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_name VARCHAR(255) NULL");
        } elseif ($column == 'shipping_cost') {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10, 2) DEFAULT 0.00");
        }
        echo "- ✅ تم إضافة العمود '$column'<br>";
    } else {
        echo "- ✅ العمود '$column' موجود<br>";
    }
}

// جلب الطلبات التي لا تحتوي على اسم مدينة
echo "<h3>2. تحديث الطلبات التي لا تحتوي على اسم المدينة:</h3>";

$update_result = $conn->query("
    SELECT id, shipping_city_id 
    FROM orders 
    WHERE shipping_city_id IS NOT NULL 
    AND shipping_city_id > 0 
    AND (shipping_city_name IS NULL OR shipping_city_name = '')
");

if ($update_result && $update_result->num_rows > 0) {
    $updated_count = 0;
    
    while($row = $update_result->fetch_assoc()) {
        $order_id = $row['id'];
        $city_id = $row['shipping_city_id'];
        
        // جلب اسم المدينة
        $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
        $city_result->bind_param("i", $city_id);
        $city_result->execute();
        $city_data = $city_result->get_result()->fetch_assoc();
        
        if ($city_data) {
            // تحديث الطلب
            $update_stmt = $conn->prepare("
                UPDATE orders 
                SET shipping_city_name = ?, shipping_cost = ? 
                WHERE id = ?
            ");
            $update_stmt->bind_param("sdi", $city_data['city_name'], $city_data['shipping_cost'], $order_id);
            
            if ($update_stmt->execute()) {
                echo "- ✅ تحديث الطلب #$order_id: " . $city_data['city_name'] . "<br>";
                $updated_count++;
            } else {
                echo "- ❌ فشل تحديث الطلب #$order_id<br>";
            }
        }
    }
    
    echo "<h3>تم تحديث $updated_count طلب بنجاح</h3>";
} else {
    echo "- ✅ جميع الطلبات التي تحتوي على city_id لديها اسم المدينة<br>";
}

// التحقق من النتيجة النهائية
echo "<h3>3. التحقق من النتيجة النهائية:</h3>";

$final_check = $conn->query("
    SELECT 
        id,
        shipping_city_id,
        shipping_city_name,
        shipping_cost
    FROM orders 
    ORDER BY id DESC 
    LIMIT 5
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>رقم الطلب</th>";
echo "<th>shipping_city_id</th>";
echo "<th>shipping_city_name</th>";
echo "<th>shipping_cost</th>";
echo "</tr>";

while($row = $final_check->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . ($row['shipping_city_id'] ?? 'NULL') . "</td>";
    echo "<td style='background: " . ($row['shipping_city_name'] ? '#d4edda' : '#f8d7da') . ";'>";
    echo ($row['shipping_city_name'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['shipping_cost'] ?? '0.00') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><a href='orders.html' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";

$conn->close();
?>
