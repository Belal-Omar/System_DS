<?php
// ملف: login.php
include(__DIR__ . '/../core/config.php");
session_start();
/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    
    // التحقق من وجود البريد الإلكتروني
    $result = $conn->query("SELECT * FROM users WHERE email = '$email'");
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // التحقق من حالة الحساب (is_active)
        if (isset($user['is_active']) && $user['is_active'] != 1) {
            $error = "طلبك تحت المراجعة. يرجى انتظار موافقة الإدارة على حسابك.";
        }
        // التحقق من كلمة المرور
        elseif (isset($user['password']) && password_verify($password, $user['password'])) {
            $login_ok = true;
            
            // ================== Device Binding Logic ==================
            // Determine or generate device ID
            $device_cookie = $_COOKIE['user_device_id'] ?? null;
            if (isset($_POST['js_device_token']) && !empty($_POST['js_device_token'])) {
                $device_cookie = $_POST['js_device_token']; // Fallback to LocalStorage token if Cookie was cleared
            }            
            // Always refresh cookie
            setcookie('user_device_id', $device_cookie, time() + (10 * 365 * 24 * 60 * 60), "/");

            // Multi-device Binding Logic
            $req_approved = $conn->prepare("SELECT id FROM device_requests WHERE account_type = 'user' AND account_id = ? AND requested_device_id = ? AND status = 'approved'");
            $req_approved->bind_param("is", $user['id'], $device_cookie);
            $req_approved->execute();
            $is_device_approved = $req_approved->get_result()->num_rows > 0;
            $req_approved->close();

            if ($is_device_approved) {
                // Device is approved!
                $login_ok = true;
            } else {
                // Check if they have ANY approved devices at all
                $any_approved = $conn->prepare("SELECT id FROM device_requests WHERE account_type = 'user' AND account_id = ? AND status = 'approved'");
                $any_approved->bind_param("i", $user['id']);
                $any_approved->execute();
                $has_any_device = $any_approved->get_result()->num_rows > 0;
                $any_approved->close();

                if (!$has_any_device) {
                    // First device ever - auto approve
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $stmt_ins = $conn->prepare("INSERT INTO device_requests (account_type, account_id, requested_device_id, ip_address, user_agent, status, created_at) VALUES ('user', ?, ?, ?, ?, 'approved', NOW())");
                    $stmt_ins->bind_param("isss", $user['id'], $device_cookie, $ip, $ua);
                    $stmt_ins->execute();
                    $stmt_ins->close();
                    
                    // Keep allowed_device_id populated just for legacy fallback if needed
                    $stmt_update = $conn->prepare("UPDATE users SET allowed_device_id = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $device_cookie, $user['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    $login_ok = true;
                } else {
                    // Has devices, but THIS one is not approved
                    $login_ok = false;
                    $error = "عذراً لم يتم التعرف على هذا الجهاز. لا يمكنك تسجيل الدخول من هذا الجهاز. لقد تم إرسال طلب للموافقة عليه، يرجى الانتظار.";
                    
                    // Check if pending request already exists for THIS device
                    $req_check = $conn->prepare("SELECT id FROM device_requests WHERE account_type = 'user' AND account_id = ? AND requested_device_id = ? AND status = 'pending'");
                    $req_check->bind_param("is", $user['id'], $device_cookie);
                    $req_check->execute();
                    $req_check_res = $req_check->get_result();
                    if ($req_check_res->num_rows === 0) {
                        $req_insert = $conn->prepare("INSERT INTO device_requests (account_type, account_id, requested_device_id, ip_address, user_agent, status, created_at) VALUES ('user', ?, ?, ?, ?, 'pending', NOW())");
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $req_insert->bind_param("isss", $user['id'], $device_cookie, $ip, $ua);
                        if ($req_insert->execute()) {
                            $userName = $conn->real_escape_string($user['fullname'] ?? 'مجهول');
                            $msg = "محاولة دخول من جهاز جديد لعميل: $userName";
                            $conn->query("INSERT INTO system_notifications (recipient_type, recipient_id, title, message, link) VALUES ('admin', 0, 'تنبيه أمني - جهاز جديد', '$msg', 'admin_panel.php?page=devices')");
                        }
                        $req_insert->close();
                    }
                    $req_check->close();
                }
            }
            // =========================================================

            if ($login_ok) {
                session_regenerate_id(true);
                // تحديث last_login
                $user_id = (int)$user['id'];
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
                
                // إعادة التوجيه حسب نوع المستخدم
                $user_type = $_SESSION['user_type'];
                if ($user_type == 'تاجر') {
                    header("Location: home111.php");
                } else {
                    header("Location: Home1.html");
                }
                exit;
            }
        } else {
            $error = "كلمة المرور غير صحيحة";
        }
    } else {
        $error = "البريد الإلكتروني غير مسجل";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Cairo", sans-serif;
        }

        body {
            height: 100vh;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .center-box {
            width: 400px;
            background-color: rgba(0, 0, 0, 0.03);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .title-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            color: #0f172a;
            margin-bottom: 15px;
        }

        .title-icon i {
            font-size: 26px;
            color: #2563eb;
        }

        .title-icon h1 {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
        }

        .center-box p {
            color: #475569;
            margin-bottom: 25px;
        }

        form {
            text-align: right;
        }

        label {
            display: block;
            margin: 15px 0 5px;
            font-weight: bold;
            color: #0f172a;
        }

        input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 16px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 5px rgba(37, 99, 235, 0.4);
        }

        .note {
            display: block;
            font-size: 13px;
            color: #facc15;
            margin-top: 4px;
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            background: linear-gradient(90deg, #2563eb, #1e3a8a);
            color: white;
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: linear-gradient(90deg, #1e3a8a, #2563eb);
        }

        .forgot {
            display: inline-block;
            margin-top: 10px;
            color: #2563eb;
            text-decoration: none;
        }

        .forgot:hover {
            text-decoration: underline;
        }

        .create {
            margin-top: 20px;
            text-align: center;
            color: #475569;
        }

        .create a {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .create a:hover {
            text-decoration: underline;
        }

        .error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="center-box">
        <div class="title-icon" style="flex-direction: column; gap: 12px;">
            <img src="brand_logo.php?f=logo" alt="Miskova Global" style="max-width: 140px; max-height: 90px; object-fit: contain; border-radius: 8px;">
            <h1>تسجيل الدخول</h1>
        </div>
        
        <p>سجل الدخول إلى حسابك للمتابعة</p>

        <?php if(isset($error)): ?>
            <div class="error">
                <i class='bx bx-error-circle'></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['success'])): ?>
            <div class="success">
                <i class='bx bx-check-circle'></i>
                تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input type="hidden" name="js_device_token" id="js_device_token" value="">
            <label for="email">البريد الإلكتروني</label>
            <input type="email" id="email" name="email" required>
            
            <label for="password">كلمة المرور</label>
            <input type="password" id="password" name="password" required>
            
            <button type="submit">تسجيل الدخول</button>
        </form>

        <a href="forgot_password.php" class="forgot">نسيت كلمة المرور؟</a>
        
        <div class="create">
            ليس لديك حساب؟ <a href="signup.php">إنشاء حساب جديد</a>
        </div>
        
        <div style="margin-top: 15px; font-size: 10px; color: #999; text-align: center;" id="debug-token-display">
            <!-- Debug Token -->
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var localToken = localStorage.getItem('system_device_token');
            var phpToken = "<?= htmlspecialchars($_COOKIE['user_device_id'] ?? '') ?>";
            
            if (phpToken && phpToken !== localToken) {
                // PHP Cookie is the source of truth if it exists
                localStorage.setItem('system_device_token', phpToken);
                localToken = phpToken;
            } else if (!localToken && !phpToken) {
                // Generate new only if both are missing
                localToken = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                    .map(b => b.toString(16).padStart(2, '0')).join('');
                localStorage.setItem('system_device_token', localToken);
                document.cookie = "user_device_id=" + localToken + "; max-age=" + (10*365*24*60*60) + "; path=/";
            }
            
            if (localToken) {
                document.getElementById('js_device_token').value = localToken;
                document.getElementById('debug-token-display').innerText = "Device ID: " + localToken.substring(0, 8) + "...";
            }
            
            document.getElementById('loginForm').addEventListener('submit', function() {
                var currentLocal = localStorage.getItem('system_device_token');
                if (!currentLocal) {
                    var newToken = Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2);
                    localStorage.setItem('system_device_token', newToken);
                    document.getElementById('js_device_token').value = newToken;
                    document.getElementById('debug-token-display').innerText = "Device ID: " + newToken.substring(0, 8) + "...";
                } else {
                    document.getElementById('js_device_token').value = currentLocal;
                }
            });
        });
    </script>
</body>
</html>