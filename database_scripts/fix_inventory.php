<?php
require 'config.php';
/** @var mysqli $conn */

// Check if user wants to execute the fix
$execute_fix = isset($_GET['fix']) && $_GET['fix'] == '1';

echo "<div style='font-family: Tahoma, Arial, sans-serif; direction: rtl; text-align: right; padding: 20px;'>";
echo "<h2>تقرير فحص وإصلاح المخزون الرئيسي</h2>";

// 1. Get current stock
$current_stock = [];
$res = $conn->query("SELECT product_code, stock_quantity FROM shipping_inventory_products");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $current_stock[$row['product_code']] = (int)$row['stock_quantity'];
    }
}

// 2. Calculate REAL used stock from active orders (not cancelled/returned)
$real_used_stock = [];
$res = $conn->query("
    SELECT product_code, SUM(pieces) as total_pieces, COUNT(*) as orders_count 
    FROM support_orders 
    WHERE product_code IS NOT NULL AND product_code != '' 
    AND order_status NOT IN ('cancelled', 'order_cancelled', 'returned')
    GROUP BY product_code
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $real_used_stock[$row['product_code']] = [
            'pieces' => (int)$row['total_pieces'],
            'orders' => (int)$row['orders_count']
        ];
    }
}

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; text-align: right; width: 100%;' dir='rtl'>";
echo "<tr style='background: #eee;'><th>المنتج</th><th>الرصيد السالب الحالي (في النظام الآن)</th><th>إجمالي الطلبات النشطة المسجلة</th><th>الرصيد المفروض (الحقيقي)</th></tr>";

foreach ($real_used_stock as $code => $data) {
    $current = $current_stock[$code] ?? 0;
    // Since we never added stock manually, the "real" balance should be 0 minus the active pieces.
    $should_be = - $data['pieces']; 
    
    echo "<tr>";
    echo "<td>{$code}</td>";
    echo "<td style='color: red; font-weight: bold;'>{$current}</td>";
    echo "<td>{$data['pieces']} قطعة (من {$data['orders']} طلب)</td>";
    echo "<td style='color: blue; font-weight: bold;'>{$should_be}</td>";
    echo "</tr>";
    
    if ($execute_fix) {
        $clean_code = $conn->real_escape_string($code);
        // We set it exactly to the negative of the active orders
        // (Assuming opening balance was 0)
        $conn->query("UPDATE shipping_inventory_products SET stock_quantity = {$should_be} WHERE product_code = '{$clean_code}'");
    }
}
echo "</table>";

if (!$execute_fix) {
    echo "<br><br><a href='?fix=1' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 16px;'>اضغط هنا لتصحيح جميع الأرصدة الآن لتطابق الطلبات الفعلية</a>";
} else {
    echo "<br><br><div style='padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; font-size: 16px;'>تم بنجاح! تم تصحيح وتحديث أرصدة المخزون بناءً على الطلبات الفعلية في النظام وتم محو تراكمات الخصم المزدوج والأخطاء السابقة.</div>";
}
echo "</div>";
