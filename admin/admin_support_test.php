<?php
// SIMPLIFIED TEST VERSION

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "<div style='color:red;padding:20px;'>غير مصرح - يجب تسجيل الدخول</div>";
    exit;
}

$current_role = $_SESSION['admin_role'] ?? '';
$is_manager = ($current_role === 'super_admin');
$is_support = ($current_role === 'support');

echo "<div style='padding:20px;background:green;color:white;'>DEBUG: role=" . $current_role . " | is_manager=" . ($is_manager ? 'yes' : 'no') . " | is_support=" . ($is_support ? 'yes' : 'no') . "</div>";

if (!$is_manager && !$is_support) {
    echo "<div style='color:red;padding:20px;'>غير مصرح - فقط المدير أو الدعم</div>";
    exit;
}

echo "<div style='padding:20px;background:blue;color:white;'>SUCCESS: Authorized user</div>";

if ($is_manager) {
    echo "<h1>متابعة فريق الدعم الفني</h1>";
    
    // Get support team
    $result = $conn->query("SELECT * FROM admins WHERE role='support'");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='width:100%'>";
        echo "<tr><th>الاسم</th><th>اليوزر</th><th>الشيتات</th><th>إجراءات</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $sheets_count = $conn->query("SELECT COUNT(*) as c FROM support_sheets WHERE support_id=".$row['id'])->fetch_assoc()['c'];
            echo "<tr>";
            echo "<td>".$row['fullname']."</td>";
            echo "<td>".$row['username']."</td>";
            echo "<td>".$sheets_count."</td>";
            echo "<td><a href='admin_support_detail.php?id=".$row['id']."'>تفاصيل</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>لا يوجد موظفين دعم فني</p>";
    }
} else {
    echo "<h1>الشيتات المرفوعة</h1>";
    
    $support_id = $_SESSION['admin_id'] ?? 0;
    $result = $conn->query("SELECT * FROM support_sheets WHERE support_id=$support_id");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div style='border:1px solid #ccc;padding:10px;margin:5px;'>";
            echo $row['sheet_name'] . " - " . $row['created_at'];
            echo "</div>";
        }
    } else {
        echo "<p>لا توجد شيتات</p>";
    }
}
?>
