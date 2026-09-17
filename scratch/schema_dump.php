<?php
$conn = new mysqli('localhost', 'root', '', 'system');
if ($conn->connect_error) die("Connection failed");
$res = $conn->query('SHOW CREATE TABLE support_orders');
$row = $res->fetch_assoc();
echo $row['Create Table'];
