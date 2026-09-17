<?php
require 'c:\xampp\htdocs\System\db_connect.php';
$q = $conn->query("SHOW TABLES");
while ($r = $q->fetch_row()) {
    echo $r[0] . "\n";
}
