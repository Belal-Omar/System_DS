<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'System');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$res = $conn->query("SHOW TABLES LIKE 'marketing%'");
while ($row = $res->fetch_array()) {
    $table = $row[0];
    echo "TABLE: $table\n";
    $cols = $conn->query("DESCRIBE $table");
    while ($col = $cols->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
$res = $conn->query("DESCRIBE products");
echo "TABLE: products\n";
while ($col = $res->fetch_assoc()) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
