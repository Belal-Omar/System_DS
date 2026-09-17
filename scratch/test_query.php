<?php
require 'c:\xampp\htdocs\System\config.php';
$orders_query = "
    SELECT o.id, o.customer_name, o.phone, o.address, o.governorate, o.order_status, 
           o.support_name, o.agent_code, o.total_price, r.name as rep_name, o.created_at
    FROM support_orders o
    LEFT JOIN shipping_company_reps r ON o.shipping_rep_id = r.id
    LIMIT 1
";
$res = $conn->query($orders_query);
if ($res) {
    print_r($res->fetch_assoc());
} else {
    echo $conn->error;
}
