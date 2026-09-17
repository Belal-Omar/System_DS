<?php
require 'config.php';
$res = $conn->query('SELECT id, name FROM shipping_accounts');
while($r=$res->fetch_assoc()) echo $r['id'].':'.$r['name']."\n";
