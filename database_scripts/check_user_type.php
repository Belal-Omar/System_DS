<?php
// ملف: check_user_type.php - التحقق من نوع المستخدم
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// يجب استدعاء config.php قبل استخدام $conn
if (!isset($conn)) {
    include(__DIR__ . '/core/config.php");
}

$user_id = $_SESSION['user_id'] ?? null;
$user = null;

if ($user_id && isset($conn)) {
    $user_id = (int)$user_id;
    $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user_query) {
        $user = $user_query->fetch_assoc();
    }
}

if (!$user) {
    header("Location: login.html");
    exit;
}

$user_type = $user['user_type'] ?? '';

// دالة للتحقق من نوع المستخدم
function require_user_type($required_type) {
    global $user_type;
    if ($user_type != $required_type) {
        if ($required_type == 'تاجر') {
            header("Location: Home1.html");
        } else {
            header("Location: home111.php");
        }
        exit;
    }
}

// دالة للتحقق من أن المستخدم ليس من نوع معين
function block_user_type($blocked_type) {
    global $user_type;
    if ($user_type == $blocked_type) {
        if ($blocked_type == 'تاجر') {
            header("Location: Home1.html");
        } else {
            header("Location: home111.php");
        }
        exit;
    }
}

