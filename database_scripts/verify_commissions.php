<?php
// ملف: verify_commissions.php
include(__DIR__ . '/core/config.php");

echo "<h2>✅ التحقق من عمولات المستخدم #1</h2>";

// حساب صافي الربح الحقيقي من الطلبات المكتملة
$orders_query = $conn->query("SELECT oi.original_price, oi.commission, oi.shipping_cost, oi.is_shipping_included, oi.quantity FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = 1 AND o.status IN ('تم التوصيل', 'محصل')");
$total_profit = 0;
$total_sales = 0;
$total_orders = 0;
while ($row = $orders_query->fetch_assoc()) {
    $net = ($row['original_price'] - $row['commission'] - ($row['is_shipping_included'] == 0 ? $row['shipping_cost'] : 0)) * $row['quantity'];
    $total_profit += $net;
    $total_sales += $row['original_price'] * $row['quantity'];
    $total_orders++;
}
echo "<h3>الطلبات المكتملة:</h3>";
echo "- عدد الطلبات: " . $total_orders . "<br>";
echo "- إجمالي المبيعات: " . $total_sales . " د.ل<br>";
echo "- إجمالي الأرباح (صافي الربح): " . $total_profit . " د.ل<br>";

// التحقق من الرصيد في marketer_balance
$balance_query = "SELECT * FROM marketer_balance WHERE user_id = 1";
$balance_result = $conn->query($balance_query);
$balance = $balance_result->fetch_assoc();

echo "<h3>الرصيد في النظام:</h3>";
echo "- إجمالي الأرباح: " . $balance['total_earnings'] . " د.ل<br>";
echo "- المتاح للسحب: " . $balance['available_balance'] . " د.ل<br>";
echo "- الأرباح المعلقة: " . $balance['pending_balance'] . " د.ل<br>";
echo "- تم السحب: " . $balance['withdrawn_balance'] . " د.ل<br>";

echo "<h3>🎯 الاستنتاج:</h3>";
if ($data['total_commissions'] == $balance['available_balance']) {
    echo "✅ النظام يعمل بشكل صحيح! العمولات متطابقة.<br>";
    echo "🔗 <a href='withdrawals.php' style='color: green; font-weight: bold;'>اذهب إلى صفحة السحب</a>";
} else {
    echo "⚠️ هناك اختلاف في الحسابات. يحتاج النظام إلى تحديث.<br>";
    echo "🔗 <a href='fix_commissions.php' style='color: red; font-weight: bold;'>إصلاح العمولات</a>";
}
?>