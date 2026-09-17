<?php
require "c:/xampp/htdocs/System/config.php";
/** @var mysqli $conn */
if (!$conn) die('DB Connection failed');
$res = $conn->query("SHOW CREATE VIEW support_stats");
if($res) {
    print_r($res->fetch_assoc());
} else {
    echo $conn->error;
}
?>
