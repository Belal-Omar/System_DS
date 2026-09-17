<?php
require 'c:\xampp\htdocs\System\config.php';
$res = $conn->query("SHOW COLUMNS FROM support_daily_leads");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
