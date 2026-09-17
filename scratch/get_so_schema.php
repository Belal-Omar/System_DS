<?php
require 'c:\xampp\htdocs\System\config.php';
if ($conn) {
    $res = $conn->query("SHOW CREATE TABLE support_orders");
    if ($res) {
        $row = $res->fetch_assoc();
        echo $row['Create Table'];
    } else {
        echo "Query failed: " . $conn->error;
    }
} else {
    echo "Connection failed.";
}
