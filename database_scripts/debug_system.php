<?php
// ملف: debug_system.php
include(__DIR__ . '/core/config.php");

echo "<h2>🔧 تشخيص النظام بالكامل</h2>";

// 1. التحقق من المستخدمين
echo "<h3>1. المستخدمون المسوقون:</h3>";
$users = $conn->query("SELECT id, fullname, email FROM users WHERE user_type = 'مسوق'");
while($user = $users->fetch_assoc()) {
    echo "👤 #{$user['id']}: {$user['fullname']} - {$user['email']}<br>";
}

// 2. التحقق من الطلبات والعمولات
echo "<h3>2. الطلبات والعمولات:</h3>";
$orders = $conn->query("
    SELECT o.*, u.fullname 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.user_id IN (1, 4)
    ORDER BY o.created_at DESC
");

echo "<table border='1' style='width:100%'>
    <tr>
        <th>ID</th>
        <th>المسوق</th>
        <th>العميل</th>
        <th>المجموع</th>
        <th>العمولة</th>
        <th>الحالة</th>
        <th>التاريخ</th>
    </tr>";

while($order = $orders->fetch_assoc()) {
    echo "<tr>
        <td>{$order['id']}</td>
        <td>{$order['fullname']}</td>
        <td>{$order['customer_name']}</td>
        <td>{$order['total']} د.ل</td>
        <td>{$order['commission_total']} د.ل</td>
        <td>{$order['status']}</td>
        <td>{$order['created_at']}</td>
    </tr>";
}
echo "</table>";

// 3. التحقق من الأرصدة
echo "<h3>3. أرصدة المسوقين:</h3>";
$balances = $conn->query("
    SELECT mb.*, u.fullname 
    FROM marketer_balance mb 
    JOIN users u ON mb.user_id = u.id
");

echo "<table border='1' style='width:100%'>
    <tr>
        <th>المسوق</th>
        <th>إجمالي الأرباح</th>
        <th>المتاح للسحب</th>
        <th>أرباح معلقة</th>
        <th>تم السحب</th>
    </tr>";

while($balance = $balances->fetch_assoc()) {
    echo "<tr>
        <td>{$balance['fullname']}</td>
        <td>{$balance['total_earnings']} د.ل</td>
        <td>{$balance['available_balance']} د.ل</td>
        <td>{$balance['pending_balance']} د.ل</td>
        <td>{$balance['withdrawn_balance']} د.ل</td>
    </tr>";
}
echo "</table>";

// 4. التحقق من سجل العمولات
echo "<h3>4. سجل العمولات:</h3>";
$commissions = $conn->query("
    SELECT ch.*, u.fullname 
    FROM commission_history ch 
    JOIN users u ON ch.user_id = u.id 
    ORDER BY ch.created_at DESC 
    LIMIT 10
");

echo "<table border='1' style='width:100%'>
    <tr>
        <th>المسوق</th>
        <th>المبلغ</th>
        <th>النوع</th>
        <th>الحالة</th>
        <th>الوصف</th>
        <th>التاريخ</th>
    </tr>";

while($commission = $commissions->fetch_assoc()) {
    echo "<tr>
        <td>{$commission['fullname']}</td>
        <td>{$commission['amount']} د.ل</td>
        <td>{$commission['type']}</td>
        <td>{$commission['status']}</td>
        <td>{$commission['description']}</td>
        <td>{$commission['created_at']}</td>
    </tr>";
}
echo "</table>";
?>