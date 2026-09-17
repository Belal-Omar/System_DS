<?php
// ملف: check_all_orders.php
// فحص جميع الطلبات للمستخدم 8 وتحديد المشاكل

session_start();
include(__DIR__ . '/core/config.php");

$user_id = 8; // Belal Medhat

echo "<h2>فحص جميع الطلبات للمستخدم: $user_id (Belal Medhat)</h2>";

// 1. فحص جميع الطلبات في قاعدة البيانات للمستخدم
echo "<h3>1. جميع الطلبات في قاعدة البيانات للمستخدم:</h3>";
$all_orders_query = $conn->query("SELECT * FROM orders WHERE user_id = $user_id ORDER BY id ASC");
$order_count = $all_orders_query->num_rows;
echo "إجمالي الطلبات في قاعدة البيانات: $order_count<br>";

if ($order_count > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>رقم الطلب</th><th>الاسم</th><th>الإجمالي</th><th>العمولة</th><th>الحالة</th><th>التاريخ</th><th>في جدول العمولات؟</th></tr>";
    
    while ($order = $all_orders_query->fetch_assoc()) {
        $order_id = $order['id'];
        
        // التحقق إذا كان الطلب في جدول العمولات
        $commission_check = $conn->query("SELECT id FROM marketer_commissions WHERE order_id = $order_id AND user_id = $user_id");
        $has_commission = $commission_check->num_rows > 0 ? "نعم" : "لا";
        
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . $order['customer_name'] . "</td>";
        echo "<td>" . $order['total'] . "</td>";
        echo "<td>" . $order['commission_total'] . "</td>";
        echo "<td>" . $order['status'] . "</td>";
        echo "<td>" . $order['created_at'] . "</td>";
        echo "<td style='background-color: " . ($has_commission == "نعم" ? "#d4edda" : "#f8d7da") . "'>" . $has_commission . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد طلبات في قاعدة البيانات!";
}

// 2. فحص الطلبات من 37 إلى 58 تحديداً
echo "<h3>2. فحص الطلبات من 37 إلى 58:</h3>";
$range_orders_query = $conn->query("SELECT * FROM orders WHERE id BETWEEN 37 AND 58 AND user_id = $user_id ORDER BY id ASC");
$range_count = $range_orders_query->num_rows;
echo "عدد الطلبات في النطاق 37-58: $range_count<br>";

if ($range_count > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>رقم الطلب</th><th>الاسم</th><th>الإجمالي</th><th>العمولة</th><th>الحالة</th><th>التاريخ</th></tr>";
    
    while ($order = $range_orders_query->fetch_assoc()) {
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
    echo "لا توجد طلبات في النطاق 37-58 لهذا المستخدم!";
}

// 3. فحص جميع الطلبات في النطاق 37-58 (للمستخدمين الآخرين)
echo "<h3>3. جميع الطلبات في النطاق 37-58 (لجميع المستخدمين):</h3>";
$all_range_query = $conn->query("SELECT o.*, u.fullname as user_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id BETWEEN 37 AND 58 ORDER BY o.id ASC");
$all_range_count = $all_range_query->num_rows;
echo "عدد الطلبات في النطاق 37-58 للجميع: $all_range_count<br>";

if ($all_range_count > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>رقم الطلب</th><th>اسم العميل</th><th>المسوق</th><th>user_id</th><th>الإجمالي</th><th>العمولة</th><th>الحالة</th><th>التاريخ</th></tr>";
    
    while ($order = $all_range_query->fetch_assoc()) {
        $row_style = ($order['user_id'] == $user_id) ? "background-color: #d4edda;" : "";
        echo "<tr style='$row_style'>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . $order['customer_name'] . "</td>";
        echo "<td>" . ($order['user_name'] ?? 'غير معروف') . "</td>";
        echo "<td>" . $order['user_id'] . "</td>";
        echo "<td>" . $order['total'] . "</td>";
        echo "<td>" . $order['commission_total'] . "</td>";
        echo "<td>" . $order['status'] . "</td>";
        echo "<td>" . $order['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "لا توجد طلبات في النطاق 37-58 إطلاقاً!";
}

// 4. حساب إجمالي العمولات المفقودة
echo "<h3>4. العمولات المفقودة:</h3>";
$missing_commissions_query = $conn->query("
    SELECT o.* 
    FROM orders o 
    LEFT JOIN marketer_commissions mc ON o.id = mc.order_id AND o.user_id = mc.user_id
    WHERE o.user_id = $user_id 
    AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل', 'في الشحن')
    AND mc.id IS NULL
");
$missing_count = $missing_commissions_query->num_rows;
$total_missing = 0;

echo "عدد الطلبات التي لم تتم إضافة عمولاتها: $missing_count<br>";

if ($missing_count > 0) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>رقم الطلب</th><th>الإجمالي</th><th>العمولة المفقودة</th><th>الحالة</th><th>التاريخ</th></tr>";
    
    while ($order = $missing_commissions_query->fetch_assoc()) {
        $total_missing += $order['commission_total'];
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . $order['total'] . "</td>";
        echo "<td style='color: red; font-weight: bold;'>" . $order['commission_total'] . "</td>";
        echo "<td>" . $order['status'] . "</td>";
        echo "<td>" . $order['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<br><strong>إجمالي العمولات المفقودة: " . $total_missing . "</strong>";
}

$conn->close();
?>
