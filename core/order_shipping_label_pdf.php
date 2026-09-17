<?php
/**
 * بوليصة الشحن PDF — طباعة نظيفة بدون رابط المتصفح
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/order_shipping_label_shared.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('غير مصرح');
}

if (!$conn || !is_object($conn)) {
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات');
}

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$label = order_shipping_label_load($conn, $order_id);
if (!$label) {
    http_response_code(404);
    exit('طلب غير موجود');
}

$tcpdf = __DIR__ . '/TCPDF-main/tcpdf.php';
if (!is_readable($tcpdf)) {
    $tcpdf = __DIR__ . '/tcpdf/tcpdf.php';
}
if (!is_readable($tcpdf)) {
    http_response_code(500);
    exit('مكتبة TCPDF غير موجودة');
}

try {
    require_once $tcpdf;

    while (ob_get_level()) {
        ob_end_clean();
    }

    $order = $label['order'];
    $h = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };

    $pdf = new TCPDF('L', 'mm', [150, 105], true, 'UTF-8', false);
    $pdf->SetCreator(' ');
    $pdf->SetAuthor(' ');
    $pdf->SetTitle(' ');
    $pdf->SetSubject(' ');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(4, 4, 4);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->setRTL(true);

    $font = 'dejavusans';
    try {
        $pdf->SetFont($font, 'B', 8);
    } catch (Throwable $e) {
        $font = 'aefurat';
        $pdf->SetFont($font, 'B', 8);
    }

    $logo_html = '';
    if (!empty($label['logo_file'])) {
        $logo_html = '<img src="' . str_replace('\\', '/', $label['logo_file']) . '" style="height:12mm;width:auto;" />';
    }

    $contacts = 'التاريخ: ' . $h($label['date']);
    if ($label['company_phone'] !== '—') {
        $contacts = 'الإدارة: ' . $h($label['company_phone']) . ' | ' . $contacts;
    }

    $barcode_digits_pdf = '<table style="width:100%;height:52mm;border-collapse:collapse;table-layout:fixed;">';
    foreach (str_split((string) $label['barcode']) as $digit) {
        $barcode_digits_pdf .= '<tr><td style="height:6.5mm;text-align:center;vertical-align:middle;font-size:9pt;font-weight:bold;line-height:1;padding:0;">' . $h($digit) . '</td></tr>';
    }
    $barcode_digits_pdf .= '</table>';

    $barcode_img_html = '';
    if (!empty($label['barcode_image'])) {
        $barcode_img_html = '<img src="' . $label['barcode_image'] . '" style="max-height:52mm;max-width:100%;width:auto;height:auto;display:block;margin:0 auto;" />';
    }

    $html = '
<style>
    .wrap { border: 1px solid #000; border-radius: 3mm; padding: 2mm; font-weight: bold; }
    table.main { width: 100%; border-collapse: collapse; font-weight: bold; }
    td { vertical-align: top; font-weight: bold; }
    .box { border: 1px solid #000; border-radius: 2mm; font-weight: bold; }
    .info { width: 100%; border-collapse: collapse; font-size: 8pt; font-weight: bold; }
    .info td { border: 0.5px solid #000; padding: 1.2mm; font-weight: bold; }
    .lbl { font-weight: bold; width: 24%; text-align: center; }
    .title { font-size: 11pt; font-weight: bold; }
    .small { font-size: 8pt; line-height: 1.45; font-weight: bold; }
    .center { text-align: center; font-weight: bold; }
    .bold { font-weight: bold; }
</style>
<div class="wrap">
<table class="main"><tr>
<td style="width:32%;vertical-align:top;">
    <div class="box" style="padding:1.5mm;height:60mm;overflow:hidden;">
        <table style="width:100%;height:57mm;border-collapse:collapse;table-layout:fixed;">
            <tr>
                <td style="width:76%;text-align:center;vertical-align:middle;padding:0;overflow:hidden;">' . $barcode_img_html . '</td>
                <td style="width:24%;text-align:center;vertical-align:middle;padding:0 0.5mm;overflow:hidden;">' . $barcode_digits_pdf . '</td>
            </tr>
        </table>
    </div>
</td>
<td style="width:68%;padding-right:2mm;">
    <table class="main"><tr>
        <td style="width:20mm;">' . $logo_html . '</td>
        <td><div class="title">شركة توصيل ' . $h($label['company']) . '</div></td>
    </tr></table>
    <hr style="border:0.5px solid #000;margin:1.5mm 0;" />
    <table class="main" style="margin-bottom:1.5mm;"><tr>
        <td class="box center bold" style="padding:1.5mm;">تسليم كامل الطرد</td>
        <td style="width:2mm;"></td>
        <td class="box center bold" style="padding:1.5mm;width:30mm;">القيمة: ' . $h($label['total']) . '</td>
    </tr></table>
    <table class="info">
        <tr><td class="lbl">المستلم</td><td>' . $h($order['customer_name']) . '</td></tr>
        <tr><td class="lbl">الهاتف</td><td>' . $h($order['customer_phone']) . '</td></tr>
        <tr><td class="lbl">التاريخ</td><td class="bold">' . $h($label['date']) . '</td></tr>
        <tr><td class="lbl">الوجهة</td><td>' . $h($label['destination']) . '</td></tr>
        <tr><td class="lbl">اسم المتجر</td><td>' . $h($label['store']) . '</td></tr>
        <tr><td class="lbl">هاتف المتجر</td><td>' . $h($label['store_phone']) . '</td></tr>
    </table>
    <div class="box" style="margin-top:1.5mm;padding:1mm;min-height:7mm;"><span class="bold">ملاحظات</span></div>
</td>
</tr></table>
<table class="main" style="margin-top:1.5mm;"><tr>
<td style="width:32%;padding-left:1mm;">
    <div class="box small" style="padding:3mm;margin-bottom:1.5mm;line-height:1.55;min-height:34mm;font-size:8.5pt;">
        <div class="bold center" style="font-size:10pt;margin-bottom:1.5mm;">خدمتنا</div>
        1- التخزين حيث نوفر مساحه تخزين أمنه<br>
        2- خدمة تجميع الطلبات حيث نوفرها من أماكن متعددة<br>
        3- خدمة تغليف الطلبات عالية الجودة
    </div>
    <div class="box small" style="padding:2mm;min-height:18mm;font-weight:bold;">
        <div class="bold">الوصف</div>
    </div>
</td>
<td style="width:70%;padding-right:1mm;">
    <div class="box center bold" style="padding:2mm;">ملاحظة نحن شركة توصيل فقط ليس لدينا علاقة بالمنتجات</div>
    <div class="small" style="margin-top:2mm;">' . $h($contacts) . '</div>
</td>
</tr></table>
</div>';

    $pdf->writeHTML($html, true, false, true, false, '');

    if (!empty($_GET['auto'])) {
        $pdf->IncludeJS('print(true);');
    }

    $filename = 'label-' . (int) $order['id'] . '.pdf';
    $pdf->Output($filename, 'I');
    exit;
} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('order_shipping_label_pdf.php: ' . $e->getMessage());
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>خطأ</title></head><body style="font-family:Tahoma;padding:40px;text-align:center">';
    echo '<h2>تعذر إنشاء بوليصة الشحن</h2>';
    echo '<p><a href="admin_panel.php?page=orders&view=' . (int) $order_id . '">رجوع لتفاصيل الطلب</a></p>';
    echo '</body></html>';
    exit;
}
