<?php
/**
 * بيانات بوليصة الشحن المشتركة بين PDF والطباعة
 */
if (!function_exists('order_shipping_label_load')) {

    function order_shipping_label_barcode_bootstrap() {
        static $loaded = false;
        if ($loaded) {
            return true;
        }
        $paths = [
            __DIR__ . '/TCPDF-main/tcpdf_barcodes_1d.php',
            __DIR__ . '/tcpdf/tcpdf_barcodes_1d.php',
        ];
        foreach ($paths as $path) {
            if (is_readable($path)) {
                require_once $path;
                $loaded = true;
                return true;
            }
        }
        return false;
    }

    function order_shipping_label_barcode_vertical_svg($code) {
        if (!order_shipping_label_barcode_bootstrap() || !class_exists('TCPDFBarcode')) {
            return '';
        }

        try {
            $obj = new TCPDFBarcode((string) $code, 'C128C');
            $arr = $obj->getBarcodeArray();
            if (empty($arr['bcode'])) {
                return '';
            }

            $barW = 2.2;
            $barH = 56;
            $origW = (float) round($arr['maxw'] * $barW, 2);
            $origH = (float) $barH;
            $canvasW = $origH;
            $canvasH = $origW;

            $maxH = 195;
            $maxW = 88;
            $scale = min($maxH / $canvasH, $maxW / $canvasW, 1.0);
            $displayW = round($canvasW * $scale, 2);
            $displayH = round($canvasH * $scale, 2);

            $svg = '<svg class="sl-bc-svg" xmlns="http://www.w3.org/2000/svg" '
                . 'width="' . $displayW . '" height="' . $displayH . '" '
                . 'viewBox="0 0 ' . $canvasW . ' ' . $canvasH . '">';
            $svg .= '<rect width="' . $canvasW . '" height="' . $canvasH . '" fill="#ffffff"/>';
            $svg .= '<g transform="translate(' . $canvasW . ',0) rotate(90)">';
            $svg .= '<g fill="#000000">';

            $x = 0.0;
            foreach ($arr['bcode'] as $v) {
                $bw = round($v['w'] * $barW, 2);
                if (!empty($v['t'])) {
                    $bh = round($v['h'] * $barH / $arr['maxh'], 2);
                    $y = round($v['p'] * $barH / $arr['maxh'], 2);
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . $bh . '"/>';
                }
                $x += $bw;
            }

            $svg .= '</g></g></svg>';
            return $svg;
        } catch (Throwable $e) {
            return '';
        }
    }

    function order_shipping_label_barcode_vertical_data_uri($code) {
        $svg = order_shipping_label_barcode_vertical_svg($code);
        return $svg !== '' ? 'data:image/svg+xml;base64,' . base64_encode($svg) : '';
    }

    /** أرقام الباركود موزعة بمسافات متساوية على كامل ارتفاع العواميد */
    function order_shipping_label_barcode_digits_vertical_html($barcode, $h) {
        $digits = str_split((string) $barcode);
        $html = '<table class="sl-bc-digits-table" cellspacing="0" cellpadding="0"><tbody>';
        foreach ($digits as $digit) {
            $html .= '<tr><td class="sl-bc-digit">' . $h($digit) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    function order_shipping_label_load($conn, $order_id) {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return null;
        }

        $order_query = $conn->query("
            SELECT o.*,
                   COALESCE(sc.city_name, o.shipping_city_name, o.shipping_city) AS shipping_city_label,
                   scmp.name AS shipping_company_name,
                   scmp.phone AS shipping_company_phone,
                   u.fullname AS store_name,
                   u.phone AS store_phone
            FROM orders o
            LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
            LEFT JOIN shipping_companies scmp ON o.shipping_company_id = scmp.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = $order_id
        ");
        if (!$order_query) {
            $order_query = $conn->query("SELECT o.* FROM orders o WHERE o.id = $order_id");
        }
        $order = $order_query ? $order_query->fetch_assoc() : null;
        if (!$order) {
            return null;
        }

        $items_query = $conn->query("
            SELECT oi.*, p.name AS product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = $order_id
        ");
        $order_items = $items_query ? $items_query->fetch_all(MYSQLI_ASSOC) : [];

        $logo_file = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png';
        $label_company = trim($order['shipping_company_name'] ?? $order['shipping_company'] ?? '') ?: 'Miskova Global';
        $label_destination_parts = array_filter([
            trim($order['shipping_city_label'] ?? ''),
            trim($order['region'] ?? ''),
            trim($order['address'] ?? ''),
        ], static function ($part) {
            return $part !== '';
        });

        $barcode = (string) random_int(10000000, 99999999);

        return [
            'order' => $order,
            'order_items' => $order_items,
            'logo_file' => is_readable($logo_file) ? $logo_file : null,
            'logo_url' => function_exists('system_brand_logo_url') ? system_brand_logo_url() : 'brand_logo.php?f=logo',
            'company' => $label_company,
            'destination' => implode(' - ', $label_destination_parts) ?: '—',
            'date' => date('Y-m-d', strtotime($order['created_at'])),
            'total' => number_format((float) $order['total'], 0),
            'store' => 'Miskova Global',
            'store_phone' => trim($order['store_phone'] ?? '') ?: '—',
            'barcode' => $barcode,
            'barcode_display' => implode(' ', str_split($barcode)),
            'barcode_svg' => order_shipping_label_barcode_vertical_svg($barcode),
            'barcode_image' => order_shipping_label_barcode_vertical_data_uri($barcode),
            'company_phone' => trim($order['shipping_company_phone'] ?? '') ?: '—',
            'products_desc' => '',
        ];
    }

    function order_shipping_label_render_html($label) {
        $order = $label['order'];
        $h = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $contacts = 'التاريخ: ' . $h($label['date']);
        if ($label['company_phone'] !== '—') {
            $contacts = 'الإدارة: ' . $h($label['company_phone']) . ' | ' . $contacts;
        }
        ob_start();
        ?>
<div class="label-page" dir="rtl">
    <div class="sl-top-row">
        <div class="sl-barcode-col">
            <div class="sl-barcode-box">
                <table class="sl-barcode-table" dir="ltr" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="sl-bc-bars">
                            <?php if (!empty($label['barcode_svg'])): ?>
                            <?= $label['barcode_svg'] ?>
                            <?php endif; ?>
                        </td>
                        <td class="sl-bc-digits">
                            <?= order_shipping_label_barcode_digits_vertical_html($label['barcode'], $h) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="sl-header-col">
            <div class="sl-brand-row">
                <img src="<?= $h($label['logo_url']) ?>" alt="" class="sl-logo">
                <div class="sl-company-name">شركة توصيل <?= $h($label['company']) ?></div>
            </div>
            <div class="sl-divider"></div>
            <div class="sl-status-row">
                <div class="sl-status-box">تسليم كامل الطرد</div>
                <div class="sl-value-box">القيمة: <?= $h($label['total']) ?></div>
            </div>
            <div class="sl-info-table">
                <div class="sl-info-row"><div class="sl-info-label">المستلم</div><div class="sl-info-value"><?= $h($order['customer_name']) ?></div></div>
                <div class="sl-info-row"><div class="sl-info-label">الهاتف</div><div class="sl-info-value"><?= $h($order['customer_phone']) ?></div></div>
                <div class="sl-info-row sl-date-row"><div class="sl-info-label">التاريخ</div><div class="sl-info-value"><?= $h($label['date']) ?></div></div>
                <div class="sl-info-row"><div class="sl-info-label">الوجهة</div><div class="sl-info-value"><?= $h($label['destination']) ?></div></div>
                <div class="sl-info-row"><div class="sl-info-label">اسم المتجر</div><div class="sl-info-value"><?= $h($label['store']) ?></div></div>
                <div class="sl-info-row"><div class="sl-info-label">هاتف المتجر</div><div class="sl-info-value"><?= $h($label['store_phone']) ?></div></div>
            </div>
            <div class="sl-notes-box"><div class="sl-notes-title">ملاحظات</div></div>
        </div>
    </div>
    <div class="sl-bottom-row">
        <div class="sl-left-col">
            <div class="sl-services">
                <div class="sl-services-title">خدمتنا</div>
                <div class="sl-services-list">
                    <div>1- التخزين حيث نوفر مساحه تخزين أمنه</div>
                    <div>2- خدمة تجميع الطلبات حيث نوفرها من أماكن متعددة</div>
                    <div>3- خدمة تغليف الطلبات عالية الجودة</div>
                </div>
            </div>
            <div class="sl-desc-box">
                <div class="sl-desc-title">الوصف</div>
                <div class="sl-desc-content"></div>
            </div>
        </div>
        <div class="sl-right-col">
            <div class="sl-disclaimer">ملاحظة نحن شركة توصيل فقط ليس لدينا علاقة بالمنتجات</div>
            <div class="sl-contacts"><?= $h($contacts) ?></div>
        </div>
    </div>
</div>
        <?php
        return ob_get_clean();
    }

    function order_shipping_label_print_styles() {
        return <<<'CSS'
@page { margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { margin: 0; padding: 0; background: #fff; color: #000; font-family: Arial, Tahoma, sans-serif; font-weight: bold; }
.label-page, .label-page * { font-weight: bold !important; }
.label-page { width: 15cm; margin: 0 auto; background: #fff; border: 2px solid #000; border-radius: 12px; padding: 6px; page-break-inside: avoid; break-inside: avoid; }
.sl-top-row { display: flex; gap: 5px; align-items: stretch; }
.sl-barcode-col { width: 32%; flex-shrink: 0; display: flex; }
.sl-header-col { flex: 1; min-width: 0; }
.sl-barcode-box {
    border: 1.5px solid #000;
    border-radius: 6px;
    width: 100%;
    min-height: 215px;
    padding: 6px 5px;
    background: #fff;
    overflow: hidden;
}
.sl-barcode-table {
    width: 100%;
    height: 203px;
    border-collapse: collapse;
    table-layout: fixed;
}
.sl-barcode-table > tbody > tr > td {
    vertical-align: middle;
    padding: 0;
    height: 203px;
}
.sl-bc-bars {
    width: 76%;
    text-align: center;
}
.sl-bc-bars .sl-bc-svg {
    display: block;
    margin: 0 auto;
    max-height: 195px;
    max-width: 100%;
    height: auto;
    width: auto;
}
.sl-bc-digits {
    width: 24%;
    text-align: center;
    vertical-align: middle;
}
.sl-bc-digits-table {
    width: 100%;
    height: 195px;
    border-collapse: collapse;
    table-layout: fixed;
    margin: 0 auto;
}
.sl-bc-digits-table tr {
    height: 12.5%;
}
.sl-bc-digit {
    height: 12.5%;
    text-align: center;
    vertical-align: middle;
    font-size: 12px;
    font-weight: bold;
    line-height: 1;
    padding: 0;
}
.sl-brand-row { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
.sl-logo { max-width: 55px; max-height: 38px; object-fit: contain; }
.sl-company-name { font-size: 13px; font-weight: bold; line-height: 1.2; }
.sl-divider { border-top: 1.5px solid #000; margin: 3px 0 4px; }
.sl-status-row { display: flex; gap: 4px; margin-bottom: 4px; }
.sl-status-box, .sl-value-box { border: 1.5px solid #000; border-radius: 6px; padding: 3px 6px; font-weight: bold; text-align: center; font-size: 11px; }
.sl-status-box { flex: 1; }
.sl-value-box { min-width: 70px; }
.sl-info-table { border: 1.5px solid #000; border-radius: 6px; overflow: hidden; margin-bottom: 4px; }
.sl-info-row { display: flex; border-bottom: 1px solid #000; min-height: 19px; }
.sl-info-row:last-child { border-bottom: none; }
.sl-info-label { width: 28%; border-left: 1px solid #000; padding: 2px 4px; font-weight: bold; font-size: 10px; display: flex; align-items: center; justify-content: center; text-align: center; }
.sl-info-value { flex: 1; padding: 2px 6px; font-size: 10px; font-weight: bold; display: flex; align-items: center; word-break: break-word; }
.sl-info-row.sl-date-row .sl-info-value { font-weight: bold; font-size: 11px; }
.sl-notes-box { border: 1.5px solid #000; border-radius: 6px; min-height: 28px; padding: 2px 5px; }
.sl-notes-title { font-weight: bold; font-size: 10px; }
.sl-bottom-row { display: flex; gap: 5px; margin-top: 4px; align-items: stretch; }
.sl-left-col { width: 32%; flex-shrink: 0; display: flex; flex-direction: column; gap: 5px; }
.sl-right-col { flex: 1; display: flex; flex-direction: column; justify-content: space-between; gap: 4px; }
.sl-services { border: 1.5px solid #000; border-radius: 6px; padding: 10px 8px 12px; font-size: 9.5px; line-height: 1.55; min-height: 105px; flex: 1; }
.sl-services-title { font-weight: bold; font-size: 12px; margin-bottom: 6px; text-align: center; }
.sl-services-list { display: flex; flex-direction: column; gap: 4px; }
.sl-services-list div { text-align: right; word-break: break-word; font-weight: bold; }
.sl-desc-box { border: 1.5px solid #000; border-radius: 6px; min-height: 52px; padding: 5px 6px; }
.sl-desc-title { font-weight: bold; font-size: 10px; margin-bottom: 2px; }
.sl-desc-content { font-size: 9px; line-height: 1.25; word-break: break-word; font-weight: bold; }
.sl-disclaimer { border: 1.5px solid #000; border-radius: 6px; padding: 5px; font-weight: bold; font-size: 10px; text-align: center; line-height: 1.35; }
.sl-contacts { font-size: 8px; font-weight: bold; display: flex; flex-wrap: wrap; gap: 8px; }
CSS;
    }
}
