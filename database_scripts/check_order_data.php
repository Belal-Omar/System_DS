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

echo "<h2>🔍 التحقق من بيانات مدينة الشحن في الطلبات</h2>";

// استعلام مباشر لجلب بيانات مدينة الشحن
$result = $conn->query("
    SELECT 
        id,
        customer_name,
        shipping_city_id,
        shipping_city_name,
        shipping_cost,
        total,
        status,
        created_at
    FROM orders 
    ORDER BY id DESC 
    LIMIT 5
");

echo "<h3>بيانات مدينة الشحن في الطلبات:</h3>";

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>رقم الطلب</th>";
    echo "<th>اسم العميل</th>";
    echo "<th>shipping_city_id</th>";
    echo "<th>shipping_city_name</th>";
    echo "<th>shipping_cost</th>";
    echo "<th>الإجمالي</th>";
    echo "<th>الحالة</th>";
    echo "</tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['customer_name'] . "</td>";
        echo "<td>" . ($row['shipping_city_id'] ?? 'NULL') . "</td>";
        echo "<td style='background: " . ($row['shipping_city_name'] ? '#d4edda' : '#f8d7da') . ";'>";
        echo ($row['shipping_city_name'] ?? 'NULL') . "</td>";
        echo "<td>" . $row['shipping_cost'] . "</td>";
        echo "<td>" . $row['total'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>تحليل البيانات:</h3>";
    
    // إعادة الاستعلام للتحليل
    $result->data_seek(0);
    $with_city = 0;
    $without_city = 0;
    
    while($row = $result->fetch_assoc()) {
        if ($row['shipping_city_name']) {
            $with_city++;
        } else {
            $without_city++;
        }
    }
    
    echo "- عدد الطلبات التي تحتوي على مدينة الشحن: <strong style='color: green;'>$with_city</strong><br>";
    echo "- عدد الطلبات التي لا تحتوي على مدينة الشحن: <strong style='color: red;'>$without_city</strong><br>";
    
} else {
    echo "لا توجد طلبات";
}

// التحقق من جدول shipping_cities
echo "<h3>التأكد من جدول shipping_cities:</h3>";
$cities_result = $conn->query("SELECT id, city_name FROM shipping_cities LIMIT 10");
if ($cities_result && $cities_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 50%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>اسم المدينة</th></tr>";
    while($city = $cities_result->fetch_assoc()) {
        echo "<tr><td>" . $city['id'] . "</td><td>" . $city['city_name'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد مدن في جدول shipping_cities";
}

$conn->close();
?>
