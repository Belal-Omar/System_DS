<?php
// ملف: support_sheet_viewer.php
// عارض الشيتات الآمن - يتحقق من الصلاحيات قبل عرض الملف

session_start();
include(__DIR__ . '/core/config.php");

// التحقق من الصلاحيات - فقط الدعم الفني
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('غير مصرح');
}

if (($_SESSION['admin_role'] ?? '') !== 'support') {
    http_response_code(403);
    die('غير مصرح - فريق الدعم فقط');
}

// التحقق من معرف الشيت
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('معرف غير صالح');
}

$sheet_id = intval($_GET['id']);
$support_id = $_SESSION['admin_id'] ?? 0;

// جلب معلومات الشيت والتحقق من ملكيته
$sheet = $conn->query("
    SELECT * FROM support_sheets 
    WHERE id = $sheet_id AND support_id = $support_id
")->fetch_assoc();

if (!$sheet) {
    http_response_code(404);
    die('الشيت غير موجود أو غير مصرح لك بالوصول');
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

// إرسال Headers لمنع التنزيل والتخزين المؤقت
header('Content-Type: ' . $content_type);
header('Content-Disposition: inline; filename="' . $sheet['original_name'] . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// قراءة وعرض الملف
readfile($file_path);
exit;
?>
