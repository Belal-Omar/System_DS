<?php
require_once 'c:/xampp/htdocs/System/config.php';
$res = $conn->query("DESCRIBE support_orders");
while($r = $res->fetch_assoc()) echo $r['Field']." - ".$r['Type']."\n";
