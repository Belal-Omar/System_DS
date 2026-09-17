<?php
require 'config.php';
/** @var mysqli $conn */
$res = $conn->query('SELECT username, role, allowed_device_id FROM admins');
while ($row = $res->fetch_assoc()) {
    echo $row['username'] . " (" . $row['role'] . ") -> " . ($row['allowed_device_id'] ?: 'NULL') . "\n";
}
