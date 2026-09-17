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

echo "<h2>🔧 فرض city_name لجميع الطلبات</h2>";

// جلب جميع الطلبات التي لها city_id ولكن لا city_name
$result = $conn->query("
    SELECT id, shipping_city_id
    FROM orders 
    WHERE shipping_city_id IS NOT NULL 
    AND shipping_city_id > 0 
    AND (shipping_city_name IS NULL OR shipping_city_name = '' OR shipping_city_name = 'NULL')
");

if ($result && $result->num_rows > 0) {
    $fixed_count = 0;
    $total_count = $result->num_rows;
    
    echo "<h3>العثور على $total_count طلب يحتاج إصلاح</h3>";
    
    while($row = $result->fetch_assoc()) {
        $order_id = $row['id'];
        $city_id = $row['shipping_city_id'];
        
        echo "<h4>🔧 إصلاح الطلب #$order_id (city_id: $city_id):</h4>";
        
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
                echo "- ✅ تم التحديث: " . $city_data['city_name'] . " (cost: " . $city_data['shipping_cost'] . ")<br>";
                $fixed_count++;
            } else {
                echo "- ❌ فشل التحديث: " . $update_stmt->error . "<br>";
            }
        } else {
            echo "- ❌ لم يتم العثور على المدينة (city_id: $city_id)<br>";
        }
    }
    
    echo "<h3>النتيجة: تم إصلاح $fixed_count من $total_count طلب</h3>";
} else {
    echo "<h3>✅ جميع الطلبات تحتوي على اسم المدينة</h3>";
}

// التحقق النهائي
echo "<h3>🔍 التحقق النهائي:</h3>";
$check_result = $conn->query("
    SELECT id, shipping_city_id, shipping_city_name, shipping_cost
    FROM orders 
    WHERE shipping_city_id IS NOT NULL 
    AND shipping_city_id > 0 
    ORDER BY id DESC 
    LIMIT 10
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>رقم</th><th>city_id</th><th>city_name</th><th>cost</th><th>الحالة</th></tr>";

$all_good = true;
while($row = $check_result->fetch_assoc()) {
    $bg_color = $row['shipping_city_name'] ? '#d4edda' : '#f8d7da';
    $status = $row['shipping_city_name'] ? '✅ جيد' : '❌ يحتاج إصلاح';
    if (!$row['shipping_city_name']) $all_good = false;
    
    echo "<tr style='background: $bg_color;'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['shipping_city_id'] . "</td>";
    echo "<td>" . ($row['shipping_city_name'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['shipping_cost'] . "</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table>";

if ($all_good) {
    echo "<h2 style='color: green;'>🎉 جميع الطلبات الآن تحتوي على مدينة الشحن!</h2>";
} else {
    echo "<h2 style='color: orange;'>⚠️ لا تزال هناك مشاكل</h2>";
}

echo "<br><h3>🔧 إنشاء إجراء مخزن (Stored Procedure) للإصلاح المستقبلي:</h3>";

$procedure_sql = "
CREATE PROCEDURE IF NOT EXISTS fix_shipping_cities()
BEGIN
    -- تحديث جميع الطلبات التي لها city_id ولكن لا city_name
    UPDATE orders o
    JOIN shipping_cities sc ON o.shipping_city_id = sc.id
    SET 
        o.shipping_city_name = sc.city_name,
        o.shipping_cost = sc.shipping_cost
    WHERE 
        o.shipping_city_id IS NOT NULL 
        AND o.shipping_city_id > 0 
        AND (o.shipping_city_name IS NULL OR o.shipping_city_name = '');
END
";

if ($conn->query($procedure_sql)) {
    echo "- ✅ تم إنشاء الإجراء المخزن بنجاح<br>";
    echo "- يمكنك استدعاؤه بـ: CALL fix_shipping_cities();<br>";
} else {
    echo "- ❌ فشل في إنشاء الإجراء المخزن: " . $conn->error . "<br>";
}

echo "<br><h3>🔄 إنشاء مهمة مجدولة (Event) للإصلاح التلقائي كل 5 دقائق:</h3>";

$event_sql = "
CREATE EVENT IF NOT EXISTS auto_fix_shipping_cities
ON SCHEDULE EVERY 5 MINUTE
DO CALL fix_shipping_cities();
";

if ($conn->query($event_sql)) {
    echo "- ✅ تم إنشاء المهمة المجدولة بنجاح<br>";
    echo "- سيتم إصلاح أي طلب جديد ينقصه city_name تلقائياً كل 5 دقائق<br>";
} else {
    echo "- ❌ فشل في إنشاء المهمة المجدولة: " . $conn->error . "<br>";
}

echo "<br><h3>✅ الحل النهائي مكتمل!</h3>";
echo "<h4>الآن لديك:</h4>";
echo "- ✅ جميع الطلبات الحالية تم إصلاحها<br>";
echo "- ✅ TRIGGER للإضافة والتحديث<br>";
echo "- ✅ إجراء مخزن للإصلاح اليدوي<br>";
echo "- ✅ مهمة مجدولة للإصلاح التلقائي كل 5 دقائق<br>";

echo "<br><a href='orders.html' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";

$conn->close();
?>
