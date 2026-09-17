<?php
// ملف: setup_shipping_system.php
// إعداد نظام الشحن
include(__DIR__ . '/core/config.php");

echo "<h2>إعداد نظام الشحن</h2>";

// إنشاء جدول المدن ومصاريف الشحن
$sql1 = "CREATE TABLE IF NOT EXISTS shipping_cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(100) NOT NULL UNIQUE,
    shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql1)) {
    echo "<p style='color: green;'>✓ تم إنشاء جدول shipping_cities بنجاح</p>";
} else {
    echo "<p style='color: red;'>✗ خطأ في إنشاء جدول shipping_cities: " . $conn->error . "</p>";
}

// إضافة عمود shipping_city_id في جدول orders
$sql2 = "ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS shipping_city_id INT NULL,
ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10, 2) DEFAULT 0.00,
ADD FOREIGN KEY (shipping_city_id) REFERENCES shipping_cities(id) ON DELETE SET NULL";

// التحقق من وجود الأعمدة أولاً
$check_columns = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_city_id'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_id INT NULL");
    echo "<p style='color: green;'>✓ تم إضافة عمود shipping_city_id في جدول orders</p>";
} else {
    echo "<p style='color: blue;'>- عمود shipping_city_id موجود بالفعل</p>";
}

$check_shipping_cost = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_cost'");
if ($check_shipping_cost->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10, 2) DEFAULT 0.00");
    echo "<p style='color: green;'>✓ تم إضافة عمود shipping_cost في جدول orders</p>";
} else {
    echo "<p style='color: blue;'>- عمود shipping_cost موجود بالفعل</p>";
}

// إضافة بعض المدن الافتراضية
$default_cities = [
    ['طرابلس', 5.00],
    ['بنغازي', 5.00],
    ['مصراتة', 7.00],
    ['سبها', 10.00],
    ['زليتن', 6.00],
    ['البيضاء', 8.00],
    ['درنة', 9.00],
    ['غريان', 6.00]
];

foreach ($default_cities as $city) {
    $city_name = $conn->real_escape_string($city[0]);
    $cost = floatval($city[1]);
    
    $check = $conn->query("SELECT id FROM shipping_cities WHERE city_name = '$city_name'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO shipping_cities (city_name, shipping_cost) VALUES ('$city_name', $cost)");
        echo "<p style='color: green;'>✓ تم إضافة مدينة: $city_name بسعر $cost د.ل</p>";
    } else {
        echo "<p style='color: blue;'>- مدينة $city_name موجودة بالفعل</p>";
    }
}

echo "<h3 style='color: green; margin-top: 20px;'>✓ تم إعداد نظام الشحن بنجاح!</h3>";
echo "<p><a href='admin_panel.php?page=products'>العودة إلى لوحة التحكم</a></p>";
?>

