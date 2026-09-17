<?php
require 'config.php';
$res = $conn->query("SHOW TABLES LIKE '%spend%'");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
$res = $conn->query("SHOW TABLES LIKE '%market%'");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
