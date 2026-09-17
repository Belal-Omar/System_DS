<?php
require 'config.php';
require_once 'helpers.php'; // To load functions
/** @var mysqli $conn */

echo "<pre>";
$ship = calculate_shipping_collection_balance($conn, '2026-07-01', '2026-07-31', null);
echo "calculate_shipping_collection_balance() output:\n";
print_r($ship);

$delivered = accounts_delivered_statuses_sql();
$start_date = '2026-07-01';
$end_date = '2026-07-31';
$period_filter = ' AND ' . support_orders_period_sql($start_date, $end_date, 'o');
$sale_sql = accounts_order_sale_price_sql('o', 'e');

$sql = "
    SELECT
        SUM(CASE WHEN o.order_status IN ($delivered) THEN 1 ELSE 0 END) as delivered_count,
        SUM(CASE WHEN o.order_status IN ($delivered) THEN ($sale_sql) ELSE 0 END) as total_sale,
        SUM(CASE WHEN o.order_status IN ($delivered) THEN COALESCE(o.snap_dom_shipping, e.dom_shipping, 0) ELSE 0 END) as total_dom_shipping
    FROM support_orders o
    LEFT JOIN support_sheets s ON o.sheet_id = s.id
    LEFT JOIN support_product_monthly e
        ON DATE_FORMAT(COALESCE(s.created_at, o.created_at), '%Y-%m') = e.month
        AND e.product_code = COALESCE(NULLIF(o.product_code, ''), 'Etala001')
    WHERE 1=1 $period_filter
";

echo "\nRaw query:\n$sql\n";
$q = $conn->query($sql);
if (!$q) {
    echo "SQL Error: " . $conn->error;
} else {
    print_r($q->fetch_assoc());
}
echo "</pre>";
