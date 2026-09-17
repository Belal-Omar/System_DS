<?php
// ملف: reset_password.php
session_start();
include(__DIR__ . '/../core/config.php");

$error = '';
$success = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

// التحقق من وجود رمز في الرابط
if ($token) {
    // تنظيف الرمز بشكل آمن
    $token_clean = $conn->real_escape_string($token);
    
    // التحقق من وجود الجدول أولاً
    $table_check = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if (!$table_check || $table_check->num_rows == 0) {
        $error = "جدول إعادة تعيين كلمة المرور غير موجود. يرجى طلب رابط جديد.";
    } else {
        // التحقق من صحة الرمز
        $result = $conn->query("
            SELECT pr.user_id, u.email, u.fullname, pr.expires_at, pr.used
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = '$token_clean'
        ");
        
        if ($result && $result->num_rows > 0) {
            $token_data = $result->fetch_assoc();
            
            // التحقق من انتهاء الصلاحية
            $expires_at = strtotime($token_data['expires_at']);
            $now = time();
            
            if ($token_data['used'] == 1) {
                $error = "تم استخدام هذا الرابط مسبقاً. يرجى طلب رابط جديد.";
            } elseif ($expires_at <= $now) {
                $error = "انتهت صلاحية الرابط. يرجى طلب رابط جديد.";
            } else {
                $valid_token = true;
                $user_data = $token_data;
            }
        } else {
            $error = "الرمز غير صالح. يرجى التحقق من الرابط أو طلب رابط جديد.";
        }
    }
} else {
    $error = "لم يتم توفير رمز إعادة التعيين";
}

// معالجة إعادة تعيين كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $token = clean_input($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "كلمات المرور غير متطابقة";
    } elseif (strlen($password) < 6) {
        $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } else {
        // تنظيف الرمز
        $token_clean = $conn->real_escape_string($token);
        
        // التحقق من صحة الرمز
        $result = $conn->query("
            SELECT pr.user_id, u.email, pr.expires_at, pr.used
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = '$token_clean'
        ");
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // التحقق من انتهاء الصلاحية والاستخدام
            $expires_at = strtotime($data['expires_at']);
            $now = time();
            
            if ($data['used'] == 1) {
                $error = "تم استخدام هذا الرابط مسبقاً. يرجى طلب رابط جديد.";
            } elseif ($expires_at <= $now) {
                $error = "انتهت صلاحية الرابط. يرجى طلب رابط جديد.";
            } else {
                $user_id = $data['user_id'];
                
                // تحديث كلمة المرور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    // تعليم الرمز كمستخدم
                    $conn->query("UPDATE password_resets SET used = 1 WHERE token = '$token_clean'");
                    $success = "تم إعادة تعيين كلمة المرور بنجاح! يمكنك تسجيل الدخول الآن.";
                    $valid_token = false; // إخفاء النموذج بعد النجاح
                } else {
                    $error = "حدث خطأ أثناء تحديث كلمة المرور: " . $stmt->error;
                }
            }
        } else {
            $error = "الرمز غير صالح. يرجى التحقق من الرابط أو طلب رابط جديد.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
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

        .back-link {
            display: inline-block;
            margin-top: 10px;
            color: #2563eb;
            text-decoration: none;
        }

        .back-link:hover {
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
        <div class="title-icon">
            <i class='bx bx-lock-alt'></i>
            <h1>إعادة تعيين كلمة المرور</h1>
        </div>
        
        <?php if($error && !$valid_token): ?>
            <div class="error">
                <i class='bx bx-error-circle'></i>
                <?= $error ?>
            </div>
            <a href="forgot_password.php" class="back-link">طلب رابط جديد</a>
        <?php elseif($success): ?>
            <div class="success">
                <i class='bx bx-check-circle'></i>
                <?= $success ?>
            </div>
            <a href="login.php" class="back-link">العودة لتسجيل الدخول</a>
        <?php elseif($valid_token): ?>
            <p>أدخل كلمة المرور الجديدة</p>
            <?php if($error): ?>
                <div class="error">
                    <i class='bx bx-error-circle'></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <label for="password">كلمة المرور الجديدة</label>
                <input type="password" id="password" name="password" required minlength="6">
                
                <label for="confirm_password">تأكيد كلمة المرور</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                
                <button type="submit" name="reset_password">إعادة تعيين كلمة المرور</button>
            </form>
        <?php else: ?>
            <div class="error">
                <i class='bx bx-error-circle'></i>
                الرمز غير صالح أو منتهي الصلاحية
            </div>
            <a href="forgot_password.php" class="back-link">طلب رابط جديد</a>
        <?php endif; ?>
        
        <a href="login.php" class="back-link">العودة لتسجيل الدخول</a>
    </div>
</body>
</html>

