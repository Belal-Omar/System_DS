<?php
require 'c:\xampp\htdocs\System\config.php';
$company_id = 3; // Miskova Global might have ID=3 or something. I'll just select first shipping company.
$res = $conn->query("SELECT id FROM shipping_companies LIMIT 1");
$company_id = $res->fetch_assoc()['id'];

$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status IN ('received', 'delivered') THEN 1 ELSE 0 END) as total_delivered,
        SUM(CASE WHEN order_status IN ('order_cancelled', 'cancelled') THEN 1 ELSE 0 END) as total_cancelled,
        SUM(CASE WHEN order_status IN ('returned', 'return') THEN 1 ELSE 0 END) as total_returned,
        SUM(CASE WHEN order_status NOT IN ('received', 'delivered', 'order_cancelled', 'cancelled', 'returned', 'return') THEN 1 ELSE 0 END) as total_pending
    FROM support_orders 
    WHERE shipping_company_id = ? OR shipping_rep_id IN (SELECT id FROM shipping_company_reps WHERE company_id = ?)
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $company_id, $company_id);
$stmt->execute();
print_r($stmt->get_result()->fetch_assoc());
