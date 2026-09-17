<?php
require 'c:\xampp\htdocs\System\config.php';
$res = $conn->query("SELECT marketing_agent, COUNT(*) as c FROM support_orders GROUP BY marketing_agent LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo $row['marketing_agent'] . " : " . $row['c'] . "\n";
}
