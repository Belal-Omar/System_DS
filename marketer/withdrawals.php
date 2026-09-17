<?php
// ملف: withdrawals.php
session_start();
include(__DIR__ . '/../core/config.php");
/** @var mysqli $conn */
include(__DIR__ . '/../core/helpers.php");

// التحقق من تسجيل الدخول ونوع المستخدم
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// إذا كان المستخدم مسجل دخول، استخدم بياناته
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

// منع التاجر من دخول صفحات المسوق
if ($user && ($user['user_type'] ?? '') == 'تاجر') {
    header("Location: home111.php");
    exit;
}

// حساب الأرباح المتاحة للسحب (للمستخدم المسجل فقط)
$available_profit = 0;
$withdrawn_amount = 0;
$available_withdrawal = 0;
$commissions_check = null;
$commissions_query = null;

if ($user_id) {
    // استخدام نظام الأرصدة المحدث لضمان الدقة
    if (file_exists("balance_system.php")) {
        include_once("balance_system.php");
        $balanceSystem = new BalanceSystem($conn);
        $balanceData = $balanceSystem->refreshUserBalance($user_id);
        
        $available_profit = $balanceData['total_earnings'];
        $available_withdrawal = $balanceData['available_balance'];
        $withdrawn_amount = $balanceData['withdrawn_balance'];
        $total_special = $balanceData['total_special'] ?? 0;
        $gross_profit = $available_profit + $total_special;
    } else {
        // حساب إجمالي العمولة من جدول marketer_commissions (الأولوية) - للطلبات المكتملة فقط
        $commissions_check = $conn->query("SHOW TABLES LIKE 'marketer_commissions'");
        if ($commissions_check && $commissions_check->num_rows > 0) {
            $available_profit_query = $conn->prepare("
                SELECT SUM(mc.commission_amount) as total 
                FROM marketer_commissions mc
                INNER JOIN orders o ON mc.order_id = o.id
                WHERE mc.user_id = ? AND mc.status = 'مكتمل' AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
            ");
            $available_profit_query->bind_param("i", $user_id);
            $available_profit_query->execute();
            $available_profit_result = $available_profit_query->get_result();
            $available_profit_row = $available_profit_result->fetch_assoc();
            $available_profit = $available_profit_row['total'] ? floatval($available_profit_row['total']) : 0;
        } else {
            // الرجوع للطريقة القديمة إذا لم يكن جدول العمولات موجود - للطلبات المكتملة فقط
            $available_profit_query = $conn->prepare("
                SELECT SUM(commission_total) as total 
                FROM orders 
                WHERE user_id = ? AND status IN ('تم التوصيل', 'محصل', 'مكتمل')
            ");
            $available_profit_query->bind_param("i", $user_id);
            $available_profit_query->execute();
            $available_profit_result = $available_profit_query->get_result();
            $available_profit_row = $available_profit_result->fetch_assoc();
            $available_profit = $available_profit_row['total'] ? floatval($available_profit_row['total']) : 0;
        }

        // حساب المبلغ المسحوب سابقاً (الطلبات المكتملة فقط)
        $withdrawn_query = $conn->query("
            SELECT SUM(amount) as total 
            FROM withdrawals 
            WHERE user_id = $user_id AND status = 'مكتمل'
        ");
        $withdrawn_row = $withdrawn_query->fetch_assoc();
        $withdrawn_amount = $withdrawn_row['total'] ? floatval($withdrawn_row['total']) : 0;

        // المبلغ المتاح للسحب
        $available_withdrawal = $available_profit - $withdrawn_amount;
    }

    // المبلغ المتاح للسحب = إجمالي العمولة - المبلغ المسحوب سابقاً
    $available_withdrawal = $available_profit - $withdrawn_amount;
    
    // التأكد من أن المبلغ المتاح ليس سالباً
    if ($available_withdrawal < 0) {
        $available_withdrawal = 0;
    }
    
    // جلب العمولات المضافة حديثاً من جدول marketer_commissions (إذا كان الجدول موجوداً)
    $commissions_query = null;
    if ($commissions_check && $commissions_check->num_rows > 0) {
        $commissions_query = $conn->query("
            SELECT mc.*, o.customer_name, o.created_at as order_date
            FROM marketer_commissions mc
            LEFT JOIN orders o ON mc.order_id = o.id
            WHERE mc.user_id = $user_id 
            ORDER BY mc.created_at DESC
        ");
    }
}

// معالجة طلب سحب جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_withdrawal'])) {
    
    // التحقق من تسجيل الدخول أولاً
    if (!$user_id) {
        $error = "يجب تسجيل الدخول أولاً لتقديم طلب سحب";
    } else {
        $amount = floatval($_POST['amount']);
        $phone = $conn->real_escape_string($_POST['phone']);
        
        if ($amount <= 0) {
            $error = "المبلغ يجب أن يكون أكبر من الصفر";
        } else {
            // منع الـ Race Condition باستخدام Transaction و Pessimistic Lock
            $conn->begin_transaction();
            try {
                // قفل صف المستخدم لمنع طلبات متزامنة في نفس اللحظة
                $conn->query("SELECT id FROM users WHERE id = $user_id FOR UPDATE");
                
                // إعادة حساب الرصيد المتاح حالياً داخل الـ Transaction للتأكد
                if (file_exists("balance_system.php")) {
                    include_once("balance_system.php");
                    $bs = new BalanceSystem($conn);
                    $freshBalance = $bs->refreshUserBalance($user_id);
                    $available_withdrawal = $freshBalance['available_balance'];
                }

                if ($amount > $available_withdrawal) {
                    $error = "المبلغ المطلوب أكبر من المبلغ المتاح للسحب";
                    $conn->rollback();
                } else {
                    $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, phone, status) VALUES (?, ?, ?, 'قيد المراجعة')");
                    $stmt->bind_param("ids", $user_id, $amount, $phone);
                    
                    if ($stmt->execute()) {
                        $withdrawal_id = $conn->insert_id;
                        $conn->commit();
                        $success = "تم ارسال طلب السحب بنجاح سيتم مراجعته من قبل الادارة";
                        // تحديث المبلغ المتاح مباشرة
                        $available_withdrawal -= $amount;
                        
                        // إشعار للإدارة بطلب سحب جديد
                        if (function_exists('send_notification')) {
                            $sender = $_SESSION['fullname'] ?? 'مستخدم';
                            $title = "طلب سحب جديد";
                            $msg = "تم تقديم طلب سحب بقيمة $amount من قبل $sender";
                            $link = "admin_panel.php?page=withdrawals";
                            send_notification($conn, 'admin', 0, $title, $msg, $link);
                        }
                    } else {
                        $conn->rollback();
                        $error = "حدث خطأ أثناء إرسال طلب السحب: " . $stmt->error;
                    }
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "حدث خطأ غير متوقع أثناء المعالجة.";
            }
        }
    }
}

// جلب طلبات السحب السابقة (للمستخدم المسجل فقط)
$withdrawals_query = null;
if ($user_id) {
    $withdrawals_query = $conn->query("
        SELECT * FROM withdrawals 
        WHERE user_id = $user_id 
        ORDER BY created_at DESC
    ");
}

    // جلب الطلبات المكتملة فقط مع العمولات (للعرض)
    $completed_orders_query = null;
    $completed_orders = [];
    if ($user_id) {
        $completed_orders_query = $conn->prepare("
            SELECT * FROM orders 
            WHERE user_id = ? AND status IN ('تم التوصيل', 'محصل', 'مكتمل')
            ORDER BY created_at DESC
        ");
        $completed_orders_query->bind_param("i", $user_id);
        $completed_orders_query->execute();
        $completed_orders_result = $completed_orders_query->get_result();
        
        while($order = $completed_orders_result->fetch_assoc()) {
            $completed_orders[] = $order;
        }
    }
    
    // جلب آخر 5 طلبات للمستخدم (للعرض)
    $recent_orders_query = null;
    $recent_orders = [];
    if ($user_id) {
        $recent_orders_query = $conn->query("
            SELECT * FROM orders 
            WHERE user_id = $user_id 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        while($order = $recent_orders_query->fetch_assoc()) {
            $recent_orders[] = $order;
        }
    }

// جلب بيانات المستخدم للـ navbar
$user_name = '';
if ($user_id && $user) {
    $user_name = $user['fullname'] ?? '';
}

// الحصول على الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? 'index.php');
$current_page = str_replace(['.php', '.html'], '', $current_page);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلبات السحب - لوحة المسوق</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* استبدال Tailwind بـ CSS مدمج */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .grid {
            display: grid;
            gap: 1.5rem;
        }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        @media (max-width: 768px) {
            .grid-cols-3 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        }
        .bg-green-100 { background-color: #d1fae5; }
        .bg-blue-100 { background-color: #dbeafe; }
        .bg-purple-100 { background-color: #f3e8ff; }
        .bg-red-100 { background-color: #fee2e2; }
        .bg-yellow-100 { background-color: #fef3c7; }
        .bg-gray-50 { background-color: #f9fafb; }
        .text-green-600 { color: #059669; }
        .text-blue-600 { color: #2563eb; }
        .text-purple-600 { color: #9333ea; }
        .text-red-600 { color: #dc2626; }
        .text-gray-600 { color: #4b5563; }
        .text-gray-700 { color: #374151; }
        .text-gray-800 { color: #1f2937; }
        .text-gray-500 { color: #6b7280; }
        .text-white { color: white; }
        .border-l-4 { border-left: 4px solid; }
        .border-l-green-500 { border-left-color: #10b981; }
        .border-l-blue-500 { border-left-color: #3b82f6; }
        .border-l-purple-500 { border-left-color: #a855f7; }
        .border-b-2 { border-bottom: 2px solid; }
        .border-gray-200 { border-color: #e5e7eb; }
        .border-green-200 { border-color: #bbf7d0; }
        .p-4 { padding: 1rem; }
        .p-6 { padding: 1.5rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mb-8 { margin-bottom: 2rem; }
        .mr-2 { margin-right: 0.5rem; }
        .mr-4 { margin-right: 1rem; }
        .mt-1 { margin-top: 0.25rem; }
        .text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
        .text-2xl { font-size: 1.5rem; line-height: 2rem; }
        .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
        .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .gap-4 { gap: 1rem; }
        .w-full { width: 100%; }
        .overflow-x-auto { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .hover\\:bg-gray-50:hover { background-color: #f9fafb; }
        .transition { transition: all 0.3s ease; }
        .rounded-lg { border-radius: 0.5rem; }
        .rounded { border-radius: 0.25rem; }
        .border { border: 1px solid #e5e7eb; }
        .bg-green-50 { background-color: #f0fdf4; }
        .bg-blue-50 { background-color: #eff6ff; }
        .bg-purple-50 { background-color: #faf5ff; }
        .bg-red-50 { background-color: #fef2f2; }
        .bg-yellow-50 { background-color: #fefce8; }
        .bg-white { background-color: white; }
        .shadow { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
        .mx-auto { margin-left: auto; margin-right: auto; }
        .inline-flex { display: inline-flex; }
        .block { display: block; }
        .hidden { display: none; }
        .relative { position: relative; }
        .absolute { position: absolute; }
        .top-1\/2 { top: 50%; }
        .left-1\/2 { left: 50%; }
        .transform { transform: translateX(-50%); }
        .z-50 { z-index: 50; }
        .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .bg-\[\#4b6b2f\] { background-color: #4b6b2f; }
        .hover\:bg-\[\#3a5524\]:hover { background-color: #3a5524; }
    </style>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: #f6f8f4;
            margin: 0;
            padding: 0;
            padding-top: 100px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border: 1px solid #f1f5f9;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(75, 107, 47, 0.15);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .order-status {
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-delivered {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* Navbar Styles */
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
          padding: 0.8rem 2rem;
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
          font-size: 1.8rem;
          font-weight: 800;
          color: #4b6b2f;
          letter-spacing: 1px;
          text-decoration: none;
          transition: all 0.3s ease;
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }
        .navbar-logo i {
          font-size: 1.5rem;
        }
        .navbar-logo:hover {
          color: #3a5524;
          transform: scale(1.03);
        }
        .navbar-links {
          display: flex;
          gap: 2rem;
          align-items: center;
        }
        .navbar-link {
          font-weight: 600;
          color: #1e293b;
          text-decoration: none;
          font-size: 1.1rem;
          transition: all 0.3s ease;
          position: relative;
          padding: 0.5rem 0;
        }
        .navbar-link.active {
          color: #4b6b2f;
          font-weight: 700;
        }
        .navbar-link.active::after {
          content: '';
          position: absolute;
          bottom: 0;
          left: 0;
          width: 100%;
          height: 3px;
          background-color: #4b6b2f;
          border-radius: 3px;
          animation: slideIn 0.3s ease;
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
          height: 3px;
          background-color: #4b6b2f;
          border-radius: 3px;
          animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
          from { width: 0; opacity: 0; }
          to { width: 100%; opacity: 1; }
        }
        .navbar-actions {
          display: flex;
          align-items: center;
          gap: 1.2rem;
        }
        .navbar-cart {
          position: relative;
          color: #4b6b2f;
          font-size: 1.5rem;
          text-decoration: none;
          transition: all 0.3s ease;
          display: flex;
          align-items: center;
          justify-content: center;
          width: 2.5rem;
          height: 2.5rem;
          border-radius: 50%;
        }
        .navbar-cart:hover {
          background-color: rgba(75, 107, 47, 0.1);
          transform: translateY(-2px);
        }
        .navbar-cart .cart-count {
          position: absolute;
          top: -2px;
          right: -2px;
          background: #ef4444;
          color: white;
          font-size: 0.7rem;
          width: 20px;
          height: 20px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 600;
          border: 2px solid white;
        }
        .navbar-btn {
          padding: 0.6rem 1.4rem;
          border-radius: 0.6rem;
          font-weight: 600;
          transition: all 0.3s ease;
          text-decoration: none;
          font-size: 1rem;
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
        }
        .navbar-btn i {
          font-size: 1.1rem;
        }
        .navbar-btn-login {
          color: #4b6b2f;
          border: 2px solid #4b6b2f;
        }
        .navbar-btn-login:hover {
          background-color: rgba(75, 107, 47, 0.1);
          transform: translateY(-2px);
        }
        .navbar-btn-register {
          background-color: #4b6b2f;
          color: white;
        }
        .navbar-btn-register:hover {
          background-color: #3a5524;
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(75, 107, 47, 0.2);
        }
        .navbar-btn-logout {
          background-color: #ef4444;
          color: white;
          border: none;
          cursor: pointer;
        }
        .navbar-btn-logout:hover {
          background-color: #dc2626;
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        .navbar-user {
          display: flex;
          align-items: center;
          gap: 0.6rem;
          color: #4b6b2f;
          text-decoration: none;
          font-weight: 600;
          padding: 0.5rem 1rem;
          border-radius: 0.6rem;
          transition: all 0.3s ease;
        }
        .navbar-user:hover {
          background-color: rgba(75, 107, 47, 0.1);
          transform: translateY(-2px);
        }
        .navbar-user i {
          font-size: 1.4rem;
        }
        .hidden {
          transform: translate(-50%, -150%) !important;
          opacity: 0;
          pointer-events: none;
        }
        @media (max-width: 1024px) {
          .main-navbar {
            padding: 0.8rem 1.5rem;
          }
          .navbar-logo {
            font-size: 1.6rem;
          }
          .navbar-links {
            gap: 1.2rem;
          }
          .navbar-link {
            font-size: 1rem;
          }
          .navbar-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
          }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="main-navbar" id="mainNavbar">
        <div style="display: flex; align-items: center; gap: 2.5rem;">
            <a href="Home1.html" class="navbar-logo">
        <img src="brand_logo.php?f=logo" alt="Miskova Global" style="height: 42px; width: auto; object-fit: contain; border-radius: 6px;">
                <span>لوحة المسوق</span>
            </a>
            <div class="navbar-links">
                <a href="Home1.html" class="navbar-link <?php echo ($current_page == 'Home1' || $current_page == 'home') ? 'active' : ''; ?>">
                    <i class='bx bx-home-alt'></i>
                    الرئيسية
                </a>
                <a href="product.html" class="navbar-link <?php echo ($current_page == 'product') ? 'active' : ''; ?>">
                    <i class='bx bx-package'></i>
                    المنتجات
                </a>
                <a href="orders.html" class="navbar-link <?php echo ($current_page == 'orders') ? 'active' : ''; ?>">
                    <i class='bx bx-receipt'></i>
                    الطلبات
                </a>
                <a href="withdrawals.php" class="navbar-link <?php echo ($current_page == 'withdrawals') ? 'active' : ''; ?>">
                    <i class='bx bx-money-withdraw'></i>
                    السحب
                </a>
                <a href="shipping_cities_statistics.html" class="navbar-link <?php echo ($current_page == 'shipping_cities_statistics') ? 'active' : ''; ?>">
                    <i class='bx bx-map-pin'></i>
                    إحصائيات المدن
                </a>
                <a href="product_stats.php" class="navbar-link <?php echo ($current_page == 'product_stats') ? 'active' : ''; ?>">
                    <i class='bx bx-stats'></i>
                    إحصائيات المنتجات
                </a>
            </div>
        </div>
        <div class="navbar-actions">
            <a href="cart.html" class="navbar-cart" title="سلة المشتريات">
                <i class='bx bx-cart'></i>
                <span class="cart-count">0</span>
            </a>
            <?php if ($user_id && $user): ?>
                <a href="profile_marketer.php" class="navbar-user">
                    <i class='bx bx-user-circle'></i>
                    <span class="hidden md:inline"><?php echo htmlspecialchars($user_name); ?></span>
                </a>
                <a href="logout.php" class="navbar-btn navbar-btn-logout">
                    <i class='bx bx-log-out'></i>
                    <span>تسجيل الخروج</span>
                </a>
            <?php else: ?>
                <a href="login.html" class="navbar-btn navbar-btn-login">
                    <i class='bx bx-log-in-circle'></i>
                    <span>تسجيل الدخول</span>
                </a>
                <a href="register.html" class="navbar-btn navbar-btn-register">
                    <i class='bx bx-user-plus'></i>
                    <span>إنشاء حساب</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <script>
        // Navbar scroll behavior
        let lastScroll = 0;
        const navbar = document.getElementById('mainNavbar');
        let isScrolling = false;
        
        function handleScroll() {
          if (isScrolling) return;
          
          isScrolling = true;
          
          requestAnimationFrame(() => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll <= 0) {
              navbar.classList.remove('hidden');
              navbar.style.transform = 'translateX(-50%)';
              isScrolling = false;
              return;
            }
            
            if (currentScroll > lastScroll && currentScroll > 100) {
              // Scrolling down
              navbar.classList.add('hidden');
            } else {
              // Scrolling up
              navbar.classList.remove('hidden');
              navbar.style.transform = 'translateX(-50%)';
            }
            
            lastScroll = currentScroll;
            isScrolling = false;
          });
        }
        
        // Throttle scroll events for better performance
        let scrollTimeout;
        window.addEventListener('scroll', () => {
          clearTimeout(scrollTimeout);
          scrollTimeout = setTimeout(handleScroll, 50);
        }, { passive: true });
        
        // Cart count functionality
        function updateCartCount() {
          try {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const cartCountEls = document.querySelectorAll('.cart-count');
            const totalItems = cart.reduce((total, item) => total + (parseInt(item.quantity) || 0), 0);
            
            cartCountEls.forEach(el => {
              el.textContent = totalItems;
              // Add animation when cart count changes
              if (parseInt(el.textContent) !== parseInt(el.dataset.lastCount || '0')) {
                el.style.transform = 'scale(1.3)';
                setTimeout(() => {
                  el.style.transform = 'scale(1)';
                }, 200);
              }
              el.dataset.lastCount = totalItems;
            });
          } catch (e) {
            console.error('Error updating cart count:', e);
          }
        }
        
        // Initialize cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
          // Initial cart count update
          updateCartCount();
          
          // Update cart count when storage changes (from other tabs/windows)
          window.addEventListener('storage', updateCartCount);
          
          // Update cart count every second (to catch any changes from other tabs)
          setInterval(updateCartCount, 1000);
          
          // Add click animation to cart icon
          const cartIcon = document.querySelector('.navbar-cart');
          if (cartIcon) {
            cartIcon.addEventListener('click', function() {
              this.style.transform = 'scale(0.9)';
              setTimeout(() => {
                this.style.transform = '';
              }, 200);
            });
          }
        });
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

        <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">طلبات السحب</h1>

        <?php if(!$user_id): ?>
        <!-- رسالة ترحيب للزوار -->
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-6 rounded mb-6 text-center">
            <i class='bx bx-info-circle text-2xl mb-2 block'></i>
            <h3 class="text-xl font-bold mb-2">مرحباً بك!</h3>
            <p class="mb-4">لتتمكن من إدارة طلبات السحب والأرباح، يرجى تسجيل الدخول أو إنشاء حساب جديد.</p>
            <div class="flex justify-center gap-4">
                <a href="login.html" class="bg-[#4b6b2f] text-white px-6 py-2 rounded-lg hover:bg-[#3a5524] transition">
                    تسجيل الدخول
                </a>
                <a href="register.html" class="bg-white text-[#4b6b2f] border border-[#4b6b2f] px-6 py-2 rounded-lg hover:bg-gray-50 transition">
                    إنشاء حساب
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if($user_id): ?>
        <!-- إحصائيات السحب (تظهر فقط للمستخدم المسجل) -->
        

            

            <div class="stat-card p-6 border-l-4 border-l-green-500">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg mr-4">
                        <i class='bx bx-money text-green-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">صافي الأرباح</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($available_profit, 2); ?> د.ل</h3>
                        <p class="text-sm text-gray-500 mt-1">المستحق النهائي</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="stat-card p-6 border-l-4 border-l-blue-500">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg mr-4">
                        <i class='bx bx-wallet text-blue-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">المتاح للسحب</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($available_withdrawal, 2); ?> د.ل</h3>
                        <p class="text-sm text-gray-500 mt-1">الصافي - المسحوب</p>
                    </div>
                </div>
            </div>

            <div class="stat-card p-6 border-l-4 border-l-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                        <i class='bx bx-credit-card text-yellow-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">تم السحب</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($withdrawn_amount, 2); ?> د.ل</h3>
                        <p class="text-sm text-gray-500 mt-1">طلبات سحب مكتملة</p>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- زر طلب سحب جديد (يظهر فقط للمستخدم المسجل) -->
        <?php if($available_withdrawal > 0): ?>
        <div class="text-center mb-8">
            <button onclick="openWithdrawModal()" 
                    class="bg-[#4b6b2f] text-white px-6 py-3 rounded-lg hover:bg-[#3a5524] transition font-medium flex items-center mx-auto">
                <i class='bx bx-plus mr-2'></i>
                طلب سحب جديد
            </button>
        </div>
        <?php else: ?>
        <div class="text-center mb-8">
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded inline-flex items-center">
                <i class='bx bx-info-circle mr-2'></i>
                لا يوجد رصيد متاح للسحب حالياً
            </div>
        </div>
        <?php endif; ?>

        <!-- الطلبات المكتملة مع العمولات (للمستخدم المسجل) -->
        <?php if(count($completed_orders) > 0): ?>
        <div class="stat-card p-6 mb-8">
            <h3 class="text-xl font-bold mb-4 text-gray-800">
                <i class='bx bx-check-circle text-green-600 mr-2'></i>
                الطلبات المكتملة والعمولات المتاحة
            </h3>
            <p class="text-gray-600 mb-4 text-sm">
                هذه هي جميع الطلبات التي تم إكمالها (تم التوصيل/محصل/مكتمل) والعمولات المتاحة منها للسحب
            </p>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <th class="p-4 text-right text-gray-700 font-bold">#</th>
                            <th class="p-4 text-right text-gray-700 font-bold">العميل</th>
                            <th class="p-4 text-right text-gray-700 font-bold">المجموع</th>
                            <th class="p-4 text-right text-gray-700 font-bold">العمولة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">الحالة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($completed_orders as $order): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-4 text-gray-600">#<?php echo $order['id']; ?></td>
                                <td class="p-4">
                                    <div class="text-gray-800 font-medium"><?php echo $order['customer_name']; ?></div>
                                    <div class="text-gray-500 text-sm"><?php echo $order['customer_phone']; ?></div>
                                </td>
                                <td class="p-4 font-bold text-green-600"><?php echo number_format($order['total'], 2); ?> د.ل</td>
                                <td class="p-4 font-bold text-purple-600">
                                    <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg">
                                        <?php echo number_format($order['commission_total'], 2); ?> د.ل
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="order-status status-delivered">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-gray-500 text-sm"><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-green-50 border-t-2 border-green-200">
                            <td colspan="3" class="p-4 text-right font-bold text-gray-800">
                                إجمالي العمولات المتاحة:
                            </td>
                            <td class="p-4 font-bold text-green-700 text-lg">
                                <?php echo number_format($available_profit, 2); ?> د.ل
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- العمولات المضافة حديثاً (إذا كانت موجودة) -->
        <?php if($commissions_query && $commissions_query->num_rows > 0): ?>
        <div class="stat-card p-6 mb-8">
            <h3 class="text-xl font-bold mb-4 text-gray-800">
                <i class='bx bx-money text-green-600 mr-2'></i>
                العمولات المضافة حديثاً
            </h3>
            <p class="text-gray-600 mb-4 text-sm">
                هذه هي العمولات التي تم إضافتها تلقائياً عند توصيل الطلبات
            </p>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <th class="p-4 text-right text-gray-700 font-bold">#</th>
                            <th class="p-4 text-right text-gray-700 font-bold">رقم الطلب</th>
                            <th class="p-4 text-right text-gray-700 font-bold">العميل</th>
                            <th class="p-4 text-right text-gray-700 font-bold">مبلغ العمولة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">الحالة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">تاريخ الإضافة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($commission = $commissions_query->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-4 text-gray-600">#<?php echo $commission['id']; ?></td>
                                <td class="p-4 font-bold text-blue-600">#<?php echo $commission['order_id']; ?></td>
                                <td class="p-4">
                                    <div class="text-gray-800 font-medium"><?php echo $commission['customer_name'] ?? '---'; ?></div>
                                </td>
                                <td class="p-4 font-bold text-green-600">
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-lg">
                                        <?php echo number_format($commission['commission_amount'], 2); ?> د.ل
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="status-badge status-completed">
                                        <?php echo $commission['status']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-gray-500 text-sm"><?php echo date('Y-m-d H:i', strtotime($commission['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- آخر الطلبات (للمستخدم المسجل) -->
        <?php if(count($recent_orders) > 0): ?>
        <div class="stat-card p-6 mb-8">
            <h3 class="text-xl font-bold mb-4 text-gray-800">آخر الطلبات</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <th class="p-4 text-right text-gray-700 font-bold">#</th>
                            <th class="p-4 text-right text-gray-700 font-bold">العميل</th>
                            <th class="p-4 text-right text-gray-700 font-bold">المجموع</th>
                            <th class="p-4 text-right text-gray-700 font-bold">العمولة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">الحالة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_orders as $order): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-4 text-gray-600">#<?php echo $order['id']; ?></td>
                                <td class="p-4">
                                    <div class="text-gray-800 font-medium"><?php echo $order['customer_name']; ?></div>
                                    <div class="text-gray-500 text-sm"><?php echo $order['customer_phone']; ?></div>
                                </td>
                                <td class="p-4 font-bold text-green-600"><?php echo number_format($order['total'], 2); ?> د.ل</td>
                                <td class="p-4 font-bold text-purple-600"><?php echo number_format($order['commission_total'], 2); ?> د.ل</td>
                                <td class="p-4">
                                    <span class="order-status <?php echo $order['status'] == 'تم التوصيل' ? 'status-delivered' : 'status-pending'; ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-gray-500 text-sm"><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- جدول طلبات السحب (يظهر فقط للمستخدم المسجل) -->
        <div class="stat-card p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">طلبات السحب السابقة</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <th class="p-4 text-right text-gray-700 font-bold">#</th>
                            <th class="p-4 text-right text-gray-700 font-bold">المبلغ</th>
                            <th class="p-4 text-right text-gray-700 font-bold">رقم الهاتف</th>
                            <th class="p-4 text-right text-gray-700 font-bold">الحالة</th>
                            <th class="p-4 text-right text-gray-700 font-bold">تاريخ الطلب</th>
                            <th class="p-4 text-right text-gray-700 font-bold">تاريخ المعالجة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($withdrawals_query && $withdrawals_query->num_rows > 0) { ?>
                            <?php while($withdrawal = $withdrawals_query->fetch_assoc()) { ?>
                                <?php
                                if ($withdrawal['status'] == 'مكتمل') {
                                    $status_class = 'status-completed';
                                } elseif ($withdrawal['status'] == 'مرفوض') {
                                    $status_class = 'status-rejected';
                                } else {
                                    $status_class = 'status-pending';
                                }
                                ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="p-4 text-gray-600">#<?php echo $withdrawal['id']; ?></td>
                                    <td class="p-4 font-bold text-green-600"><?php echo number_format($withdrawal['amount'], 2); ?> د.ل</td>
                                    <td class="p-4 text-gray-600"><?php echo $withdrawal['phone']; ?></td>
                                    <td class="p-4">
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $withdrawal['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-500 text-sm"><?php echo date('Y-m-d H:i', strtotime($withdrawal['created_at'])); ?></td>
                                    <td class="p-4 text-gray-500 text-sm">
                                        <?php echo $withdrawal['processed_at'] ? date('Y-m-d H:i', strtotime($withdrawal['processed_at'])) : 'قيد المراجعة'; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500">
                                    <i class='bx bx-wallet text-4xl mb-2 block'></i>
                                    لا توجد طلبات سحب سابقة
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal طلب سحب جديد (يظهر فقط للمستخدم المسجل) -->
    <?php if($user_id && $available_withdrawal > 0): ?>
    <div id="withdrawModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">طلب سحب جديد</h3>
                <button onclick="closeWithdrawModal()" class="text-gray-500 hover:text-gray-700">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="request_withdrawal" value="1">
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">المبلغ المتاح للسحب</label>
                    <input type="text" value="<?php echo number_format($available_withdrawal, 2); ?> د.ل" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">المبلغ المطلوب *</label>
                    <input type="number" name="amount" step="0.01" min="1" max="<?php echo $available_withdrawal; ?>" 
                           required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f]">
                    <p class="text-sm text-gray-500 mt-1">أقصى مبلغ يمكن سحبه: <?php echo number_format($available_withdrawal, 2); ?> د.ل</p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">رقم فودافون كاش *</label>
                    <input type="text" name="phone" placeholder="مثال: 01012345678" 
                           required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f]">
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse mt-6">
                    <button type="button" onclick="closeWithdrawModal()" 
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-[#4b6b2f] text-white rounded-lg hover:bg-[#3a5524]">
                        تأكيد الطلب
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'flex';
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // أي كود جافاسكريبت إضافي
        });
    </script>
</body>
</html>