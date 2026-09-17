<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = new mysqli("127.0.0.1", "root", "", "system_db");
if ($db->connect_error) {
    $db = new mysqli("127.0.0.1", "root", "", "system");
}
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "support_orders data for Fadwa (id=3):\n";
$res = $db->query("SELECT order_status, COUNT(*) as c FROM support_orders WHERE support_id=3 GROUP BY order_status");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['order_status'] . " : " . $row['c'] . "\n";
    }
} else {
    echo $db->error;
}
?>
