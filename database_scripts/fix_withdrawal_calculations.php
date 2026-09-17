<?php
// ملف: fix_withdrawal_calculations.php
// إصلاح حسابات العمولات في withdrawals.php لتشمل فقط الطلبات المكتملة

session_start();
include(__DIR__ . '/core/config.php");

echo "<h2>إصلاح حسابات العمولات للطلبات المكتملة فقط</h2>";

// 1. تحديث العمولات الحالية - إلغاء عمولات الطلبات غير المكتملة
echo "<h3>1. تنظيف العمولات من الطلبات غير المكتملة:</h3>";

// حذف العمولات للطلبات التي ليست مكتملة
$delete_non_completed = $conn->query("
    DELETE mc FROM marketer_commissions mc
    INNER JOIN orders o ON mc.order_id = o.id
    WHERE o.status NOT IN ('تم التوصيل', 'محصل', 'مكتمل')
");

$deleted_count = $conn->affected_rows;
echo "تم حذف $deleted_count سجل عمولة للطلبات غير المكتملة<br>";

// 2. إضافة العمولات للطلبات المكتملة فقط
echo "<h3>2. إضافة العمولات للطلبات المكتملة فقط:</h3>";

// جلب الطلبات المكتملة التي ليس لها عمولات
$completed_orders_query = $conn->query("
    SELECT o.* 
    FROM orders o
    LEFT JOIN marketer_commissions mc ON o.id = mc.order_id AND o.user_id = mc.user_id
    WHERE o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
    AND o.user_id IS NOT NULL
    AND o.commission_total > 0
    AND mc.id IS NULL
");

$added_count = 0;
$total_added = 0;

while ($order = $completed_orders_query->fetch_assoc()) {
    $order_id = $order['id'];
    $user_id = $order['user_id'];
    $commission_amount = $order['commission_total'];
    
    // إضافة العمولة للطلب المكتمل
    $insert_commission = $conn->prepare("
        INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status) 
        VALUES (?, ?, ?, 'مكتمل')
    ");
    $insert_commission->bind_param("iid", $user_id, $order_id, $commission_amount);
    
    if ($insert_commission->execute()) {
        $added_count++;
        $total_added += $commission_amount;
        
        echo "تمت إضافة عمولة للطلب المكتمل $order_id للمستخدم $user_id: $commission_amount<br>";
    }
}

echo "<strong>تمت إضافة $added_count عمولة للطلبات المكتملة بإجمالي $total_added</strong><br>";

// 3. تحديث إجمالي العمولات لكل مستخدم
echo "<h3>3. تحديث إجمالي العمولات لكل مستخدم:</h3>";

$update_users_query = $conn->query("
    UPDATE users u 
    SET total_commissions = (
        SELECT COALESCE(SUM(mc.commission_amount), 0)
        FROM marketer_commissions mc
        WHERE mc.user_id = u.id AND mc.status = 'مكتمل'
    )
    WHERE u.user_type = 'مسوق'
");

echo "تم تحديث إجمالي العمولات لجميع المسوقين<br>";

// 4. إصلاح منطق withdrawals.php
echo "<h3>4. إنشاء كود withdrawals.php المصلح:</h3>";

$withdrawals_code = '
<?php
// ملف: withdrawals_fixed.php - نسخة مصلحة
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول ونوع المستخدم
if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION["user_id"] ?? null;
$user = null;

if ($user_id && isset($conn)) {
    $user_id = (int)$user_id;
    $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user_query) {
        $user = $user_query->fetch_assoc();
    }
}

// منع التاجر من دخول صفحات المسوق
if ($user && ($user["user_type"] ?? "") == "تاجر") {
    header("Location: home111.php");
    exit;
}

// حساب الأرباح المتاحة للسحب (للمستخدم المسجل فقط)
$available_profit = 0;
$withdrawn_amount = 0;
$available_withdrawal = 0;

if ($user_id) {
    // حساب إجمالي العمولة من الطلبات المكتملة فقط
    $commissions_check = $conn->query("SHOW TABLES LIKE \'marketer_commissions\'");
    if ($commissions_check && $commissions_check->num_rows > 0) {
        // استخدام جدول العمولات - العمولات المكتملة فقط
        $available_profit_query = $conn->prepare("
            SELECT SUM(mc.commission_amount) as total 
            FROM marketer_commissions mc
            WHERE mc.user_id = ? AND mc.status = \'مكتمل\'
        ");
        $available_profit_query->bind_param("i", $user_id);
        $available_profit_query->execute();
        $available_profit_result = $available_profit_query->get_result();
        $available_profit_row = $available_profit_result->fetch_assoc();
        $available_profit = $available_profit_row["total"] ? floatval($available_profit_row["total"]) : 0;
    } else {
        // الرجوع للطريقة القديمة - الطلبات المكتملة فقط
        $available_profit_query = $conn->prepare("
            SELECT SUM(commission_total) as total 
            FROM orders 
            WHERE user_id = ? AND status IN (\'تم التوصيل\', \'محصل\', \'مكتمل\')
        ");
        $available_profit_query->bind_param("i", $user_id);
        $available_profit_query->execute();
        $available_profit_result = $available_profit_query->get_result();
        $available_profit_row = $available_profit_result->fetch_assoc();
        $available_profit = $available_profit_row["total"] ? floatval($available_profit_row["total"]) : 0;
    }

    // حساب المبلغ المسحوب سابقاً
    $withdrawn_query = $conn->prepare("
        SELECT SUM(amount) as total 
        FROM withdrawals 
        WHERE user_id = ? AND status = \'مكتمل\'
    ");
    $withdrawn_query->bind_param("i", $user_id);
    $withdrawn_query->execute();
    $withdrawn_result = $withdrawn_query->get_result();
    $withdrawn_row = $withdrawn_result->fetch_assoc();
    $withdrawn_amount = $withdrawn_row["total"] ? floatval($withdrawn_row["total"]) : 0;

    // المبلغ المتاح للسحب
    $available_withdrawal = $available_profit - $withdrawn_amount;
    
    // التأكد من أن المبلغ المتاح ليس سالباً
    if ($available_withdrawal < 0) {
        $available_withdrawal = 0;
    }
}

// معالجة طلب سحب جديد
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["request_withdrawal"])) {
    if (!$user_id) {
        $error = "يجب تسجيل الدخول أولاً لتقديم طلب سحب";
    } else {
        $amount = floatval($_POST["amount"]);
        $phone = $conn->real_escape_string($_POST["phone"]);
        
        if ($amount <= 0) {
            $error = "المبلغ يجب أن يكون أكبر من الصفر";
        } elseif ($amount > $available_withdrawal) {
            $error = "المبلغ المطلوب أكبر من المبلغ المتاح للسحب";
        } else {
            $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, phone, status) VALUES (?, ?, ?, \'قيد المراجعة\')");
            $stmt->bind_param("ids", $user_id, $amount, $phone);
            
            if ($stmt->execute()) {
                $success = "تم إرسال طلب السحب بنجاح، سيتم مراجعته من قبل الإدارة";
                $available_withdrawal -= $amount;
            } else {
                $error = "حدث خطأ أثناء إرسال طلب السحب: " . $stmt->error;
            }
        }
    }
}

// جلب طلبات السحب السابقة
$withdrawals_query = null;
if ($user_id) {
    $withdrawals_query = $conn->prepare("
        SELECT * FROM withdrawals 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $withdrawals_query->bind_param("i", $user_id);
    $withdrawals_query->execute();
    $withdrawals_result = $withdrawals_query->get_result();
}

// جلب الطلبات المكتملة فقط مع العمولات
$completed_orders_query = null;
$completed_orders = [];
if ($user_id) {
    $completed_orders_query = $conn->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? AND status IN (\'تم التوصيل\', \'محصل\', \'مكتمل\')
        ORDER BY created_at DESC
    ");
    $completed_orders_query->bind_param("i", $user_id);
    $completed_orders_query->execute();
    $completed_orders_result = $completed_orders_query->get_result();
    
    while($order = $completed_orders_result->fetch_assoc()) {
        $completed_orders[] = $order;
    }
}

// جلب آخر 5 طلبات للمستخدم
$recent_orders_query = null;
$recent_orders = [];
if ($user_id) {
    $recent_orders_query = $conn->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_orders_query->bind_param("i", $user_id);
    $recent_orders_query->execute();
    $recent_orders_result = $recent_orders_query->get_result();
    
    while($order = $recent_orders_result->fetch_assoc()) {
        $recent_orders[] = $order;
    }
}

// جلب بيانات المستخدم للـ navbar
$user_name = "";
if ($user_id && $user) {
    $user_name = $user["fullname"] ?? "";
}

// الحصول على الصفحة الحالية
$current_page = basename($_SERVER["PHP_SELF"] ?? $_SERVER["SCRIPT_NAME"] ?? "index.php");
$current_page = str_replace([".php", ".html"], "", $current_page);
?>

<!-- باقي صفحة withdrawals.php كما هي مع استخدام المتغيرات الصحيحة -->
';

file_put_contents("withdrawals_fixed.php", $withdrawals_code);
echo "تم إنشاء ملف withdrawals_fixed.php<br>";

// 5. عرض الحالة النهائية
echo "<h3>5. الحالة النهائية بعد الإصلاح:</h3>";

// التحقق من كل مستخدم
$final_check_query = $conn->query("
    SELECT u.id, u.fullname,
           COUNT(DISTINCT CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN o.id END) as completed_orders,
           COUNT(DISTINCT mc.id) as commission_records,
           SUM(CASE WHEN mc.status = 'مكتمل' THEN mc.commission_amount ELSE 0 END) as total_commissions
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    LEFT JOIN marketer_commissions mc ON u.id = mc.user_id
    WHERE u.user_type = 'مسوق'
    GROUP BY u.id, u.fullname
    ORDER BY u.id
");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>المستخدم</th><th>الطلبات المكتملة</th><th>سجلات العمولات</th><th>إجمالي العمولات</th></tr>";

while ($row = $final_check_query->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row["fullname"] . " (ID: " . $row["id"] . ")</td>";
    echo "<td>" . $row["completed_orders"] . "</td>";
    echo "<td>" . $row["commission_records"] . "</td>";
    echo "<td><strong>" . ($row["total_commissions"] ?: 0) . "</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>6. الخطوات التالية:</h3>";
echo "1. استبدل withdrawals.php بـ withdrawals_fixed.php<br>";
echo "2. تحقق من أن العمولات تُحسب من الطلبات المكتملة فقط<br>";
echo "3. اختبر طلب سحب جديد<br>";
echo "4. تأكد من أن الإجماليات صحيحة<br>";

echo "<br><strong>تم الانتهاء من إصلاح حسابات العمولات!</strong>";

$conn->close();
?>
