<?php
// ملف: register.php (محدث)
session_start();
include(__DIR__ . '/../core/config.php");
/** @var mysqli $conn */
include(__DIR__ . '/../core/helpers.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $region = $conn->real_escape_string($_POST['region'] ?? '');
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    $national_id = $conn->real_escape_string($_POST['national_id'] ?? '');
    $bank_name = $conn->real_escape_string($_POST['bank_name'] ?? '');
    $bank_account = $conn->real_escape_string($_POST['bank_account'] ?? '');
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_type = $conn->real_escape_string($_POST['user_type'] ?? 'مسوق');

    // التحقق من عدم وجود البريد الإلكتروني مسبقاً
    $check_email = $conn->query("SELECT id FROM users WHERE email = '$email'");
    
    if ($check_email && $check_email->num_rows > 0) {
        $error = "البريد الإلكتروني مسجل مسبقاً";
    } else {
        // التحقق من عدم وجود رقم الهاتف مسبقاً
        $check_phone = $conn->query("SELECT id FROM users WHERE phone = '$phone'");
        if ($check_phone && $check_phone->num_rows > 0) {
            $error = "رقم الهاتف مسجل مسبقاً";
        } else {
            // إدراج المستخدم الجديد مع القيم الافتراضية
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, region, address, national_id, bank_name, bank_account, password, user_type, wallet_balance, total_earnings, total_withdrawn, balance, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 1)");
            $stmt->bind_param("ssssssssss", $fullname, $email, $phone, $region, $address, $national_id, $bank_name, $bank_account, $password, $user_type);
            
            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                $success = "تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.";
                
                // إشعار للإدارة بتسجيل مستخدم جديد
                if (function_exists('send_notification')) {
                    $title = "تسجيل مستخدم جديد";
                    $msg = "تم تسجيل حساب جديد باسم: $fullname ($user_type)";
                    $link = "admin_panel.php?page=users";
                    send_notification($conn, 'admin', 0, $title, $msg, $link);
                }
            } else {
                $error = "حدث خطأ أثناء إنشاء الحساب: " . $stmt->error;
            }
        }
    }
}
?>