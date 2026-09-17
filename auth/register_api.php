<?php
// ملف: register_api.php - API لإنشاء حساب جديد
session_start();
include(__DIR__ . '/core/config.php");

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $response['message'] = 'بيانات غير صالحة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $fullname = $conn->real_escape_string($data['fullname'] ?? '');
    $email = $conn->real_escape_string($data['email'] ?? '');
    $phone = $conn->real_escape_string($data['phone'] ?? '');
    $user_type = $conn->real_escape_string($data['user_type'] ?? 'مسوق');
    $region = $conn->real_escape_string($data['region'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($fullname) || empty($email) || empty($phone) || empty($user_type) || empty($password)) {
        $response['message'] = 'يرجى ملء جميع الحقول المطلوبة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // التحقق من صحة البريد الإلكتروني
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'صيغة البريد الإلكتروني غير صحيحة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // التحقق من عدم وجود البريد الإلكتروني مسبقاً
    $check_email = $conn->query("SELECT id FROM users WHERE email = '$email'");
    
    if ($check_email && $check_email->num_rows > 0) {
        $response['message'] = 'البريد الإلكتروني مسجل مسبقاً';
    } else {
        // التحقق من عدم وجود رقم الهاتف مسبقاً
        $check_phone = $conn->query("SELECT id FROM users WHERE phone = '$phone'");
        if ($check_phone && $check_phone->num_rows > 0) {
            $response['message'] = 'رقم الهاتف مسجل مسبقاً';
        } else {
            // تشفير كلمة المرور
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // إدراج المستخدم الجديد مع القيم الافتراضية
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, user_type, region, password, wallet_balance, total_earnings, total_withdrawn, balance, is_active) VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 1)");
            $stmt->bind_param("ssssss", $fullname, $email, $phone, $user_type, $region, $password_hash);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.';
                $response['redirect'] = 'login.html?success=1';
            } else {
                $response['message'] = 'حدث خطأ أثناء إنشاء الحساب: ' . $stmt->error;
            }
            
            $stmt->close();
        }
    }
} else {
    $response['message'] = 'طريقة غير مسموحة';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>