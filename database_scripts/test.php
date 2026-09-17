<?php
require 'config.php';
/** @var mysqli $conn */
$q = $conn->query("DESCRIBE admins");
while ($r = $q->fetch_assoc()) print_r($r);
