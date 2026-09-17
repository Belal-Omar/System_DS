<?php
$conn = new mysqli('localhost', 'root', '', 'System');
$res = $conn->query("SHOW COLUMNS FROM support_orders");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
