<?php
$conn = new mysqli("localhost", "root", "", "u497700233_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$q = $conn->query("SELECT id, support_id, sheet_id, sheet_row_index, order_status, phone, created_at FROM support_orders ORDER BY id DESC LIMIT 20");
while($row = $q->fetch_assoc()) {
    print_r($row);
}
