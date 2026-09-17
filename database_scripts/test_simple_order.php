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

echo "<h2>🧪 اختبار بسيط لإضافة طلب</h2>";

// اختبار إضافة طلب يدوي
$sql = "INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("فشل في تحضير الاستعلام: " . $conn->error);
}

$name = "عميل اختبار";
$phone = "0912345678";
$region = "طرابلس";
$address = "عنوان اختبار";
$city_id = 1;
$city_name = "طرابلس";
$shipping_cost = 5.00;
$total = 100.00;
$commission_total = 10.00;
$status = "قيد الانتظار";

$stmt->bind_param("sssssiddds", $name, $phone, $region, $address, $city_id, $city_name, $shipping_cost, $total, $commission_total, $status);

if ($stmt->execute()) {
    $order_id = $conn->insert_id;
    echo "<h3>✅ تم إضافة الطلب بنجاح!</h3>";
    echo "رقم الطلب: " . $order_id . "<br>";
    
    // التحقق من البيانات المحفوظة
    $check = $conn->query("SELECT * FROM orders WHERE id = $order_id");
    $order = $check->fetch_assoc();
    
    echo "<h3>البيانات المحفوظة:</h3>";
    echo "الاسم: " . $order['customer_name'] . "<br>";
    echo "shipping_city_id: " . $order['shipping_city_id'] . "<br>";
    echo "shipping_city_name: " . $order['shipping_city_name'] . "<br>";
    echo "shipping_cost: " . $order['shipping_cost'] . "<br>";
    
    // اختبار جلب البيانات مثل get_orders_final.php
    echo "<h3>اختبار جلب البيانات:</h3>";
    $test_result = $conn->query("SELECT id, customer_name, shipping_city_id, shipping_city_name, shipping_cost FROM orders WHERE id = $order_id");
    $test_order = $test_result->fetch_assoc();
    
    echo "البيانات المستلمة:<br>";
    echo "<pre>" . print_r($test_order, true) . "</pre>";
    
    // تحويل إلى JSON مثل get_orders_final.php
    $json_data = json_encode([
        'id' => $test_order['id'],
        'name' => $test_order['customer_name'],
        'shipping_city_id' => $test_order['shipping_city_id'],
        'shipping_city_name' => $test_order['shipping_city_name'],
        'shipping_cost' => $test_order['shipping_cost']
    ], JSON_UNESCAPED_UNICODE);
    
    echo "JSON Output:<br>";
    echo "<pre>" . htmlspecialchars($json_data) . "</pre>";
    
} else {
    echo "<h3>❌ فشل في إضافة الطلب: " . $stmt->error . "</h3>";
}

$stmt->close();
$conn->close();
?>
