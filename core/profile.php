<?php
// ملف: profile.php
session_start();
include(__DIR__ . '/core/config.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user = null;
$user_query = null;

if ($user_id && isset($conn)) {
    $user_id = (int)$user_id;
    $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user_query) {
        $user = $user_query->fetch_assoc();
    }
}

// التأكد من وجود جميع الحقول المطلوبة
if (!$user) {
    header("Location: login.html");
    exit;
}

// تهيئة الحقول المفقودة بقيم افتراضية
$user['phone'] = $user['phone'] ?? '';
$user['region'] = $user['region'] ?? '';
$user['address'] = $user['address'] ?? '';
$user['national_id'] = $user['national_id'] ?? '';
$user['user_type'] = $user['user_type'] ?? 'عادي';
$user['bank_name'] = $user['bank_name'] ?? '';
$user['bank_account'] = $user['bank_account'] ?? '';
$user['fullname'] = $user['fullname'] ?? '';
$user['email'] = $user['email'] ?? '';

// تحديث بيانات المستخدم
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $region = $conn->real_escape_string($_POST['region']);
    $address = $conn->real_escape_string($_POST['address']);
    $national_id = $conn->real_escape_string($_POST['national_id']);
    $bank_name = $conn->real_escape_string($_POST['bank_name']);
    $bank_account = $conn->real_escape_string($_POST['bank_account']);
    
    $stmt = $conn->prepare("UPDATE users SET fullname = ?, phone = ?, region = ?, address = ?, national_id = ?, bank_name = ?, bank_account = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $fullname, $phone, $region, $address, $national_id, $bank_name, $bank_account, $user_id);
    
    if ($stmt->execute()) {
        $success = "تم تحديث البيانات بنجاح!";
        // إعادة تحميل بيانات المستخدم
        $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
        $user = $user_query->fetch_assoc();
    } else {
        $error = "حدث خطأ أثناء تحديث البيانات: " . $stmt->error;
    }
}

// تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // التحقق من كلمة المرور (دعم كلمة المرور الافتراضية وكلمات المرور المشفرة)
    $password_valid = false;
    if ($current_password == '123456' || (isset($user['password']) && password_verify($current_password, $user['password']))) {
        $password_valid = true;
    }
    
    if ($password_valid) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password = '$new_password_hash' WHERE id = $user_id");
                $success = "تم تغيير كلمة المرور بنجاح!";
            } else {
                $error = "كلمة المرور يجب أن تكون 6 أحرف على الأقل";
            }
        } else {
            $error = "كلمة المرور الجديدة غير متطابقة";
        }
    } else {
        $error = "كلمة المرور الحالية غير صحيحة";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 100px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <?php
    $current_page = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $current_page = str_replace(['.php', '.html'], '', $current_page);
    
    $user_name = '';
    if ($user_id && $user) {
        $user_name = $user['fullname'] ?? '';
    }
    ?>
    <style>
        .main-navbar {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 72rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.1);
            border-radius: 1rem;
            z-index: 1000;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .main-navbar.hidden {
            transform: translateX(-50%) translateY(-150%);
            opacity: 0;
        }
        .navbar-logo {
            font-size: 2rem;
            font-weight: 900;
            color: #4b6b2f;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .navbar-logo:hover {
            color: #3a5524;
            transform: scale(1.05);
        }
        .navbar-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        .navbar-link {
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            position: relative;
            padding-bottom: 5px;
        }
        .navbar-link.active {
            color: #4b6b2f;
        }
        .navbar-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #4b6b2f;
            border-radius: 2px;
        }
        .navbar-link:hover {
            color: #4b6b2f;
            transform: translateY(-2px);
        }
        .navbar-link:hover::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #4b6b2f;
            border-radius: 2px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { width: 0; }
            to { width: 100%; }
        }
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            background: rgba(75, 107, 47, 0.1);
        }
        .navbar-user:hover {
            background: rgba(75, 107, 47, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(75, 107, 47, 0.2);
        }
        .navbar-user i {
            font-size: 1.5rem;
            color: #4b6b2f;
            transition: transform 0.3s ease;
        }
        .navbar-user:hover i {
            transform: scale(1.1);
        }
        .navbar-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .navbar-btn-login {
            background: #4b6b2f;
            color: white;
        }
        .navbar-btn-login:hover {
            background: #3a5524;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(75, 107, 47, 0.4);
        }
        .navbar-btn-register {
            background: transparent;
            color: #4b6b2f;
            border: 2px solid #4b6b2f;
        }
        .navbar-btn-register:hover {
            background: #4b6b2f;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(75, 107, 47, 0.4);
        }
        .navbar-btn-logout {
            background: #ef4444;
            color: white;
        }
        .navbar-btn-logout:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
    </style>
    <nav class="main-navbar" id="mainNavbar">
        <div style="display: flex; align-items: center; gap: 2rem;">
            <a href="Home1.html" class="navbar-logo">لوحة المسوق</a>
            <div class="navbar-links">
                <a href="Home1.html" class="navbar-link <?php echo ($current_page == 'Home1' || $current_page == 'home') ? 'active' : ''; ?>">الرئيسية</a>
                <a href="product.html" class="navbar-link <?php echo ($current_page == 'product') ? 'active' : ''; ?>">المنتجات</a>
                <a href="orders.html" class="navbar-link <?php echo ($current_page == 'orders') ? 'active' : ''; ?>">الطلبات</a>
                <a href="withdrawals.php" class="navbar-link <?php echo ($current_page == 'withdrawals') ? 'active' : ''; ?>">السحب</a>
            </div>
        </div>
        <div class="navbar-actions">
            <?php if ($user_id && $user): ?>
                <a href="profile.php" class="navbar-user">
                    <i class='bx bx-user-circle'></i>
                    <span><?php echo htmlspecialchars($user_name); ?></span>
                </a>
                <a href="logout.php" class="navbar-btn navbar-btn-logout">تسجيل الخروج</a>
            <?php else: ?>
                <a href="login.html" class="navbar-btn navbar-btn-login">تسجيل الدخول</a>
                <a href="register.html" class="navbar-btn navbar-btn-register">إنشاء حساب</a>
            <?php endif; ?>
        </div>
    </nav>
    <script>
        let lastScroll = 0;
        const navbar = document.getElementById('mainNavbar');
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll <= 0) {
                navbar.classList.remove('hidden');
                return;
            }
            
            if (currentScroll > lastScroll && currentScroll > 100) {
                // Scrolling down
                navbar.classList.add('hidden');
            } else {
                // Scrolling up
                navbar.classList.remove('hidden');
            }
            
            lastScroll = currentScroll;
        });
        
        // تحديث عداد السلة
    </script>
    
    <div class="container mx-auto px-4 py-8">
        <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
            <i class='bx bx-check-circle mr-2'></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
            <i class='bx bx-error-alt mr-2'></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">الملف الشخصي</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- معلومات الحساب -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-4">المعلومات الشخصية</h2>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">الاسم الكامل</label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">البريد الإلكتروني</label>
                                <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">رقم الهاتف</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">المنطقة</label>
                                <input type="text" name="region" value="<?php echo htmlspecialchars($user['region'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">العنوان</label>
                                <textarea name="address" class="w-full px-3 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">الرقم القومي</label>
                                <input type="text" name="national_id" value="<?php echo htmlspecialchars($user['national_id'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">نوع الحساب</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['user_type'] ?? 'عادي'); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">اسم البنك</label>
                                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">رقم الحساب</label>
                                <input type="text" name="bank_account" value="<?php echo htmlspecialchars($user['bank_account'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-4 bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700">
                            حفظ التعديلات
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- الإحصائيات -->
            <div>
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">إحصائيات الحساب</h2>
                    
                    <div class="space-y-4">
                        <?php
                        // حساب الإحصائيات من قاعدة البيانات
                        // حساب صافي الربح الحقيقي من الطلبات المنفذة
                        $orders_query = $conn->query("SELECT oi.original_price, oi.commission, oi.shipping_cost, oi.is_shipping_included, oi.quantity FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')");
                        $total_profit = 0;
                        while ($row = $orders_query->fetch_assoc()) {
                            $net = ($row['original_price'] - $row['commission'] - ($row['is_shipping_included'] == 0 ? $row['shipping_cost'] : 0)) * $row['quantity'];
                            $total_profit += $net;
                        }
                        $total_withdrawn = $conn->query("SELECT SUM(amount) as total FROM withdrawals WHERE user_id = $user_id AND status = 'مكتمل'")->fetch_assoc()['total'] ?? 0;
                        $total_orders = $conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id")->fetch_assoc()['total'] ?? 0;
                        $available_balance = floatval($total_profit) - floatval($total_withdrawn);
                        if ($available_balance < 0) $available_balance = 0;
                        ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">الرصيد المتاح</span>
                            <span class="font-bold text-green-600"><?php echo number_format($available_balance, 2); ?> د.ل</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">إجمالي الأرباح</span>
                            <span class="font-bold text-blue-600"><?php echo number_format($total_commissions, 2); ?> د.ل</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">إجمالي المسحوب</span>
                            <span class="font-bold text-purple-600"><?php echo number_format($total_withdrawn, 2); ?> د.ل</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">إجمالي الطلبات</span>
                            <span class="font-bold text-gray-800"><?php echo $total_orders; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">نوع الحساب</span>
                            <span class="font-bold text-indigo-600"><?php echo htmlspecialchars($user['user_type'] ?? 'عادي'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- تغيير كلمة المرور -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-4">تغيير كلمة المرور</h2>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 mb-2">كلمة المرور الحالية</label>
                                <input type="password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">كلمة المرور الجديدة</label>
                                <input type="password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">تأكيد كلمة المرور</label>
                                <input type="password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            تغيير كلمة المرور
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>