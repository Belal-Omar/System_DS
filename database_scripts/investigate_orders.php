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

echo "<h2>🔍 التحقيق في اختفاء بيانات مدينة الشحن</h2>";

// التحقق من تاريخ إنشاء الأعمدة
echo "<h3>1. تاريخ إنشاء الأعمدة:</h3>";

$columns = ['shipping_city_id', 'shipping_city_name', 'shipping_cost'];
foreach ($columns as $column) {
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE '$column'");
    if ($result && $result->num_rows > 0) {
        $col_info = $result->fetch_assoc();
        echo "- ✅ $column: {$col_info['Type']} ({$col_info['Null']})<br>";
    } else {
        echo "- ❌ $column: غير موجود<br>";
    }
}

// تحليل البيانات حسب التاريخ
echo "<h3>2. تحليل البيانات حسب التاريخ:</h3>";

// جلب آخر 20 طلب مع التاريخ
$result = $conn->query("
    SELECT 
        id,
        customer_name,
        shipping_city_id,
        shipping_city_name,
        shipping_cost,
        total,
        status,
        created_at,
        DATE(created_at) as order_date
    FROM orders 
    ORDER BY created_at DESC 
    LIMIT 20
");

echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>رقم</th>";
echo "<th>العميل</th>";
echo "<th>city_id</th>";
echo "<th>city_name</th>";
echo "<th>cost</th>";
echo "<th>التاريخ</th>";
echo "<th>الحالة</th>";
echo "</tr>";

$with_city = 0;
$without_city = 0;
$orders_by_date = [];

while($row = $result->fetch_assoc()) {
    $date = $row['order_date'];
    if (!isset($orders_by_date[$date])) {
        $orders_by_date[$date] = ['with' => 0, 'without' => 0];
    }
    
    $has_city = !empty($row['shipping_city_name']);
    if ($has_city) {
        $with_city++;
        $orders_by_date[$date]['with']++;
    } else {
        $without_city++;
        $orders_by_date[$date]['without']++;
    }
    
    $bg_color = $has_city ? '#d4edda' : '#f8d7da';
    echo "<tr style='background: $bg_color;'>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . substr($row['customer_name'], 0, 15) . "</td>";
    echo "<td>" . ($row['shipping_city_id'] ?? '-') . "</td>";
    echo "<td>" . ($row['shipping_city_name'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['shipping_cost'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>3. تحليل حسب التاريخ:</h3>";
foreach ($orders_by_date as $date => $counts) {
    $total = $counts['with'] + $counts['without'];
    $percentage = $total > 0 ? ($counts['with'] / $total) * 100 : 0;
    echo "- $date: {$counts['with']} مع مدينة، {$counts['without']} بدون مدينة (" . round($percentage, 1) . "% مع مدينة)<br>";
}

echo "<h3>4. الإجمالي:</h3>";
$total_orders = $with_city + $without_city;
$overall_percentage = $total_orders > 0 ? ($with_city / $total_orders) * 100 : 0;
echo "- الإجمالي: $with_city مع مدينة، $without_city بدون مدينة (" . round($overall_percentage, 1) . "% مع مدينة)<br>";

// التحقق من الطلبات التي لها city_id ولكن لا city_name
echo "<h3>5. الطلبات التي لها city_id ولكن لا city_name:</h3>";

$fix_result = $conn->query("
    SELECT id, shipping_city_id, customer_name, created_at
    FROM orders 
    WHERE shipping_city_id IS NOT NULL 
    AND shipping_city_id > 0 
    AND (shipping_city_name IS NULL OR shipping_city_name = '')
    ORDER BY created_at DESC
    LIMIT 10
");

if ($fix_result && $fix_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #fff3cd;'><th>رقم الطلب</th><th>city_id</th><th>العميل</th><th>التاريخ</th><th>إجراء</th></tr>";
    
    while($row = $fix_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['shipping_city_id'] . "</td>";
        echo "<td>" . $row['customer_name'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td><a href='fix_single_order.php?id=" . $row['id'] . "' style='color: blue;'>إصلاح</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✅ لا توجد طلبات تحتاج إصلاح";
}

$conn->close();
?>
