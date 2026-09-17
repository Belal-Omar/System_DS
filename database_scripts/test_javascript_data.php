<?php
header("Content-Type: text/html; charset=UTF-8");

echo "<h2>🔍 اختبار بيانات JavaScript المرسلة</h2>";

// عرض البيانات المستلمة بالكامل
$input = file_get_contents('php://input');
echo "<h3>البيانات الخام المستلمة:</h3>";
echo "<pre>" . htmlspecialchars($input) . "</pre>";

$data = json_decode($input, true);

if ($data) {
    echo "<h3>البيانات بعد فك تشفير JSON:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    
    echo "<h3>تحليل بيانات مدينة الشحن:</h3>";
    echo "- shipping_city_id: " . ($data['shipping_city_id'] ?? 'غير موجود') . "<br>";
    echo "- shipping_city_name: '" . ($data['shipping_city_name'] ?? 'غير موجود') . "'<br>";
    echo "- shipping_cost: " . ($data['shipping_cost'] ?? 'غير موجود') . "<br>";
    echo "- selectedShippingCity: " . (isset($data['selectedShippingCity']) ? 'موجود' : 'غير موجود') . "<br>";
    
    if (isset($data['selectedShippingCity'])) {
        echo "<h3>selectedShippingCity:</h3>";
        echo "<pre>" . print_r($data['selectedShippingCity'], true) . "</pre>";
    }
    
    // التحقق من المشاكل المحتملة
    echo "<h3>التشخيص:</h3>";
    $problems = [];
    
    if (!isset($data['shipping_city_id'])) {
        $problems[] = "❌ shipping_city_id غير موجود";
    }
    
    if (!isset($data['shipping_city_name'])) {
        $problems[] = "❌ shipping_city_name غير موجود";
    } elseif (empty($data['shipping_city_name'])) {
        $problems[] = "⚠️ shipping_city_name فارغ";
    }
    
    if (empty($problems)) {
        echo "<h3 style='color: green;'>✅ البيانات سليمة</h3>";
    } else {
        echo "<h3 style='color: red;'>المشاكل:</h3>";
        foreach ($problems as $problem) {
            echo "- $problem<br>";
        }
    }
    
} else {
    echo "<h3 style='color: red;'>❌ فشل في فك تشفير JSON</h3>";
    echo "الخطأ: " . json_last_error_msg() . "<br>";
}

echo "<br><h3>مثال للبيانات الصحيحة التي يجب إرسالها:</h3>";
echo "<pre>{
    'name': 'اسم العميل',
    'phone': 'رقم الهاتف',
    'region': 'المنطقة',
    'address': 'العنوان',
    'shipping_city_id': 1,
    'shipping_city_name': 'طرابلس',
    'shipping_cost': 5.00,
    'total': 100.00,
    'commission_total': 10.00,
    'items': [...]
}</pre>";

// اختبار بسيط لإضافة طلب مباشرة
echo "<hr><h2>🧪 اختبار إضافة طلب مباشرة:</h2>";

// الاتصال بقاعدة البيانات
$servername = "localhost";
$username = "root"; 
$password = "BelalOmar499881";
$dbname = "system";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// بيانات اختبار مباشرة
$test_city_id = 1;
$test_city_name = '';
$test_cost = 0;

echo "<h3>قبل التحقق:</h3>";
echo "- city_id: $test_city_id<br>";
echo "- city_name: '$test_city_name'<br>";
echo "- cost: $test_cost<br>";

// تطبيق نفس منطق add_order_simple.php
if (empty($test_city_name) && $test_city_id > 0) {
    echo "<h3>✅ الشرط تحقق - جلب بيانات المدينة...</h3>";
    
    $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result->bind_param("i", $test_city_id);
    $city_result->execute();
    $city_data = $city_result->get_result()->fetch_assoc();
    
    if ($city_data) {
        $test_city_name = $city_data['city_name'];
        if ($test_cost == 0) {
            $test_cost = $city_data['shipping_cost'];
        }
        echo "<h3>✅ تم جلب البيانات:</h3>";
        echo "- city_name: '$test_city_name'<br>";
        echo "- cost: $test_cost<br>";
    } else {
        echo "<h3>❌ لم يتم العثور على المدينة</h3>";
    }
}

echo "<h3>بعد التحقق:</h3>";
echo "- city_id: $test_city_id<br>";
echo "- city_name: '$test_city_name'<br>";
echo "- cost: $test_cost<br>";

$conn->close();
?>
