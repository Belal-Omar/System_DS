<?php
// ملف: login_api.php - API لتسجيل الدخول الآمن
session_start();
include(__DIR__ . '/core/config.php");

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        $response['message'] = 'بيانات غير صالحة';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        $response['message'] = 'يرجى إدخال البريد الإلكتروني وكلمة المرور';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // تحقق من وجود المستخدم باستخدام Prepared Statement
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // التحقق من حالة الحساب إذا كان العمود موجود
        if (isset($user['is_active']) && $user['is_active'] != 1) {
            $response['message'] = 'طلبك تحت المراجعة. يرجى انتظار موافقة الإدارة على حسابك.';
        }
        // التحقق من كلمة المرور
        elseif (isset($user['password']) && password_verify($password, $user['password'])) {
            $user_id = (int)$user['id'];

            // تحديث last_login إذا كان العمود موجود
            $conn->query("UPDATE users SET last_login = NOW() WHERE id = $user_id");

            // حفظ بيانات المستخدم في الجلسة
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_fullname'] = $user['fullname'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'] ?? '';
            $_SESSION['user_type'] = $user['user_type'] ?? 'مسوق';
            $_SESSION['wallet_balance'] = floatval($user['wallet_balance'] ?? 0);
            $_SESSION['total_earnings'] = floatval($user['total_earnings'] ?? 0);
            $_SESSION['total_withdrawn'] = floatval($user['total_withdrawn'] ?? 0);
            $_SESSION['balance'] = floatval($user['balance'] ?? 0);

            $response['success'] = true;
            $response['message'] = 'تم تسجيل الدخول بنجاح';

            // إعادة التوجيه حسب نوع المستخدم
            $user_type = $_SESSION['user_type'];
            if ($user_type === 'تاجر') {
                $response['redirect'] = 'home111.php';
            } else {
                $response['redirect'] = 'Home1.html';
            }
        } else {
            $response['message'] = 'كلمة المرور غير صحيحة';
        }
    } else {
        $response['message'] = 'البريد الإلكتروني غير مسجل';
    }

    $stmt->close();
} else {
    $response['message'] = 'طريقة غير مسموحة';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
