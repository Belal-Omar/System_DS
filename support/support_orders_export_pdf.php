<?php
/**
 * تصدير طلبات شيت الدعم إلى PDF من السيرفر (TCPDF + عربي)
 * support_orders_export_pdf.php?sheet_id=1&all=1
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

session_start();
require_once __DIR__ . '/config.php';

function so_pdf_fail($msg) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>تصدير PDF</title>
    <link rel="icon" type="image/png" href="brand_logo.php?f=logo">
    <link rel="shortcut icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="apple-touch-icon" href="brand_logo.php?f=logo"></head>';
    echo '<body style="font-family:Tahoma;padding:40px;text-align:center">';
    echo '<h2>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<p><a href="javascript:history.back()">رجوع</a></p></body></html>';
    exit;
}

$role = $_SESSION['admin_role'] ?? '';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true
    || !in_array($role, ['super_admin', 'admin'], true)) {
    so_pdf_fail('غير مصرح.');
}
if (!$conn || !is_object($conn)) {
    so_pdf_fail('تعذر الاتصال بقاعدة البيانات.');
}

$sheet_id = isset($_GET['sheet_id']) ? (int) $_GET['sheet_id'] : -1;
$support_id = isset($_GET['support_id']) ? (int) $_GET['support_id'] : -1;
$conf_date = $_GET['conf_date'] ?? '';

if ($sheet_id < 0 && empty($conf_date)) {
    so_pdf_fail('معرّف الشيت غير صحيح.');
}
$export_all = isset($_GET['all']) && ($_GET['all'] === '1' || $_GET['all'] === 'true');

if ($export_all) {
    $where = '1=1';
} else {
    $where = "o.order_status IN ('order_confirmed','confirmed','received','delivered')";
}

if ($sheet_id >= 0) {
    if ($sheet_id > 0) {
        $where .= ' AND o.sheet_id = ' . $sheet_id;
    } else {
        $where .= ' AND (o.sheet_id IS NULL OR o.sheet_id = 0)';
    }
} elseif (!empty($conf_date) && $support_id > 0) {
    $where .= ' AND o.support_id = ' . $support_id;
    $where .= " AND DATE(COALESCE(o.confirmed_at, o.updated_at, o.created_at)) = '" . $conn->real_escape_string($conf_date) . "'";
}

$sql = "SELECT o.*, s.sheet_name
        FROM support_orders o
        LEFT JOIN support_sheets s ON s.id = o.sheet_id
        WHERE $where
        ORDER BY o.created_at ASC";
$res = $conn->query($sql);
if (!$res) {
    so_pdf_fail('خطأ استعلام: ' . $conn->error);
}

$status_ar = [
    'order_confirmed' => 'تم تأكيد الطلب',
    'confirmed' => 'تم تأكيد الطلب',
    'delivered' => 'تم التسليم',
    'cancelled' => 'ملغي',
    'returned' => 'مرتجع',
    'pending' => 'قيد الانتظار',
];

$rows = [];
$sheet_name = 'شيت';
while ($r = $res->fetch_assoc()) {
    if (!empty($r['sheet_name'])) {
        $sheet_name = $r['sheet_name'];
    }
    $dt = $r['order_date'] ?? (substr((string) ($r['created_at'] ?? ''), 0, 10) ?: '');
    $name = ($r['recipient_name'] !== null && $r['recipient_name'] !== '')
        ? $r['recipient_name']
        : ($r['customer_name'] ?? '');
    $st = $status_ar[$r['order_status'] ?? ''] ?? ($r['order_status'] ?? '');
    $rows[] = [
        'date' => $dt,
        'name' => $name,
        'phone' => (string) ($r['phone'] ?? ''),
        'status' => $st,
        'notes' => (string) ($r['notes'] ?? ''),
        'gov' => (string) ($r['governorate'] ?? ''),
        'address' => (string) ($r['address'] ?? ''),
        'pieces' => (string) ($r['pieces'] ?? $r['quantity'] ?? 1),
        'price' => (string) ($r['total_price'] ?? ''),
    ];
}

$tcpdf = __DIR__ . '/TCPDF-main/tcpdf.php';
if (!is_readable($tcpdf)) {
    $tcpdf = __DIR__ . '/tcpdf/tcpdf.php';
}
if (!is_readable($tcpdf)) {
    so_pdf_fail('مكتبة TCPDF غير موجودة.');
}
require_once $tcpdf;

while (ob_get_level()) {
    ob_end_clean();
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('System');
$pdf->SetTitle('طلبات الشيت');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();
$pdf->setRTL(true);

$font = 'dejavusans';
try {
    $pdf->SetFont($font, 'B', 14);
} catch (Throwable $e) {
    $font = 'aefurat';
    $pdf->SetFont($font, 'B', 14);
}

$title = ($export_all ? 'كل الطلبات — ' : 'الطلبات المؤكدة — ') . $sheet_name;
$pdf->Cell(0, 9, $title, 0, 1, 'C');
$pdf->SetFont($font, '', 9);
$pdf->Cell(0, 6, 'عدد الصفوف: ' . count($rows) . ' | التاريخ: ' . date('Y-m-d H:i'), 0, 1, 'C');
$pdf->Ln(2);

$html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">
<thead><tr style="background-color:#e2e8f0;font-weight:bold;font-size:8pt;">
<th width="4%">#</th>
<th width="9%">التاريخ</th>
<th width="12%">المستلم</th>
<th width="10%">الهاتف</th>
<th width="10%">الحالة</th>
<th width="14%">ملاحظات</th>
<th width="8%">المحافظة</th>
<th width="16%">العنوان</th>
<th width="5%">قطع</th>
<th width="12%">السعر</th>
</tr></thead><tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="10" align="center">لا توجد طلبات</td></tr>';
} else {
    $i = 1;
    foreach ($rows as $row) {
        $html .= '<tr style="font-size:7.5pt;">';
        $html .= '<td align="center">' . $i++ . '</td>';
        foreach (['date', 'name', 'phone', 'status', 'notes', 'gov', 'address', 'pieces', 'price'] as $k) {
            $align = in_array($k, ['phone', 'pieces', 'price'], true) ? 'center' : 'right';
            $html .= '<td align="' . $align . '">' . htmlspecialchars($row[$k], ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }
}
$html .= '</tbody></table>';

$pdf->SetFont($font, '', 8);
$pdf->writeHTML($html, true, false, true, false, '');

$prefix = $export_all ? 'all' : 'confirmed';
$fname = $prefix . '_sheet_' . ($sheet_id ?: '0') . '_' . date('Y-m-d_His') . '.pdf';
$pdf->Output($fname, 'D');
exit;
