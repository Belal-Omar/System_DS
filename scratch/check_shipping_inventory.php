<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'System');
$q = $conn->query("SHOW CREATE TABLE shipping_inventory");
$r = $q->fetch_row();
echo $r[1];
