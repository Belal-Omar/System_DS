<?php
// ملف: orders1.php - لوحة التاجر - الطلبات
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

// جلب طلبات منتجات التاجر فقط
$orders_query = $conn->query("
    SELECT DISTINCT
        o.id,
        o.customer_name as name,
        o.customer_phone as phone,
        o.region,
        o.address,
        o.shipping_city_id,
        o.shipping_city_name,
        o.shipping_cost,
        o.total,
        o.commission_total,
        o.total_special_commission,
        o.status,
        o.created_at as date,
        o.cancellation_reason,
        o.return_reason,
        o.reason_type,
        GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as products_list
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

$orders = [];
$total_profit = 0;
$withdrawn_amount = 0;

// حساب إجمالي الأرباح الصافية للتاجر (سعر البيع - العمولة المخصومة للسيستم)
$total_profit_query = $conn->query("
    SELECT COALESCE(SUM((oi.original_price - oi.special_commission) * oi.quantity), 0) as total_profit
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");
$total_profit = 0;
if ($total_profit_query) {
    $row = $total_profit_query->fetch_assoc();
    $total_profit = floatval($row['total_profit'] ?? 0);
}
if ($total_profit < 0) $total_profit = 0;

// جلب الطلبات للعرض
while($row = $orders_query->fetch_assoc()) {
    $orders[] = $row;
}

// حساب المبلغ المسحوب
$withdrawn_query = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM withdrawals 
    WHERE user_id = $user_id AND status = 'مكتمل'
");
$withdrawn_amount = 0;
if ($withdrawn_query) {
    $row = $withdrawn_query->fetch_assoc();
    $withdrawn_amount = floatval($row['total'] ?? 0);
}

// الربح النهائي المتاح (متاح للسحب)
$available_profit = $total_profit - $withdrawn_amount;
if ($available_profit < 0) $available_profit = 0;

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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>الطلبات</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
  <link rel="stylesheet" href="Stylepp.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
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

    .status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
    }
    .status.pending {
      background: #fef3c7;
      color: #d97706;
    }
    .status.completed {
      background: #d1fae5;
      color: #065f46;
    }
    .status.rejected {
      background: #fee2e2;
      color: #dc2626;
    }
    .status.shipping {
      background: #dbeafe;
      color: #1e40af;
    }
    .status.confirmed {
      background: #e0e7ff;
      color: #3730a3;
    }
    .mini-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px;
      border-bottom: 1px solid #f3f4f6;
    }
    .mini-item:last-child {
      border-bottom: none;
    }
    .mini-item img {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid #e5e7eb;
    }
    .mini-title {
      font-weight: 600;
      font-size: 14px;
      color: #1f2937;
    }
    .mini-meta {
      font-size: 12px;
      color: #6b7280;
    }
    .order-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      margin-bottom: 8px;
    }
    .order-item img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 6px;
    }
    .refresh-btn {
      background: #4b6b2f;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      margin-left: 10px;
    }
    .refresh-btn:hover {
      background: #3a5524;
    }
    .refresh-btn.loading {
      background: #6b7280;
      cursor: not-allowed;
    }
    .error-message {
      background: #fee2e2;
      color: #dc2626;
      padding: 12px;
      border-radius: 6px;
      margin: 10px 0;
      text-align: center;
    }
    .success-message {
      background: #d1fae5;
      color: #065f46;
      padding: 12px;
      border-radius: 6px;
      margin: 10px 0;
      text-align: center;
    }
    .orders-table {
      width: 100%;
      border-collapse: collapse;
    }
    .orders-table th {
      background: #f8f9fa;
      padding: 12px;
      text-align: right;
      font-weight: bold;
      border-bottom: 2px solid #e5e7eb;
    }
    .orders-table td {
      padding: 12px;
      border-bottom: 1px solid #f3f4f6;
    }
    .orders-table tr:hover {
      background: #f9fafb;
    }
    
    /* Modal styles for scrollable content */
    .modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      padding: 20px;
    }
    
    .modal-card {
      background: white;
      border-radius: 12px;
      max-width: 600px;
      width: 100%;
      max-height: 85vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      overflow: hidden;
    }
    
    .modal-close {
      position: absolute;
      top: 15px;
      left: 15px;
      background: #f3f4f6;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: #6b7280;
      transition: all 0.2s;
      z-index: 10;
    }
    
    .modal-close:hover {
      background: #e5e7eb;
      color: #374151;
    }
    
    .modal-title {
      background: #4b6b2f;
      color: white;
      padding: 20px;
      margin: 0;
      text-align: center;
      font-size: 1.5rem;
      font-weight: 600;
    }
    
    #statusContent {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      max-height: calc(85vh - 140px); /* Account for title and actions */
    }
    
    #statusContent::-webkit-scrollbar {
      width: 8px;
    }
    
    #statusContent::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    
    #statusContent::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    
    #statusContent::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
    
    .modal-actions {
      padding: 20px;
      border-top: 1px solid #e5e7eb;
      background: #f9fafb;
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    
    .order-items {
      max-height: 300px;
      overflow-y: auto;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 10px;
    }
    
    .order-items::-webkit-scrollbar {
      width: 6px;
    }
    
    .order-items::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 3px;
    }
    
    .order-items::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 3px;
    }
    
    .order-item {
      display: flex;
      gap: 12px;
      padding: 12px;
      border-bottom: 1px solid #f3f4f6;
      align-items: center;
    }
    
    .order-item:last-child {
      border-bottom: none;
    }
    
    .order-item img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
    }
    
    .mini-title {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 4px;
    }
    
    .mini-meta {
      font-size: 13px;
      color: #6b7280;
    }
    
    /* Responsive adjustments */
    @media (max-width: 640px) {
      .modal {
        padding: 10px;
      }
      
      .modal-card {
        max-height: 90vh;
      }
      
      #statusContent {
        padding: 15px;
        max-height: calc(90vh - 120px);
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

<main class="page-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1 class="page-title">جميع الطلبات</h1>
    <button class="refresh-btn" onclick="location.reload()" id="refreshBtn">
      <i class="fa fa-refresh"></i> تحديث الطلبات
    </button>
  </div>

  <!-- رسائل التحديث -->
  <div id="messageContainer"></div>

  <div class="orders-wrapper">
    <table class="orders-table">
      <thead>
        <tr>
          <th>رقم/تاريخ</th>
          <th>اسم العميل</th>
          <th>المنتجات</th>
          <th>المنطقة</th>
          <th>مدينة الشحن</th>
          <th>المجموع</th>
          <th>صافي الربح</th>
          <th>الحالة</th>
          <th>سبب الإلغاء/الإرجاع</th>
          <th>تعليقات الإدارة</th>
          <th>إجراءات</th>
        </tr>
      </thead>
      
      <tbody id="orders-body">
        <?php if(empty($orders)): ?>
        <tr>
          <td colspan="10" class="empty" style="text-align: center; padding: 40px; color: #6b7280;">
            <i class="fa fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
            لا توجد طلبات حتى الآن
          </td>
        </tr>
        <?php else: ?>
        <?php 
        foreach($orders as $order): 
          // جلب تفاصيل المنتجات للطلب
          $order_items_query = $conn->query("
            SELECT 
              oi.*,
              oi.commission as item_commission,
              oi.special_commission as item_special_commission,
              p.name as product_name,
              pi.image_path as product_image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
            WHERE oi.order_id = " . intval($order['id']) . " AND p.user_id = $user_id
          ");
          
          $order_items = [];
          while($item = $order_items_query->fetch_assoc()) {
            // إذا لم تكن هناك صورة رئيسية، جلب أول صورة
            if (!$item['product_image']) {
              $first_image_result = $conn->query("
                SELECT image_path FROM product_images 
                WHERE product_id = {$item['product_id']} 
                LIMIT 1
              ");
              if ($first_image_result && $first_image_result->num_rows > 0) {
                $first_image = $first_image_result->fetch_assoc();
                $item['product_image'] = $first_image['image_path'];
              }
            }
            $order_items[] = $item;
          }
          
          $items_sum = 0;
          $merchant_profit_sum = 0;
          foreach($order_items as $item) {
              $items_sum += (floatval($item['original_price'] ?? 0) * intval($item['quantity'] ?? 1));
              $special_com = floatval($item['item_special_commission'] ?? 0);
              $merchant_profit_sum += (floatval($item['original_price'] ?? 0) - $special_com) * intval($item['quantity'] ?? 1);
          }
          
          $grand = $items_sum; // إجمالي بيع القطع بدون عمولة المسوق
          $total = $merchant_profit_sum; // صافي ربح التاجر بعد خصم عمولته
          
          // تحديد كلاس الحالة
          $status = $order['status'] ?? 'قيد الانتظار';
          $statusClass = 'status pending';
          if ($status === 'تم التوصيل' || $status === 'محصل' || $status === 'مكتمل') {
            $statusClass = 'status completed';
          } elseif ($status === 'مرفوض' || $status === 'ملغي') {
            $statusClass = 'status rejected';
          } elseif ($status === 'في الشحن') {
            $statusClass = 'status shipping';
          } elseif ($status === 'تم التأكيد') {
            $statusClass = 'status confirmed';
          }
          
          // عرض المنتجات
          $productsHtml = '';
          if (!empty($order_items)) {
            foreach($order_items as $item) {
              $img = !empty($item['product_image']) ? htmlspecialchars($item['product_image']) : 'imgs/default-product.jpg';
              $productsHtml .= '
                <div class="mini-item">
                  <img src="' . $img . '" alt="' . htmlspecialchars($item['product_name']) . '" onerror="this.src=\'imgs/default-product.jpg\'">
                  <div>
                    <div class="mini-title">' . htmlspecialchars($item['product_name']) . '</div>
                    <div class="mini-meta">لون: ' . htmlspecialchars($item['color'] ?? '---') . ' | ' . htmlspecialchars($item['size'] ?? '---') . ' | كمية: ' . intval($item['quantity'] ?? 1) . '</div>
                  </div>
                </div>
              ';
            }
          } else {
            $productsHtml = 'لا توجد منتجات';
          }
        ?>
        <tr>
          <td>
            #<?php echo $order['id']; ?>
            <br>
            <small style="color: #6b7280; font-size: 12px;"><?php echo date('Y-m-d', strtotime($order['date'])); ?></small>
          </td>
          <td>
            <?php echo htmlspecialchars($order['name'] ?? 'غير معروف'); ?>
            <br>
            <small style="color: #6b7280; font-size: 12px;"><?php echo htmlspecialchars($order['phone'] ?? ''); ?></small>
          </td>
          <td><?php echo $productsHtml; ?></td>
          <td><?php echo htmlspecialchars($order['region'] ?? '---'); ?></td>
          <td><?php echo htmlspecialchars($order['shipping_city_name'] ?? '---'); ?></td>
          <td><strong><?php echo number_format($grand, 2); ?> د.ل</strong></td>
          <td><strong style="color: #4b6b2f;"><?php echo number_format($total, 2); ?> د.ل</strong></td>
          <td><span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
          <td>
            <?php if ($status === 'ملغي' || $status === 'مرفوض'): ?>
              <div style="color: #dc2626; font-size: 12px;">
                <i class="fa fa-times-circle" style="margin-left: 4px;"></i>
                <?php echo htmlspecialchars($order['cancellation_reason'] ?: 'غير محدد'); ?>
              </div>
            <?php elseif ($status === 'مرتجع'): ?>
              <div style="color: #f59e0b; font-size: 12px;">
                <i class="fa fa-undo" style="margin-left: 4px;"></i>
                <?php echo htmlspecialchars($order['return_reason'] ?: 'غير محدد'); ?>
              </div>
            <?php else: ?>
              <span style="color: #6b7280; font-size: 12px;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn small" style="background: #9333ea; color: white;" onclick="showOrderComments(<?php echo $order['id']; ?>)">
              <i class='bx bx-comment-detail'></i> التعليقات
            </button>
          </td>
          <td>
            <button class="btn small" onclick="openOrderModal(<?php echo $order['id']; ?>)">عرض التفاصيل</button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Modal: حالة/تفاصيل الطلب -->
<div id="statusModal" class="modal hidden">
  <div class="modal-card">
    <button class="modal-close" id="closeStatusModal">&times;</button>
    <h2 class="modal-title">تفاصيل الطلب</h2>
    <div id="statusContent"></div>

    <div class="modal-actions">
      <button id="closeStatus" class="btn ghost">إغلاق</button>
    </div>
  </div>
</div>

<!-- Modal: تعليقات الإدارة -->
<div id="commentsViewModal" class="modal hidden">
  <div class="modal-card">
    <button class="modal-close" onclick="closeCommentsViewModal()">&times;</button>
    <h2 class="modal-title" style="background: #9333ea;">تعليقات الإدارة - الطلب #<span id="commentsOrderId"></span></h2>
    <div id="commentsViewContent" style="padding: 20px; max-height: 400px; overflow-y: auto;">
      <div style="text-align: center; color: #6b7280; padding: 30px;">
        <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem;"></i>
        <p>جاري تحميل التعليقات...</p>
      </div>
    </div>
    <div class="modal-actions">
      <button onclick="closeCommentsViewModal()" class="btn ghost">إغلاق</button>
    </div>
  </div>
</div>

<script>
// بيانات الطلبات من PHP
const ordersData = <?php echo json_encode($orders); ?>;
const orderItemsData = {};
<?php
// إضافة بيانات المنتجات لكل طلب
foreach($orders as $order) {
    $order_items_query = $conn->query("
      SELECT 
        oi.*,
        oi.commission as item_commission,
        oi.special_commission as item_special_commission,
        p.name as product_name,
        pi.image_path as product_image
      FROM order_items oi
      JOIN products p ON oi.product_id = p.id
      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
      WHERE oi.order_id = " . intval($order['id']) . " AND p.user_id = $user_id
    ");
    
    $items = [];
    while($item = $order_items_query->fetch_assoc()) {
        // إذا لم تكن هناك صورة رئيسية، جلب أول صورة
        if (!$item['product_image']) {
            $first_image_result = $conn->query("
                SELECT image_path FROM product_images 
                WHERE product_id = {$item['product_id']} 
                LIMIT 1
            ");
            if ($first_image_result && $first_image_result->num_rows > 0) {
                $first_image = $first_image_result->fetch_assoc();
                $item['product_image'] = $first_image['image_path'];
            }
        }
        $items[] = $item;
    }
    echo "orderItemsData[" . $order['id'] . "] = " . json_encode($items) . ";\n";
}
?>

const statusModal = document.getElementById('statusModal');
const statusContent = document.getElementById('statusContent');
const closeStatusModal = document.getElementById('closeStatusModal');
const closeStatusBtn = document.getElementById('closeStatus');

// تحويل الحالة إلى كلاس لون
function statusClass(status) {
  if (status === 'تم التوصيل' || status === 'محصل' || status === 'مكتمل') return 'status completed';
  if (status === 'مرفوض' || status === 'ملغي') return 'status rejected';
  if (status === 'في الشحن') return 'status shipping';
  if (status === 'تم التأكيد') return 'status confirmed';
  return 'status pending';
}

// تنسيق رقم الهاتف لضهور الصفر في البداية
function formatPhoneNumber(phone) {
  if (!phone) return '';
  
  // تحويل الرقم إلى نص للتأكد من الحفاظ على الأصفار
  let phoneStr = String(phone);
  
  // إذا كان الرقم يبدأ بـ 0، نحافظ عليه كما هو
  if (phoneStr.startsWith('0')) {
    return phoneStr;
  }
  
  // إذا كان الرقم مكون من 10 أرقام ولا يبدأ بـ 0، نضيف 0 في البداية
  if (phoneStr.length === 10 && !phoneStr.startsWith('0')) {
    return '0' + phoneStr;
  }
  
  // إذا كان الرقم مكون من 11 رقمًا ويبدأ بـ 1، نستبدل الـ 1 بـ 0
  if (phoneStr.length === 11 && phoneStr.startsWith('1')) {
    return '0' + phoneStr.substring(1);
  }
  
  // في الحالات الأخرى، نرجع الرقم كما هو
  return phoneStr;
}

// فتح المودال وعرض تفاصيل الطلب
function openOrderModal(orderId) {
  console.log('openOrderModal called with orderId:', orderId);
  console.log('ordersData:', ordersData);
  console.log('orderItemsData:', orderItemsData);
  
  // البحث عن الطلب
  const order = ordersData.find(o => o.id == orderId);
  console.log('found order:', order);
  
  if (!order) {
    showWarning('الطلب غير موجود');
    return;
  }
  
  const items = orderItemsData[orderId] || [];
  console.log('order items:', items);
  
  displayOrderDetails(order, items);
}

// عرض تفاصيل الطلب
function displayOrderDetails(order, items) {
  const total = parseFloat(order.total || 0).toFixed(2);
  const grand = total; // الإجمالي الكلي يساوي المجموع فقط (بدون عمولة)
  const shippingCost = parseFloat(order.shipping_cost || 0).toFixed(2);
  const commissionTotal = parseFloat(order.commission_total || 0).toFixed(2);

  statusContent.innerHTML = `
    <div style="line-height: 1.8;">
      <h3 style="color: #4b6b2f; margin-bottom: 15px; text-align: center;">📋 تفاصيل الطلب</h3>
      
      <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
        <h4 style="color: #1e293b; margin-bottom: 10px;">👤 بيانات العميل</h4>
        <p><strong>الاسم الكامل:</strong> ${order.name || 'غير معروف'}</p>
        <p><strong>رقم الهاتف:</strong> ${formatPhoneNumber(order.phone) || '---'}</p>
        <p><strong>المنطقة:</strong> ${order.region || '---'}</p>
        <p><strong>العنوان الكامل:</strong> ${order.address || '---'}</p>
      </div>
      
      <div style="background: #f0f7e8; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
        <h4 style="color: #4b6b2f; margin-bottom: 10px;">📦 معلومات الشحن</h4>
        <p><strong>مدينة الشحن:</strong> ${order.shipping_city_name || '---'}</p>
        <p><strong>تاريخ الطلب:</strong> ${order.date ? new Date(order.date).toLocaleString('ar-EG') : '---'}</p>
      </div>
      
      <hr style="margin: 15px 0;">
      
      <h4 style="margin: 16px 0 8px 0;">🛍️ المنتجات:</h4>
      <div class="order-items">
        ${items.map(it => `
          <div class="order-item">
            <img src="${it.product_image || 'imgs/default-product.jpg'}" alt="${it.product_name}" onerror="this.src='imgs/default-product.jpg'">
            <div style="flex: 1;">
              <div class="mini-title">${it.product_name}</div>
              <div class="mini-meta">لون: ${it.color || '---'} | ${it.size || '---'} | كمية: ${it.quantity || 1}</div>
              <div style="font-size: 14px; margin-top: 4px;">
                السعر: ${parseFloat(it.price || 0).toFixed(2)} د.ل
              </div>
            </div>
          </div>
        `).join('')}
        ${items.length === 0 ? '<p style="color: #6b7280;">لا توجد منتجات</p>' : ''}
      </div>
      
      <hr style="margin: 15px 0;">
      
      <div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
        <h4 style="color: #92400e; margin-bottom: 10px;">💰 تفاصيل المالية</h4>
        <p><strong>سعر المنتجات:</strong> ${items.reduce((s, it) => s + (parseFloat(it.original_price || 0) * (it.quantity || 1)), 0).toFixed(2)} د.ل</p>
        <p><strong>استقطاع النظام:</strong> <span style="color: #dc2626;">-${items.reduce((s, it) => s + (parseFloat(it.special_commission || 0) * (it.quantity || 1)), 0).toFixed(2)} د.ل</span></p>
        <hr style="border-color: #fde68a; margin: 10px 0;">
        <p><strong>صافي الربح (للتاجر):</strong> <span style="color: #059669; font-weight: bold; font-size: 18px;">${items.reduce((s, it) => s + ((parseFloat(it.original_price || 0) - parseFloat(it.special_commission || 0)) * (it.quantity || 1)), 0).toFixed(2)} د.ل</span></p>
      </div>
      
      <div style="background: #e0f2fe; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
        <h4 style="color: #0369a1; margin-bottom: 10px;">📊 حالة الطلب</h4>
        <p><strong>الحالة الحالية:</strong> <span class="${statusClass(order.status)}">${order.status || 'قيد الانتظار'}</span></p>
        ${order.status === 'ملغي' || order.status === 'مرفوض' ? 
          `<p style="margin-top: 8px;"><strong>سبب الإلغاء:</strong> <span style="color: #dc2626;">${order.cancellation_reason || 'غير محدد'}</span></p>` : 
          order.status === 'مرتجع' ? 
          `<p style="margin-top: 8px;"><strong>سبب الإرجاع:</strong> <span style="color: #f59e0b;">${order.return_reason || 'غير محدد'}</span></p>` :
          ''
        }
      </div>
      
      <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
        <small>💡 ملاحظة: يمكن للمسؤول تغيير حالة الطلب من لوحة التحكم</small>
      </div>
    </div>
  `;

  statusModal.classList.remove('hidden');
}

// إغلاق المودال
closeStatusModal.addEventListener('click', () => statusModal.classList.add('hidden'));
closeStatusBtn.addEventListener('click', () => statusModal.classList.add('hidden'));

// إغلاق المودال عند النقر خارج المحتوى
statusModal.addEventListener('click', (e) => {
  if (e.target === statusModal) {
    statusModal.classList.add('hidden');
  }
});

// =============== نظام عرض تعليقات الإدارة ===============
function showOrderComments(orderId) {
  document.getElementById('commentsOrderId').textContent = orderId;
  document.getElementById('commentsViewModal').classList.remove('hidden');
  
  const container = document.getElementById('commentsViewContent');
  container.innerHTML = `
    <div style="text-align: center; color: #6b7280; padding: 30px;">
      <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem;"></i>
      <p>جاري تحميل التعليقات...</p>
    </div>
  `;
  
  fetch('get_order_comments.php?order_id=' + orderId)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.comments.length > 0) {
        container.innerHTML = data.comments.map(comment => `
          <div style="background: #f8f9fa; border-right: 4px solid #9333ea; padding: 15px; margin-bottom: 10px; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
              <span style="font-weight: bold; color: #9333ea;">
                <i class='bx bx-user-circle'></i>
                ${comment.admin_name}
              </span>
              <span style="color: #6b7280; font-size: 12px;">${comment.formatted_date}</span>
            </div>
            <p style="color: #1f2937; margin: 0;">${comment.comment}</p>
          </div>
        `).join('');
      } else {
        container.innerHTML = `
          <div style="text-align: center; color: #9ca3af; padding: 40px;">
            <i class='bx bx-comment-x' style="font-size: 3rem; display: block; margin-bottom: 10px;"></i>
            <p>لا توجد تعليقات من الإدارة على هذا الطلب</p>
          </div>
        `;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      container.innerHTML = `
        <div style="text-align: center; color: #dc2626; padding: 40px;">
          <i class='bx bx-error-circle' style="font-size: 3rem; display: block; margin-bottom: 10px;"></i>
          <p>فشل في تحميل التعليقات</p>
        </div>
      `;
    });
}

function closeCommentsViewModal() {
  document.getElementById('commentsViewModal').classList.add('hidden');
}

// إغلاق modal التعليقات عند النقر خارجها
document.getElementById('commentsViewModal').addEventListener('click', (e) => {
  if (e.target.id === 'commentsViewModal') {
    closeCommentsViewModal();
  }
});
</script>

</body>
</html>