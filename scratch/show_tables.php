<?php
require_once 'c:/xampp/htdocs/System/config.php';
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
