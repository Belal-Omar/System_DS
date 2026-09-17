<?php
$conn = new mysqli("127.0.0.1", "root", "", "System");
if ($conn->connect_error) { die("Connection failed"); }
$res = $conn->query("DESCRIBE shipping_companies");
if ($res) {
    while($row = $res->fetch_assoc()) { print_r($row); }
} else { echo $conn->error; }
