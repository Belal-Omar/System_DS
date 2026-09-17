<?php
// ملف: forgot_password.php
session_start();
include(__DIR__ . '/../core/config.php");

$error = '';
$success = '';
$step = 1; // 1: طلب الكود، 2: التحقق وتغيير كلمة المرور

// معالجة طلب كود إعادة التعيين
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_code'])) {
    $email = clean_input($_POST['email']);
    
    // التحقق من وجود البريد الإلكتروني
    $result = $conn->query("SELECT id, fullname FROM users WHERE email = '$email'");
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // إنشاء كود مكون من 6 أرقام
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // حفظ الكود في قاعدة البيانات
        $conn->query("DELETE FROM password_resets WHERE user_id = {$user['id']} OR expires_at < NOW()");
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user['id'], $code, $expires_at);
        
        if ($stmt->execute()) {
            // إرسال البريد الإلكتروني (تصميم احترافي)
            $subject = "كود التحقق لإعادة تعيين كلمة المرور";
            
            $message = "
            <div dir='rtl' style='font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #2563eb 0%, #1e3a8a 100%); padding: 30px; text-align: center;'>
                        <h1 style='color: white; margin: 0; font-size: 24px;'>إعادة تعيين كلمة المرور</h1>
                    </div>
                    <div style='padding: 40px; color: #333333; line-height: 1.6; text-align: right;'>
                        <p style='font-size: 18px;'>مرحباً <strong>" . htmlspecialchars($user['fullname']) . "</strong>،</p>
                        <p>لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بك. يرجى استخدام الكود التالي:</p>
                        
                        <div style='background-color: #f1f5f9; border: 2px dashed #2563eb; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0;'>
                            <p style='margin: 0 0 10px 0; color: #64748b; font-size: 14px;'>كود التحقق الخاص بك</p>
                            <div style='font-size: 36px; font-weight: bold; color: #1e3a8a; letter-spacing: 10px;'>" . $code . "</div>
                        </div>
                        
                        <p style='color: #dc2626; font-weight: bold;'>هذا الكود صالح لمدة 15 دقيقة فقط.</p>
                        <p>إذا لم تطلب إرسال هذا الكود، يرجى تجاهل هذا البريد الإلكتروني. لن يتم إجراء أي تغيير على حسابك.</p>
                    </div>
                    <div style='background-color: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e2e8f0;'>
                        <p style='margin: 0;'>&copy; " . date('Y') . " " . $_SERVER['HTTP_HOST'] . ". جميع الحقوق محفوظة.</p>
                    </div>
                </div>
            </div>";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";

            if (mail($email, $subject, $message, $headers)) {
                $success = "تم إرسال كود التحقق إلى بريدك الإلكتروني.";
                $_SESSION['reset_email'] = $email; // حفظ البريد للمرحلة التالية
                $step = 2;
            } else {
                // للمساعدة في التطوير إذا فشل الإرسال
                $error = "فشل في إرسال البريد الإلكتروني. يرجى التأكد من إعدادات الخادم.";
                // $error .= " الكود (للتجربة): " . $code; 
            }
        } else {
            $error = "حدث خطأ أثناء إنشاء كود التحقق";
        }
    } else {
        $error = "البريد الإلكتروني غير مسجل";
    }
}

// معالجة إعادة تعيين كلمة المرور بالكود
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $email = $_SESSION['reset_email'] ?? '';
    $code = clean_input($_POST['code']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $step = 2;

    if (!$email) {
        $error = "انتهت الجلسة، يرجى إعادة الطلب";
        $step = 1;
    } elseif ($password !== $confirm_password) {
        $error = "كلمات المرور غير متطابقة";
    } elseif (strlen($password) < 6) {
        $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
    } else {
        // التحقق من صحة الكود
        $result = $conn->query("
            SELECT pr.user_id 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE u.email = '$email' 
            AND pr.token = '$code' 
            AND pr.expires_at > NOW() 
            AND pr.used = 0
        ");
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $user_id = $data['user_id'];
            
            // تحديث كلمة المرور
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $conn->query("UPDATE password_resets SET used = 1 WHERE token = '$code' AND user_id = $user_id");
                $success = "تم تغيير كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول.";
                unset($_SESSION['reset_email']);
                $step = 1; // العودة للبداية بعد النجاح
            } else {
                $error = "حدث خطأ أثناء تحديث كلمة المرور";
            }
        } else {
            $error = "كود التحقق غير صحيح أو منتهي الصلاحية";
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

        .success a {
            color: #065f46;
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="center-box">
        <div class="title-icon">
            <i class='bx bx-lock-alt'></i>
            <h1>إعادة تعيين كلمة المرور</h1>
        </div>
        
        <?php if($error): ?>
            <div class="error">
                <i class='bx bx-error-circle'></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if($success && $step == 1): ?>
            <div class="success">
                <i class='bx bx-check-circle'></i>
                <?= $success ?>
            </div>
            <a href="login.php" class="back-link">العودة لتسجيل الدخول</a>
        <?php else: ?>
            <?php if($step == 1): ?>
                <p>أدخل بريدك الإلكتروني لإرسال كود التحقق</p>
                <form method="POST">
                    <label for="email">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" required placeholder="example@mail.com">
                    
                    <button type="submit" name="request_code">إرسال كود التحقق</button>
                </form>
            <?php elseif($step == 2): ?>
                <p>تم إرسال كود إلى <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong></p>
                
                <?php if($success): ?>
                    <div class="success">
                        <i class='bx bx-check-circle'></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <label for="code">كود التحقق (6 أرقام)</label>
                    <input type="text" id="code" name="code" required maxlength="6" pattern="\d{6}" placeholder="000000" style="text-align: center; font-size: 24px; letter-spacing: 5px;">
                    
                    <label for="password">كلمة المرور الجديدة</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="******">
                    
                    <label for="confirm_password">تأكيد كلمة المرور</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="******">
                    
                    <button type="submit" name="reset_password">تغيير كلمة المرور</button>
                </form>
                
                <div style="margin-top: 20px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['reset_email']) ?>">
                        <button type="submit" name="request_code" style="background: none; color: #2563eb; font-size: 14px; padding: 0; margin: 0; width: auto; font-weight: normal; cursor: pointer;">أعد إرسال الكود</button>
                    </form>
                    | 
                    <a href="forgot_password.php" class="back-link" style="margin: 0; font-size: 14px;">تغيير البريد الإلكتروني</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div style="margin-top: 20px; border-top: 1px solid #e2e8f0; pt-4">
            <a href="login.php" class="back-link">العودة لتسجيل الدخول</a>
        </div>
    </div>
</body>
</html>
