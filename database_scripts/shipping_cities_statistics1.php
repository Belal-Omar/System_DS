<?php 
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول ونوع المستخدم
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user = null;

if ($user_id) {
    $user_id = (int)$user_id;
    $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user_query) {
        $user = $user_query->fetch_assoc();
    }
}

// منع المسوق من دخول صفحات التاجر
if ($user && ($user['user_type'] ?? '') == 'مسوق') {
    header("Location: home.php");
    exit;
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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>إحصائيات مدن الشحن</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
  <link rel="stylesheet" href="Stylepp.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* FORCE OVERRIDE - MUST BE FIRST */
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      max-width: 100vw !important;
      min-width: 100% !important;
      overflow-x: hidden !important;
      overflow-y: auto !important;
      box-sizing: border-box !important;
      position: relative !important;
    }
    * {
      box-sizing: border-box !important;
      max-width: 100% !important;
    }
    body > * {
      max-width: 100% !important;
    }
    .container, .container-fluid, .row, .col, [class*="col-"] {
      max-width: 100% !important;
      overflow-x: hidden !important;
    }
    table {
      max-width: 100% !important;
      table-layout: fixed !important;
    }
    img, video, iframe {
      max-width: 100% !important;
      height: auto !important;
    }
    /* Chart.js specific fixes */
    .chart-container, .chart-wrapper, .chart-box {
      max-width: 100% !important;
      overflow: hidden !important;
      position: relative !important;
    }
    .chart-container canvas, .chart-wrapper canvas, .chart-box canvas {
      max-width: 100% !important;
      height: auto !important;
      max-height: 400px !important;
    }
    .chartjs-render-monitor {
      max-width: 100% !important;
      overflow: hidden !important;
    }
    .main-navbar {
      position: fixed;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 100%;
      max-width: 1400px;
      background: white;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      z-index: 1000;
      padding: 1rem 2rem;
      transition: all 0.3s ease;
    }
    .navbar-logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1.5rem;
      font-weight: bold;
      color: #4b6b2f;
      text-decoration: none;
    }
    .navbar-links {
      display: flex;
      gap: 2rem;
    }
    .navbar-link {
      color: #6b7280;
      text-decoration: none;
      font-weight: 500;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      transition: all 0.3s ease;
    }
    .navbar-link:hover {
      color: #4b6b2f;
      background: rgba(75, 107, 47, 0.1);
    }
    .navbar-link.active {
      color: #4b6b2f;
      background: rgba(75, 107, 47, 0.1);
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
      text-decoration: none;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-size: 0.9rem;
    }
    .navbar-btn-logout {
      background: #dc2626;
      color: white;
    }
    .navbar-btn-logout:hover {
      background: #b91c1c;
      transform: translateY(-2px);
    }
    .navbar-user {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #4b6b2f;
      font-weight: 600;
      text-decoration: none;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      transition: all 0.3s ease;
    }
    .navbar-user:hover {
      background: rgba(75, 107, 47, 0.1);
    }
    .page-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 120px 2rem 2rem;
      width: 100%;
      box-sizing: border-box;
    }
    .page-title {
      font-size: 2.5rem;
      font-weight: bold;
      color: #1f2937;
      margin-bottom: 2rem;
      text-align: center;
      position: relative;
    }
    .page-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 4px;
      background: linear-gradient(135deg, #4b6b2f 0%, #3a5524 100%);
      border-radius: 2px;
    }
    
    /* Modern Cards Design */
    .stat-card {
      background: white;
      border-radius: 1.2rem;
      padding: 2rem;
      box-shadow: 0 4px 6px rgba(0,0,0,0.07);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(0,0,0,0.05);
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 4px;
      height: 100%;
      background: linear-gradient(135deg, #4b6b2f 0%, #3a5524 100%);
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .stat-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 24px rgba(75, 107, 47, 0.15);
    }
    .stat-card:hover::before {
      opacity: 1;
    }
    .stat-card.border-blue { border-right: 4px solid #3b82f6; }
    .stat-card.border-green { border-right: 4px solid #10b981; }
    .stat-card.border-yellow { border-right: 4px solid #f59e0b; }
    .stat-card.border-purple { border-right: 4px solid #8b5cf6; }
    
    /* Icon Boxes */
    .icon-box {
      width: 60px;
      height: 60px;
      border-radius: 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-left: 1.5rem;
      position: relative;
    }
    .icon-box::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border-radius: 1rem;
      background: inherit;
      opacity: 0.1;
      z-index: -1;
    }
    .icon-box.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .icon-box.green { background: linear-gradient(135deg, #10b981, #059669); }
    .icon-box.yellow { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .icon-box.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .icon-box i {
      color: white;
      font-size: 1.5rem;
    }
    
    /* Modern Chart Containers */
    .chart-container {
      background: white;
      border-radius: 1.2rem;
      padding: 2rem;
      box-shadow: 0 4px 6px rgba(0,0,0,0.07);
      border: 1px solid rgba(0,0,0,0.05);
      transition: all 0.3s ease;
    }
    .chart-container:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .chart-container h3 {
      font-size: 1.3rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .chart-container h3::before {
      content: '📊';
      font-size: 1.5rem;
    }
    
    /* Modern Tables */
    .modern-table {
      background: white;
      border-radius: 1.2rem;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0,0,0,0.07);
      border: 1px solid rgba(0,0,0,0.05);
    }
    .modern-table thead {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }
    .modern-table th {
      padding: 1.2rem 1rem;
      text-align: right;
      font-weight: 700;
      color: #374151;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #e5e7eb;
    }
    .modern-table td {
      padding: 1rem;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }
    .modern-table tbody tr {
      transition: all 0.2s ease;
    }
    .modern-table tbody tr:hover {
      background: #f8fafc;
      transform: scale(1.01);
    }
    
    /* Status Badges */
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.4rem 0.8rem;
      border-radius: 0.5rem;
      font-size: 0.85rem;
      font-weight: 600;
      gap: 0.4rem;
    }
    .status-badge.completed {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      color: #065f46;
    }
    .status-badge.cancelled {
      background: linear-gradient(135deg, #fee2e2, #fecaca);
      color: #991b1b;
    }
    .status-badge.returned {
      background: linear-gradient(135deg, #fed7aa, #fdba74);
      color: #92400e;
    }
    
    /* Progress Bars */
    .progress-wrapper {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .progress-bar {
      flex: 1;
      height: 8px;
      background: #e5e7eb;
      border-radius: 4px;
      overflow: hidden;
      position: relative;
    }
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #10b981 0%, #059669 100%);
      border-radius: 4px;
      transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }
    .progress-fill::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      animation: shimmer 2s infinite;
    }
    @keyframes shimmer {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }
    
    /* City Rankings */
    .city-ranking {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: white;
      border-radius: 1rem;
      transition: all 0.3s ease;
      border: 1px solid rgba(0,0,0,0.05);
    }
    .city-ranking:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .ranking-number {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
    }
    .ranking-number.gold {
      background: linear-gradient(135deg, #fbbf24, #f59e0b);
      color: white;
    }
    .ranking-number.silver {
      background: linear-gradient(135deg, #e5e7eb, #d1d5db);
      color: #374151;
    }
    .ranking-number.bronze {
      background: linear-gradient(135deg, #f97316, #ea580c);
      color: white;
    }
    .ranking-number.other {
      background: linear-gradient(135deg, #6b7280, #4b5563);
      color: white;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .page-container { padding: 100px 1rem 1rem; }
      .page-title { font-size: 2rem; }
      .stat-card { padding: 1.5rem; }
      .chart-container { padding: 1.5rem; }
      .modern-table { font-size: 0.9rem; }
      .modern-table th, .modern-table td { padding: 0.75rem 0.5rem; }
    }
    }
    .city-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .chart-container {
      background: white;
      border-radius: 1rem;
      padding: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    .progress-bar {
      background: #e5e7eb;
      border-radius: 0.5rem;
      height: 0.5rem;
      overflow: hidden;
    }
    .progress-fill {
      background: #4b6b2f;
      height: 100%;
      transition: width 0.3s ease;
    }
    .status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-completed {
      background: #d1fae5;
      color: #065f46;
    }
    .status-cancelled {
      background: #fee2e2;
      color: #dc2626;
    }
    .status-returned {
      background: #fed7aa;
      color: #c2410c;
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

  <script src="https://cdn.tailwindcss.com"></script>
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
      display: flex;
      align-items: center;
      gap: 0.5rem;
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

<main class="page-container">
  <div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">إحصائيات مدن الشحن</h1>
    <div class="flex gap-3">
        <button class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors" onclick="exportToExcel()">
            <i class="bx bx-download ml-2"></i>
            تصدير Excel
        </button>
        <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors" onclick="syncCitiesStats()" id="refreshBtn">
            <i class="fa fa-refresh"></i> تحديث الإحصائيات
        </button>
    </div>
</div>

<!-- رسائل التحديث -->
<div id="messageContainer"></div>

<div class="page-wrapper">

<!-- إحصائيات عامة -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg ml-4">
                <i class='bx bx-map text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">عدد المدن</p>
                <h3 class="text-2xl font-bold text-gray-800" id="totalCities">0</h3>
                <p class="text-sm text-gray-500 mt-1">مدينة نشطة</p>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-green-500">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg ml-4">
                <i class='bx bx-shopping-bag text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الطلبات</p>
                <h3 class="text-2xl font-bold text-gray-800" id="totalOrders">0</h3>
                <p class="text-sm text-gray-500 mt-1">من جميع المدن</p>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-yellow-500">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg ml-4">
                <i class='bx bx-money text-yellow-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الإيرادات المكتملة</p>
                <h3 class="text-2xl font-bold text-gray-800" id="totalRevenue">0 د.ل</h3>
                <p class="text-sm text-gray-500 mt-1">المجموع</p>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-purple-500">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg ml-4">
                <i class='bx bx-trending-up text-purple-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">متوسط قيمة الطلب المكتمل</p>
                <h3 class="text-2xl font-bold text-gray-800" id="avgOrderValue">0 د.ل</h3>
                <p class="text-sm text-gray-500 mt-1">المجموع فقط لكل طلب مكتمل</p>
            </div>
        </div>
    </div>
</div>

<!-- رسوم بيانية -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="chart-container">
        <h3 class="text-xl font-bold text-gray-800 mb-4">توزيع الطلبات حسب المدينة</h3>
        <canvas id="citiesChart" height="300"></canvas>
    </div>
    
    <div class="chart-container">
        <h3 class="text-xl font-bold text-gray-800 mb-4">الإيرادات المكتملة حسب المدينة (المجموع فقط)</h3>
        <canvas id="revenueChart" height="300"></canvas>
    </div>
</div>

<!-- أفضل المدن -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="stat-card">
        <h3 class="text-xl font-bold text-gray-800 mb-4">أفضل المدن (الأكثر إيرادات مكتملة)</h3>
        <div class="space-y-3" id="topCities">
            <!-- سيتم ملؤها بالجافاسكريبت -->
        </div>
    </div>
    
    <div class="stat-card">
        <h3 class="text-xl font-bold text-gray-800 mb-4">المدن الأقل نشاطاً (أقل إيرادات مكتملة)</h3>
        <div class="space-y-3" id="bottomCities">
            <!-- سيتم ملؤها بالجافاسكريبت -->
        </div>
    </div>
</div>

<!-- إحصائيات الحالات -->
<div class="stat-card p-6 mb-8">
    <h3 class="text-xl font-bold text-gray-800 mb-4">توزيع الطلبات حسب الحالة</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4" id="statusStats">
        <!-- سيتم ملؤها بالجافاسكريبت -->
    </div>
</div>

<!-- تفاصيل جميع المدن -->
<div class="stat-card">
    <h3 class="text-xl font-bold text-gray-800 mb-6">تفاصيل جميع المدن</h3>
    <div class="overflow-x-auto">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>المدينة</th>
                    <th>مصاريف الشحن</th>
                    <th>عدد الطلبات</th>
                    <th>الإيرادات المكتملة (المجموع فقط)</th>
                    <th>مكتمل</th>
                    <th>ملغي</th>
                    <th>مرتجع</th>
                    <th>متوسط الطلب</th>
                    <th>نسبة النجاح</th>
                    <th>المسوقون الفريدون</th>
                </tr>
            </thead>
            <tbody id="citiesTableBody">
                <!-- سيتم ملؤها بالجافاسكريبت -->
            </tbody>
        </table>
    </div>
</div>

</div>

</main>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .stat-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .orders-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .orders-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: right;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
    }
    
    .orders-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .orders-table tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-completed {
        background: #d4edda;
        color: #155724;
    }
    
    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-returned {
        background: #fff3cd;
        color: #856404;
    }
    
    .progress-bar {
        width: 100%;
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: #4b6b2f;
        transition: width 0.3s ease;
    }
    
    .refresh-btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .message {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 500;
    }
    
    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<script>
// بيانات المدن
let citiesData = [];
let apiData = null; // تخزين بيانات الـ API الكاملة
let citiesChart = null;
let revenueChart = null;

// جلب إحصائيات المدن
async function syncCitiesStats() {
  const refreshBtn = document.getElementById('refreshBtn');
  const messageContainer = document.getElementById('messageContainer');
  
  try {
    refreshBtn.classList.add('loading');
    refreshBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري التحديث...';
    
    const response = await fetch('admin_get_shipping_cities_stats.php');
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.success) {
      citiesData = data.cities || [];
      apiData = data; // تخزين بيانات الـ API الكاملة
      console.log('Admin API Data:', apiData); // للتصحيح
      updateUI();
      showMessage('تم تحديث الإحصائيات بنجاح', 'success');
    } else {
      showMessage(data.message || 'فشل تحميل الإحصائيات', 'error');
    }
  } catch (error) {
    console.error('Error fetching cities stats:', error);
    showMessage('حدث خطأ في الاتصال بالخادم: ' + error.message, 'error');
  } finally {
    refreshBtn.classList.remove('loading');
    refreshBtn.innerHTML = '<i class="fa fa-refresh"></i> تحديث الإحصائيات';
  }
}

// تحديث الواجهة
function updateUI() {
  updateStatistics();
  updateCharts();
  updateTopCities();
  updateBottomCities();
  updateStatusStats();
  updateCitiesTable();
}

// تحديث الإحصائيات العامة
function updateStatistics() {
  const totalCities = citiesData.length;
  const totalOrders = apiData?.admin_stats?.direct_total_orders || 0;
  const completedOrders = apiData?.admin_stats?.direct_completed_orders || 0;
  const totalRevenue = parseFloat(apiData?.admin_stats?.direct_completed_revenue || 0);
  console.log('Admin Values:', { totalOrders, completedOrders, totalRevenue }); // للتصحيح
  
  document.getElementById('totalCities').textContent = totalCities;
  document.getElementById('totalOrders').textContent = totalOrders.toLocaleString();
  document.getElementById('totalRevenue').textContent = totalRevenue.toFixed(2) + ' د.ل';
  document.getElementById('avgOrderValue').textContent = (completedOrders > 0 ? totalRevenue / completedOrders : 0).toFixed(2) + ' د.ل';
}

// تحديث الرسوم البيانية
function updateCharts() {
  // تدمير الرسوم البيانية الموجودة
  if (citiesChart) {
    citiesChart.destroy();
  }
  if (revenueChart) {
    revenueChart.destroy();
  }
  
  // رسم بياني لتوزيع الطلبات
  const citiesCtx = document.getElementById('citiesChart').getContext('2d');
  const maxOrders = Math.max(...citiesData.slice(0, 10).map(city => city.orders_count || 0));
  
  citiesChart = new Chart(citiesCtx, {
    type: 'bar',
    data: {
      labels: citiesData.slice(0, 10).map(city => city.city_name || 'Unknown'),
      datasets: [{
        label: 'عدد الطلبات',
        data: citiesData.slice(0, 10).map(city => parseInt(city.orders_count || 0)),
        backgroundColor: 'rgba(75, 107, 47, 0.8)',
        borderColor: 'rgba(75, 107, 47, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: maxOrders > 0 ? maxOrders * 1.2 : 10
        },
        x: {
          ticks: {
            maxRotation: 45,
            minRotation: 0,
            font: {
              size: 11
            }
          }
        }
      }
    }
  });
  
  // رسم بياني للإيرادات
  const revenueCtx = document.getElementById('revenueChart').getContext('2d');
  revenueChart = new Chart(revenueCtx, {
    type: 'doughnut',
    data: {
      labels: citiesData.slice(0, 8).map(city => city.city_name || 'Unknown'),
      datasets: [{
        data: citiesData.slice(0, 8).map(city => parseFloat(city.completed_revenue || 0)),
        backgroundColor: [
          'rgba(75, 107, 47, 0.8)',
          'rgba(59, 130, 246, 0.8)',
          'rgba(245, 158, 11, 0.8)',
          'rgba(239, 68, 68, 0.8)',
          'rgba(139, 92, 246, 0.8)',
          'rgba(236, 72, 153, 0.8)',
          'rgba(34, 197, 94, 0.8)',
          'rgba(251, 146, 60, 0.8)'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 12,
            padding: 8,
            font: {
              size: 10
            }
          }
        }
      },
      cutout: '50%'
    }
  });
}

// تحديث أفضل المدن
function updateTopCities() {
  const topCities = citiesData.slice(0, 5);
  const container = document.getElementById('topCities');
  
  container.innerHTML = topCities.map((city, index) => `
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <div class="flex items-center">
        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center ml-3">
          <span class="text-green-600 font-bold">${index + 1}</span>
        </div>
        <div>
          <p class="font-semibold text-gray-800">${city.city_name || 'Unknown'}</p>
          <p class="text-sm text-gray-500">${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل</p>
        </div>
      </div>
      <div class="text-left">
        <p class="font-bold text-gray-800">${city.completed_orders || 0}</p>
        <p class="text-sm text-gray-500">طلب مكتمل</p>
      </div>
    </div>
  `).join('');
}

// تحديث المدن الأقل نشاطاً
function updateBottomCities() {
  const bottomCities = citiesData.slice(-5).reverse();
  const container = document.getElementById('bottomCities');
  
  container.innerHTML = bottomCities.map((city, index) => `
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
      <div class="flex items-center">
        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center ml-3">
          <span class="text-red-600 font-bold">${index + 1}</span>
        </div>
        <div>
          <p class="font-semibold text-gray-800">${city.city_name || 'Unknown'}</p>
          <p class="text-sm text-gray-500">${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل</p>
        </div>
      </div>
      <div class="text-left">
        <p class="font-bold text-gray-800">${city.completed_orders || 0}</p>
        <p class="text-sm text-gray-500">طلب مكتمل</p>
      </div>
    </div>
  `).join('');
}

// تحديث إحصائيات الحالات
function updateStatusStats() {
  const statusStats = apiData?.status_stats || {};
  const container = document.getElementById('statusStats');
  
  const statusConfig = {
    'pending': { label: 'قيد الانتظار', color: 'yellow' },
    'confirmed': { label: 'تم التأكيد', color: 'blue' },
    'processing': { label: 'قيد التنفيذ', color: 'purple' },
    'shipping': { label: 'في الشحن', color: 'indigo' },
    'completed': { label: 'مكتمل', color: 'green' },
    'cancelled': { label: 'ملغي', color: 'red' },
    'returned': { label: 'مرتجع', color: 'orange' }
  };
  
  container.innerHTML = Object.entries(statusConfig).map(([key, config]) => `
    <div class="text-center p-4 bg-${key === 'completed' ? 'green' : 'gray'}-50 rounded-lg">
      <div class="text-2xl font-bold text-${config.color}-600">${statusStats[key] || 0}</div>
      <div class="text-sm text-gray-600">${config.label}</div>
    </div>
  `).join('');
}

// تحديث جدول المدن
function updateCitiesTable() {
  const tbody = document.getElementById('citiesTableBody');
  
  tbody.innerHTML = citiesData.map(city => {
    const successRate = (city.orders_count || 0) > 0 ? ((city.completed_orders || 0) / (city.orders_count || 0)) * 100 : 0;
    
    return `
      <tr class="hover:bg-gray-50">
        <td>
          <div class="flex items-center">
            <i class='bx bx-map-pin text-blue-500 ml-2'></i>
            <span class="font-semibold">${city.city_name || 'Unknown'}</span>
          </div>
        </td>
        <td>${parseFloat(city.shipping_cost || 0).toFixed(2)} د.ل</td>
        <td>
          <span class="font-bold text-blue-600">${city.orders_count || 0}</span>
        </td>
        <td>
          <span class="font-bold text-green-600">${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل</span>
        </td>
        <td>
          <span class="status-badge status-completed">${city.completed_orders || 0}</span>
        </td>
        <td>
          <span class="status-badge status-cancelled">${city.cancelled_orders || 0}</span>
        </td>
        <td>
          <span class="status-badge status-returned">${city.returned_orders || 0}</span>
        </td>
        <td>${parseFloat(city.avg_order_value || 0).toFixed(2)} د.ل</td>
        <td>
          <div class="flex items-center">
            <div class="progress-bar w-16 ml-2">
              <div class="progress-fill" style="width: ${successRate}%"></div>
            </div>
            <span class="text-sm font-semibold">${successRate.toFixed(1)}%</span>
          </div>
        </td>
        <td>
          <span class="font-bold text-purple-600">${city.unique_marketers || 0}</span>
        </td>
      </tr>
    `;
  }).join('');
}

// عرض الرسائل
function showMessage(message, type) {
  const messageContainer = document.getElementById('messageContainer');
  const messageDiv = document.createElement('div');
  messageDiv.className = `message ${type}`;
  messageDiv.textContent = message;
  
  messageContainer.innerHTML = '';
  messageContainer.appendChild(messageDiv);
  
  setTimeout(() => {
    messageDiv.remove();
  }, 5000);
}

// تصدير Excel
function exportToExcel() {
  if (!citiesData || citiesData.length === 0) {
    showMessage('لا توجد بيانات للتصدير', 'error');
    return;
  }
  
  let csv = '\ufeff'; // BOM for UTF-8
  csv += 'المدينة,مصاريف الشحن,عدد الطلبات,الإيرادات المكتملة,مكتمل,ملغي,مرتجع,متوسط الطلب,نسبة النجاح,المسوقون الفريدون\n';
  
  citiesData.forEach(city => {
    const successRate = (city.orders_count || 0) > 0 ? ((city.completed_orders || 0) / (city.orders_count || 0)) * 100 : 0;
    csv += `"${city.city_name || 'Unknown'}",`;
    csv += `"${parseFloat(city.shipping_cost || 0).toFixed(2)} د.ل",`;
    csv += `"${city.orders_count || 0}",`;
    csv += `"${parseFloat(city.completed_revenue || 0).toFixed(2)} د.ل",`;
    csv += `"${city.completed_orders || 0}",`;
    csv += `"${city.cancelled_orders || 0}",`;
    csv += `"${city.returned_orders || 0}",`;
    csv += `"${parseFloat(city.avg_order_value || 0).toFixed(2)} د.ل",`;
    csv += `"${successRate.toFixed(1)}%",`;
    csv += `"${city.unique_marketers || 0}"\n`;
  });
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `shipping_cities_stats_${new Date().toISOString().split('T')[0]}.csv`;
  link.click();
  
  showMessage('تم تصدير البيانات بنجاح', 'success');
}

// تحديث تلقائي كل 5 دقائق
setInterval(syncCitiesStats, 5 * 60 * 1000);

// تحميل البيانات عند فتح الصفحة
document.addEventListener('DOMContentLoaded', function() {
  syncCitiesStats();
});
</script>

</body>
</html>
