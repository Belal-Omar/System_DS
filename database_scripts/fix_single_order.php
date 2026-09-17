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

$order_id = intval($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("رقم طلب غير صحيح");
}

echo "<h2>🔧 إصلاح الطلب #$order_id</h2>";

// جلب بيانات الطلب
$result = $conn->query("SELECT * FROM orders WHERE id = $order_id");
$order = $result->fetch_assoc();

if (!$order) {
    die("الطلب غير موجود");
}

echo "<h3>البيانات الحالية:</h3>";
echo "<pre>" . print_r($order, true) . "</pre>";

// إذا كان هناك city_id، جلب اسم المدينة
if ($order['shipping_city_id'] && $order['shipping_city_id'] > 0) {
    $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result->bind_param("i", $order['shipping_city_id']);
    $city_result->execute();
    $city_data = $city_result->get_result()->fetch_assoc();
    
    if ($city_data) {
        echo "<h3>بيانات المدينة من جدول shipping_cities:</h3>";
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
            echo "<h3 style='color: green;'>✅ تم تحديث الطلب بنجاح!</h3>";
            
            // عرض البيانات بعد التحديث
            $check_result = $conn->query("SELECT * FROM orders WHERE id = $order_id");
            $updated_order = $check_result->fetch_assoc();
            
            echo "<h3>البيانات بعد التحديث:</h3>";
            echo "<pre>" . print_r($updated_order, true) . "</pre>";
            
        } else {
            echo "<h3 style='color: red;'>❌ فشل في تحديث الطلب: " . $update_stmt->error . "</h3>";
        }
    } else {
        echo "<h3 style='color: orange;'>⚠️ لم يتم العثور على المدينة في جدول shipping_cities</h3>";
    }
} else {
    echo "<h3 style='color: orange;'>⚠️ الطلب لا يحتوي على shipping_city_id</h3>";
}

echo "<br><a href='investigate_orders.php' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 العودة للتحقيق</a>";
echo "<br><br><a href='orders.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";

$conn->close();
?>
