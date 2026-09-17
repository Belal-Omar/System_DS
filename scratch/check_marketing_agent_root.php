<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'System');
if ($conn->connect_error) {
    $conn = new mysqli('127.0.0.1', 'root', '', 'u497700233_System');
}
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$res = $conn->query("SELECT marketing_agent, COUNT(*) as c FROM support_orders GROUP BY marketing_agent LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo $row['marketing_agent'] . " : " . $row['c'] . "\n";
}
