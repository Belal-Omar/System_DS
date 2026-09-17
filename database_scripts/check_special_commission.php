<?php
// ملف: check_special_commission.php
header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/core/config.php';

echo "<h1>فحص قيم العمولة الخاصة (Special Commission)</h1>";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Check if column exists physically
$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'special_commission'");
if ($colCheck->num_rows > 0) {
    echo "<p style='color:green'>✅ العمود special_commission موجود في الجدول.</p>";
} else {
    echo "<p style='color:red'>❌ العمود special_commission غير موجود!</p>";
}

// 2. List all products with non-zero special commission
echo "<h3>المنتجات التي لها عمولة خاصة (أكبر من 0):</h3>";
$sql = "SELECT id, name, commission, special_commission FROM products WHERE special_commission > 0";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>اسم المنتج</th><th>العمولة العادية</th><th>العمولة الخاصة (الخصم)</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["id"] . "</td>";
        echo "<td>" . $row["name"] . "</td>";
        echo "<td>" . $row["commission"] . "</td>";
        echo "<td style='color:red; font-weight:bold'>" . $row["special_commission"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange'>⚠️ لا توجد أي منتجات بقيمة خصم أكبر من 0.</p>";
    echo "<p>يرجى التأكد من أنك قمت بحفظ المنتج بشكل صحيح بعد التعديل الأخير.</p>";
}

// 3. Show top 5 products (debug)
echo "<h3>آخر 5 منتجات مضاف/معدلة:</h3>";
$sql = "SELECT id, name, commission, special_commission FROM products ORDER BY id DESC LIMIT 5";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>اسم المنتج</th><th>العمولة العادية</th><th>العمولة الخاصة</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["id"] . "</td>";
        echo "<td>" . $row["name"] . "</td>";
        echo "<td>" . $row["commission"] . "</td>";
        echo "<td>" . ($row["special_commission"] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
