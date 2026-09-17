<?php
// ملف: fix_commissions.php
include(__DIR__ . '/core/config.php");

// تصحيح عمولات الطلبات بناءً على العناصر
$orders = $conn->query("SELECT id FROM orders");
while($order = $orders->fetch_assoc()) {
    $order_id = $order['id'];
    
    // حساب العمولة الإجمالية من العناصر
    $commission_result = $conn->query("
        SELECT SUM(commission * quantity) as total_commission 
        FROM order_items 
        WHERE order_id = $order_id
    ");
    $commission_data = $commission_result->fetch_assoc();
    $total_commission = $commission_data['total_commission'] ?? 0;
    
    // تحديث العمولة في الطلب
    $conn->query("UPDATE orders SET commission_total = $total_commission WHERE id = $order_id");
}

echo "تم تصحيح عمولات جميع الطلبات بنجاح!";
?>