<?php
// ملف: debug_calculation_issue.php
// فحص مشكلة الحسابات الخاطئة

session_start();
include(__DIR__ . '/core/config.php");

echo "<h2>فحص مشكلة الحسابات الخاطئة</h2>";

// 1. فحص جميع الطلبات المكتملة للمستخدم 8
echo "<h3>1. الطلبات المكتملة للمستخدم 8:</h3>";
$user8_completed = $conn->query("
    SELECT * FROM orders 
    WHERE user_id = 8 AND status IN ('تم التوصيل', 'محصل', 'مكتمل')
    ORDER BY created_at DESC
");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>رقم الطلب</th><th>الاسم</th><th>الإجمالي</th><th>العمولة</th><th>الحالة</th><th>التاريخ</th></tr>";

$total_commission_should_be = 0;
while ($order = $user8_completed->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $order['id'] . "</td>";
    echo "<td>" . $order['customer_name'] . "</td>";
    echo "<td>" . $order['total'] . "</td>";
    echo "<td><strong>" . $order['commission_total'] . "</strong></td>";
    echo "<td>" . $order['status'] . "</td>";
    echo "<td>" . $order['created_at'] . "</td>";
    echo "</tr>";
    $total_commission_should_be += $order['commission_total'];
}
echo "</table>";
echo "<strong>إجمالي العمولات من الطلبات المكتملة: $total_commission_should_be</strong><br>";

// 2. فحص العمولات المسجلة في جدول marketer_commissions
echo "<h3>2. العمولات المسجلة في جدول marketer_commissions للمستخدم 8:</h3>";
$user8_commissions = $conn->query("
    SELECT mc.*, o.status as order_status, o.customer_name
    FROM marketer_commissions mc
    LEFT JOIN orders o ON mc.order_id = o.id
    WHERE mc.user_id = 8
    ORDER BY mc.created_at DESC
");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>رقم العمولة</th><th>رقم الطلب</th><th>الاسم</th><th>مبلغ العمولة</th><th>الحالة</th><th>حالة الطلب</th><th>التاريخ</th></tr>";

$total_commission_in_table = 0;
while ($commission = $user8_commissions->fetch_assoc()) {
    $row_style = ($commission['order_status'] != 'تم التوصيل' && $commission['order_status'] != 'محصل' && $commission['order_status'] != 'مكتمل') ? "background-color: #ffcccc;" : "";
    echo "<tr style='$row_style'>";
    echo "<td>" . $commission['id'] . "</td>";
    echo "<td>" . $commission['order_id'] . "</td>";
    echo "<td>" . $commission['customer_name'] . "</td>";
    echo "<td><strong>" . $commission['commission_amount'] . "</strong></td>";
    echo "<td>" . $commission['status'] . "</td>";
    echo "<td>" . $commission['order_status'] . "</td>";
    echo "<td>" . $commission['created_at'] . "</td>";
    echo "</tr>";
    
    // فقط احسب العمولات للطلبات المكتملة
    if (in_array($commission['order_status'], ['تم التوصيل', 'محصل', 'مكتمل'])) {
        $total_commission_in_table += $commission['commission_amount'];
    }
}
echo "</table>";
echo "<strong>إجمالي العمولات في الجدول (للطلبات المكتملة فقط): $total_commission_in_table</strong><br>";

// 3. فحص كيف يتم الحساب في withdrawals.php
echo "<h3>3. محاكاة حساب withdrawals.php:</h3>";

// الطريقة الأولى: من جدول العمولات
$commissions_check = $conn->query("SHOW TABLES LIKE 'marketer_commissions'");
if ($commissions_check && $commissions_check->num_rows > 0) {
    $withdrawals_calc_1 = $conn->query("
        SELECT SUM(mc.commission_amount) as total 
        FROM marketer_commissions mc
        INNER JOIN orders o ON mc.order_id = o.id
        WHERE mc.user_id = 8 AND mc.status = 'مكتمل' AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
    ");
    $result_1 = $withdrawals_calc_1->fetch_assoc();
    $calc_1 = $result_1['total'] ?: 0;
    echo "حساب withdrawals.php (طريقة 1 - جدول العمولات): $calc_1<br>";
}

// الطريقة الثانية: من جدول الطلبات مباشرة
$withdrawals_calc_2 = $conn->query("
    SELECT SUM(commission_total) as total 
    FROM orders 
    WHERE user_id = 8 AND status IN ('تم التوصيل', 'محصل', 'مكتمل')
");
$result_2 = $withdrawals_calc_2->fetch_assoc();
$calc_2 = $result_2['total'] ?: 0;
echo "حساب withdrawals.php (طريقة 2 - جدول الطلبات): $calc_2<br>";

// 4. تحديد المشكلة
echo "<h3>4. تحليل المشكلة:</h3>";
echo "الإجمالي الصحيح من الطلبات: $total_commission_should_be<br>";
echo "الإجمالي في جدول العمولات: $total_commission_in_table<br>";
echo "حساب withdrawals.php: $calc_1<br>";

if ($total_commission_should_be != $total_commission_in_table) {
    echo "<strong style='color: red;'>المشكلة: هناك عمولات مفقودة في جدول marketer_commissions</strong><br>";
}

if ($total_commission_should_be != $calc_1) {
    echo "<strong style='color: red;'>المشكلة: حساب withdrawals.php غير صحيح</strong><br>";
}

// 5. عرض العمولات المفقودة
echo "<h3>5. العمولات المفقودة:</h3>";
$missing_commissions = $conn->query("
    SELECT o.* 
    FROM orders o
    LEFT JOIN marketer_commissions mc ON o.id = mc.order_id AND o.user_id = mc.user_id
    WHERE o.user_id = 8 
    AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
    AND o.commission_total > 0
    AND mc.id IS NULL
");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>رقم الطلب</th><th>الاسم</th><th>العمولة المفقودة</th><th>الحالة</th><th>التاريخ</th></tr>";

while ($order = $missing_commissions->fetch_assoc()) {
    echo "<tr style='background-color: #ffcccc;'>";
    echo "<td>" . $order['id'] . "</td>";
    echo "<td>" . $order['customer_name'] . "</td>";
    echo "<td style='color: red; font-weight: bold;'>" . $order['commission_total'] . "</td>";
    echo "<td>" . $order['status'] . "</td>";
    echo "<td>" . $order['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 6. عرض العمولات الزائدة (لطلبات غير مكتملة)
echo "<h3>6. العمولات الزائدة (لطلبات غير مكتملة):</h3>";
$extra_commissions = $conn->query("
    SELECT mc.*, o.status as order_status, o.customer_name
    FROM marketer_commissions mc
    INNER JOIN orders o ON mc.order_id = o.id
    WHERE mc.user_id = 8 
    AND o.status NOT IN ('تم التوصيل', 'محصل', 'مكتمل')
");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>رقم العمولة</th><th>رقم الطلب</th><th>الاسم</th><th>مبلغ العمولة</th><th>حالة الطلب</th><th>التاريخ</th></tr>";

while ($commission = $extra_commissions->fetch_assoc()) {
    echo "<tr style='background-color: #ffcccc;'>";
    echo "<td>" . $commission['id'] . "</td>";
    echo "<td>" . $commission['order_id'] . "</td>";
    echo "<td>" . $commission['customer_name'] . "</td>";
    echo "<td style='color: red;'>" . $commission['commission_amount'] . "</td>";
    echo "<td>" . $commission['order_status'] . "</td>";
    echo "<td>" . $commission['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
