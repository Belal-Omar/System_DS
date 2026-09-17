<?php
// ملف: config.php - نسخة آمنة للـ Hostinger

// 🔹 Session Configuration - زيادة وقت الجلسة (المدة بالثواني)
if (session_status() === PHP_SESSION_NONE) {
    // 🔹 Session Configuration - Custom Path to prevent Hostinger GC
    $session_path = __DIR__ . '/sessions';
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0777, true);
    }
    @session_save_path($session_path);
    
    @ini_set('session.gc_maxlifetime', 2592000); // 30 أيام
    @ini_set('session.cookie_lifetime', 2592000); // 30 أيام
    @session_set_cookie_params(2592000);
} else {
    // Force update cookie if session was already started before config.php
    if (!headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), time() + 2592000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
}

date_default_timezone_set('Africa/Cairo');

$servername = "127.0.0.1";
$username   = "u497700233_medhatomar5555";
$password   = "BelalOmar49988155$";
$dbname     = "u497700233_System";

// إنشاء الاتصال مع معالجة الأخطاء (PHP 8 يرمي Exception بدل connect_error فقط)
$conn = null;
try {
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }
    $conn = @new mysqli($servername, $username, $password, $dbname, 3306);
    if (!$conn || $conn->connect_error) {
        $conn = null;
    } else {
        $conn->set_charset("utf8mb4");
        
        // Sync MySQL timezone
        if (!$conn->query("SET time_zone = 'Africa/Cairo'")) {
            $conn->query("SET time_zone = '+03:00'");
        }
    }
} catch (Throwable $e) {
    $conn = null;
}

// دالة لتنظيف المدخلات
if (!function_exists('clean_input')) {
    function clean_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}
