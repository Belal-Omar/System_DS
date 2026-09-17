<?php
/**
 * تصدير سجل التحصيلات — من السيرفر مباشرة (بدون مكتبات المتصفح)
 * debts_export.php?format=csv|pdf&scope=period&range=month&month=2026-04&year=2026&product=all
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function debts_export_fail($msg) {
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

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    debts_export_fail('يجب تسجيل الدخول أولاً.');
}

if (!$conn || !is_object($conn)) {
    debts_export_fail('تعذر الاتصال بقاعدة البيانات.');
}

if (function_exists('ensure_debts_schema')) {
    ensure_debts_schema($conn);
}

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv', 'pdf', 'excel'], true)) {
    $format = 'csv';
}
if ($format === 'excel') {
    $format = 'csv';
}

$scope = ($_GET['scope'] ?? 'period') === 'lifetime' ? 'lifetime' : 'period';
$range = ($_GET['range'] ?? 'month') === 'year' ? 'year' : 'month';
$selected_month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$selected_year = preg_match('/^\d{4}$/', (string) ($_GET['year'] ?? '')) ? $_GET['year'] : date('Y');
$selected_product = trim((string) ($_GET['product'] ?? 'all'));
if ($selected_product === '') {
    $selected_product = 'all';
}

[$start_date, $end_date] = get_accounts_period_dates($range, $selected_month, $selected_year);
$period_title = $range === 'year' ? ('سنة ' . $selected_year) : ('شهر ' . $selected_month);
$scope_label = $scope === 'lifetime' ? 'تراكمي — كل الفترات' : $period_title;

$has_pm = function_exists('ensure_debts_schema') ? ensure_debts_schema($conn) : false;
$pm_expr = function_exists('debts_period_month_sql')
    ? debts_period_month_sql('', $has_pm)
    : "DATE_FORMAT(entry_date, '%Y-%m')";

$sql = "SELECT entry_date, amount, note, $pm_expr AS apply_month
        FROM support_financial_records
        WHERE type = 'debtor'";
if ($scope === 'period') {
    $start_m = $conn->real_escape_string(substr($start_date, 0, 7));
    $end_m = $conn->real_escape_string(substr($end_date, 0, 7));
    $sql .= " AND $pm_expr BETWEEN '$start_m' AND '$end_m'";
}
$sql .= ' ORDER BY entry_date DESC, id DESC';

$res = $conn->query($sql);
if (!$res) {
    debts_export_fail('خطأ في جلب البيانات: ' . $conn->error);
}

$rows = [];
$total = 0.0;
while ($r = $res->fetch_assoc()) {
    $amt = (float) ($r['amount'] ?? 0);
    $total += $amt;
    $rows[] = [
        'entry_date' => (string) ($r['entry_date'] ?? ''),
        'apply_month' => (string) ($r['apply_month'] ?? ''),
        'note' => (string) ($r['note'] ?? 'تحصيل من شركة شحن'),
        'amount' => $amt,
    ];
}

$stamp = date('Y-m-d_His');
$base = 'collections_' . ($scope === 'lifetime' ? 'all' : str_replace('-', '', $selected_month)) . '_' . $stamp;

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
    fputcsv($out, ['تاريخ الاستلام', 'شهر الاستحقاق', 'البيان', 'المبلغ المستلم', 'الفترة'], ',');
    if (empty($rows)) {
        fputcsv($out, ['لا توجد تحصيلات في هذه الفترة', '', '', '', $scope_label], ',');
    } else {
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['entry_date'],
                $row['apply_month'],
                $row['note'],
                number_format($row['amount'], 2, '.', ''),
                $scope_label,
            ], ',');
        }
        fputcsv($out, ['', '', 'المجموع', number_format($total, 2, '.', ''), ''], ',');
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
    debts_export_fail('مكتبة TCPDF غير موجودة على السيرفر.');
}
require_once $tcpdf;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('سجل التحصيلات');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->setRTL(true);

$font = 'dejavusans';
if (method_exists($pdf, 'SetFont')) {
    // بعض النسخ تستخدم aefurat
    try {
        $pdf->SetFont('dejavusans', 'B', 16);
    } catch (Throwable $e) {
        $font = 'aefurat';
        $pdf->SetFont($font, 'B', 16);
    }
}

$pdf->Cell(0, 10, 'سجل تحصيلات شركات الشحن', 0, 1, 'C');
$pdf->SetFont($font, '', 11);
$pdf->Cell(0, 8, $scope_label, 0, 1, 'C');
$pdf->Ln(4);

$html = '<table border="1" cellpadding="5" cellspacing="0" width="100%">
<thead>
<tr style="background-color:#eef2ff;font-weight:bold;">
<th width="22%">تاريخ الاستلام</th>
<th width="18%">شهر الاستحقاق</th>
<th width="40%">البيان</th>
<th width="20%">المبلغ</th>
</tr>
</thead>
<tbody>';

if (empty($rows)) {
    $html .= '<tr><td colspan="4" align="center">لا توجد تحصيلات في هذه الفترة</td></tr>';
} else {
    foreach ($rows as $row) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['entry_date'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['apply_month'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td align="center">' . number_format($row['amount'], 2) . ' دل</td>';
        $html .= '</tr>';
    }
    $html .= '<tr style="font-weight:bold;background-color:#f0fdf4;">';
    $html .= '<td colspan="3" align="center">المجموع</td>';
    $html .= '<td align="center">' . number_format($total, 2) . ' دل</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table>';

$pdf->SetFont($font, '', 10);
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($base . '.pdf', 'D');
exit;
