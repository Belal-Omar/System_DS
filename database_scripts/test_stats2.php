<?php
require "c:/xampp/htdocs/System/config.php";

echo "\n\nsupport_orders data for Fadwa:\n";
$res = $conn->query("SELECT order_status, COUNT(*) as c FROM support_orders WHERE support_id=3 GROUP BY order_status");
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo $conn->error;
}
?>
