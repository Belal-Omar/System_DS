<?php
/**
 * HTML بوليصة الشحن للطباعة المباشرة (raw=1)
 */
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

if (empty($_GET['raw'])) {
    header('Location: admin_panel.php?page=orders&view=' . $order_id);
    exit;
}

$body = order_shipping_label_render_html($label);
$styles = order_shipping_label_print_styles();

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title> </title><style>';
echo $styles;
echo '</style></head><body>';
echo $body;
echo '</body></html>';
