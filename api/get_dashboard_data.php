<?php
// ملف: get_dashboard_data.php
header('Content-Type: application/json; charset=utf-8');
session_start();
include(__DIR__ . '/core/config.php");

$response = [];

try {
    // الحصول على user_id من الجلسة (إذا كان المستخدم مسجل دخول)
    $user_id = $_SESSION['user_id'] ?? null;
    
    // بناء WHERE clause - جلب بيانات المستخدم المسجل فقط
    if (!$user_id) {
        $response['success'] = true;
        $response['message'] = 'يرجى تسجيل الدخول لعرض الإحصائيات';
        echo json_encode($response);
        exit();
    }
    
    $user_id = intval($user_id);
    $where_clause = "WHERE user_id = $user_id";
    
    // إجمالي الطلبات (خاصة بالمستخدم إذا كان مسجل دخول)
    $total_orders_result = $conn->query("SELECT COUNT(*) as total FROM orders $where_clause");
    $total_orders = $total_orders_result ? $total_orders_result->fetch_assoc()['total'] : 0;
    
    // نسبة التسليم (الطلبات المكتملة)
    $delivered_where = $where_clause ? $where_clause . " AND status = 'تم التوصيل'" : "WHERE status = 'تم التوصيل'";
    $delivered_orders_result = $conn->query("SELECT COUNT(*) as total FROM orders $delivered_where");
    $delivered_orders = $delivered_orders_result ? $delivered_orders_result->fetch_assoc()['total'] : 0;
    $delivery_rate = $total_orders > 0 ? round(($delivered_orders / $total_orders) * 100, 2) : 0;
    
    // إجمالي الأرباح (العمولات من الطلبات المكتملة) - خاصة بالمستخدم
    $profit_where = $where_clause ? $where_clause . " AND status IN ('تم التوصيل', 'محصل', 'مكتمل')" : "WHERE status IN ('تم التوصيل', 'محصل', 'مكتمل')";
    $total_profit_result = $conn->query("SELECT SUM(commission_total) as total FROM orders $profit_where");
    $total_profit_row = $total_profit_result ? $total_profit_result->fetch_assoc() : ['total' => 0];
    $total_profit = $total_profit_row['total'] ? floatval($total_profit_row['total']) : 0;
    
    // أرباح قيد الانتظار (العمولات من الطلبات غير المكتملة) - خاصة بالمستخدم
    $pending_where = $where_clause ? $where_clause . " AND status NOT IN ('تم التوصيل', 'محصل', 'مكتمل', 'ملغي', 'مرفوض')" : "WHERE status NOT IN ('تم التوصيل', 'محصل', 'مكتمل', 'ملغي', 'مرفوض')";
    $pending_profit_result = $conn->query("SELECT SUM(commission_total) as total FROM orders $pending_where");
    $pending_profit_row = $pending_profit_result ? $pending_profit_result->fetch_assoc() : ['total' => 0];
    $pending_profit = $pending_profit_row['total'] ? floatval($pending_profit_row['total']) : 0;
    
    // تم السحب (من جدول السحوبات المكتملة) - خاصة بالمستخدم
    if ($user_id) {
        $withdrawn_where = "WHERE user_id = $user_id AND status = 'مكتمل'";
    } else {
        $withdrawn_where = "WHERE status = 'مكتمل'";
    }
    $withdrawn_result = $conn->query("SELECT SUM(amount) as total FROM withdrawals $withdrawn_where");
    $withdrawn_row = $withdrawn_result ? $withdrawn_result->fetch_assoc() : ['total' => 0];
    $withdrawn_amount = $withdrawn_row['total'] ? floatval($withdrawn_row['total']) : 0;
    
    // متاح السحب (إجمالي الأرباح - المسحوب)
    $available_withdrawal = $total_profit - $withdrawn_amount;
    if ($available_withdrawal < 0) $available_withdrawal = 0;
    
    // أرباح متوقعة (إجمالي العمولات من جميع الطلبات النشطة) - خاصة بالمستخدم
    $expected_where = $where_clause ? $where_clause . " AND status NOT IN ('ملغي', 'مرفوض')" : "WHERE status NOT IN ('ملغي', 'مرفوض')";
    $expected_profit_result = $conn->query("SELECT SUM(commission_total) as total FROM orders $expected_where");
    $expected_profit_row = $expected_profit_result ? $expected_profit_result->fetch_assoc() : ['total' => 0];
    $expected_profit = $expected_profit_row['total'] ? floatval($expected_profit_row['total']) : 0;
    
    // إحصائيات حالات الطلبات - خاصة بالمستخدم
    $status_counts = [
        'مغلق' => 0,
        'تم التأكيد' => 0,
        'قيد الانتظار' => 0,
        'في الشحن' => 0,
        'تم التوصيل' => 0,
        'مرتجع' => 0,
        'ملغي' => 0,
        'محصل' => 0,
        'تحت التحضير' => 0,
        'مرفوض' => 0,
        'قيد التنفيذ' => 0
    ];
    
    $status_result = $conn->query("SELECT status, COUNT(*) as count FROM orders $where_clause GROUP BY status");
    if ($status_result) {
        while($row = $status_result->fetch_assoc()) {
            $status_counts[$row['status']] = $row['count'];
        }
    }
    
    // تحويل أسماء الحالات للتوافق مع واجهة المستخدم
    $ui_status_counts = [
        'مغلق' => $status_counts['مغلق'] + $status_counts['محصل'],
        'تم التأكيد' => $status_counts['تم التأكيد'],
        'قيد الانتظار' => $status_counts['قيد الانتظار'],
        'في الشحن' => $status_counts['في الشحن'],
        'تم التوصيل' => $status_counts['تم التوصيل'],
        'مرتجع' => $status_counts['مرتجع'],
        'ملغي' => $status_counts['ملغي'] + $status_counts['مرفوض'],
        'محصل' => $status_counts['محصل'],
        'تحت التحضير' => $status_counts['تحت التحضير'] + $status_counts['قيد التنفيذ']
    ];
    
    // بيانات الرسم البياني الدائري - الإلغاء والتوصيل والمرتجع فقط
    $pie_data = [];
    $pie_labels = [];
    $pie_colors = [];
    
    // الحالات المطلوبة للرسم البياني الدائري
    $pie_statuses = [
        'تم التوصيل' => '#4b6b2f',  // أخضر
        'ملغي' => '#ef4444',         // أحمر
        'مرتجع' => '#8b5cf6'         // بنفسجي
    ];
    
    // حساب إجمالي الطلبات للحالات المطلوبة
    $total_for_pie = 0;
    foreach($pie_statuses as $status => $color) {
        $count = $status_counts[$status] ?? 0;
        $total_for_pie += $count;
    }
    
    // إذا كان هناك طلبات، احسب النسب المئوية
    if ($total_for_pie > 0) {
        foreach($pie_statuses as $status => $color) {
            $count = $status_counts[$status] ?? 0;
            if ($count > 0) {
                $pie_labels[] = $status;
                $percentage = round(($count / $total_for_pie) * 100, 1);
                $pie_data[] = $percentage;
                $pie_colors[] = $color;
            }
        }
    }
    
    // إذا لم تكن هناك بيانات، إضافة بيانات افتراضية للرسم البياني
    if (empty($pie_data) || $total_for_pie == 0) {
        $pie_labels = ['لا توجد طلبات'];
        $pie_data = [100];
        $pie_colors = ['#9ca3af'];
    }
    
    // بيانات الرسم البياني الخطي (أرباح آخر 12 شهر - السنة الكاملة)
    $line_labels = [];
    $line_data = [];
    
    // أسماء الأشهر بالعربية
    $arabic_months = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر'
    ];
    
    // جلب بيانات آخر 12 شهر
    for($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_english = date('F', strtotime($month . '-01'));
        $month_arabic = $arabic_months[$month_english] ?? $month_english;
        $year = date('Y', strtotime($month . '-01'));
        $line_labels[] = $month_arabic;
        
        $month_where = $where_clause ? $where_clause . " AND status IN ('تم التوصيل', 'محصل', 'مكتمل') AND DATE_FORMAT(created_at, '%Y-%m') = '$month'" : "WHERE status IN ('تم التوصيل', 'محصل', 'مكتمل') AND DATE_FORMAT(created_at, '%Y-%m') = '$month'";
        $month_profit_result = $conn->query("
            SELECT SUM(commission_total) as total 
            FROM orders 
            $month_where
        ");
        
        if ($month_profit_result) {
            $month_profit_row = $month_profit_result->fetch_assoc();
            $line_data[] = $month_profit_row['total'] ? floatval($month_profit_row['total']) : 0;
        } else {
            $line_data[] = 0;
        }
    }
    
    $response['success'] = true;
    $response['stats'] = [
        'total_orders' => $total_orders,
        'delivery_rate' => $delivery_rate,
        'total_profit' => number_format($total_profit, 2),
        'pending_profit' => number_format($pending_profit, 2),
        'withdrawn_amount' => number_format($withdrawn_amount, 2),
        'available_withdrawal' => number_format($available_withdrawal, 2),
        'expected_profit' => number_format($expected_profit, 2),
        'status_counts' => $ui_status_counts
    ];
    
    $response['charts'] = [
        'pieChart' => [
            'labels' => $pie_labels,
            'data' => $pie_data,
            'colors' => $pie_colors
        ],
        'lineChart' => [
            'labels' => $line_labels,
            'data' => $line_data
        ]
    ];
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>