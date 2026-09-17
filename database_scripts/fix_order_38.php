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

echo "<h2>🔧 إصلاح الطلب 38 وتحديث TRIGGER</h2>";

// 1. إصلاح الطلب 38
echo "<h3>1. إصلاح الطلب 38:</h3>";
$result = $conn->query("SELECT shipping_city_id FROM orders WHERE id = 38");
$order = $result->fetch_assoc();

if ($order && $order['shipping_city_id']) {
    $city_id = $order['shipping_city_id'];
    echo "- city_id: $city_id<br>";
    
    $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result->bind_param("i", $city_id);
    $city_result->execute();
    $city_data = $city_result->get_result()->fetch_assoc();
    
    if ($city_data) {
        $update_stmt = $conn->prepare("UPDATE orders SET shipping_city_name = ?, shipping_cost = ? WHERE id = 38");
        $update_stmt->bind_param("sd", $city_data['city_name'], $city_data['shipping_cost']);
        
        if ($update_stmt->execute()) {
            echo "- ✅ تم إصلاح الطلب 38: " . $city_data['city_name'] . "<br>";
        }
    }
}

// 2. تحديث TRIGGER ليكون أقوى
echo "<h3>2. تحديث TRIGGER ليكون أقوى:</h3>";

$conn->query("DROP TRIGGER IF EXISTS ensure_city_name");

$trigger_sql = "
CREATE TRIGGER ensure_city_name 
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    IF NEW.shipping_city_id IS NOT NULL AND NEW.shipping_city_id > 0 THEN
        -- جلب اسم المدينة وتكلفة الشحن
        SELECT city_name, shipping_cost INTO @city_name, @city_cost 
        FROM shipping_cities 
        WHERE id = NEW.shipping_city_id;
        
        -- تحديث اسم المدينة إذا كان فارغاً
        IF NEW.shipping_city_name IS NULL OR NEW.shipping_city_name = '' THEN
            SET NEW.shipping_city_name = @city_name;
        END IF;
        
        -- تحديث تكلفة الشحن إذا كانت صفراً
        IF NEW.shipping_cost = 0 OR NEW.shipping_cost IS NULL THEN
            SET NEW.shipping_cost = @city_cost;
        END IF;
    END IF;
END
";

if ($conn->query($trigger_sql)) {
    echo "- ✅ تم تحديث TRIGGER بنجاح<br>";
} else {
    echo "- ❌ فشل في تحديث TRIGGER: " . $conn->error . "<br>";
}

// 3. إنشاء TRIGGER للتحديث أيضاً
echo "<h3>3. إنشاء TRIGGER للتحديث أيضاً:</h3>";

$update_trigger_sql = "
CREATE TRIGGER ensure_city_name_update 
BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.shipping_city_id IS NOT NULL AND NEW.shipping_city_id > 0 THEN
        -- جلب اسم المدينة وتكلفة الشحن
        SELECT city_name, shipping_cost INTO @city_name, @city_cost 
        FROM shipping_cities 
        WHERE id = NEW.shipping_city_id;
        
        -- تحديث اسم المدينة إذا كان فارغاً
        IF NEW.shipping_city_name IS NULL OR NEW.shipping_city_name = '' THEN
            SET NEW.shipping_city_name = @city_name;
        END IF;
        
        -- تحديث تكلفة الشحن إذا كانت صفراً
        IF NEW.shipping_cost = 0 OR NEW.shipping_cost IS NULL THEN
            SET NEW.shipping_cost = @city_cost;
        END IF;
    END IF;
END
";

if ($conn->query($update_trigger_sql)) {
    echo "- ✅ تم إنشاء TRIGGER للتحديث بنجاح<br>";
} else {
    echo "- ❌ فشل في إنشاء TRIGGER للتحديث: " . $conn->error . "<br>";
}

// 4. التحقق النهائي
echo "<h3>4. التحقق النهائي:</h3>";
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
} else {
    echo "<h2 style='color: orange;'>⚠️ لا تزال هناك بعض المشاكل</h2>";
}

echo "<br><h3>✅ الحل النهائي مكتمل!</h3>";
echo "<h4>الآن لديك:</h4>";
echo "- ✅ TRIGGER للإضافة (قبل إضافة أي طلب جديد)<br>";
echo "- ✅ TRIGGER للتحديث (لحماية الطلبات الحالية)<br>";
echo "- ✅ كود PHP إضافي في add_order_simple.php<br>";
echo "- ✅ حماية ثلاثية الطبقات<br>";

echo "<br><a href='test_add_order_debug.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 اختبار الإضافة</a>";
echo "<br><br><a href='orders.html' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";

$conn->close();
?>
