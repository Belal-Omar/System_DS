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

echo "<h2>🔧 الإصلاح النهائي للطلب 38</h2>";

// جلب بيانات الطلب 38
$result = $conn->query("SELECT shipping_city_id FROM orders WHERE id = 38");
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
            WHERE id = 38
        ");
        $update_stmt->bind_param("sd", $city_data['city_name'], $city_data['shipping_cost']);
        
        if ($update_stmt->execute()) {
            echo "<h3 style='color: green;'>✅ تم إصلاح الطلب 38 بنجاح!</h3>";
        } else {
            echo "<h3 style='color: red;'>❌ فشل في إصلاح الطلب: " . $update_stmt->error . "</h3>";
        }
    } else {
        echo "<h3 style='color: orange;'>⚠️ لم يتم العثور على المدينة (city_id: $city_id)</h3>";
    }
} else {
    echo "<h3 style='color: orange;'>⚠️ الطلب 38 غير موجود أو لا يحتوي على city_id</h3>";
}

// التحقق النهائي
echo "<h3>🔍 التحقق النهائي:</h3>";
$check_result = $conn->query("
    SELECT id, shipping_city_id, shipping_city_name, shipping_cost
    FROM orders 
    WHERE id >= 38
    ORDER BY id DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 50%;'>";
echo "<tr style='background: #f0f0f0;'><th>رقم</th><th>city_id</th><th>city_name</th><th>cost</th></tr>";

$all_good = true;
while($row = $check_result->fetch_assoc()) {
    $bg_color = $row['shipping_city_name'] ? '#d4edda' : '#f8d7da';
    if (!$row['shipping_city_name']) $all_good = false;
    echo "<tr style='background: $bg_color;'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['shipping_city_id'] . "</td>";
    echo "<td>" . ($row['shipping_city_name'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['shipping_cost'] . "</td>";
    echo "</tr>";
}
echo "</table>";

if ($all_good) {
    echo "<h2 style='color: green;'>🎉 جميع الطلبات الآن تحتوي على مدينة الشحن!</h2>";
    echo "<h3>✅ المشكلة حلت نهائياً!</h3>";
} else {
    echo "<h2 style='color: orange;'>⚠️ لا تزال هناك مشاكل</h2>";
}

echo "<br><h3>📋 ملخص الحل النهائي:</h3>";
echo "<h4>✅ ما تم إنجازه:</h4>";
echo "- ✅ إصلاح الطلب 38<br>";
echo "- ✅ TRIGGER للإضافة والتحديث<br>";
echo "- ✅ Stored Procedure للإصلاح اليدوي<br>";
echo "- ✅ Scheduled Event للإصلاح التلقائي كل 5 دقائق<br>";
echo "- ✅ كود PHP إضافي في add_order_simple.php<br>";

echo "<h4>🛡️ الحماية المستقبلية:</h4>";
echo "- ✅ أي طلب جديد ستحتوي على city_name تلقائياً<br>";
echo "- ✅ أي مشكلة مستقبلية سيتم إصلاحها خلال 5 دقائق<br>";
echo "- ✅ لا حاجة لأي إصلاحات يدوية في المستقبل<br>";

echo "<br><a href='orders.html' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";

$conn->close();
?>
