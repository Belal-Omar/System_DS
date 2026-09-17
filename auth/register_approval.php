<?php
// ملف: register_approval.php - إنشاء حساب جديد بانتظار الموافقة
session_start();
include(__DIR__ . '/core/config.php");

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
            // إدراج المستخدم الجديد مع is_active = 0 (بانتظار الموافقة)
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, region, address, national_id, bank_name, bank_account, password, user_type, wallet_balance, total_earnings, total_withdrawn, balance, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 0)");
            $stmt->bind_param("ssssssssss", $fullname, $email, $phone, $region, $address, $national_id, $bank_name, $bank_account, $password, $user_type);
            
            if ($stmt->execute()) {
                $success = "تم إنشاء الحساب بنجاح! في انتظار مراجعته من قبل المسؤول.";
            } else {
                $error = "حدث خطأ أثناء إنشاء الحساب: " . $stmt->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <img src="brand_logo.php?f=logo" alt="Miskova Global" class="mx-auto mb-4" style="max-width: 140px; max-height: 90px; object-fit: contain; border-radius: 8px;">
            <h2 class="text-2xl font-bold text-gray-800">إنشاء حساب جديد</h2>
            <p class="text-gray-600 mt-2">سيتم مراجعة طلبك من قبل الإدارة</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                <i class='bx bx-error-circle ml-2'></i><?= $error ?>
            </div>
        <?php endif; ?>

        <?php if(isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-center">
                <i class='bx bx-check-circle ml-2'></i><?= $success ?>
            </div>
            <div class="text-center">
                <a href="login.html" class="text-purple-600 hover:text-purple-800 font-medium">الذهاب لتسجيل الدخول</a>
            </div>
        <?php else: ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">الاسم بالكامل *</label>
                <input type="text" name="fullname" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">البريد الإلكتروني *</label>
                <input type="email" name="email" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">رقم الهاتف *</label>
                <input type="tel" name="phone" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">نوع المستخدم *</label>
                <select name="user_type" required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
                    <option value="مسوق">مسوق</option>
                    <option value="تاجر">تاجر</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">المنطقة</label>
                <input type="text" name="region" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">العنوان</label>
                <textarea name="address" rows="2" 
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500"></textarea>
            </div>

            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">كلمة المرور *</label>
                <input type="password" name="password" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-purple-500">
            </div>

            <button type="submit" 
                    class="w-full bg-purple-600 text-white py-3 rounded-lg hover:bg-purple-700 transition font-bold">
                إنشاء الحساب
            </button>
        </form>

        <div class="text-center mt-6">
            <p class="text-gray-600">لديك حساب بالفعل؟ 
                <a href="login.html" class="text-purple-600 hover:text-purple-800 font-medium">تسجيل الدخول</a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
