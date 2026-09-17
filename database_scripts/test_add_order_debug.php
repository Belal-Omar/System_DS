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

echo "<h2>🔍 اختبار إضافة طلب مع city_name</h2>";

// بيانات اختبار مثل التي تأتي من JavaScript
$test_data = [
    'name' => 'عميل اختبار',
    'phone' => '0912345678',
    'region' => 'طرابلس',
    'address' => 'عنوان اختبار',
    'shipping_city_id' => 1,
    'shipping_city_name' => '', // فارغ للاختبار
    'shipping_cost' => 0,
    'total' => 100.00,
    'commission_total' => 10.00,
    'items' => [
        [
            'product_id' => 1,
            'quantity' => 1,
            'price' => 100.00,
            'commission' => 10.00,
            'color' => 'أحمر',
            'size' => 'M'
        ]
    ]
];

echo "<h3>البيانات الأصلية:</h3>";
echo "<pre>" . print_r($test_data, true) . "</pre>";

// تطبيق نفس الكود الموجود في add_order_simple.php
$shipping_city_id = intval($test_data['shipping_city_id'] ?? 0);
$shipping_city_name = $conn->real_escape_string($test_data['shipping_city_name'] ?? '');
$shipping_cost = floatval($test_data['shipping_cost'] ?? 0);

echo "<h3>قبل التحقق:</h3>";
echo "- shipping_city_id: $shipping_city_id<br>";
echo "- shipping_city_name: '$shipping_city_name'<br>";
echo "- shipping_cost: $shipping_cost<br>";

// التأكد من وجود اسم المدينة
if (empty($shipping_city_name) && $shipping_city_id > 0) {
    echo "<h3>✅ الشرط تحقق: city_name فارغ و city_id موجود</h3>";
    
    // جلب اسم المدينة من جدول shipping_cities
    $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result->bind_param("i", $shipping_city_id);
    $city_result->execute();
    $city_data = $city_result->get_result()->fetch_assoc();
    
    if ($city_data) {
        $shipping_city_name = $city_data['city_name'];
        if ($shipping_cost == 0) {
            $shipping_cost = $city_data['shipping_cost'];
        }
        echo "<h3>✅ تم جلب بيانات المدينة:</h3>";
        echo "- اسم المدينة: $shipping_city_name<br>";
        echo "- تكلفة الشحن: $shipping_cost<br>";
    } else {
        echo "<h3>❌ لم يتم العثور على المدينة</h3>";
    }
} else {
    echo "<h3>❌ الشرط لم يتحقق:</h3>";
    echo "- city_name فارغ: " . (empty($shipping_city_name) ? 'نعم' : 'لا') . "<br>";
    echo "- city_id > 0: " . ($shipping_city_id > 0 ? 'نعم' : 'لا') . "<br>";
}

echo "<h3>بعد التحقق:</h3>";
echo "- shipping_city_id: $shipping_city_id<br>";
echo "- shipping_city_name: '$shipping_city_name'<br>";
echo "- shipping_cost: $shipping_cost<br>";

// اختبار الإضافة الفعلية
echo "<h3>اختبار إضافة الطلب:</h3>";

try {
    $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssiddds", 
        $test_data['name'],
        $test_data['phone'], 
        $test_data['region'],
        $test_data['address'],
        $shipping_city_id,
        $shipping_city_name,
        $shipping_cost,
        $test_data['total'],
        $test_data['commission_total'],
        'قيد الانتظار'
    );
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        echo "<h3 style='color: green;'>✅ تم إضافة الطلب بنجاح! رقم: $order_id</h3>";
        
        // التحقق من البيانات المحفوظة
        $check = $conn->query("SELECT shipping_city_id, shipping_city_name, shipping_cost FROM orders WHERE id = $order_id");
        $saved_order = $check->fetch_assoc();
        
        echo "<h3>البيانات المحفوظة:</h3>";
        echo "- city_id: " . $saved_order['shipping_city_id'] . "<br>";
        echo "- city_name: " . ($saved_order['shipping_city_name'] ?? 'NULL') . "<br>";
        echo "- cost: " . $saved_order['shipping_cost'] . "<br>";
        
        if ($saved_order['shipping_city_name']) {
            echo "<h3 style='color: green;'>🎉 النجاح! city_name تم حفظه</h3>";
        } else {
            echo "<h3 style='color: red;'>❌ الفشل! city_name لم يتم حفظه</h3>";
        }
        
    } else {
        echo "<h3 style='color: red;'>❌ فشل في الإضافة: " . $stmt->error . "</h3>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ خطأ: " . $e->getMessage() . "</h3>";
}

$conn->close();
?>
