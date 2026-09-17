<?php
// ملف: home111.php - لوحة التاجر
session_start();
include(__DIR__ . '/../core/config.php");
/** @var mysqli $conn */
include_once("helpers.php");

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

// حساب إجمالي الأرباح = مجموع (سعر المنتج الأصلي - العمولة) للطلبات المكتملة
$total_profit_query = $conn->query("
    SELECT 
        COALESCE(SUM(oi.original_price * oi.quantity - oi.commission), 0) as total_profit
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

// الأرباح المتوقعة = للطلبات قيد الانتظار (سعر المنتج الأصلي - العمولة)
$expected_profit_query = $conn->query("
    SELECT 
        COALESCE(SUM(oi.original_price * oi.quantity - oi.commission), 0) as expected_profit
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('قيد الانتظار', 'تم التأكيد', 'قيد التنفيذ', 'تحت التحضير', 'في الشحن')
");
$expected_profit = 0;
if ($expected_profit_query) {
    $row = $expected_profit_query->fetch_assoc();
    $expected_profit = floatval($row['expected_profit'] ?? 0);
}
if ($expected_profit < 0) $expected_profit = 0;

// تم السحب = إجمالي ما تم سحبه من الحساب
$withdrawn_query = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM withdrawals 
    WHERE user_id = $user_id AND status = 'مكتمل'
");
$withdrawn = 0;
if ($withdrawn_query) {
    $row = $withdrawn_query->fetch_assoc();
    $withdrawn = floatval($row['total'] ?? 0);
}

// متاح للسحب = الربح الصافي = إجمالي الأرباح - ما تم سحبه
$available_withdrawal = $total_profit - $withdrawn;
if ($available_withdrawal < 0) $available_withdrawal = 0;

// طلبات معلقة (من منتجات التاجر)
$pending_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('قيد الانتظار', 'تم التأكيد', 'قيد التنفيذ', 'تحت التحضير', 'في الشحن')
");
$pending_orders = $pending_orders_query ? intval($pending_orders_query->fetch_assoc()['total'] ?? 0) : 0;

// عدد المنتجات (منتجات التاجر فقط)
$total_products_query = $conn->query("SELECT COUNT(*) as total FROM products WHERE user_id = $user_id");
$total_products = $total_products_query ? intval($total_products_query->fetch_assoc()['total'] ?? 0) : 0;

// عدد الطلبات (من منتجات التاجر)
$total_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id
");
$total_orders = $total_orders_query ? intval($total_orders_query->fetch_assoc()['total'] ?? 0) : 0;

// Get user-specific statistics using new helper functions
$product_stats = get_user_product_stats($conn, $user_id, 'merchant');
$cities_stats = get_user_cities_stats($conn, $user_id);
$orders_stats = get_user_orders_stats($conn, $user_id);

// حالات الطلبات (من منتجات التاجر)
$returned_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status = 'مرتجع'
");
$returned_orders = $returned_orders_query ? intval($returned_orders_query->fetch_assoc()['total'] ?? 0) : 0;

$cancelled_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status = 'ملغي'
");
$cancelled_orders = $cancelled_orders_query ? intval($cancelled_orders_query->fetch_assoc()['total'] ?? 0) : 0;

// محصل = الطلبات التي تم التوصيل أو محصل
$collected_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
");
$collected_orders = $collected_orders_query ? intval($collected_orders_query->fetch_assoc()['total'] ?? 0) : 0;

// نسبة التسليمات
$delivered_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status = 'تم التوصيل'
");
$delivered_orders = $delivered_orders_query ? intval($delivered_orders_query->fetch_assoc()['total'] ?? 0) : 0;
$delivery_rate = $total_orders > 0 ? round(($delivered_orders / $total_orders) * 100, 2) : 0;

// نسبة التأكيدات
$confirmed_orders_query = $conn->query("
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status = 'تم التأكيد'
");
$confirmed_orders = $confirmed_orders_query ? intval($confirmed_orders_query->fetch_assoc()['total'] ?? 0) : 0;
$confirmation_rate = $total_orders > 0 ? round(($confirmed_orders / $total_orders) * 100, 2) : 0;

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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>لوحة التاجر</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styleH.css" />
  <style>
    body {
      padding-top: 100px;
    }
    
    /* حركات Scroll-triggered */
    .animate-on-scroll {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
    
    .animate-on-scroll.animated {
      opacity: 1;
      transform: translateY(0);
    }
    
    /* إزالة الحركات التلقائية من الكروت */
    .card {
      animation: none !important;
      opacity: 0;
      transform: scale(0.9);
      transition: opacity 0.6s ease, transform 0.6s ease;
    }
    
    .card.animated {
      opacity: 1;
      transform: scale(1);
    }
    
    .card:nth-child(1) { transition-delay: 0.1s; }
    .card:nth-child(2) { transition-delay: 0.2s; }
    .card:nth-child(3) { transition-delay: 0.3s; }
    .card:nth-child(4) { transition-delay: 0.4s; }
    .card:nth-child(5) { transition-delay: 0.5s; }
    .card:nth-child(6) { transition-delay: 0.6s; }
    
    /* إزالة الحركات التلقائية من الرسوم البيانية */
    .chart-box {
      animation: none !important;
      opacity: 0;
      transform: translateY(40px);
      transition: opacity 1s ease, transform 1s ease;
    }
    
    .chart-box.animated {
      opacity: 1;
      transform: translateY(0);
    }
    
    /* FORCE OVERRIDE - MUST BE FIRST */
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
        body {
      background: #f8fafc;
      font-family: 'Cairo', sans-serif;
      color: #1e293b;
      padding-top: 90px !important;
    }
    
    .chart-box:nth-child(1) { transition-delay: 0.2s; }
    .chart-box:nth-child(2) { transition-delay: 0.4s; }

    /* Balanced Elegant Navbar Styles */
    .main-navbar {
      position: fixed;
      top: 0.7rem;
      left: 50%;
      transform: translateX(-50%);
      width: 91%;
      max-width: 70rem;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(16px);
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.09);
      border-radius: 0.9rem;
      z-index: 1000;
      padding: 0.65rem 1.6rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .main-navbar.hidden {
      transform: translateX(-50%) translateY(-150%) !important;
      opacity: 0;
      pointer-events: none;
    }
    .navbar-logo {
      font-size: 1.45rem;
      font-weight: 750;
      color: #4b6b2f;
      letter-spacing: 0.5px;
      text-decoration: none;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.45rem;
    }
    .navbar-logo i {
      font-size: 1.3rem;
    }
    .navbar-logo:hover {
      color: #3a5524;
      transform: scale(1.02);
    }
    .navbar-links {
      display: flex;
      gap: 1.4rem;
      align-items: center;
    }
    .navbar-link {
      font-weight: 600;
      color: #1e293b;
      text-decoration: none;
      font-size: 1.0rem;
      transition: all 0.3s ease;
      position: relative;
      padding: 0.4rem 0;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }
    .navbar-link i {
      font-size: 1.2rem;
    }
    .navbar-link.active {
      color: #4b6b2f;
      font-weight: 700;
    }
    .navbar-link.active::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 100%;
      height: 2.5px;
      background-color: #4b6b2f;
      border-radius: 2px;
      animation: slideIn 0.3s ease;
    }
    .navbar-link:hover {
      color: #4b6b2f;
      transform: translateY(-1px);
    }
    .navbar-link:hover::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 100%;
      height: 2.5px;
      background-color: #4b6b2f;
      border-radius: 2px;
      animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
      from { width: 0; opacity: 0; }
      to { width: 100%; opacity: 1; }
    }
    .navbar-actions {
      display: flex;
      align-items: center;
      gap: 0.9rem;
    }
    .navbar-btn {
      padding: 0.5rem 1.1rem;
      border-radius: 0.55rem;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      font-size: 0.92rem;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }
    .navbar-btn i {
      font-size: 1.05rem;
    }
    .navbar-btn-login {
      color: #4b6b2f;
      border: 1.5px solid #4b6b2f;
    }
    .navbar-btn-login:hover {
      background-color: rgba(75, 107, 47, 0.1);
      transform: translateY(-1px);
    }
    .navbar-btn-register {
      background-color: #4b6b2f;
      color: white;
    }
    .navbar-btn-register:hover {
      background-color: #3a5524;
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(75, 107, 47, 0.2);
    }
    .navbar-btn-logout {
      background-color: #ef4444;
      color: white;
      border: none;
      cursor: pointer;
    }
    .navbar-btn-logout:hover {
      background-color: #dc2626;
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(239, 68, 68, 0.2);
    }
    .navbar-user {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #4b6b2f;
      text-decoration: none;
      font-weight: 600;
      padding: 0.45rem 0.85rem;
      border-radius: 0.55rem;
      transition: all 0.3s ease;
      font-size: 0.92rem;
    }
    .navbar-user:hover {
      background-color: rgba(75, 107, 47, 0.1);
      transform: translateY(-1px);
    }
    .navbar-user i {
      font-size: 1.3rem;
    }
    @media (max-width: 1024px) {
      .main-navbar {
        padding: 0.5rem 1.2rem;
      }
      .navbar-logo {
        font-size: 1.25rem;
      }
      .navbar-links {
        gap: 1rem;
      }
      .navbar-link {
        font-size: 0.9rem;
      }
      .navbar-btn {
        padding: 0.4rem 0.85rem;
        font-size: 0.85rem;
      }
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="main-navbar" id="mainNavbar">
    <div style="display: flex; align-items: center; gap: 1.8rem;">
      <a href="home111.php" class="navbar-logo">
        <img src="brand_logo.php?f=logo" alt="Miskova Global" style="height: 37px; width: auto; object-fit: contain; border-radius: 5px;">
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

<!-- Dashboard -->
<section class="dashboard animate-on-scroll">
  <h1>لوحة التحكم</h1>
  <div class="cards">

    <div class="card">
      <div class="icon">📦</div>
      <h3>إجمالي الطلبات</h3>
      <p><?php echo $total_orders; ?></p>
    </div>

    <div class="card">
      <div class="icon">🚚</div>
      <h3>نسبة التسليم</h3>
      <p><?php echo $delivery_rate; ?>%</p>
    </div>

    <div class="card">
      <div class="icon">💰</div>
      <h3>إجمالي الأرباح</h3>
      <p><?php echo number_format($total_profit, 2); ?> د.ل</p>
    </div>

    <div class="card">
      <div class="icon">⏳</div>
      <h3>أرباح قيد الانتظار</h3>
      <p><?php echo number_format($expected_profit, 2); ?> د.ل</p>
    </div>

    <div class="card">
      <div class="icon">🏦</div>
      <h3>تم السحب</h3>
      <p><?php echo number_format($withdrawn, 2); ?> د.ل</p>
    </div>

    <div class="card">
      <div class="icon">💳</div>
      <h3>متاح السحب</h3>
      <p><?php echo number_format($available_withdrawal, 2); ?> د.ل</p>
    </div>

    <div class="card">
      <div class="icon">📈</div>
      <h3>أرباح متوقعة</h3>
      <p><?php echo number_format($expected_profit, 2); ?> د.ل</p>
    </div>

  </div>
</section>


  <!-- Order Status -->
  <section class="status-section">
    <h2>حالات الطلبات</h2>
    <div class="status-cards">
      <?php
      // حساب حالات الطلبات المختلفة
      $status_closed_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id AND o.status = 'مغلق'
      ");
      $status_closed = $status_closed_query ? intval($status_closed_query->fetch_assoc()['total'] ?? 0) : 0;

      $status_confirmed_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id AND o.status = 'تم التأكيد'
      ");
      $status_confirmed = $status_confirmed_query ? intval($status_confirmed_query->fetch_assoc()['total'] ?? 0) : 0;

      $status_pending_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id AND o.status = 'قيد الانتظار'
      ");
      $status_pending = $status_pending_query ? intval($status_pending_query->fetch_assoc()['total'] ?? 0) : 0;

      $status_shipping_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id AND o.status = 'في الشحن'
      ");
      $status_shipping = $status_shipping_query ? intval($status_shipping_query->fetch_assoc()['total'] ?? 0) : 0;

      $status_delivered_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id AND o.status = 'تم التوصيل'
      ");
      $status_delivered = $status_delivered_query ? intval($status_delivered_query->fetch_assoc()['total'] ?? 0) : 0;

      $status_preparing_query = $conn->query("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = $user_id AND o.status = 'تحت التحضير'
      ");
      $status_preparing = $status_preparing_query ? intval($status_preparing_query->fetch_assoc()['total'] ?? 0) : 0;
      ?>
      <div class="status-card">
        <h3>مغلق</h3>
        <p><?php echo $status_closed; ?></p>
      </div>
      <div class="status-card">
        <h3>تم التأكيد</h3>
        <p><?php echo $status_confirmed; ?></p>
      </div>
      <div class="status-card">
        <h3>قيد الانتظار</h3>
        <p><?php echo $status_pending; ?></p>
      </div>
      <div class="status-card">
        <h3>في الشحن</h3>
        <p><?php echo $status_shipping; ?></p>
      </div>
      <div class="status-card">
        <h3>تم التوصيل</h3>
        <p><?php echo $status_delivered; ?></p>
      </div>
      <div class="status-card">
        <h3>مرتجع</h3>
        <p><?php echo $returned_orders; ?></p>
      </div>
      <div class="status-card">
        <h3>ملغي</h3>
        <p><?php echo $cancelled_orders; ?></p>
      </div>
      <div class="status-card">
        <h3>محصل</h3>
        <p><?php echo $collected_orders; ?></p>
      </div>
      <div class="status-card">
        <h3>تحت التحضير </h3>
        <p><?php echo $status_preparing; ?></p>
      </div>
    </div>
  </section>
 <!-- Charts Section -->
<section class="charts-section animate-on-scroll">
  <div class="charts-container">
    
    <!-- Pie Chart -->
    <div class="chart-box">
      <h3>نسب حالات الطلبات (تم التوصيل / ملغي / مرتجع)</h3>
      <canvas id="pieChart"></canvas>
    </div>

    <!-- Line Chart -->
    <div class="chart-box">
      <h3>الأرباح على مدار السنة (12 شهر)</h3>
      <canvas id="lineChart"></canvas>
    </div>

  </div>
</section>

    <!-- FOOTER -->
  <footer>
  <div class="social-links">
    <a href="https://facebook.com" target="_blank"><i class='bx bxl-facebook-circle'></i></a>
    <a href="https://instagram.com" target="_blank"><i class='bx bxl-instagram'></i></a>
    <a href="https://tiktok.com" target="_blank"><i class='bx bxl-tiktok'></i></a>
    <a href="https://t.me" target="_blank"><i class='bx bxl-telegram'></i></a>
  </div>
  <p>© 2025 Techora - جميع الحقوق محفوظة</p>
</footer>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

  <script>
  // بيانات الرسوم البيانية
  const chartsData = {
    pieChart: {
      labels: ['تم التوصيل', 'ملغي', 'مرتجع'],
      data: [
        <?php 
        $total_for_pie = $status_delivered + $cancelled_orders + $returned_orders;
        if ($total_for_pie > 0) {
          echo round(($status_delivered / $total_for_pie) * 100, 2) . ', ';
          echo round(($cancelled_orders / $total_for_pie) * 100, 2) . ', ';
          echo round(($returned_orders / $total_for_pie) * 100, 2);
        } else {
          echo '0, 0, 0';
        }
        ?>
      ],
      colors: ['#4b6b2f', '#ef4444', '#8b5cf6']
    },
    lineChart: {
      labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
      data: [
        <?php
        // حساب الأرباح لكل شهر (سعر المنتج الأصلي - العمولة - مصاريف الشحن)
        $monthly_profits = [];
        for ($i = 1; $i <= 12; $i++) {
          $month_start = date('Y-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01');
          $month_end = date('Y-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-t');
          
          $month_profit_query = $conn->query("
            SELECT 
              COALESCE(SUM(oi.original_price * oi.quantity - oi.commission - COALESCE(oi.shipping_cost, 0)), 0) as month_profit
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.user_id = $user_id 
            AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
            AND DATE(o.created_at) BETWEEN '$month_start' AND '$month_end'
          ");
          
          $month_profit = 0;
          if ($month_profit_query) {
            $row = $month_profit_query->fetch_assoc();
            $month_profit = floatval($row['month_profit'] ?? 0);
          }
          if ($month_profit < 0) $month_profit = 0;
          
          $monthly_profits[] = number_format($month_profit, 2);
        }
        echo implode(', ', $monthly_profits);
        ?>
      ]
    }
  };

  // جلب البيانات من قاعدة البيانات
  function loadDashboardData() {
    // انتظار تحميل Chart.js قبل إنشاء الرسوم البيانية
    if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
      updateCharts(chartsData);
    } else {
      console.warn('Chart.js or ChartDataLabels not loaded yet, retrying...');
      setTimeout(() => {
        if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
          updateCharts(chartsData);
        }
      }, 500);
    }
  }

  // تحديث الرسومات البيانية
  function updateCharts(charts) {
    // التحقق من وجود العناصر
    const pieCanvas = document.getElementById('pieChart');
    const lineCanvas = document.getElementById('lineChart');
    
    if (!pieCanvas || !lineCanvas) {
      console.error('Canvas elements not found');
      return;
    }
    
    // Pie Chart
    const ctxPie = pieCanvas.getContext('2d');
    // التحقق من وجود الرسم البياني السابق وتدميره بشكل صحيح
    if (window.pieChart && typeof window.pieChart.destroy === 'function') {
      window.pieChart.destroy();
      window.pieChart = null;
    }
    
    // التحقق من وجود البيانات - السماح بالبيانات الفارغة لعرض رسالة
    if (!charts || !charts.pieChart) {
      console.warn('No pie chart data available');
      return;
    }
    
    window.pieChart = new Chart(ctxPie, {
      type: 'pie',
      data: {
        labels: charts.pieChart.labels || [],
        datasets: [{
          data: charts.pieChart.data || [],
          backgroundColor: charts.pieChart.colors || ['#4b6b2f', '#ef4444', '#8b5cf6'],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: {
          animateRotate: true,
          animateScale: true,
          duration: 1800,
          easing: 'easeOutQuart'
        },
        plugins: {
          legend: { 
            position: 'bottom', 
            labels: { 
              padding: 16,
              font: { size: 14 }
            } 
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                let label = context.label || '';
                if (label) {
                  label += ': ';
                }
                label += context.parsed + '%';
                return label;
              }
            }
          },
          datalabels: {
            color: '#fff',
            font: { weight: 'bold', size: 14 },
            formatter: (value) => value + '%'
          }
        }
      },
      plugins: [ChartDataLabels]
    });

    // Line Chart
    const ctxLine = lineCanvas.getContext('2d');
    // التحقق من وجود الرسم البياني السابق وتدميره بشكل صحيح
    if (window.lineChart && typeof window.lineChart.destroy === 'function') {
      window.lineChart.destroy();
      window.lineChart = null;
    }
    
    // التحقق من وجود البيانات - السماح بالبيانات الفارغة لعرض الرسم البياني
    if (!charts || !charts.lineChart) {
      console.warn('No line chart data available');
      return;
    }
    
    // التأكد من وجود labels و data
    if (!charts.lineChart.labels || charts.lineChart.labels.length === 0) {
      charts.lineChart.labels = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    }
    if (!charts.lineChart.data || charts.lineChart.data.length === 0) {
      charts.lineChart.data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    }
    
    window.lineChart = new Chart(ctxLine, {
      type: 'line',
      data: {
        labels: charts.lineChart.labels || [],
        datasets: [{
          label: 'الأرباح (د.ل)',
          data: charts.lineChart.data || [],
          borderColor: '#4b6b2f',
          backgroundColor: 'rgba(75,107,47,0.15)',
          tension: 0.4,
          fill: true,
          borderWidth: 3,
          pointRadius: 5,
          pointHoverRadius: 7,
          pointBackgroundColor: '#4b6b2f',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { 
            beginAtZero: true, 
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: {
              callback: function(value) {
                return value + ' د.ل';
              }
            }
          },
          x: { grid: { display: false } }
        },
        animation: {
          duration: 2500,
          easing: 'easeInOutCubic'
        }
      }
    });
  }

  // تفعيل الحركات عند الوصول للعنصر بالتمرير
  function initScrollAnimations() {
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animated');
          // إيقاف مراقبة العنصر بعد تفعيل الحركة
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);
    
    // مراقبة جميع العناصر التي تحتاج حركة
    document.querySelectorAll('.animate-on-scroll, .card, .chart-box').forEach(el => {
      observer.observe(el);
    });
  }

  // تحميل البيانات عند فتح الصفحة
  document.addEventListener('DOMContentLoaded', function() {
    // تفعيل الحركات
    initScrollAnimations();
    
    // انتظار تحميل Chart.js و ChartDataLabels
    if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
      loadDashboardData();
    } else {
      // إعادة المحاولة بعد تحميل المكتبات
      window.addEventListener('load', function() {
        setTimeout(loadDashboardData, 100);
      });
    }
  });

  // تحديث البيانات كل 30 ثانية
  setInterval(loadDashboardData, 30000);

</script>

</body>
</html>
<?php // end ?>