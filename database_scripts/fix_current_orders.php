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

echo "<h2>🔧 إصلاح الطلبات الحالية</h2>";

// إصلاح الطلبين 34 و 35
$problem_orders = [34, 35];
$fixed_count = 0;

foreach ($problem_orders as $order_id) {
    echo "<h3>🔧 إصلاح الطلب #$order_id:</h3>";
    
    // جلب بيانات الطلب
    $result = $conn->query("SELECT shipping_city_id FROM orders WHERE id = $order_id");
    $order = $result->fetch_assoc();
    
    if ($order && $order['shipping_city_id']) {
        $city_id = $order['shipping_city_id'];
        echo "- city_id: $city_id<br>";
        
        // جلب اسم المدينة
        $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
        $city_result->bind_param("i", $city_id);
        $city_result->execute();
        $city_data = $city_result->get_result()->fetch_assoc();
        
        if ($city_data) {
            echo "- اسم المدينة: " . $city_data['city_name'] . "<br>";
            echo "- تكلفة الشحن: " . $city_data['shipping_cost'] . "<br>";
            
            // تحديث الطلب
            $update_stmt = $conn->prepare("
                UPDATE orders 
                SET shipping_city_name = ?, shipping_cost = ? 
                WHERE id = ?
            ");
            $update_stmt->bind_param("sdi", $city_data['city_name'], $city_data['shipping_cost'], $order_id);
            
            if ($update_stmt->execute()) {
                echo "- ✅ تم التحديث بنجاح<br>";
                $fixed_count++;
            } else {
                echo "- ❌ فشل التحديث: " . $update_stmt->error . "<br>";
            }
        } else {
            echo "- ❌ لم يتم العثور على المدينة<br>";
        }
    } else {
        echo "- ❌ الطلب غير موجود أو لا يحتوي على city_id<br>";
    }
    echo "<br>";
}

echo "<h2>📊 النتيجة النهائية:</h2>";
echo "- تم إصلاح $fixed_count طلب بنجاح<br>";

// التحقق من النتيجة
echo "<h3>🔍 التحقق من النتيجة:</h3>";
$check_result = $conn->query("
    SELECT id, shipping_city_id, shipping_city_name, shipping_cost
    FROM orders 
    WHERE id IN (34, 35)
    ORDER BY id DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 50%;'>";
echo "<tr style='background: #f0f0f0;'><th>رقم</th><th>city_id</th><th>city_name</th><th>cost</th></tr>";

while($row = $check_result->fetch_assoc()) {
    $bg_color = $row['shipping_city_name'] ? '#d4edda' : '#f8d7da';
    echo "<tr style='background: $bg_color;'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['shipping_city_id'] . "</td>";
    echo "<td>" . ($row['shipping_city_name'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['shipping_cost'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><a href='orders.html' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";
echo "<br><br><h3>✅ الآن يجب أن تظهر مدينة الشحن في جميع الطلبات!</h3>";

$conn->close();
?>
