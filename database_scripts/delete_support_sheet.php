<?php
// ملف: delete_support_sheet.php
// حذف شيت موظف دعم فني (JSON API)

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

// التحقق من معرف الشيت
if (!isset($_GET['sheet_id']) || !is_numeric($_GET['sheet_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف الشيت مطلوب']);
    exit;
}

$sheet_id = intval($_GET['sheet_id']);

// جلب معلومات الشيت
$sheet = $conn->query("SELECT file_name FROM support_sheets WHERE id = $sheet_id")->fetch_assoc();

if (!$sheet) {
    echo json_encode(['success' => false, 'message' => 'الشيت غير موجود']);
    exit;
}

// حذف الملف من المجلد
$file_path = 'uploads/support_sheets/' . $sheet['file_name'];
if (file_exists($file_path)) {
    if (!unlink($file_path)) {
        echo json_encode(['success' => false, 'message' => 'فشل في حذف الملف']);
        exit;
    }
}

// حذف الطلبات المرتبطة بهذا الشيت أولاً حتى تنقص الأرقام من الإحصائيات
$conn->query("DELETE FROM support_orders WHERE sheet_id = $sheet_id");

// حذف السجل من قاعدة البيانات
if ($conn->query("DELETE FROM support_sheets WHERE id = $sheet_id")) {
    echo json_encode(['success' => true, 'message' => 'تم الحذف بنجاح']);
} else {
    echo json_encode(['success' => false, 'message' => 'فشل في حذف السجل: ' . $conn->error]);
}
?>
