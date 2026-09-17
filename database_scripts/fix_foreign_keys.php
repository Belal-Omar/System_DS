<?php
// ملف: fix_foreign_keys.php
include(__DIR__ . '/core/config.php");

echo "<h2>إصلاح المفاتيح الخارجية التالفة</h2>";

// 1. إصلاح الطلبات التي لها user_id غير موجود
echo "<h3>1. إصلاح الطلبات:</h3>";
$broken_orders = $conn->query("
    SELECT o.id, o.user_id 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE u.id IS NULL
");

$fixed_orders = 0;
while($order = $broken_orders->fetch_assoc()) {
    // تعيين user_id إلى أول مستخدم مسوق موجود
    $first_marketer = $conn->query("SELECT id FROM users WHERE user_type = 'مسوق' LIMIT 1")->fetch_assoc();
    if ($first_marketer) {
        $new_user_id = $first_marketer['id'];
        $conn->query("UPDATE orders SET user_id = $new_user_id WHERE id = {$order['id']}");
        $fixed_orders++;
        echo "تم إصلاح الطلب #{$order['id']} - user_id: {$order['user_id']} → $new_user_id<br>";
    }
}

// 2. إصلاح marketer_balance
echo "<h3>2. إصلاح أرصدة المسوقين:</h3>";
$broken_balances = $conn->query("
    SELECT mb.user_id 
    FROM marketer_balance mb 
    LEFT JOIN users u ON mb.user_id = u.id 
    WHERE u.id IS NULL
");

$fixed_balances = 0;
while($balance = $broken_balances->fetch_assoc()) {
    $conn->query("DELETE FROM marketer_balance WHERE user_id = {$balance['user_id']}");
    $fixed_balances++;
    echo "تم حذف رصيد المستخدم #{$balance['user_id']} (غير موجود)<br>";
}

// 3. إصلاح commission_history
echo "<h3>3. إصلاح سجل العمولات:</h3>";
$broken_commissions = $conn->query("
    SELECT ch.id, ch.user_id 
    FROM commission_history ch 
    LEFT JOIN users u ON ch.user_id = u.id 
    WHERE u.id IS NULL
");

$fixed_commissions = 0;
while($commission = $broken_commissions->fetch_assoc()) {
    $conn->query("DELETE FROM commission_history WHERE id = {$commission['id']}");
    $fixed_commissions++;
    echo "تم حذف سجل العمولة #{$commission['id']} للمستخدم #{$commission['user_id']} (غير موجود)<br>";
}

echo "<h3>✅ تم الانتهاء من الإصلاح!</h3>";
echo "- الطلبات المصلحة: $fixed_orders<br>";
echo "- الأرصدة المحذوفة: $fixed_balances<br>";
echo "- سجلات العمولات المحذوفة: $fixed_commissions<br>";
?>