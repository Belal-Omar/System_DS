<?php
// ملف: debug_commissions.php
session_start();
include(__DIR__ . '/core/config.php");

$user_id = $_SESSION['user_id'] ?? 1; // استخدام أول مستخدم للتجربة

echo "<h2>تشخيص نظام العمولات</h2>";

// فحص الطلبات والعمولات
echo "<h3>الطلبات والعمولات:</h3>";
$orders_query = $conn->query("
    SELECT o.*, 
           SUM(oi.commission * oi.quantity) as calculated_commission
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = $user_id
    GROUP BY o.id
");

echo "<table border='1'>
    <tr>
        <th>رقم الطلب</th>
        <th>الحالة</th>
        <th>العمولة في الطلب</th>
        <th>العمولة المحسوبة</th>
        <th>الفرق</th>
    </tr>";

while($order = $orders_query->fetch_assoc()) {
    $difference = $order['commission_total'] - $order['calculated_commission'];
    echo "<tr>
        <td>{$order['id']}</td>
        <td>{$order['status']}</td>
        <td>{$order['commission_total']}</td>
        <td>{$order['calculated_commission']}</td>
        <td>{$difference}</td>
    </tr>";
}
echo "</table>";

// فحص عناصر الطلبات
echo "<h3>عناصر الطلبات:</h3>";
$items_query = $conn->query("
    SELECT oi.*, p.name as product_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = $user_id
");

echo "<table border='1'>
    <tr>
        <th>المنتج</th>
        <th>الكمية</th>
        <th>العمولة</th>
        <th>الإجمالي</th>
    </tr>";

while($item = $items_query->fetch_assoc()) {
    $total = $item['commission'] * $item['quantity'];
    echo "<tr>
        <td>{$item['product_name']}</td>
        <td>{$item['quantity']}</td>
        <td>{$item['commission']}</td>
        <td>{$total}</td>
    </tr>";
}
echo "</table>";
?>