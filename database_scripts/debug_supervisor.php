<?php
// ملف تشخيص لصفحة المتابعة
session_start();
include(__DIR__ . '/core/config.php");

echo "<h1>تشخيص صفحة المتابعة</h1>";

// 1. التحقق من صلاحيات المستخدم الحالي
echo "<h2>1. معلومات المستخدم الحالي:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 2. التحقق من وجود جدول support_stats
echo "<h2>2. التحقق من الجداول:</h2>";
$tables = ['support_stats', 'support_sheets', 'admins'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ جدول $table موجود<br>";
        
        // عرض عدد السجلات
        $count = $conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total'];
        echo "   - عدد السجلات: $count<br>";
    } else {
        echo "❌ جدول $table غير موجود!<br>";
    }
}

// 3. التحقق من موظفي الدعم الفني
echo "<h2>3. موظفي الدعم الفني:</h2>";
$support_users = $conn->query("SELECT id, username, fullname, role FROM admins WHERE role = 'support'");
if ($support_users && $support_users->num_rows > 0) {
    echo "✅ يوجد " . $support_users->num_rows . " موظف دعم فني:<br>";
    while ($user = $support_users->fetch_assoc()) {
        echo "   - {$user['fullname']} ({$user['username']}) - ID: {$user['id']}<br>";
    }
} else {
    echo "❌ لا يوجد موظفين دعم فني!<br>";
}

// 4. التحقق من إحصائيات الدعم
echo "<h2>4. إحصائيات الدعم:</h2>";
$stats = $conn->query("SELECT * FROM support_stats");
if ($stats && $stats->num_rows > 0) {
    echo "✅ يوجد " . $stats->num_rows . " سجل إحصائيات:<br>";
    while ($stat = $stats->fetch_assoc()) {
        echo "   - Support ID: {$stat['support_id']}, Confirmation: {$stat['confirmation_rate']}%, Delivery: {$stat['delivery_rate']}%, Cancel: {$stat['cancellation_rate']}%<br>";
    }
} else {
    echo "❌ لا توجد إحصائيات مسجلة!<br>";
}

// 5. اختبار الاستعلام المستخدم في الصفحة
echo "<h2>5. اختبار الاستعلام الرئيسي:</h2>";
$query = "
    SELECT 
        a.id,
        a.fullname,
        a.username,
        a.email,
        COALESCE(s.confirmation_rate, 0) as confirmation_rate,
        COALESCE(s.delivery_rate, 0) as delivery_rate,
        COALESCE(s.cancellation_rate, 0) as cancellation_rate,
        COALESCE(s.total_orders, 0) as total_orders,
        COALESCE(s.completed_orders, 0) as completed_orders,
        COALESCE(s.cancelled_orders, 0) as cancelled_orders
    FROM admins a
    LEFT JOIN support_stats s ON a.id = s.support_id
    WHERE a.role = 'support'
    ORDER BY a.fullname ASC
";

$result = $conn->query($query);
if ($result) {
    echo "✅ الاستعلام ناجح، عدد النتائج: " . $result->num_rows . "<br>";
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>الاسم</th><th>نسبة التأكيد</th><th>نسبة التوصيل</th><th>نسبة الإلغاء</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['fullname']}</td>";
            echo "<td>{$row['confirmation_rate']}%</td>";
            echo "<td>{$row['delivery_rate']}%</td>";
            echo "<td>{$row['cancellation_rate']}%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "❌ خطأ في الاستعلام: " . $conn->error . "<br>";
}

// 6. روابط سريعة
echo "<h2>6. روابط سريعة:</h2>";
echo "<a href='setup_support_tables.php' style='background:green; color:white; padding:10px; margin:5px; display:inline-block;'>إعداد الجداول</a>";
echo "<a href='update_admin_role_support.php' style='background:blue; color:white; padding:10px; margin:5px; display:inline-block;'>تعديل عمود role</a>";
echo "<a href='admin_panel.php?page=supervisor' style='background:orange; color:white; padding:10px; margin:5px; display:inline-block;'>الذهاب لصفحة المتابعة</a>";
?>
