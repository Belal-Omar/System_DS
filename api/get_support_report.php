<?php
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");
session_start();

header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['admin_role'] ?? '';
$can_manage_support = ($role === 'super_admin' || (function_exists('admin_can_access_page') && admin_can_access_page('support', $role, $_SESSION['admin_allowed_pages'] ?? '')));
if (!$can_manage_support) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

if (!isset($_GET['support_id']) || !is_numeric($_GET['support_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف الموظف مطلوب']);
    exit;
}

$support_id = (int) $_GET['support_id'];
$period = $_GET['period'] ?? 'all';
$start_date = null;

switch ($period) {
    case '1day':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case '1week':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        break;
    case '1month':
        $start_date = date('Y-m-d', strtotime('-1 month'));
        break;
    case '3months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        break;
    case '9months':
        $start_date = date('Y-m-d', strtotime('-9 months'));
        break;
    case '1year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        break;
}

$perf = get_support_member_performance($conn, $support_id, $start_date);

$sheets_filter = $start_date ? " AND created_at >= '" . $conn->real_escape_string($start_date) . "'" : '';
$sheets_result = $conn->query("SELECT COUNT(*) as sheets_count FROM support_sheets WHERE support_id = $support_id $sheets_filter");
$sheets_data = $sheets_result ? $sheets_result->fetch_assoc() : ['sheets_count' => 0];
$sheets_count = (int) ($sheets_data['sheets_count'] ?? 0);
$total_call_seconds = support_get_total_call_seconds($conn, $support_id, $start_date);
$call_duration_formatted = format_activity_duration($total_call_seconds);

echo json_encode([
    'success' => true,
    'period' => $period,
    'period_label' => getPeriodLabel($period),
    'data' => [
        'total_orders' => $perf['total_orders'],
        'confirmed_orders' => $perf['confirmed_orders'],
        'delivered_orders' => $perf['delivered_orders'],
        'cancelled_orders' => $perf['cancelled_orders'],
        'confirmation_rate' => $perf['confirmation_rate'],
        'delivery_rate' => $perf['delivery_rate'],
        'cancellation_rate' => $perf['cancellation_rate'],
        'call_duration' => $total_call_seconds,
        'call_duration_formatted' => $call_duration_formatted,
        'customer_satisfaction' => $perf['personal_rating'],
        'sheets_count' => $sheets_count,
        'total_call_seconds' => $total_call_seconds,
    ],
]);

function getPeriodLabel($period) {
    $labels = [
        'all' => 'الكل',
        '1day' => 'يوم واحد',
        '1week' => 'أسبوع',
        '1month' => 'شهر',
        '3months' => '3 شهور',
        '6months' => '6 شهور',
        '9months' => '9 شهور',
        '1year' => 'سنة',
    ];
    return $labels[$period] ?? $period;
}
