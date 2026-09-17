<?php
/**
 * رفع شيتات الدعم — يُستدعى من admin_panel.php قبل أي HTML
 */
if (!isset($conn) || !is_object($conn) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['sheet_file'])) {
    return;
}

$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['super_admin', 'admin'], true)) {
    $_SESSION['support_sheet_flash_error'] = 'ليس لديك صلاحية رفع الشيتات.';
    header('Location: admin_panel.php?page=supervisor');
    exit;
}

$support_id = (int) ($_POST['support_id'] ?? $_GET['id'] ?? 0);
$return_page = $_GET['page'] ?? 'support_detail';
$period = $_GET['period'] ?? 'all';

if ($support_id <= 0) {
    $_SESSION['support_sheet_flash_error'] = 'معرف موظف الدعم غير صالح.';
    header('Location: admin_panel.php?page=supervisor');
    exit;
}

$member_check = $conn->query("SELECT id FROM admins WHERE id = $support_id AND role = 'support' LIMIT 1");
if (!$member_check || $member_check->num_rows === 0) {
    $_SESSION['support_sheet_flash_error'] = 'موظف الدعم غير موجود.';
    header('Location: admin_panel.php?page=supervisor');
    exit;
}

$file = $_FILES['sheet_file'];
if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['support_sheet_flash_error'] = support_sheet_upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE);
    header('Location: admin_panel.php?page=' . urlencode($return_page) . '&id=' . $support_id . '&period=' . urlencode($period));
    exit;
}

$original_filename = basename($file['name']);
$ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
$allowed_ext = ['xlsx', 'xls', 'csv'];

if (!in_array($ext, $allowed_ext, true)) {
    $_SESSION['support_sheet_flash_error'] = 'صيغة الملف غير مدعومة. استخدم .xlsx أو .xls أو .csv';
    header('Location: admin_panel.php?page=' . urlencode($return_page) . '&id=' . $support_id . '&period=' . urlencode($period));
    exit;
}

$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'support_sheets' . DIRECTORY_SEPARATOR;
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
    $_SESSION['support_sheet_flash_error'] = 'تعذر إنشاء مجلد رفع الشيتات.';
    header('Location: admin_panel.php?page=' . urlencode($return_page) . '&id=' . $support_id . '&period=' . urlencode($period));
    exit;
}

$safe_name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $original_filename);
$file_name = $support_id . '_' . time() . '_' . $safe_name;
$target_file = $upload_dir . $file_name;

if (!move_uploaded_file($file['tmp_name'], $target_file)) {
    $_SESSION['support_sheet_flash_error'] = 'فشل حفظ الملف على السيرفر. تحقق من صلاحيات مجلد uploads/support_sheets.';
    header('Location: admin_panel.php?page=' . urlencode($return_page) . '&id=' . $support_id . '&period=' . urlencode($period));
    exit;
}

$names_count = count_support_sheet_rows($target_file);
$sheet_name_input = trim((string) ($_POST['sheet_name'] ?? ''));
$sheet_name = $conn->real_escape_string($sheet_name_input !== '' ? $sheet_name_input : $original_filename);
$original_escaped = $conn->real_escape_string($original_filename);
$file_name_escaped = $conn->real_escape_string($file_name);
$call_duration = (int) ($_POST['call_duration'] ?? 0);
$uploaded_by = (int) ($_SESSION['admin_id'] ?? 0);

$insert_ok = $conn->query("
    INSERT INTO support_sheets (support_id, file_name, original_name, sheet_name, uploaded_by, names_count, call_duration, created_at)
    VALUES ($support_id, '$file_name_escaped', '$original_escaped', '$sheet_name', $uploaded_by, $names_count, $call_duration, NOW())
");

if (!$insert_ok) {
    @unlink($target_file);
    $_SESSION['support_sheet_flash_error'] = 'فشل حفظ بيانات الشيت في قاعدة البيانات: ' . $conn->error;
    header('Location: admin_panel.php?page=' . urlencode($return_page) . '&id=' . $support_id . '&period=' . urlencode($period));
    exit;
}

$sheet_id = (int) $conn->insert_id;
$imported = 0;
if ($sheet_id > 0 && function_exists('import_support_sheet_orders_to_db')) {
    $imported = (int) import_support_sheet_orders_to_db($conn, $sheet_id, $support_id, $file_name);
}

if (function_exists('sync_support_stats')) {
    sync_support_stats($conn, $support_id);
}

// إشعار لموظف الدعم المخصص
if (function_exists('send_notification') && $support_id > 0) {
    $uploader_name = $_SESSION['admin_fullname'] ?? 'الإدارة';
    $title = "تم رفع شيت جديد";
    $msg = "تم رفع وتعيين شيت جديد لك باسم ($sheet_name) يحتوي على $names_count طلب بواسطة $uploader_name";
    $link = "admin_panel.php?page=support_sheet&id=$support_id";
    send_notification($conn, 'admin', $support_id, $title, $msg, $link);
}

$flash_name = $sheet_name_input !== '' ? $sheet_name_input : $original_filename;
$_SESSION['support_sheet_flash_success'] = 'تم رفع الشيت بنجاح: ' . $flash_name
    . " ($names_count اسم)"
    . ($imported > 0 ? " — وتم إنزال $imported طلب في سجل كل الطلبات" : '');

if ($return_page === 'supervisor') {
    header('Location: admin_panel.php?page=supervisor');
} else {
    header('Location: admin_panel.php?page=support_detail&id=' . $support_id . '&period=' . urlencode($period));
}
exit;
