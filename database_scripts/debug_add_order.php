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

echo "<h2>🧪 اختبار إضافة طلب</h2>";

// بيانات اختبار
$test_order = [
    'name' => 'اختبار العميل',
    'phone' => '0912345678',
    'region' => 'طرابلس',
    'address' => 'عنوان اختبار',
    'shipping_city_id' => 1,
    'shipping_city_name' => 'طرابلس',
    'shipping_cost' => 5.00,
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

echo "<h3>بيانات الاختبار:</h3>";
echo "<pre>" . print_r($test_order, true) . "</pre>";

// محاولة إضافة الطلب
try {
    $conn->begin_transaction();
    
    // إضافة الطلب
    $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssiddds", 
        $test_order['name'],
        $test_order['phone'], 
        $test_order['region'],
        $test_order['address'],
        $test_order['shipping_city_id'],
        $test_order['shipping_city_name'],
        $test_order['shipping_cost'],
        $test_order['total'],
        $test_order['commission_total'],
        'قيد الانتظار'
    );
    
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        echo "<h3>✅ تم إضافة الطلب بنجاح!</h3>";
        echo "رقم الطلب: " . $order_id;
        
        // التحقق من البيانات المحفوظة
        $check_result = $conn->query("SELECT * FROM orders WHERE id = $order_id");
        $saved_order = $check_result->fetch_assoc();
        
        echo "<h3>البيانات المحفوظة:</h3>";
        echo "<pre>" . print_r($saved_order, true) . "</pre>";
        
        // التحقق من shipping_city_name
        if ($saved_order['shipping_city_name']) {
            echo "<h3>✅ مدينة الشحن تم حفظها: " . $saved_order['shipping_city_name'] . "</h3>";
        } else {
            echo "<h3>❌ مدينة الشحن لم يتم حفظها!</h3>";
        }
        
        $conn->commit();
    } else {
        echo "<h3>❌ فشل في إضافة الطلب: " . $stmt->error . "</h3>";
        $conn->rollback();
    }
    
} catch (Exception $e) {
    echo "<h3>❌ خطأ: " . $e->getMessage() . "</h3>";
    $conn->rollback();
}

$conn->close();
?>
