<?php
// ملف: get_support_sheets.php
// إرجاع قائمة الشيتات الخاصة بموظف دعم فني (JSON API)

include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");
session_start();

header('Content-Type: application/json; charset=utf-8');

// التحقق من الصلاحيات
$role = $_SESSION['admin_role'] ?? '';
$can_manage_support = ($role === 'super_admin' || (function_exists('admin_can_access_page') && admin_can_access_page('support', $role, $_SESSION['admin_allowed_pages'] ?? '')));
if (!$can_manage_support) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// التحقق من معرف الموظف
if (!isset($_GET['support_id']) || !is_numeric($_GET['support_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف الموظف مطلوب']);
    exit;
}

$support_id = intval($_GET['support_id']);

// جلب الشيتات
$sheets = [];
$result = $conn->query("SELECT id, sheet_name, file_name, original_name, created_at 
                        FROM support_sheets 
                        WHERE support_id = $support_id 
                        ORDER BY created_at DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sheets[] = $row;
    }
}

echo json_encode(['success' => true, 'sheets' => $sheets]);
?>
