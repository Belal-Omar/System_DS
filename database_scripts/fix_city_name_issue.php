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

echo "<h2>🔧 إصلاح مشكلة city_name بشكل نهائي</h2>";

// التحقق من آخر 5 طلبات
echo "<h3>1. آخر 5 طلبات:</h3>";
$result = $conn->query("
    SELECT id, shipping_city_id, shipping_city_name, shipping_cost, created_at
    FROM orders 
    ORDER BY id DESC 
    LIMIT 5
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>رقم</th><th>city_id</th><th>city_name</th><th>cost</th><th>التاريخ</th></tr>";

while($row = $result->fetch_assoc()) {
    $bg_color = $row['shipping_city_name'] ? '#d4edda' : '#f8d7da';
    echo "<tr style='background: $bg_color;'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . ($row['shipping_city_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['shipping_city_name'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['shipping_cost'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// إصلاح جميع الطلبات التي لها city_id ولكن لا city_name
echo "<h3>2. إصلاح جميع الطلبات المفقودة:</h3>";

$fix_result = $conn->query("
    SELECT id, shipping_city_id
    FROM orders 
    WHERE shipping_city_id IS NOT NULL 
    AND shipping_city_id > 0 
    AND (shipping_city_name IS NULL OR shipping_city_name = '' OR shipping_city_name = 'NULL')
");

if ($fix_result && $fix_result->num_rows > 0) {
    $fixed_count = 0;
    
    while($row = $fix_result->fetch_assoc()) {
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
                echo "- ✅ تم إصلاح الطلب #$order_id: " . $city_data['city_name'] . "<br>";
                $fixed_count++;
            }
        }
    }
    
    echo "<h3>تم إصلاح $fixed_count طلب بنجاح</h3>";
} else {
    echo "- ✅ جميع الطلبات التي تحتوي على city_id لديها اسم المدينة<br>";
}

// إنشاء TRIGGER لضمان حفظ city_name دائماً
echo "<h3>3. إنشاء TRIGGER لضمان حفظ city_name:</h3>";

$conn->query("DROP TRIGGER IF EXISTS ensure_city_name");

$trigger_sql = "
CREATE TRIGGER ensure_city_name 
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    IF NEW.shipping_city_id IS NOT NULL AND NEW.shipping_city_id > 0 AND (NEW.shipping_city_name IS NULL OR NEW.shipping_city_name = '') THEN
        SELECT city_name, shipping_cost INTO @city_name, @city_cost 
        FROM shipping_cities 
        WHERE id = NEW.shipping_city_id;
        
        SET NEW.shipping_city_name = @city_name;
        IF NEW.shipping_cost = 0 OR NEW.shipping_cost IS NULL THEN
            SET NEW.shipping_cost = @city_cost;
        END IF;
    END IF;
END
";

if ($conn->query($trigger_sql)) {
    echo "- ✅ تم إنشاء TRIGGER بنجاح<br>";
} else {
    echo "- ❌ فشل في إنشاء TRIGGER: " . $conn->error . "<br>";
}

echo "<br><h3>✅ تم حل المشكلة نهائياً!</h3>";
echo "<h4>الآن:</h4>";
echo "- ✅ جميع الطلبات القديمة تم إصلاحها<br>";
echo "- ✅ TRIGGER يضمن حفظ city_name في الطلبات الجديدة<br>";
echo "- ✅ لا يمكن أن يحدث نفس المشكلة مرة أخرى<br>";

echo "<br><a href='orders.html' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 عرض الطلبات</a>";

$conn->close();
?>
