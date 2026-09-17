<?php
// support_export_confirmed.php

session_start();
include(__DIR__ . '/core/config.php");
/** @var mysqli $conn */
include(__DIR__ . '/core/helpers.php");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
$is_manager = in_array($_SESSION['admin_role'], ['super_admin', 'admin', 'manager'], true);

// Get query parameters
$sheet_id = isset($_GET['sheet_id']) ? (int) $_GET['sheet_id'] : 0;
$support_id = isset($_GET['support_id']) ? (int) $_GET['support_id'] : 0;
$conf_date = $_GET['conf_date'] ?? '';
$export_all = isset($_GET['all']) && $_GET['all'] == '1';

if ($sheet_id <= 0 && empty($conf_date) && !$is_manager) {
    die('معرف الشيت غير صالح.');
}

$status_ar = [
    'pending'         => 'قيد الانتظار',
    'confirmed'       => 'تم التأكيد',
    'order_confirmed' => 'تم تأكيد الطلب',
    'ready_to_ship'   => 'جاهز للشحن',
    'received'        => 'تم الاستلام',
    'delivered'       => 'تم التوصيل',
    'cancelled'       => 'ملغي',
    'canceled'        => 'ملغى',
    'order_cancelled' => 'تم إلغاء الطلب',
    'postponed'       => 'مؤجل',
    'no_answer'       => 'لم يرد',
    'wrong_number'    => 'رقم خطأ',
    'out_of_coverage' => 'غير متاح',
    'duplicate'       => 'مكرر',
    'fake'            => 'وهمي',
    'other'           => 'أخرى',
    'new'             => 'جديد',
    'returned'        => 'مرتجع',
    'محصل'            => 'محصل'
];

$sql = "SELECT * FROM support_orders WHERE 1=1";
if ($sheet_id > 0) {
    $sql .= " AND sheet_id = $sheet_id";
} elseif (!empty($conf_date) && $support_id > 0) {
    $sql .= " AND support_id = $support_id";
    $sql .= " AND DATE(COALESCE(confirmed_at, updated_at, created_at)) = '" . $conn->real_escape_string($conf_date) . "'";
}

if (!$export_all) {
    $conf = accounts_confirmed_statuses_sql();
    $sql .= " AND order_status IN ($conf)";
}
$sql .= " ORDER BY created_at DESC";

$res = $conn->query($sql);
if (!$res) {
    die('خطأ في استعلام قاعدة البيانات: ' . $conn->error);
}

function export_fail(int $code, string $msg) {
    http_response_code($code);
    die($msg);
}

$headers = [
    'التاريخ',
    'إسم المستلم',
    'رقم الهاتف',
    'حالة الطلب',
    'ملاحظات',
    'المحافظة',
    'العنوان',
    'القطع',
    'حالة العميل',
    'C.C Agent',
    'M. Agent',
    'Campaign',
    'Version',
    'Article',
    'السعر',
];

$prefix = $export_all ? 'all' : 'confirmed';
if (!empty($conf_date)) {
    $fname = $prefix . '_date_' . $conf_date . '_' . date('His') . '.csv';
} else {
    $fname = $prefix . '_sheet_' . ($sheet_id ?: '0') . '_' . date('Y-m-d_His') . '.csv';
}

// تنظيف أي مخرجات سابقة
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
if (!$out) {
    export_fail(500, 'تعذر فتح مجرى التصدير.');
}

fputcsv($out, $headers, ',');

$count = 0;
while ($r = $res->fetch_assoc()) {
    $count++;
    $dt = $r['order_date'] ?? (substr((string) ($r['created_at'] ?? ''), 0, 10) ?: '');
    $name = $r['recipient_name'] !== null && $r['recipient_name'] !== ''
        ? $r['recipient_name']
        : ($r['customer_name'] ?? '');
    $stKey = $r['order_status'] ?? '';
    $st = $status_ar[$stKey] ?? $stKey;
    fputcsv($out, [
        $dt,
        $name,
        $r['phone'] ?? '',
        $st,
        $r['notes'] ?? '',
        $r['governorate'] ?? '',
        $r['address'] ?? '',
        (string) ($r['pieces'] ?? $r['quantity'] ?? 1),
        $r['customer_status'] ?? '',
        $r['agent_code'] ?? '',
        $r['marketing_agent'] ?? '',
        $r['campaign'] ?? '',
        $r['version'] ?? '',
        $r['article'] ?? '',
        (string) ($r['total_price'] ?? ''),
    ], ',');
}

if ($count === 0) {
    fputcsv($out, ['لا توجد طلبات للتصدير في هذا الفلتر'], ',');
}

fclose($out);
exit;
