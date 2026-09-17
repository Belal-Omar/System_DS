<?php
$conn = new mysqli("127.0.0.1", "root", "", "system"); // Using typical XAMPP local config
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
} else {
    $res = $conn->query("DESCRIBE support_orders");
    while ($r = $res->fetch_assoc()) {
        echo $r['Field'] . "\n";
    }
}
