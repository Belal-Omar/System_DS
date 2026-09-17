<?php
require_once 'config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed"); }
$res = $conn->query("DESCRIBE shipping_companies");
if ($res) {
    while($row = $res->fetch_assoc()) { echo $row['Field'] . "\n"; }
} else { echo $conn->error; }
