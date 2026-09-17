<?php
require 'config.php';
$r = $conn->query("SHOW COLUMNS FROM support_daily_leads");
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' ' . $row['Type'] . "\n";
}
