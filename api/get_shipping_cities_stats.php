<?php
session_start();
include(__DIR__ . '/core/config.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header
header('Content-Type: application/json');

// Debug: Log session info
error_log("Session data: " . print_r($_SESSION, true));

// التحقق من تسجيل الدخول ونوع المستخدم
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session");
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول - لا يوجد جلسة']);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("User ID: $user_id");

// Check database connection
if (!$conn) {
    error_log("Database connection failed");
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات']);
    exit;
}

$user_query = $conn->query("SELECT user_type FROM users WHERE id = $user_id");
if (!$user_query) {
    error_log("User query failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'فشل جلب بيانات المستخدم']);
    exit;
}

$user = $user_query->fetch_assoc();
error_log("User data: " . print_r($user, true));

if (!$user || $user['user_type'] !== 'مسوق') {
    error_log("User not marketer or not found");
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول للمسوقين فقط']);
    exit;
}

// جلب إحصائيات مدن الشحن للمسوق
$shipping_cities_stats = [];
$total_orders = 0;
$total_revenue = 0;

// جلب مدن الشحن مع إحصائياتها للمسوق
$cities_query = $conn->query("
    SELECT 
        sc.id,
        sc.city_name,
        sc.shipping_cost,
        COUNT(DISTINCT o.id) as orders_count,
        COALESCE(SUM(o.total), 0) as total_revenue,
        COALESCE(SUM(o.shipping_cost), 0) as total_shipping_cost,
        COALESCE(SUM(o.total + o.shipping_cost), 0) as grand_total,
        COUNT(DISTINCT CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN o.id END) as completed_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'ملغي' OR o.status = 'مرفوض' THEN o.id END) as cancelled_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'مرتجع' THEN o.id END) as returned_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'قيد الانتظار' THEN o.id END) as pending_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'تم التأكيد' THEN o.id END) as confirmed_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'قيد التنفيذ' THEN o.id END) as processing_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'في الشحن' THEN o.id END) as shipping_orders,
        AVG(o.total) as avg_order_value,
        MIN(o.created_at) as first_order_date,
        MAX(o.created_at) as last_order_date,
        COUNT(DISTINCT o.customer_name) as unique_customers,
        COALESCE(SUM(o.commission_total), 0) as total_commission,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN o.total ELSE 0 END), 0) as completed_revenue
    FROM shipping_cities sc
    LEFT JOIN orders o ON sc.id = o.shipping_city_id
    WHERE o.user_id = $user_id OR o.shipping_city_id IS NULL
    GROUP BY sc.id, sc.city_name, sc.shipping_cost
    HAVING orders_count > 0
    ORDER BY orders_count DESC, grand_total DESC
");

if (!$cities_query) {
    error_log("Cities query failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'فشل جلب بيانات المدن']);
    exit;
}

error_log("Cities query executed successfully");

if ($cities_query) {
    while ($city = $cities_query->fetch_assoc()) {
        $shipping_cities_stats[] = $city;
        $total_orders += $city['orders_count'];
        $total_revenue += $city['grand_total'];
    }
}

// حساب إجمالي الطلبات الصحيح للمسوق
$direct_total_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as direct_total_orders
    FROM orders o
    WHERE o.user_id = $user_id
");

$direct_total_orders = 0;
if ($direct_total_orders_query) {
    $direct_result = $direct_total_orders_query->fetch_assoc();
    $direct_total_orders = isset($direct_result['direct_total_orders']) ? (int)$direct_result['direct_total_orders'] : 0;
}

// حساب إجمالي الإيرادات المكتملة الصحيح للمسوق (مثل orders.html بدون عمولة)
$direct_completed_revenue_query = $conn->query("
    SELECT COALESCE(SUM(o.total), 0) as direct_completed_revenue
    FROM orders o
    WHERE o.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");

$direct_completed_revenue = 0;
if ($direct_completed_revenue_query) {
    $revenue_result = $direct_completed_revenue_query->fetch_assoc();
    $direct_completed_revenue = isset($revenue_result['direct_completed_revenue']) ? (float)$revenue_result['direct_completed_revenue'] : 0;
}

// حساب إجمالي الطلبات المكتملة الصحيح للمسوق
$direct_completed_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as direct_completed_orders
    FROM orders o
    WHERE o.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");

$direct_completed_orders = 0;
if ($direct_completed_orders_query) {
    $completed_result = $direct_completed_orders_query->fetch_assoc();
    $direct_completed_orders = isset($completed_result['direct_completed_orders']) ? (int)$completed_result['direct_completed_orders'] : 0;
}

// استخدام الأرقام المباشرة بدلاً من المجموع
$total_orders = $direct_total_orders;
$total_revenue = $direct_completed_revenue;

// للتصحيح - طباعة القيم
error_log("Direct values: total_orders=$direct_total_orders, completed_orders=$direct_completed_orders, completed_revenue=$direct_completed_revenue (بدون عمولة)");

// إحصائيات إضافية للمسوق
$marketer_stats = [
    'total_cities' => count($shipping_cities_stats),
    'total_orders' => $total_orders,
    'total_revenue' => $total_revenue,
    'direct_total_orders' => $direct_total_orders,
    'direct_completed_orders' => $direct_completed_orders,
    'direct_completed_revenue' => $direct_completed_revenue,
    'total_commission' => array_sum(array_column($shipping_cities_stats, 'total_commission')),
    'avg_order_value' => $direct_completed_orders > 0 ? $total_revenue / $direct_completed_orders : 0,
    'top_city' => !empty($shipping_cities_stats) ? $shipping_cities_stats[0]['city_name'] : null,
    'top_city_orders' => !empty($shipping_cities_stats) ? $shipping_cities_stats[0]['orders_count'] : 0
];

// إحصائيات الحالات
$status_stats = [
    'pending' => array_sum(array_column($shipping_cities_stats, 'pending_orders')),
    'confirmed' => array_sum(array_column($shipping_cities_stats, 'confirmed_orders')),
    'processing' => array_sum(array_column($shipping_cities_stats, 'processing_orders')),
    'shipping' => array_sum(array_column($shipping_cities_stats, 'shipping_orders')),
    'completed' => array_sum(array_column($shipping_cities_stats, 'completed_orders')),
    'cancelled' => array_sum(array_column($shipping_cities_stats, 'cancelled_orders')),
    'returned' => array_sum(array_column($shipping_cities_stats, 'returned_orders'))
];

header('Content-Type: application/json');

try {
    echo json_encode([
        'success' => true,
        'message' => 'تم جلب إحصائيات المدن بنجاح',
        'cities' => $shipping_cities_stats,
        'marketer_stats' => $marketer_stats,
        'status_stats' => $status_stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("JSON encode error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ في تجهيز البيانات'
    ]);
}
?>
