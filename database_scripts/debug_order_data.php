<?php
header("Content-Type: text/html; charset=UTF-8");

echo "<h2>🔍 تحليل بيانات الطلب القادمة من JavaScript</h2>";

// عرض البيانات المستلمة
$input = file_get_contents('php://input');
$data = json_decode($input, true);

echo "<h3>البيانات المستلمة:</h3>";
echo "<pre>" . print_r($data, true) . "</pre>";

if ($data) {
    echo "<h3>تحليل بيانات مدينة الشحن:</h3>";
    echo "- shipping_city_id: " . ($data['shipping_city_id'] ?? 'NULL') . "<br>";
    echo "- shipping_city_name: " . ($data['shipping_city_name'] ?? 'NULL') . "<br>";
    echo "- shipping_cost: " . ($data['shipping_cost'] ?? 'NULL') . "<br>";
    
    if (empty($data['shipping_city_name'])) {
        echo "<h3 style='color: red;'>❌ المشكلة: shipping_city_name فارغ!</h3>";
        echo "<h3>الحل: يجب التأكد من إرسال shipping_city_name من JavaScript</h3>";
    } else {
        echo "<h3 style='color: green;'>✅ shipping_city_name موجود: " . $data['shipping_city_name'] . "</h3>";
    }
} else {
    echo "<h3 style='color: red;'>❌ لا توجد بيانات مستلمة</h3>";
}

echo "<br><h3>مثال للبيانات الصحيحة:</h3>";
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
?>
