<?php
// ملف: support_sheet_download.php
// تحميل الشيتات للمدير فقط

session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('غير مصرح - يرجى تسجيل الدخول');
}

$current_user_id = $_SESSION['admin_id'] ?? 0;
$current_role = $_SESSION['admin_role'] ?? '';

// التحقق من معرف الشيت
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('معرف غير صالح');
}

$sheet_id = intval($_GET['id']);

// جلب معلومات الشيت
$sheet = $conn->query("SELECT * FROM support_sheets WHERE id = $sheet_id")->fetch_assoc();

if (!$sheet) {
    http_response_code(404);
    die('الشيت غير موجود');
}

// التحقق من الصلاحيات: المدير يحمّل كل شيء، الموظف يحمّل شيتاته فقط
$is_manager = ($current_role === 'super_admin');
if (!$is_manager && $current_role === 'support' && $sheet['support_id'] != $current_user_id) {
    http_response_code(403);
    die('غير مصرح - يمكنك تحميل شيتاتك فقط');
}

$file_path = 'uploads/support_sheets/' . $sheet['file_name'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die('الملف غير موجود');
}

// تحديد نوع MIME
$mime_types = [
    'csv' => 'text/csv',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'pdf' => 'application/pdf'
];

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$content_type = $mime_types[$ext] ?? 'application/octet-stream';

// إرسال Headers للتحميل
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $sheet['original_name'] . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600');

readfile($file_path);
exit;
?>
