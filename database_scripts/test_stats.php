<?php
$db = new mysqli("127.0.0.1", "root", "", "system_db");
if($db->connect_error) $db = new mysqli("127.0.0.1", "root", "", "system");

echo "support_stats view:\n";
$res = $db->query("SHOW CREATE VIEW support_stats");
if($res) print_r($res->fetch_assoc());
else echo $db->error;

echo "\n\nsupport_orders data for Fadwa (id=3):\n";
$res = $db->query("SELECT order_status, COUNT(*) FROM support_orders WHERE support_id=3 GROUP BY order_status");
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
