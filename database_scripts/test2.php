<?php
$conn = new mysqli('127.0.0.1', 'u497700233_medhatomar5555', 'BelalOmar49988155$', 'u497700233_System');
$res = $conn->query("SHOW CREATE TABLE admins");
$row = $res->fetch_assoc();
echo $row['Create Table'];
