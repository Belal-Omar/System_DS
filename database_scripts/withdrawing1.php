<?php
// ملف: withdrawing1.php - سحب الأرباح للتاجر
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول ونوع المستخدم
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

// التحقق من نوع المستخدم
if (!$user || ($user['user_type'] ?? '') != 'تاجر') {
    header("Location: Home1.html");
    exit;
}

// حساب الأرباح المتاحة للسحب (للتاجر - صافي الربح فقط بدون عمولة)
$available_profit_query = $conn->query("
    SELECT COALESCE(SUM((oi.price - oi.commission) * oi.quantity), 0) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");

// Debug: Show individual items for verification
$debug_query = $conn->query("
    SELECT p.name, oi.price, oi.commission, oi.quantity,
           (oi.price - oi.commission) as net_profit_per_item,
           ((oi.price - oi.commission) * oi.quantity) as total_net_profit
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");

$available_profit = 0;
if ($available_profit_query) {
    $row = $available_profit_query->fetch_assoc();
    $available_profit = floatval($row['total'] ?? 0);
}
if ($available_profit < 0) $available_profit = 0;

// حساب المبلغ المسحوب سابقاً
$withdrawn_query = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM withdrawals 
    WHERE user_id = $user_id AND status = 'مكتمل'
");
$withdrawn_row = $withdrawn_query->fetch_assoc();
$withdrawn_amount = $withdrawn_row['total'] ? floatval($withdrawn_row['total']) : 0;

// المبلغ المتاح للسحب
$available_withdrawal = $available_profit - $withdrawn_amount;
if ($available_withdrawal < 0) {
    $available_withdrawal = 0;
}

// معالجة طلب سحب جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = floatval($_POST['amount']);
    $phone = $conn->real_escape_string($_POST['phone']);
    
    if ($amount <= 0) {
        $error = "المبلغ يجب أن يكون أكبر من الصفر";
    } elseif ($amount > $available_withdrawal) {
        $error = "المبلغ المطلوب أكبر من المبلغ المتاح للسحب";
    } else {
        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, phone, status) VALUES (?, ?, ?, 'قيد المراجعة')");
        $stmt->bind_param("ids", $user_id, $amount, $phone);
        
        if ($stmt->execute()) {
            $success = "تم إرسال طلب السحب بنجاح، سيتم مراجعته من قبل الإدارة";
            // تحديث المبلغ المتاح
            $available_withdrawal -= $amount;
        } else {
            $error = "حدث خطأ أثناء إرسال طلب السحب: " . $stmt->error;
        }
    }
}

// جلب طلبات السحب السابقة
$withdrawals_query = null;
$withdrawals = [];
if ($user_id) {
    $withdrawals_query = $conn->query("
        SELECT * FROM withdrawals 
        WHERE user_id = $user_id 
        ORDER BY created_at DESC
    ");
    while($row = $withdrawals_query->fetch_assoc()) {
        $withdrawals[] = $row;
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
    <title>طلبات السحب - لوحة التاجر</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
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
      <a href="home111.php" class="navbar-logo">
        <img src="brand_logo.php?f=logo" alt="Miskova Global" style="height: 42px; width: auto; object-fit: contain; border-radius: 6px;">
        <span>لوحة التاجر</span>
      </a>
      <div class="navbar-links">
        <a href="home111.php" class="navbar-link <?php echo ($current_page == 'home111' || $current_page == 'home') ? 'active' : ''; ?>">
          <i class='bx bx-home-alt'></i>
          الرئيسية
        </a>
        <a href="products1.php" class="navbar-link <?php echo ($current_page == 'products1' || $current_page == 'products') ? 'active' : ''; ?>">
          <i class='bx bx-package'></i>
          المنتجات
        </a>
        <a href="orders1.php" class="navbar-link <?php echo ($current_page == 'orders1' || $current_page == 'orders') ? 'active' : ''; ?>">
          <i class='bx bx-receipt'></i>
          الطلبات
        </a>
        <a href="shipping_cities_statistics1.php" class="navbar-link <?php echo ($current_page == 'shipping_cities_statistics1') ? 'active' : ''; ?>">
          <i class='bx bx-map-pin'></i>
          إحصائيات المدن
        </a>
        <a href="withdrawing1.php" class="navbar-link <?php echo ($current_page == 'withdrawing1' || $current_page == 'withdrawing') ? 'active' : ''; ?>">
          <i class='bx bx-money-withdraw'></i>
          السحب
        </a>
        <a href="product_statistics1.php" class="navbar-link <?php echo ($current_page == 'product_statistics1') ? 'active' : ''; ?>">
          <i class='bx bx-bar-chart-alt'></i>
          إحصائيات المنتجات
        </a>
      </div>
    </div>
    <div class="navbar-actions">
      
      <?php if ($user_id && $user): ?>
        <a href="profile_merchant.php" class="navbar-user">
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

        <?php if($user_id): ?>
        <!-- إحصائيات السحب (تظهر فقط للمستخدم المسجل) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card p-6 border-l-4 border-l-green-500">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg mr-4">
                        <i class='bx bx-money text-green-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">إجمالي الأرباح</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($available_profit, 2); ?> د.ل</h3>
                        <p class="text-sm text-gray-500 mt-1">من الطلبات المكتملة (صافي الربح = سعر المنتج - العمولة)</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card p-6 border-l-4 border-l-blue-500">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg mr-4">
                        <i class='bx bx-wallet text-blue-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">المتاح للسحب</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($available_withdrawal, 2); ?> د.ل</h3>
                        <p class="text-sm text-gray-500 mt-1">الإجمالي - المسحوب</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card p-6 border-l-4 border-l-purple-500">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg mr-4">
                        <i class='bx bx-credit-card text-purple-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">تم السحب</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($withdrawn_amount, 2); ?> د.ل</h3>
                        <p class="text-sm text-gray-500 mt-1">طلبات سحب مكتملة</p>
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
                        <?php if(!empty($withdrawals)): ?>
                            <?php foreach($withdrawals as $withdrawal): ?>
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
                                    <td class="p-4 text-gray-600"><?php echo htmlspecialchars($withdrawal['phone']); ?></td>
                                    <td class="p-4">
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($withdrawal['status']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-500 text-sm"><?php echo date('Y-m-d H:i', strtotime($withdrawal['created_at'])); ?></td>
                                    <td class="p-4 text-gray-500 text-sm">
                                        <?php echo isset($withdrawal['processed_at']) && $withdrawal['processed_at'] ? date('Y-m-d H:i', strtotime($withdrawal['processed_at'])) : 'قيد المراجعة'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500">
                                    <i class='bx bx-wallet text-4xl mb-2 block'></i>
                                    لا توجد طلبات سحب سابقة
                                </td>
                            </tr>
                        <?php endif; ?>
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

        // تحديث عداد السلة
       
        // تحديث العداد عند تحميل الصفحة
       ;
    </script>
</body>
</html>