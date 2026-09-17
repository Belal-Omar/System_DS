<?php
// ملف: debug_orders_withdrawals.php
// فحص الطلبات والعمولات للمستخدم الحالي

session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo "يجب تسجيل الدخول أولاً";
    exit();
}

$user_id = (int)$_SESSION['user_id'];
echo "<h2>فحص الطلبات والعمولات للمستخدم: $user_id</h2>";

// 1. فحص بيانات المستخدم
echo "<h3>1. بيانات المستخدم:</h3>";
$user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
if ($user = $user_query->fetch_assoc()) {
    echo "الاسم: " . $user['fullname'] . "<br>";
    echo "النوع: " . $user['user_type'] . "<br>";
    echo "إجمالي العمولات: " . $user['total_commissions'] . "<br>";
} else {
    echo "المستخدم غير موجود!";
}

// 2. فحص جميع الطلبات للمستخدم
echo "<h3>2. جميع الطلبات للمستخدم:</h3>";
$orders_query = $conn->query("SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC");
$order_count = $orders_query->num_rows;
echo "عدد الطلبات: $order_count<br>";

if ($order_count > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>رقم الطلب</th><th>الاسم</th><th>الإجمالي</th><th>العمولة</th><th>الحالة</th><th>التاريخ</th></tr>";
    
    while ($order = $orders_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . $order['customer_name'] . "</td>";
        echo "<td>" . $order['total'] . "</td>";
        echo "<td>" . $order['commission_total'] . "</td>";
        echo "<td>" . $order['status'] . "</td>";
        echo "<td>" . $order['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد طلبات لهذا المستخدم!";
}

// 3. فحص جدول العمولات
echo "<h3>3. جدول العمولات (marketer_commissions):</h3>";
$commissions_check = $conn->query("SHOW TABLES LIKE 'marketer_commissions'");
if ($commissions_check && $commissions_check->num_rows > 0) {
    $commissions_query = $conn->query("SELECT * FROM marketer_commissions WHERE user_id = $user_id ORDER BY created_at DESC");
    $commission_count = $commissions_query->num_rows;
    echo "عدد سجلات العمولات: $commission_count<br>";
    
    if ($commission_count > 0) {
        echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
        echo "<tr><th>الرقم</th><th>رقم الطلب</th><th>مبلغ العمولة</th><th>الحالة</th><th>التاريخ</th></tr>";
        
        while ($commission = $commissions_query->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $commission['id'] . "</td>";
            echo "<td>" . $commission['order_id'] . "</td>";
            echo "<td>" . $commission['commission_amount'] . "</td>";
            echo "<td>" . $commission['status'] . "</td>";
            echo "<td>" . $commission['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // إجمالي العمولات المكتملة
        $total_commissions = $conn->query("SELECT SUM(commission_amount) as total FROM marketer_commissions WHERE user_id = $user_id AND status = 'مكتمل'");
        $total = $total_commissions->fetch_assoc();
        echo "<br><strong>إجمالي العمولات المكتملة: " . $total['total'] . "</strong>";
    } else {
        echo "لا توجد عمولات مسجلة لهذا المستخدم!";
    }
} else {
    echo "جدول marketer_commissions غير موجود!";
}

// 4. فحص طلبات السحب
echo "<h3>4. طلبات السحب:</h3>";
$withdrawals_query = $conn->query("SELECT * FROM withdrawals WHERE user_id = $user_id ORDER BY created_at DESC");
$withdrawal_count = $withdrawals_query->num_rows;
echo "عدد طلبات السحب: $withdrawal_count<br>";

if ($withdrawal_count > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>الرقم</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th></tr>";
    
    while ($withdrawal = $withdrawals_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $withdrawal['id'] . "</td>";
        echo "<td>" . $withdrawal['amount'] . "</td>";
        echo "<td>" . $withdrawal['status'] . "</td>";
        echo "<td>" . $withdrawal['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد طلبات سحب لهذا المستخدم!";
}

// 5. الحسابات النهائية
echo "<h3>5. الحسابات النهائية:</h3>";
if ($commissions_check && $commissions_check->num_rows > 0) {
    $available_profit_query = $conn->query("
        SELECT SUM(mc.commission_amount) as total 
        FROM marketer_commissions mc
        WHERE mc.user_id = $user_id AND mc.status = 'مكتمل'
    ");
    $available_profit_row = $available_profit_query->fetch_assoc();
    $available_profit = $available_profit_row['total'] ? floatval($available_profit_row['total']) : 0;
} else {
    $available_profit_query = $conn->query("
        SELECT SUM(commission_total) as total 
        FROM orders 
        WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل', 'مكتمل')
    ");
    $available_profit_row = $available_profit_query->fetch_assoc();
    $available_profit = $available_profit_row['total'] ? floatval($available_profit_row['total']) : 0;
}

$withdrawn_query = $conn->query("
    SELECT SUM(amount) as total 
    FROM withdrawals 
    WHERE user_id = $user_id AND status = 'مكتمل'
");
$withdrawn_row = $withdrawn_query->fetch_assoc();
$withdrawn_amount = $withdrawn_row['total'] ? floatval($withdrawn_row['total']) : 0;

$available_withdrawal = $available_profit - $withdrawn_amount;

echo "إجمالي العمولات المكتسبة: $available_profit<br>";
echo "المبلغ المسحوب بالفعل: $withdrawn_amount<br>";
echo "المبلغ المتاح للسحب: $available_withdrawal<br>";

$conn->close();
?>
