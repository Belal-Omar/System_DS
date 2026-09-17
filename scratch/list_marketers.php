<?php
require 'config.php';
$res = $conn->query("SELECT id, fullname, role, marketer_code FROM admins WHERE role LIKE 'marketing%'");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
