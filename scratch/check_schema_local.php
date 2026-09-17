<?php
$conn = new mysqli("127.0.0.1", "root", "", "u497700233_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$tables = ['landing_page_leads', 'support_orders', 'orders', 'products'];
$schema = [];
foreach($tables as $table) {
    echo "--- $table ---\n";
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if($res) {
        while($row = $res->fetch_assoc()) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } else {
        echo "Table not found: " . $conn->error . "\n";
    }
}
