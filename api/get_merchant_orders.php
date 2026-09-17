<?php
// ملف: get_merchant_orders.php - جلب طلبات منتجات التاجر
header('Content-Type: application/json; charset=utf-8');
session_start();
include(__DIR__ . '/core/config.php");

$response = ['success' => false, 'orders' => [], 'total_profit' => 0];

try {
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        throw new Exception("يجب تسجيل الدخول");
    }

    $user_id = (int)$user_id;

    // جلب طلبات منتجات التاجر مع تفاصيل المنتجات
    $result = $conn->query("
        SELECT DISTINCT
            o.id,
            o.customer_name as name,
            o.customer_phone as phone,
            o.region,
            o.address,
            o.total,
            o.commission_total,
            o.status,
            o.created_at as date,
            o.shipping_cost,
            p.name as product_name,
            oi.quantity,
            oi.original_price as price,  -- استخدام السعر الأصلي للمنتج
            oi.price as original_price,  -- الاحتفاظ بالسعر الأصلي
            oi.commission,
            oi.shipping_cost as item_shipping_cost
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id
        ORDER BY o.created_at DESC
    ");

    $orders = [];
    $total_profit = 0;

    while($row = $result->fetch_assoc()) {
        // حساب صافي الربح (السعر الأصلي - العمولة - مصاريف الشحن)
        $net_price = floatval($row['original_price']);
        $commission = floatval($row['commission'] ?? 0);
        $shipping = floatval($row['item_shipping_cost'] ?? 0);
        
        $row['net_price'] = max(0, $net_price - $commission - $shipping);
        $orders[] = $row;
        
        if (in_array($row['status'], ['تم التوصيل', 'محصل', 'مكتمل'])) {
            $total_profit += $row['net_price'] * floatval($row['quantity']);
        }
    }
    
    $response['success'] = true;
    $response['orders'] = $orders;
    $response['total_profit'] = $total_profit;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>