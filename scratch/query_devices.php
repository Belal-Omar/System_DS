<?php
$c = new mysqli('127.0.0.1', 'root', '', 'miskova');
$r = $c->query('SELECT * FROM device_requests ORDER BY id DESC LIMIT 10');
while($row=$r->fetch_assoc()) echo json_encode($row)."\n";
