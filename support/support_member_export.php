<?php
/**
 * تصدير تقرير موظف الدعم (إحصائيات + مكالمات) — بدون الشيتات
 * support_member_export.php?id=13&period=all&format=csv|pdf
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function sme_fail($msg) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>تصدير</title>
    <link rel="icon" type="image/png" href="brand_logo.php?f=logo">
    <link rel="shortcut icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="apple-touch-icon" href="brand_logo.php?f=logo"></head>';
    echo '<body style="font-family:Tahoma;padding:40px;text-align:center">';
    echo '<h2>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<p><a href="javascript:history.back()">رجوع</a></p></body></html>';
    exit;
}

$role = $_SESSION['admin_role'] ?? '';
$pages = $_SESSION['admin_allowed_pages'] ?? null;
$can = ($role === 'super_admin')
    || (function_exists('admin_can_access_page') && (
        admin_can_access_page('supervisor', $role, $pages)
        || admin_can_access_page('support_detail', $role, $pages)
    ));

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !$can) {
    sme_fail('غير مصرح.');
}
if (!$conn || !is_object($conn)) {
    sme_fail('تعذر الاتصال بقاعدة البيانات.');
}

$support_id = (int) ($_GET['id'] ?? 0);
if ($support_id <= 0) {
    sme_fail('معرف الموظف غير صالح.');
}

$period = (string) ($_GET['period'] ?? 'all');
$period_labels = [
    'all' => 'الكل',
    '1day' => 'يوم واحد',
    '1week' => 'أسبوع',
    '1month' => 'شهر',
    '3months' => '3 شهور',
    '6months' => '6 شهور',
    '9months' => '9 شهور',
    '1year' => 'سنة',
];
if (!isset($period_labels[$period])) {
    $period = 'all';
}

$start_date = null;
switch ($period) {
    case '1day': $start_date = date('Y-m-d', strtotime('-1 day')); break;
    case '1week': $start_date = date('Y-m-d', strtotime('-1 week')); break;
    case '1month': $start_date = date('Y-m-d', strtotime('-1 month')); break;
    case '3months': $start_date = date('Y-m-d', strtotime('-3 months')); break;
    case '6months': $start_date = date('Y-m-d', strtotime('-6 months')); break;
    case '9months': $start_date = date('Y-m-d', strtotime('-9 months')); break;
    case '1year': $start_date = date('Y-m-d', strtotime('-1 year')); break;
}

$member = $conn->query("SELECT id, fullname, username FROM admins WHERE id = $support_id AND role = 'support' LIMIT 1");
if (!$member || !($mem = $member->fetch_assoc())) {
    sme_fail('الموظف غير موجود.');
}

$fullname = trim((string) ($mem['fullname'] ?? ''));
$username = trim((string) ($mem['username'] ?? ''));
if ($fullname === '') {
    $fullname = $username !== '' ? $username : ('موظف #' . $support_id);
}

$date_filter = $start_date ? (" AND created_at >= '" . $conn->real_escape_string($start_date) . "'") : '';
$sheets_count = 0;
$sq = $conn->query("SELECT COUNT(*) AS c FROM support_sheets WHERE support_id = $support_id $date_filter");
if ($sq && ($sr = $sq->fetch_assoc())) {
    $sheets_count = (int) ($sr['c'] ?? 0);
}

if (function_exists('support_calls_sync_schema')) {
    @support_calls_sync_schema($conn);
}
$total_call_seconds = function_exists('support_get_total_call_seconds')
    ? (int) support_get_total_call_seconds($conn, $support_id, $start_date)
    : 0;
$call_duration_formatted = function_exists('format_activity_duration')
    ? format_activity_duration($total_call_seconds)
    : ($total_call_seconds . 'ث');
$calls = function_exists('support_get_calls_list')
    ? support_get_calls_list($conn, $support_id, $start_date, 200)
    : [];

$perf = function_exists('get_support_member_performance')
    ? get_support_member_performance($conn, $support_id, $start_date)
    : [
        'total_orders' => 0,
        'confirmed_orders' => 0,
        'delivered_orders' => 0,
        'cancelled_orders' => 0,
        'confirmation_rate' => 0,
        'delivery_rate' => 0,
        'cancellation_rate' => 0,
        'personal_rating' => 0,
    ];

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv', 'pdf', 'excel'], true)) {
    $format = 'csv';
}
if ($format === 'excel') {
    $format = 'csv';
}

$period_label = $period_labels[$period];
$report_date = date('Y-m-d H:i');
$safe_name = preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $fullname);
$base = 'support_report_' . $safe_name . '_' . $period . '_' . date('Y-m-d_His');

$status_call_ar = [
    'active' => 'جارية',
    'completed' => 'مكتملة',
    'cancelled' => 'ملغاة',
    'canceled' => 'ملغاة',
];

while (ob_get_level()) {
    ob_end_clean();
}

// ========== CSV / Excel ==========
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    fputcsv($out, ['تقرير موظف الدعم الفني'], ',');
    fputcsv($out, ['الاسم', $fullname], ',');
    fputcsv($out, ['اسم المستخدم', $username], ',');
    fputcsv($out, ['الدور', 'موظف دعم فني'], ',');
    fputcsv($out, ['الفترة', $period_label], ',');
    fputcsv($out, ['تاريخ التقرير', $report_date], ',');
    fputcsv($out, [''], ',');

    fputcsv($out, ['الإحصائيات'], ',');
    fputcsv($out, ['المؤشر', 'القيمة'], ',');
    fputcsv($out, ['إجمالي الطلبات (من الشيتات المرفوعة)', (int) ($perf['total_orders'] ?? 0)], ',');
    fputcsv($out, ['طلبات مؤكدة', (int) ($perf['confirmed_orders'] ?? 0)], ',');
    fputcsv($out, ['طلبات مسلمة', (int) ($perf['delivered_orders'] ?? 0)], ',');
    fputcsv($out, ['طلبات ملغاة', (int) ($perf['cancelled_orders'] ?? 0)], ',');
    fputcsv($out, ['نسبة التأكيد', number_format((float) ($perf['confirmation_rate'] ?? 0), 1) . '%'], ',');
    fputcsv($out, ['نسبة التسليم', number_format((float) ($perf['delivery_rate'] ?? 0), 1) . '%'], ',');
    fputcsv($out, ['نسبة الإلغاء', number_format((float) ($perf['cancellation_rate'] ?? 0), 1) . '%'], ',');
    fputcsv($out, ['مدة المكالمات', $call_duration_formatted], ',');
    fputcsv($out, ['التقييم الشخصي', number_format((float) ($perf['personal_rating'] ?? 0), 1) . '/5'], ',');
    fputcsv($out, ['عدد الشيتات', $sheets_count], ',');
    fputcsv($out, [''], ',');

    fputcsv($out, ['توزيع نسب الأداء'], ',');
    fputcsv($out, ['تأكيد', number_format((float) ($perf['confirmation_rate'] ?? 0), 1) . '%'], ',');
    fputcsv($out, ['تسليم', number_format((float) ($perf['delivery_rate'] ?? 0), 1) . '%'], ',');
    fputcsv($out, ['إلغاء', number_format((float) ($perf['cancellation_rate'] ?? 0), 1) . '%'], ',');
    fputcsv($out, [''], ',');

    fputcsv($out, ['سجل المكالمات'], ',');
    fputcsv($out, ['اسم العميل', 'رقم الهاتف', 'وقت البدء', 'المدة', 'الحالة'], ',');
    if (empty($calls)) {
        fputcsv($out, ['لا توجد مكالمات مسجلة في هذه الفترة', '', '', '', ''], ',');
    } else {
        foreach ($calls as $call) {
            $st = (string) ($call['status'] ?? '');
            fputcsv($out, [
                (string) ($call['client_name'] ?: '—'),
                (string) ($call['client_phone'] ?: '—'),
                (string) ($call['started_at'] ?? ''),
                (string) ($call['duration_formatted'] ?? ''),
                $status_call_ar[$st] ?? $st,
            ], ',');
        }
    }

    fclose($out);
    exit;
}

// ========== PDF ==========
$tcpdf = __DIR__ . '/TCPDF-main/tcpdf.php';
if (!is_readable($tcpdf)) {
    $tcpdf = __DIR__ . '/tcpdf/tcpdf.php';
}
if (!is_readable($tcpdf)) {
    sme_fail('مكتبة TCPDF غير موجودة على السيرفر.');
}
require_once $tcpdf;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('تقرير موظف الدعم');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->setRTL(true);

$font = 'dejavusans';
try {
    $pdf->SetFont($font, 'B', 16);
} catch (Throwable $e) {
    $font = 'aefurat';
    $pdf->SetFont($font, 'B', 16);
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$pdf->Cell(0, 10, 'تقرير موظف الدعم الفني', 0, 1, 'C');
$pdf->SetFont($font, '', 11);
$pdf->Cell(0, 7, $fullname . '  |  @' . $username, 0, 1, 'C');
$pdf->Cell(0, 6, 'الفترة: ' . $period_label . '  |  تاريخ التقرير: ' . $report_date, 0, 1, 'C');
$pdf->Ln(3);

$conf_r = number_format((float) ($perf['confirmation_rate'] ?? 0), 1);
$del_r = number_format((float) ($perf['delivery_rate'] ?? 0), 1);
$can_r = number_format((float) ($perf['cancellation_rate'] ?? 0), 1);
$rating = number_format((float) ($perf['personal_rating'] ?? 0), 1);

$html = '
<h3 style="color:#1e40af;">الإحصائيات الرئيسية</h3>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
<tr style="background-color:#eff6ff;font-weight:bold;">
  <th width="50%">المؤشر</th><th width="50%">القيمة</th>
</tr>
<tr><td>إجمالي الطلبات (من الشيتات المرفوعة)</td><td align="center">' . (int) ($perf['total_orders'] ?? 0) . '</td></tr>
<tr><td>طلبات مؤكدة</td><td align="center">' . (int) ($perf['confirmed_orders'] ?? 0) . ' (' . $conf_r . '%)</td></tr>
<tr><td>طلبات مسلمة</td><td align="center">' . (int) ($perf['delivered_orders'] ?? 0) . ' (' . $del_r . '%)</td></tr>
<tr><td>طلبات ملغاة</td><td align="center">' . (int) ($perf['cancelled_orders'] ?? 0) . ' (' . $can_r . '%)</td></tr>
</table>

<h3 style="color:#1e40af;">مؤشرات الأداء</h3>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
<tr style="background-color:#f0fdf4;font-weight:bold;">
  <th width="50%">المؤشر</th><th width="50%">القيمة</th>
</tr>
<tr><td>نسبة التأكيد</td><td align="center">' . $conf_r . '%</td></tr>
<tr><td>نسبة التسليم</td><td align="center">' . $del_r . '%</td></tr>
<tr><td>نسبة الإلغاء</td><td align="center">' . $can_r . '%</td></tr>
<tr><td>مدة المكالمات (إجمالي)</td><td align="center">' . $h($call_duration_formatted) . '</td></tr>
<tr><td>التقييم الشخصي</td><td align="center">' . $rating . '/5</td></tr>
<tr><td>عدد الشيتات</td><td align="center">' . (int) $sheets_count . '</td></tr>
</table>

<h3 style="color:#1e40af;">توزيع نسب الأداء</h3>
<table border="1" cellpadding="5" cellspacing="0" width="100%">
<tr style="background-color:#fff7ed;font-weight:bold;">
  <th width="33%">تأكيد</th><th width="34%">تسليم</th><th width="33%">إلغاء</th>
</tr>
<tr>
  <td align="center">' . $conf_r . '%</td>
  <td align="center">' . $del_r . '%</td>
  <td align="center">' . $can_r . '%</td>
</tr>
</table>

<h3 style="color:#1e40af;">سجل المكالمات</h3>
<table border="1" cellpadding="4" cellspacing="0" width="100%">
<tr style="background-color:#ecfdf5;font-weight:bold;">
  <th width="22%">اسم العميل</th>
  <th width="20%">الهاتف</th>
  <th width="28%">وقت البدء</th>
  <th width="15%">المدة</th>
  <th width="15%">الحالة</th>
</tr>';

if (empty($calls)) {
    $html .= '<tr><td colspan="5" align="center">لا توجد مكالمات مسجلة في هذه الفترة</td></tr>';
} else {
    foreach ($calls as $call) {
        $st = (string) ($call['status'] ?? '');
        $html .= '<tr>';
        $html .= '<td>' . $h($call['client_name'] ?: '—') . '</td>';
        $html .= '<td>' . $h($call['client_phone'] ?: '—') . '</td>';
        $html .= '<td>' . $h($call['started_at'] ?? '') . '</td>';
        $html .= '<td align="center">' . $h($call['duration_formatted'] ?? '') . '</td>';
        $html .= '<td align="center">' . $h($status_call_ar[$st] ?? $st) . '</td>';
        $html .= '</tr>';
    }
}
$html .= '</table>';
$html .= '<p style="color:#64748b;font-size:10px;margin-top:10px;">* التقرير لا يتضمن محتويات الشيتات المرفوعة ولا جداول الطلبات الخاصة بالشيتات.</p>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($base . '.pdf', 'D');
exit;
