<?php
$servername = "127.0.0.1";
$dbname = "system_db";
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", "root", "");
} catch(PDOException $e) {
    $dbname = "system";
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", "root", "");
}
$stmt = $pdo->query("SHOW CREATE TABLE users");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SHOW CREATE TABLE admins");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
