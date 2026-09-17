<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'System', 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$columns = $conn->query("SHOW COLUMNS FROM products");
if ($columns) {
    while ($row = $columns->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Query failed: " . $conn->error;
}
?>
