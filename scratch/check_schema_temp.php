<?php
require 'config.php';
$tables = ['landing_page_leads', 'support_orders', 'orders'];
foreach($tables as $table) {
    echo "--- $table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } else {
        echo "Table not found\n";
    }
}
