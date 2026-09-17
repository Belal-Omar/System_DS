<?php
/**
 * تعيين / إلغاء «تم الاستلام» من مدير الدعم - قبل أي HTML
 */
if (!isset($conn) || !is_object($conn) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$is_mark = isset($_POST['mark_order_received']);
$is_unmark = isset($_POST['unmark_order_received']);
if (!$is_mark && !$is_unmark) {
    return;
}

$is_ajax = !empty($_POST['ajax']);
$back = (int) ($_POST['support_member_id'] ?? 0);
$redirect = 'admin_panel.php?page=support_detail&id=' . max(0, $back);

function finish_received_action($is_ajax, $success, $message, $redirect) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    } else {
        if ($success) {
            $_SESSION['support_detail_flash_success'] = $message;
        } else {
            $_SESSION['support_detail_flash_error'] = $message;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

if (!function_exists('can_mark_order_as_received') || !can_mark_order_as_received()) {
    finish_received_action($is_ajax, false, 'ليس لديك الصلاحية لتعديل حالة «تم الاستلام».', $redirect);
}

$order_id = (int) ($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    finish_received_action($is_ajax, false, 'معرّف الطلب غير صالح.', $redirect);
}

$row = $conn->query("SELECT id, support_id, sheet_id, order_status FROM support_orders WHERE id = $order_id LIMIT 1");
if (!$row || !($order = $row->fetch_assoc())) {
    finish_received_action($is_ajax, false, 'الطلب غير موجود.', $redirect);
}

$support_id = (int) $order['support_id'];
if ($back <= 0 && $support_id > 0) {
    $redirect = 'admin_panel.php?page=support_detail&id=' . $support_id;
}

$current = (string) ($order['order_status'] ?? '');

if ($is_mark) {
    if (in_array($current, ['received', 'delivered'], true)) {
        finish_received_action($is_ajax, true, 'الطلب معين بالفعل على «تم الاستلام».', $redirect);
    }
    $ok = $conn->query("UPDATE support_orders SET order_status = 'received', received_at = NOW() WHERE id = $order_id LIMIT 1");
    if (!$ok) {
        finish_received_action($is_ajax, false, 'فشل تحديث الحالة: ' . $conn->error, $redirect);
    }
    if (function_exists('sync_support_stats') && $support_id > 0) {
        @sync_support_stats($conn, $support_id);
    }
    finish_received_action($is_ajax, true, 'تم تعيين الطلب #' . $order_id . ' على «تم الاستلام».', $redirect);
}

// إلغاء تعيين تم الاستلام (الرجوع لتأكيد الطلب)
if (!in_array($current, ['received', 'delivered'], true)) {
    finish_received_action($is_ajax, false, 'الطلب ليس بحالة تم الاستلام.', $redirect);
}

$ok = $conn->query("UPDATE support_orders SET order_status = 'order_confirmed' WHERE id = $order_id LIMIT 1");
if (!$ok) {
    finish_received_action($is_ajax, false, 'فشل إلغاء «تم الاستلام»: ' . $conn->error, $redirect);
}
if (function_exists('sync_support_stats') && $support_id > 0) {
    @sync_support_stats($conn, $support_id);
}
finish_received_action($is_ajax, true, 'تم إلغاء «تم الاستلام» للطلب #' . $order_id . ' وإعادته إلى حالة تأكيد الطلب.', $redirect);
