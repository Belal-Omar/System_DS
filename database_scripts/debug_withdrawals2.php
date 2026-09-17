<?php
// ملف: debug_withdrawals2.php - للتحقق من بيانات السحب والربح
include(__DIR__ . '/core/config.php");

echo "<h1>تقرير تصحيح بيانات السحب والربح</h1>";

// اختبار تاجر معين
$merchant_id = 1; // غير الرقم حسب الحاجة

echo "<h2>تاجر ID: $merchant_id</h2>";

// جلب بيانات التاجر
$user_query = $conn->prepare("SELECT id, fullname, email FROM users WHERE id = ? AND user_type = 'تاجر'");
$user_query->bind_param("i", $merchant_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if ($user) {
    echo "<p>التاجر: " . $user['fullname'] . " (" . $user['email'] . ")</p>";
    
    // التحقق من المنتجات
    $products_query = $conn->prepare("SELECT id, name, stock FROM products WHERE user_id = ?");
    $products_query->bind_param("i", $merchant_id);
    $products_query->execute();
    $products = $products_query->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>المنتجات (" . count($products) . ")</h3>";
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
    
    // التحقق من الطلبات المكتملة
    $orders_query = $conn->prepare("
        SELECT 
            oi.id,
            oi.product_id,
            oi.quantity,
            oi.price,
            oi.commission,
            oi.price * oi.quantity as total_price,
            (oi.price - oi.commission) * oi.quantity as net_profit,
            o.status,
            p.name as product_name
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = ? AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
        ORDER BY oi.id DESC
    ");
    $orders_query->bind_param("i", $merchant_id);
    $orders_query->execute();
    $orders = $orders_query->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>الطلبات المكتملة (" . count($orders) . ")</h3>";
    echo "<table border='1'>";
    echo "<tr><th>الرقم</th><th>المنتج</th><th>الكمية</th><th>السعر</th><th>العمولة</th><th>صافي الربح</th><th>الحالة</th></tr>";
    
    $total_profit = 0;
    foreach($orders as $order) {
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . $order['product_name'] . "</td>";
        echo "<td>" . $order['quantity'] . "</td>";
        echo "<td>" . number_format($order['price'], 2) . "</td>";
        echo "<td>" . number_format($order['commission'], 2) . "</td>";
        echo "<td>" . number_format($order['net_profit'], 2) . "</td>";
        echo "<td>" . $order['status'] . "</td>";
        echo "</tr>";
        $total_profit += $order['net_profit'];
    }
    echo "</table>";
    echo "<h3>إجمالي الربح المحسوب: " . number_format($total_profit, 2) . " د.ل</h3>";
    
    // التحقق من عمليات السحب
    $withdrawals_query = $conn->prepare("
        SELECT id, amount, status, created_at, payment_method, notes
        FROM withdrawals 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $withdrawals_query->bind_param("i", $merchant_id);
    $withdrawals_query->execute();
    $withdrawals = $withdrawals_query->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>عمليات السحب (" . count($withdrawals) . ")</h3>";
    echo "<table border='1'>";
    echo "<tr><th>الرقم</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th>طريقة الدفع</th><th>ملاحظات</th></tr>";
    
    $total_withdrawn = 0;
    foreach($withdrawals as $withdrawal) {
        echo "<tr>";
        echo "<td>" . $withdrawal['id'] . "</td>";
        echo "<td>" . number_format($withdrawal['amount'], 2) . "</td>";
        echo "<td>" . $withdrawal['status'] . "</td>";
        echo "<td>" . $withdrawal['created_at'] . "</td>";
        echo "<td>" . ($withdrawal['payment_method'] ?? '-') . "</td>";
        echo "<td>" . ($withdrawal['notes'] ?? '-') . "</td>";
        echo "</tr>";
        
        if ($withdrawal['status'] == 'مكتمل') {
            $total_withdrawn += $withdrawal['amount'];
        }
    }
    echo "</table>";
    echo "<h3>إجمالي المسحوب: " . number_format($total_withdrawn, 2) . " د.ل</h3>";
    echo "<h3>المتاح للسحب: " . number_format($total_profit - $total_withdrawn, 2) . " د.ل</h3>";
    
} else {
    echo "<p>لم يتم العثور على التاجر</p>";
}

// عرض كل التجار
echo "<h2>كل التجار في النظام:</h2>";
$all_merchants = $conn->query("SELECT id, fullname, email, user_type FROM users WHERE user_type = 'تاجر' ORDER BY id");

echo "<table border='1'>";
echo "<tr><th>المعرف</th><th>الاسم</th><th>البريد</th><th>الاختبار</th></tr>";

while($merchant = $all_merchants->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $merchant['id'] . "</td>";
    echo "<td>" . $merchant['fullname'] . "</td>";
    echo "<td>" . $merchant['email'] . "</td>";
    echo "<td><a href='debug_withdrawals2.php?merchant_id=" . $merchant['id'] . "'>اختبار</a></td>";
    echo "</tr>";
}
echo "</table>";
?>
