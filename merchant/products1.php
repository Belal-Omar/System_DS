<?php
// ملف: products1.php - لوحة التاجر - المنتجات
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

// جلب منتجات التاجر فقط
$products_query = $conn->query("
    SELECT p.*, pi.image_path 
    FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
    WHERE p.user_id = $user_id
    ORDER BY p.created_at DESC
");

$products = [];
while($row = $products_query->fetch_assoc()) {
    if (!$row['image_path']) {
        $first_image_result = $conn->query("
            SELECT image_path FROM product_images 
            WHERE product_id = {$row['id']} 
            LIMIT 1
        ");
        if ($first_image_result && $first_image_result->num_rows > 0) {
            $first_image = $first_image_result->fetch_assoc();
            $row['image_path'] = $first_image['image_path'];
        }
    }
    // حساب السعر للتاجر (سعر المنتج الأصلي)
    $row['merchant_price'] = floatval($row['price']);
    $products[] = $row;
}

// التحقق من وجود عمود status
$check_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
$has_status_column = ($check_status && $check_status->num_rows > 0);

// الإحصائيات
$total_products = count($products);
if ($has_status_column) {
    $active_products = count(array_filter($products, function($p) { 
        return ($p['status'] ?? 'active') == 'active'; 
    }));
} else {
    $active_products = count(array_filter($products, function($p) { 
        return ($p['stock'] ?? 0) > 0; 
    }));
}
$inactive_products = $total_products - $active_products;

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
  <title>لوحة التاجر - المنتجات</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styleH.css" />

  <style>
    body {
      font-family: "Cairo", sans-serif;
      margin: 0;
      padding: 2rem;
      background: #f3f4f6;
      color: #1f2937;
      padding-top: 100px;
    }

    h1 {
      text-align: center;
      margin-top: 2rem;
      margin-bottom: 1rem;
      font-size: 1.8rem;
      font-weight: 700;
      color: #374151;
    }

    /* =========================
       Product Stats – Small Inline
    ========================= */
    .product-stats-inline {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
      color: #374151;
    }

    .product-stats-inline span {
      display: flex;
      gap: 0.25rem;
      font-weight: 600;
    }

    .product-stats-inline span b {
      color: #111827;
    }

    /* =========================
       Table Style
    ========================= */
    .table-container {
      max-width: 1100px;
      margin: 0 auto;
      overflow-x: auto;
      border-radius: 1rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
      background: #fff;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 600px;
    }

    thead {
      background-color: #e5e7eb;
      position: sticky;
      top: 0;
      z-index: 1;
    }

    th, td {
      padding: 0.75rem 1rem;
      text-align: center;
      border-bottom: 1px solid #d1d5db;
      font-size: 0.95rem;
    }

    th { font-weight: 700; color: #374151; }

    tbody tr:nth-child(even) { background-color: #f9fafb; }
    tbody tr:hover { background-color: #f3f4f6; }

    td img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 0.5rem;
      border: 1px solid #ccc;
    }

    .active { color: #16a34a; font-weight: 700; }
    .inactive { color: #ef4444; font-weight: 700; }

    footer {
      margin-top: 3rem;
      background-color: #111827;
      color: white;
      padding: 1.5rem 0;
      text-align: center;
      border-radius: 1rem;
    }

    footer .social-links {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
    }

    footer .social-links a { color: white; text-decoration: none; }
    footer .social-links a:hover { color: #9ae6b4; }

    @media (max-width: 768px){
      th, td { font-size: 0.85rem; padding: 0.5rem 0.75rem; }
      td img { width: 50px; height: 50px; }
      .product-stats-inline { gap: 1rem; font-size: 0.85rem; }
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

  <h1>المنتجات المرسلة من الادمن</h1>

  <!-- إحصائيات صغيرة inline -->
  <div class="product-stats-inline">
    <span>إجمالي المنتجات: <b id="totalProducts"><?php echo $total_products; ?></b></span>
    <span>المفعل: <b id="activeProducts"><?php echo $active_products; ?></b></span>
    <span>غير المفعل: <b id="inactiveProducts"><?php echo $inactive_products; ?></b></span>
  </div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>الصورة</th>
          <th>اسم المنتج</th>
          <th>المخزون</th>
          <th>السعر</th>
          <th>عمولة النظام</th>
          <th>صافي الربح</th>
          <th>تاريخ الرفع</th>
          <th>الحالة</th>
        </tr>
      </thead>
      <tbody id="productsTableBody">
        <?php if(empty($products)): ?>
        <tr>
          <td colspan="8">لا توجد منتجات حتى الآن</td>
        </tr>
        <?php else: ?>
        <?php foreach($products as $product): 
          // تحديد الحالة
          if ($has_status_column) {
              $product_status = $product['status'] ?? 'active';
              $status_text = $product_status == 'active' ? 'مفعل' : 'غير مفعل';
              $status_class = $product_status == 'active' ? 'active' : 'inactive';
          } else {
              $status_text = ($product['stock'] ?? 0) > 0 ? 'مفعل' : 'غير مفعل';
              $status_class = ($product['stock'] ?? 0) > 0 ? 'active' : 'inactive';
          }
          
          $image_src = $product['image_path'] ? htmlspecialchars($product['image_path']) : 'imgs/default-product.jpg';
          $date_formatted = date('Y-m-d', strtotime($product['created_at']));
          $system_commission = floatval($product['special_commission'] ?? 0);
          $net_profit = floatval($product['price']) - $system_commission;
        ?>
        <tr>
          <td><img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='imgs/default-product.jpg'" /></td>
          <td><?php echo htmlspecialchars($product['name']); ?></td>
          <td><span class="stock-quantity" data-product-id="<?php echo $product['id']; ?>"><?php echo intval($product['stock'] ?? 0); ?></span></td>
          <td><?php echo number_format($product['price'], 2); ?> د.ل</td>
          <td><?php echo number_format($system_commission, 2); ?> د.ل</td>
          <td><strong style="color: #4b6b2f;"><?php echo number_format($net_profit, 2); ?> د.ل</strong></td>
          <td><?php echo $date_formatted; ?></td>
          <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <footer>
    <div class="social-links">
      <a href="#"><i class='bx bxl-facebook-circle'></i></a>
      <a href="#"><i class='bx bxl-instagram'></i></a>
      <a href="#"><i class='bx bxl-tiktok'></i></a>
      <a href="#"><i class='bx bxl-telegram'></i></a>
    </div>
    <p>  © 2025 Techora - جميع الحقوق محفوظة </p>
  </footer>

  <script>
    function loadProducts() {
      const tbody = document.getElementById('productsTableBody');
      const totalEl = document.getElementById('totalProducts');
      const activeEl = document.getElementById('activeProducts');
      const inactiveEl = document.getElementById('inactiveProducts');

      // البيانات موجودة بالفعل من PHP، فقط نحدث الإحصائيات
      const total = <?php echo $total_products; ?>;
      const active = <?php echo $active_products; ?>;
      const inactive = <?php echo $inactive_products; ?>;

      if (totalEl) totalEl.textContent = total;
      if (activeEl) activeEl.textContent = active;
      if (inactiveEl) inactiveEl.textContent = inactive;
    }

    window.onload = loadProducts;

    // Function to update stock quantities in real-time
    function updateStockQuantities() {
      // Get all stock quantity elements
      const stockElements = document.querySelectorAll('.stock-quantity');
      
      // Extract product IDs
      const productIds = Array.from(stockElements).map(el => el.dataset.productId);
      
      if (productIds.length === 0) return;
      
      // Fetch updated stock quantities
      fetch('get_updated_stock.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_ids=' + encodeURIComponent(JSON.stringify(productIds))
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update the stock quantities on the page
          stockElements.forEach(element => {
            const productId = element.dataset.productId;
            if (data.stock[productId] !== undefined) {
              element.textContent = data.stock[productId];
              
              // Update status class based on stock
              const row = element.closest('tr');
              if (row) {
                const statusCell = row.querySelector('td:last-child span');
                if (statusCell) {
                  const newStock = parseInt(data.stock[productId]);
                  if (newStock > 0) {
                    statusCell.className = 'active';
                    statusCell.textContent = 'مفعل';
                  } else {
                    statusCell.className = 'inactive';
                    statusCell.textContent = 'غير متوفر';
                  }
                }
              }
            }
          });
          
          // Update the counters
          if (document.getElementById('activeProducts') && document.getElementById('inactiveProducts')) {
            const activeCount = document.querySelectorAll('.active').length;
            const inactiveCount = document.querySelectorAll('.inactive').length - 1; // Subtract 1 for the header
            document.getElementById('activeProducts').textContent = activeCount;
            document.getElementById('inactiveProducts').textContent = Math.max(0, inactiveCount);
          }
        }
      })
      .catch(error => console.error('Error updating stock:', error));
    }

    // Update stock every 30 seconds
    setInterval(updateStockQuantities, 30000);
    
    // Initial update after page load
    document.addEventListener('DOMContentLoaded', function() {
      // Wait for the page to fully load
      setTimeout(updateStockQuantities, 2000);
    });
  </script>

</body>
</html>