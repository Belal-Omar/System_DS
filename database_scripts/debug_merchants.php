<?php
// ملف: debug_merchants.php - للتحقق من بيانات التجار
include(__DIR__ . '/core/config.php");

echo "<h1>تقرير تصحيح بيانات التجار</h1>";

// التحقق من بيانات تاجر معين (مثال medhat)
$email = 'medhat@gmail.com';
echo "<h2>تاجر: $email</h2>";

// جلب معرف التاجر
$user_query = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? AND user_type = 'تاجر'");
$user_query->bind_param("s", $email);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if ($user) {
    echo "<p>معرف التاجر: " . $user['id'] . "</p>";
    echo "<p>الاسم: " . $user['fullname'] . "</p>";
    
    // التحقق من عدد المنتجات بطريقة مباشرة
    $products_count = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE user_id = ?");
    $products_count->bind_param("i", $user['id']);
    $products_count->execute();
    $count_result = $products_count->get_result()->fetch_assoc();
    
    echo "<h3>عدد المنتجات (طريقة مباشرة): " . $count_result['count'] . "</h3>";
    
    // عرض المنتجات
    $products_list = $conn->prepare("SELECT id, name, stock FROM products WHERE user_id = ?");
    $products_list->bind_param("i", $user['id']);
    $products_list->execute();
    $products = $products_list->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>الرقم</th><th>اسم المنتج</th><th>المخزون</th></tr>";
    foreach($products as $product) {
        echo "<tr>";
        echo "<td>" . $product['id'] . "</td>";
        echo "<td>" . $product['name'] . "</td>";
        echo "<td>" . $product['stock'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // التحقق من الاستعلام الأصلي
    echo "<h3>نتيجة الاستعلام الأصلي:</h3>";
    $original_query = $conn->prepare("
        SELECT 
            u.id, u.fullname, u.email, u.phone,
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(p.stock), 0) as total_stock
        FROM users u
        LEFT JOIN products p ON u.id = p.user_id
        WHERE u.id = ? AND u.user_type = 'تاجر'
        GROUP BY u.id
    ");
    $original_query->bind_param("i", $user['id']);
    $original_query->execute();
    $original_result = $original_query->get_result()->fetch_assoc();
    
    echo "<p>عدد المنتجات في الاستعلام الأصلي: " . $original_result['total_products'] . "</p>";
    echo "<p>إجمالي المخزون: " . $original_result['total_stock'] . "</p>";
    
} else {
    echo "<p>لم يتم العثور على التاجر</p>";
}

// عرض كل التجار للمقارنة
echo "<h2>كل التجار في النظام:</h2>";
$all_merchants = $conn->query("SELECT id, fullname, email, user_type FROM users WHERE user_type = 'تاجر'");

echo "<table border='1'>";
echo "<tr><th>المعرف</th><th>الاسم</th><th>البريد</th><th>عدد المنتجات</th></tr>";

while($merchant = $all_merchants->fetch_assoc()) {
    $product_count = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE user_id = ?");
    $product_count->bind_param("i", $merchant['id']);
    $product_count->execute();
    $count = $product_count->get_result()->fetch_assoc()['count'];
    
    echo "<tr>";
    echo "<td>" . $merchant['id'] . "</td>";
    echo "<td>" . $merchant['fullname'] . "</td>";
    echo "<td>" . $merchant['email'] . "</td>";
    echo "<td>" . $count . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
