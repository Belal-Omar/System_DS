<?php
// ملف: get_user_info.php - API للحصول على معلومات المستخدم للـ navbar
session_start();
include(__DIR__ . '/core/config.php");

header('Content-Type: application/json; charset=utf-8');

$response = ['logged_in' => false, 'user_name' => ''];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = $conn->query("SELECT fullname FROM users WHERE id = $user_id");
    if ($user_query && $user_query->num_rows > 0) {
        $user = $user_query->fetch_assoc();
        $response['logged_in'] = true;
        $response['user_name'] = $user['fullname'];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

