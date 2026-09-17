<?php
require 'config.php';
// config.php might not connect if it expects a function or relies on globals
// Let's just connect manually
$host = 'localhost';
$user = 'u497700233_medhatomar5555';
$pass = 'BelalOmar49988155$';
$db = 'u497700233_System';
$conn2 = new mysqli($host, $user, $pass, $db);
$r = $conn2->query("SHOW COLUMNS FROM support_daily_leads");
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' ' . $row['Type'] . "\n";
}
