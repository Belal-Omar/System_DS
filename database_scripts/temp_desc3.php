<?php
require_once 'config.php';
if (!$conn) { die("Connection failed"); }
$res = $conn->query("DESCRIBE shipping_companies");
if ($res) {
    while($row = $res->fetch_assoc()) { echo $row['Field'] . "\n"; }
} else { echo $conn->error; }
