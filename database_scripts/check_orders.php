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

echo "<h2>🔍 التحقق من جدول orders</h2>";

// التحقق من وجود الأعمدة
echo "<h3>1. التحقق من أعمدة جدول orders:</h3>";
$columns_result = $conn->query("SHOW COLUMNS FROM orders");
while($column = $columns_result->fetch_assoc()) {
    echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
}

// عرض آخر 5 طلبات
echo "<h3>2. آخر 5 طلبات:</h3>";
$orders_result = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");

if ($orders_result && $orders_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    
    // عرض أسماء الأعمدة
    $fields = $orders_result->fetch_fields();
    foreach($fields as $field) {
        echo "<th style='padding: 8px; text-align: right;'>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // عرض البيانات
    $orders_result->data_seek(0); // إعادة المؤشر للبداية
    while($row = $orders_result->fetch_assoc()) {
        echo "<tr>";
        foreach($row as $key => $value) {
            echo "<td style='padding: 8px;'>" . ($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد طلبات في الجدول";
}

// التحقق من جدول shipping_cities
echo "<h3>3. التحقق من جدول shipping_cities:</h3>";
$cities_result = $conn->query("SELECT COUNT(*) as count FROM shipping_cities");
$cities_count = $cities_result->fetch_assoc()['count'];
echo "عدد المدن: " . $cities_count;

if ($cities_count > 0) {
    echo "<br>أول 5 مدن:<br>";
    $cities_list = $conn->query("SELECT * FROM shipping_cities LIMIT 5");
    while($city = $cities_list->fetch_assoc()) {
        echo "- ID: " . $city['id'] . ", Name: " . $city['city_name'] . ", Cost: " . $city['shipping_cost'] . "<br>";
    }
}

$conn->close();
?>
