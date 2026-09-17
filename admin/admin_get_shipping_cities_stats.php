<?php
// ملف: admin_get_shipping_cities_stats.php
// API لجلب إحصائيات مدن الشحن للأدمن
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    include(__DIR__ . '/core/config.php");
    include(__DIR__ . '/core/helpers.php");
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
        exit;
    }

    // جلب إحصائيات مدن الشحن للأدمن
    $shipping_cities_stats = [];
    $total_orders = 0;
    $total_revenue = 0;

    // جلب مدن الشحن مع إحصائياتها الكاملة
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
            COUNT(DISTINCT u.fullname) as unique_marketers,
            COALESCE(SUM(o.commission_total), 0) as total_commission,
            COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN o.total ELSE 0 END), 0) as completed_revenue
        FROM shipping_cities sc
        LEFT JOIN orders o ON sc.id = o.shipping_city_id
        LEFT JOIN users u ON o.user_id = u.id
        GROUP BY sc.id, sc.city_name, sc.shipping_cost
        HAVING orders_count > 0
        ORDER BY orders_count DESC, grand_total DESC
    ");

    if (!$cities_query) {
        error_log("Cities query failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'فشل في جلب البيانات: ' . $conn->error]);
        exit;
    }

    if ($cities_query) {
        while ($city = $cities_query->fetch_assoc()) {
            $shipping_cities_stats[] = $city;
            $total_orders += $city['orders_count'];
            $total_revenue += $city['grand_total'];
        }
    }

    // حساب إجمالي الطلبات الصحيح للأدمن
    $direct_total_orders_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as direct_total_orders
        FROM orders o
    ");

    $direct_total_orders = 0;
    if ($direct_total_orders_query) {
        $direct_result = $direct_total_orders_query->fetch_assoc();
        $direct_total_orders = isset($direct_result['direct_total_orders']) ? (int)$direct_result['direct_total_orders'] : 0;
    }

    // حساب إجمالي الإيرادات المكتملة الصحيح للأدمن (مثل orders.html بدون عمولة)
    $direct_completed_revenue_query = $conn->query("
        SELECT COALESCE(SUM(o.total), 0) as direct_completed_revenue
        FROM orders o
        WHERE o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
    ");

    $direct_completed_revenue = 0;
    if ($direct_completed_revenue_query) {
        $revenue_result = $direct_completed_revenue_query->fetch_assoc();
        $direct_completed_revenue = isset($revenue_result['direct_completed_revenue']) ? (float)$revenue_result['direct_completed_revenue'] : 0;
    }

    // حساب إجمالي الطلبات المكتملة الصحيح للأدمن
    $direct_completed_orders_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as direct_completed_orders
        FROM orders o
        WHERE o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
    ");

    $direct_completed_orders = 0;
    if ($direct_completed_orders_query) {
        $completed_result = $direct_completed_orders_query->fetch_assoc();
        $direct_completed_orders = isset($completed_result['direct_completed_orders']) ? (int)$completed_result['direct_completed_orders'] : 0;
    }

    // استخدام الأرقام المباشرة بدلاً من المجموع
    $total_orders = $direct_total_orders;
    $total_revenue = $direct_completed_revenue;

    // إحصائيات إضافية للأدمن
    $admin_stats = [
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

    // إحصائيات شهرية للأدمن
    $monthly_stats = [];
    $monthly_query = $conn->query("
        SELECT 
            DATE_FORMAT(o.created_at, '%Y-%m') as month,
            DATE_FORMAT(o.created_at, '%Y') as year,
            DATE_FORMAT(o.created_at, '%m') as month_num,
            COUNT(DISTINCT o.id) as orders_count,
            COALESCE(SUM(o.total + o.commission_total), 0) as revenue,
            COALESCE(SUM(o.shipping_cost), 0) as shipping_revenue,
            COUNT(DISTINCT sc.id) as cities_count,
            COUNT(DISTINCT o.user_id) as marketers_count,
            COUNT(DISTINCT o.customer_name) as customers_count
        FROM orders o
        LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
        WHERE o.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m'), DATE_FORMAT(o.created_at, '%Y'), DATE_FORMAT(o.created_at, '%m')
        ORDER BY month DESC
    ");

    if ($monthly_query) {
        while ($month = $monthly_query->fetch_assoc()) {
            $monthly_stats[] = $month;
        }
    }

    echo json_encode([
        'success' => true,
        'cities' => $shipping_cities_stats,
        'admin_stats' => $admin_stats,
        'status_stats' => $status_stats,
        'monthly_stats' => $monthly_stats
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in admin_get_shipping_cities_stats: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
