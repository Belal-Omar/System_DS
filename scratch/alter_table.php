<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'System');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->query("ALTER TABLE shipping_inventory MODIFY quantity INT NOT NULL DEFAULT 0");
echo "Done";
