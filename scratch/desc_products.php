<?php
require_once __DIR__ . '/../config.php';
$r = $conn->query('DESCRIBE products');
while($col = $r->fetch_assoc()) echo $col['Field']." - ".$col['Type']."\n";
