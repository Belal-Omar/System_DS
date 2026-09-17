<?php
// ملف: admin_login.php
include(__DIR__ . '/../core/config.php");
session_start();
/** @var mysqli $conn */
include(__DIR__ . '/../core/helpers.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf()) {
        $error = "انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.";
    } else {
        $username = clean_input($_POST['username']);
        $password = clean_input($_POST['password']);
        $login_failed_message = 'اسم المستخدم أو كلمة المرور غير صحيحة';

        // البحث عن المدير في قاعدة البيانات
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        $login_ok = false;
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $login_ok = true;
                
                // ================== Device Binding Logic ==================
                // 1. Create schema if not exists (silent)
                @$conn->query("ALTER TABLE admins ADD COLUMN allowed_device_id VARCHAR(255) NULL");
                @$conn->query("ALTER TABLE users ADD COLUMN allowed_device_id VARCHAR(255) NULL");
                @$conn->query("CREATE TABLE IF NOT EXISTS device_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_type ENUM('admin', 'user') NOT NULL,
                    account_id INT NOT NULL,
                    requested_device_id VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                // 2. Determine or generate device ID
                $device_cookie = $_COOKIE['user_device_id'] ?? null;
                if (isset($_POST['js_device_token']) && !empty($_POST['js_device_token'])) {
                    $device_cookie = $_POST['js_device_token']; // Fallback to LocalStorage token if Cookie was cleared
                }
                if (!$device_cookie) {
                    $device_cookie = bin2hex(random_bytes(16));
                }
                // Always refresh cookie
                setcookie('user_device_id', $device_cookie, time() + (10 * 365 * 24 * 60 * 60), "/");

                $is_super_admin = ($admin['role'] === 'super_admin');
                
                if (!$is_super_admin) {
                    // Multi-device Binding Logic
                    $req_approved = $conn->prepare("SELECT id FROM device_requests WHERE account_type = 'admin' AND account_id = ? AND requested_device_id = ? AND status = 'approved'");
                    $req_approved->bind_param("is", $admin['id'], $device_cookie);
                    $req_approved->execute();
                    $is_device_approved = $req_approved->get_result()->num_rows > 0;
                    $req_approved->close();

                    if ($is_device_approved) {
                        $login_ok = true;
                    } else {
                        // Check if they have ANY approved devices at all
                        $any_approved = $conn->prepare("SELECT id FROM device_requests WHERE account_type = 'admin' AND account_id = ? AND status = 'approved'");
                        $any_approved->bind_param("i", $admin['id']);
                        $any_approved->execute();
                        $has_any_device = $any_approved->get_result()->num_rows > 0;
                        $any_approved->close();

                        if (!$has_any_device) {
                            // First device ever - auto approve
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            $stmt_ins = $conn->prepare("INSERT INTO device_requests (account_type, account_id, requested_device_id, ip_address, user_agent, status, created_at) VALUES ('admin', ?, ?, ?, ?, 'approved', NOW())");
                            $stmt_ins->bind_param("isss", $admin['id'], $device_cookie, $ip, $ua);
                            $stmt_ins->execute();
                            $stmt_ins->close();
                            
                            // Keep allowed_device_id populated just for legacy fallback if needed
                            $stmt_update = $conn->prepare("UPDATE admins SET allowed_device_id = ? WHERE id = ?");
                            $stmt_update->bind_param("si", $device_cookie, $admin['id']);
                            $stmt_update->execute();
                            $stmt_update->close();
                            
                            $login_ok = true;
                        } else {
                            // Has devices, but THIS one is not approved
                            $login_ok = false;
                            $error = "عذراً لم يتم التعرف على هذا الجهاز. لا يمكنك تسجيل الدخول من هذا الجهاز. لقد تم إرسال طلب للموافقة عليه، يرجى الانتظار.";
                            
                            // Check if pending request already exists for THIS device
                            $req_check = $conn->prepare("SELECT id FROM device_requests WHERE account_type = 'admin' AND account_id = ? AND requested_device_id = ? AND status = 'pending'");
                            $req_check->bind_param("is", $admin['id'], $device_cookie);
                            $req_check->execute();
                            $req_check_res = $req_check->get_result();
                            if ($req_check_res->num_rows === 0) {
                                $req_insert = $conn->prepare("INSERT INTO device_requests (account_type, account_id, requested_device_id, ip_address, user_agent, status, created_at) VALUES ('admin', ?, ?, ?, ?, 'pending', NOW())");
                                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                $req_insert->bind_param("isss", $admin['id'], $device_cookie, $ip, $ua);
                                if ($req_insert->execute()) {
                                    $adminName = $conn->real_escape_string($admin['fullname'] ?? 'مجهول');
                                    $msg = "محاولة دخول من جهاز جديد للموظف: $adminName";
                                    $conn->query("INSERT INTO system_notifications (recipient_type, recipient_id, title, message, link) VALUES ('admin', 0, 'تنبيه أمني - جهاز جديد', '$msg', 'admin_panel.php?page=devices')");
                                }
                                $req_insert->close();
                            }
                            $req_check->close();
                        }
                    }
                }
                // =========================================================

                if ($login_ok) {
                    session_regenerate_id(true);
                    
                    $update_stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                    $update_stmt->bind_param("i", $admin['id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_fullname'] = $admin['fullname'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_allowed_pages'] = $admin['allowed_pages'] ?? null;
                    $_SESSION['admin_allowed_pages_loaded'] = true;
                    $_SESSION['shipping_company_id'] = $admin['shipping_company_id'] ?? null;

                    header("Location: admin_panel.php");
                    exit;
                }
            }
        } else {
            // نفس وقت التحقق تقريباً — لا نكشف إن كان الاسم موجوداً أم لا
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
        }

        if (!$login_ok) {
            if (!isset($error)) {
                $error = $login_failed_message;
            }
        }

        $stmt->close();
    }
}
ensure_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول المدير</title>
    <link rel="icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="shortcut icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="apple-touch-icon" href="brand_logo.php?f=logo">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f6f8f4; /* نفس خلفية صفحاتك */
            font-family: 'Cairo', sans-serif;
        }
        .login-card {
            background: #e5e8e2;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border: 1px solid #d1d5cc;
        }
        .btn-primary {
            background: #4b6b2f;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #3a5524;
        }
        .input-focus:focus {
            border-color: #4b6b2f;
            box-shadow: 0 0 0 3px rgba(75, 107, 47, 0.1);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 md:p-8">
    <div class="login-card p-6 md:p-8 rounded-2xl w-full max-w-md mx-auto">
        <div class="text-center mb-8">
            <div class="mx-auto mb-4 flex items-center justify-center">
                <img src="brand_logo.php?f=logo" alt="Miskova Global" style="max-width: 160px; max-height: 100px; width: auto; height: auto; object-fit: contain;">
            </div>
            <h2 class="text-2xl font-bold text-gray-800">لوحة الإدارة</h2>
            <p class="text-gray-600 mt-2">تسجيل دخول المديرين</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <p class="font-bold">خطأ!</p>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6" id="loginForm">
            <input type="hidden" name="js_device_token" id="js_device_token" value="">
            <?= csrf_field() ?>
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم</label>
                <div class="relative">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class='bx bx-user text-gray-400'></i>
                    </div>
                    <input type="text" id="username" name="username" required
                           class="input-focus block w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md focus:outline-none transition-shadow"
                           placeholder="أدخل اسم المستخدم">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
                <div class="relative">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class='bx bx-lock-alt text-gray-400'></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           class="input-focus block w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md focus:outline-none transition-shadow"
                           placeholder="أدخل كلمة المرور">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="btn-primary w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4b6b2f]">
                    تسجيل الدخول
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-500">
            &copy; <?= date('Y') ?> جميع الحقوق محفوظة
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