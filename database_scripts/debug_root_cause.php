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

echo "<h2>🔍 تشخيص المشكلة من جذورها</h2>";

// 1. التحقق من آخر طلب تم إضافته
echo "<h3>1. آخر طلب تم إضافته:</h3>";
$last_order = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 1");
$last = $last_order->fetch_assoc();

if ($last) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>العمود</th><th>القيمة</th></tr>";
    foreach ($last as $key => $value) {
        if (strpos($key, 'shipping') !== false) {
            $bg_color = $value ? '#d4edda' : '#f8d7da';
            echo "<tr style='background: $bg_color;'><td>$key</td><td>" . ($value ?? 'NULL') . "</td></tr>";
        }
    }
    echo "</table>";
}

// 2. التحقق من TRIGGER الحالية
echo "<h3>2. التحقق من TRIGGER الحالية:</h3>";
$triggers = $conn->query("SHOW TRIGGERS");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>اسم TRIGGER</th><th>الحدث</th><th>الجدول</th><th>التوقيت</th></tr>";
while ($trigger = $triggers->fetch_assoc()) {
    echo "<tr><td>{$trigger['Trigger']}</td><td>{$trigger['Event']}</td><td>{$trigger['Table']}</td><td>{$trigger['Timing']}</td></tr>";
}
echo "</table>";

// 3. اختبار إضافة طلب مباشر
echo "<h3>3. اختبار إضافة طلب مباشر:</h3>";

// بيانات اختبار
$test_city_id = 1; // الكفرة
$test_city_name = ''; // فارغ للاختبار
$test_cost = 0;

echo "قبل الاختبار:<br>";
echo "- city_id: $test_city_id<br>";
echo "- city_name: '$test_city_name'<br>";
echo "- cost: $test_cost<br>";

// اختبار TRIGGER
$conn->query("DELETE FROM orders WHERE customer_name = 'TEST_ROOT_CAUSE'");

$stmt = $conn->prepare("INSERT INTO orders (customer_name, shipping_city_id, shipping_city_name, shipping_cost) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sisi", 'TEST_ROOT_CAUSE', $test_city_id, $test_city_name, $test_cost);

if ($stmt->execute()) {
    $insert_id = $conn->insert_id;
    
    // التحقق من النتيجة
    $check = $conn->query("SELECT shipping_city_id, shipping_city_name, shipping_cost FROM orders WHERE id = $insert_id");
    $result = $check->fetch_assoc();
    
    echo "<br>بعد الاختبار:<br>";
    echo "- city_id: " . $result['shipping_city_id'] . "<br>";
    echo "- city_name: '" . $result['shipping_city_name'] . "'<br>";
    echo "- cost: " . $result['shipping_cost'] . "<br>";
    
    if ($result['shipping_city_name']) {
        echo "<h3 style='color: green;'>✅ TRIGGER تعمل بشكل صحيح!</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ TRIGGER لا تعمل!</h3>";
    }
    
    // حذف الاختبار
    $conn->query("DELETE FROM orders WHERE id = $insert_id");
} else {
    echo "<h3 style='color: red;'>❌ فشل في الإضافة: " . $stmt->error . "</h3>";
}

// 4. التحقق من بنية الجداول
echo "<h3>4. التحقق من بنية الجداول:</h3>";
$orders_columns = $conn->query("SHOW COLUMNS FROM orders");
echo "<h4>جدول orders:</h4>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>العمود</th><th>النوع</th><th>Null</th><th>المفتاح</th></tr>";
while ($col = $orders_columns->fetch_assoc()) {
    if (strpos($col['Field'], 'shipping') !== false) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
}
echo "</table>";

// 5. التحقق من عدد الطلبات التي لا تحتوي على مدينة
echo "<h3>5. عدد الطلبات التي لا تحتوي على مدينة:</h3>";
$problem_count = $conn->query("SELECT COUNT(*) as count FROM orders WHERE shipping_city_name IS NULL OR shipping_city_name = ''");
$count = $problem_count->fetch_assoc();
echo "- عدد الطلبات المشكلة: " . $count['count'] . "<br>";

if ($count['count'] > 0) {
    echo "<h3>الطلبات المشكلة:</h3>";
    $problem_orders = $conn->query("SELECT id, shipping_city_id, shipping_city_name FROM orders WHERE shipping_city_name IS NULL OR shipping_city_name = '' LIMIT 5");
    echo "<table border='1' style='border-collapse: collapse; width: 50%;'>";
    echo "<tr style='background: #f0f0f0;'><th>رقم</th><th>city_id</th><th>city_name</th></tr>";
    while ($order = $problem_orders->fetch_assoc()) {
        echo "<tr><td>{$order['id']}</td><td>{$order['shipping_city_id']}</td><td>" . ($order['shipping_city_name'] ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
}

$conn->close();
?>
