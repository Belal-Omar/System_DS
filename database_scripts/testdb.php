<?php
$host = "mysql.hostinger.com";
$user = "u497700233_medhatomar5555";
$pass = "BelalOmar49988155$";
$db   = "u497700233_System";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
} else {
    echo "تم الاتصال بقاعدة البيانات بنجاح!";
}
?>
