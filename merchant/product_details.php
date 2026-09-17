<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ملف: product_details.php
include(__DIR__ . '/core/config.php");

$product_id = intval($_GET['id'] ?? 0);

if ($product_id == 0) {
    header("Location: product.html");
    exit;
}

// جلب بيانات المنتج
$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: product.html");
    exit;
}

// جلب جميع صور المنتج
$images_stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
$images_stmt->bind_param("i", $product_id);
$images_stmt->execute();
$all_images = $images_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// تحديد الصورة الرئيسية الأولية
$product['image'] = 'imgs/default-product.jpg';
if (count($all_images) > 0) {
    // محاولة العثور على الصورة الرئيسية المحددة
    foreach ($all_images as $img) {
        if ($img['is_main'] == 1) {
            $product['image'] = $img['image_path'];
            break;
        }
    }
    // إذا لم تكن هناك صورة رئيسية، استخدم الأولى
    if ($product['image'] == 'imgs/default-product.jpg') {
        $product['image'] = $all_images[0]['image_path'];
    }
}

// جلب المخزون التفصيلي
$inventory_stmt = $conn->prepare("SELECT * FROM product_inventory WHERE product_id = ? AND quantity > 0");
$inventory_stmt->bind_param("i", $product_id);
$inventory_stmt->execute();
$inventory = $inventory_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// تجميع الألوان والمقاسات المتاحة
$available_colors = [];
$available_sizes = [];
$inventory_data = [];
$has_inventory = count($inventory) > 0;

foreach ($inventory as $item) {
    if (!in_array($item['color'], $available_colors)) {
        $available_colors[] = $item['color'];
    }
    if (!in_array($item['size'], $available_sizes)) {
        $available_sizes[] = $item['size'];
    }
    $inventory_data[] = $item;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $product['name'] ?> | تفاصيل المنتج</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="notifications.js?v=2"></script>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="Stylepp.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
<style>
    /* Custom Styles for Shipping City Dropdown */
    #shipping-city-dropdown {
      border: 2px solid #4b6b2f !important;
      border-radius: 12px !important;
      box-shadow: 0 8px 25px rgba(75, 107, 47, 0.15) !important;
      max-height: 280px !important;
      overflow-y: auto !important;
      background: #ffffff !important;
    }
    
    .city-item {
      padding: 12px 16px !important;
      cursor: pointer !important;
      border-bottom: 1px solid #f0f0f0 !important;
      transition: all 0.3s ease !important;
      display: flex !important;
      justify-content: space-between !important;
      align-items: center !important;
    }
    
    .city-item:last-child {
      border-bottom: none !important;
    }
    
    .city-item:hover {
      background: linear-gradient(90deg, #f8faf9 0%, #f0f7e8 100%) !important;
      transform: translateX(4px) !important;
    }
    
    .city-name {
      font-weight: 600 !important;
      color: #1f2937 !important;
      font-size: 15px !important;
    }
    
    .city-cost {
      font-weight: 700 !important;
      color: #4b6b2f !important;
      background: rgba(75, 107, 47, 0.1) !important;
      padding: 4px 8px !important;
      border-radius: 6px !important;
      font-size: 13px !important;
    }
    
    .city-item-no-results {
      padding: 20px !important;
      text-align: center !important;
      color: #6b7280 !important;
      font-size: 14px !important;
      font-style: italic !important;
    }
    
    #shipping-city-input {
      border: 2px solid #e5e7eb !important;
      border-radius: 10px !important;
      transition: all 0.3s ease !important;
      font-size: 15px !important;
      padding: 12px 16px !important;
    }
    
    #shipping-city-input:focus {
      border-color: #4b6b2f !important;
      box-shadow: 0 0 0 3px rgba(75, 107, 47, 0.1) !important;
      outline: none !important;
    }
    
    #shipping-city-input::placeholder {
      color: #9ca3af !important;
    }
    
    /* Scrollbar styling */
    #shipping-city-dropdown::-webkit-scrollbar {
      width: 6px !important;
    }
    
    #shipping-city-dropdown::-webkit-scrollbar-track {
      background: #f1f1f1 !important;
      border-radius: 3px !important;
    }
    
    #shipping-city-dropdown::-webkit-scrollbar-thumb {
      background: #4b6b2f !important;
      border-radius: 3px !important;
    }
    
    #shipping-city-dropdown::-webkit-scrollbar-thumb:hover {
      background: #3a5524 !important;
    }

    /* Thumbnail Gallery Styles */
    .thumbnail-container {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      overflow-x: auto;
      padding-bottom: 5px;
      scrollbar-width: thin;
      scrollbar-color: #4b6b2f #f1f1f1;
    }
    .thumbnail-container::-webkit-scrollbar {
      height: 6px;
    }
    .thumbnail-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 3px;
    }
    .thumbnail-container::-webkit-scrollbar-thumb {
      background: #4b6b2f;
      border-radius: 3px;
    }
    .thumb-img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.3s ease;
      flex-shrink: 0;
    }
    .thumb-img:hover {
      border-color: #4b6b2f;
      transform: scale(1.05);
    }
    .thumb-img.active {
      border-color: #4b6b2f;
      box-shadow: 0 0 0 2px rgba(75, 107, 47, 0.2);
    }
  </style>
</head>

<body>

  <!-- Navbar -->
  <?php
  // Database connection is already included at the top
  // No need to start session again as it's already started at the top of the file
  
  // Get user data if logged in
  $user_id = $_SESSION['user_id'] ?? null;
  $user = null;
  $user_name = '';
  
  if ($user_id && isset($conn)) {
      $user_id = (int)$user_id;
      $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
      if ($user_query) {
          $user = $user_query->fetch_assoc();
          $user_name = $user['fullname'] ?? '';
      }
  }
  
  // Get current page for active link
  $current_page = basename($_SERVER['PHP_SELF']);
  $current_page = str_replace(['.php', '.html'], '', $current_page);
  ?>
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
        <a href="product.html" class="navbar-link <?php echo (in_array($current_page, ['product', 'product_details'])) ? 'active' : ''; ?>">
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
    
    // Add smooth scroll to all anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href === '#') return;
        
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          // Close mobile menu if open
          const mobileMenu = document.querySelector('.mobile-menu');
          if (mobileMenu && mobileMenu.classList.contains('active')) {
            mobileMenu.classList.remove('active');
            document.body.style.overflow = '';
          }
          
          // Smooth scroll to target
          window.scrollTo({
            top: target.offsetTop - 80, // Adjust for fixed navbar
            behavior: 'smooth'
          });
          
          // Update URL without page jump
          if (history.pushState) {
            history.pushState(null, null, href);
          } else {
            location.hash = href;
          }
        }
      });
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
      const hash = window.location.hash;
      if (hash) {
        const target = document.querySelector(hash);
        if (target) {
          window.scrollTo({
            top: target.offsetTop - 80,
            behavior: 'smooth'
          });
        }
      }
    });
  </script>

  <!-- Product Details -->
  <section class="product-details">
    <div class="container">
      <!-- يسار: التفاصيل -->
      <div class="details">
        <h2><?= $product['name'] ?></h2>
        <p class="price">د.ل <strong id="price-text"><?= number_format($product['price'], 2) ?></strong></p>
        <p class="commission">العمولة: د.ل <strong id="commission-text"><?= number_format($product['commission'], 2) ?></strong></p>
        
        <!-- عرض المخزون المتاح -->
        <div id="stock-info" class="mt-4 p-4 bg-gray-50 rounded-lg">
            <p class="text-green-600 font-medium text-lg">
                <i class='bx bx-package'></i> 
                المتبقي في المخزون: <strong><?= $product['stock'] ?></strong> قطعة
            </p>
            <?php if ($has_inventory): ?>
            <p class="text-sm text-gray-600 mt-2">
                <i class='bx bx-info-circle'></i>
                يتوفر بألوان ومقاسات مختلفة - اختر اللون والمقاس لمعرفة الكمية المتاحة
            </p>
            <?php endif; ?>
        </div>

        <div class="options" id="options-container" style="<?= !$has_inventory ? 'display: none;' : '' ?>">
          <label>اختر اللون:</label>
          <div class="colors" id="colors-container">
            <?php foreach($available_colors as $color): ?>
            <button type="button" class="color-btn" data-color="<?= htmlspecialchars($color) ?>">
              <?= $color ?>
            </button>
            <?php endforeach; ?>
          </div>

          <label>اختر المقاس:</label>
          <div class="sizes" id="sizes-container">
            <?php foreach($available_sizes as $size): ?>
            <button type="button" class="size-btn" data-size="<?= htmlspecialchars($size) ?>">
              <?= $size ?>
            </button>
            <?php endforeach; ?>
          </div>

          <!-- عرض الكمية المتاحة -->
          <div id="quantity-info" class="mt-4 hidden">
            <p class="text-green-600 font-medium" id="available-quantity"></p>
          </div>
        </div>

        <div class="buttons">
          <button class="add-cart" id="add-to-cart-btn">
            <i class='bx bx-cart'></i> إضافة إلى السلة
          </button>
          <button class="buy-now" id="buy-now-btn">
            <i class='bx bx-bolt'></i> اطلب الآن
          </button>
        </div>

        <?php if ($has_inventory): ?>
        <button class="stock-btn" id="stock-btn">
          <i class='bx bx-home-smile'></i> عرض تفاصيل المخزون
        </button>
        <?php endif; ?>
      </div>

      <!-- يمين: الصور -->
      <div class="images">
        <div class="image-box">
          <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="main-img" id="mainImage">
        </div>
        
        <?php if (count($all_images) > 1): ?>
        <div class="thumbnail-container" id="thumbnailGallery">
          <?php foreach ($all_images as $index => $img): ?>
          <img src="<?= htmlspecialchars($img['image_path']) ?>" 
               alt="صورة المنتج <?= $index + 1 ?>" 
               class="thumb-img <?= ($img['image_path'] == $product['image']) ? 'active' : '' ?>" 
               data-path="<?= htmlspecialchars($img['image_path']) ?>"
               data-color="<?= htmlspecialchars($img['color_name'] ?? '') ?>"
               onclick="changeMainImage(this)">
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- وصف المنتج -->
    <div class="description">
      <div class="desc-box">
        <h3>وصف المنتج</h3>
        <p><?= nl2br($product['description']) ?></p>
      </div>
    </div>
  </section>

  <!-- نافذة عرض المخزون -->
  <div class="stock-modal" id="stockModal">
    <div class="stock-content">
      <span class="close-btn" id="closeStock">&times;</span>
      <h2>تفاصيل المخزون لـ <?= $product['name'] ?></h2>
      <p class="total">إجمالي القطع في المخزون: <strong><?= $product['stock'] ?> قطعة</strong></p>

      <?php if ($has_inventory): ?>
      <table class="stock-table">
        <thead>
          <tr>
            <th>اللون</th>
            <th>المقاس</th>
            <th>الكمية المتاحة</th>
          </tr>
        </thead>
        <tbody id="stock-table-body">
          <?php foreach($inventory as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['color']) ?></td>
            <td><?= htmlspecialchars($item['size']) ?></td>
            <td><?= $item['quantity'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p>لا يوجد مخزون تفصيلي لهذا المنتج</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // بيانات المنتج والمخزون من PHP
    const product = <?= json_encode($product, JSON_UNESCAPED_UNICODE) ?>;
    const inventory = <?= json_encode($inventory_data, JSON_UNESCAPED_UNICODE) ?>;
    const allImages = <?= json_encode($all_images, JSON_UNESCAPED_UNICODE) ?>;
    const hasInventory = <?= $has_inventory ? 'true' : 'false' ?>;
    
    let selectedColor = null;
    let selectedSize = null;
    let availableQuantity = 0;

    // عناصر DOM
    const colorBtns = document.querySelectorAll('.color-btn');
    const sizeBtns = document.querySelectorAll('.size-btn');
    const addCartBtn = document.getElementById('add-to-cart-btn');
    const buyNowBtn = document.getElementById('buy-now-btn');
    const stockBtn = document.getElementById('stock-btn');
    const quantityInfo = document.getElementById('quantity-info');
    const availableQuantityEl = document.getElementById('available-quantity');
    
    // إذا لم يكن هناك inventory، تفعيل الأزرار مباشرة
    if (!hasInventory) {
      addCartBtn.disabled = false;
      buyNowBtn.disabled = false;
      if (stockBtn) stockBtn.disabled = false;
      
      addCartBtn.classList.add('active');
      buyNowBtn.classList.add('active');
      if (stockBtn) stockBtn.classList.add('active');
    } else {
      // إذا كان هناك inventory، لا تفعيل الأزرار حتى يتم الاختيار
      addCartBtn.disabled = true;
      buyNowBtn.disabled = true;
      if (stockBtn) stockBtn.disabled = false; // زر المخزون يظل متاحاً
      
      addCartBtn.classList.remove('active');
      buyNowBtn.classList.remove('active');
      if (stockBtn) stockBtn.classList.add('active');
    }

    // اختيار اللون
    colorBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        // إزالة التحديد من جميع الأزرار
        colorBtns.forEach(b => b.classList.remove('selected'));
        // تحديد الزر الحالي
        btn.classList.add('selected');
        selectedColor = btn.dataset.color;
        
        // تبديل الصورة بناءً على اللون المختار
        if (selectedColor) {
            switchImageByColor(selectedColor);
        }
        
        // تحديث المقاسات المتاحة لهذا اللون
        updateAvailableSizes();
        checkSelection();
      });
    });

    // تبديل الصورة الرئيسية
    function changeMainImage(thumb) {
        const mainImage = document.getElementById('mainImage');
        const thumbs = document.querySelectorAll('.thumb-img');
        
        mainImage.src = thumb.dataset.path;
        
        thumbs.forEach(t => t.classList.remove('active'));
        thumb.classList.add('active');
    }

    // تبديل الصورة بناءً على اللون
    function switchImageByColor(colorName) {
        if (!colorName) return;
        
        const thumbs = document.querySelectorAll('.thumb-img');
        let matchedThumb = null;
        
        // العثور على أول صورة مرتبطة بهذا اللون
        for (const thumb of thumbs) {
            if (thumb.dataset.color === colorName) {
                matchedThumb = thumb;
                break;
            }
        }
        
        if (matchedThumb) {
            changeMainImage(matchedThumb);
            // Scroll matched thumb into view if container exists
            matchedThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    // اختيار المقاس
    sizeBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        // إزالة التحديد من جميع الأزرار
        sizeBtns.forEach(b => b.classList.remove('selected'));
        // تحديد الزر الحالي
        btn.classList.add('selected');
        selectedSize = btn.dataset.size;
        
        // تحديث الكمية المتاحة
        updateAvailableQuantity();
        checkSelection();
      });
    });

    // تحديث المقاسات المتاحة بناءً على اللون المختار
    function updateAvailableSizes() {
      if (!selectedColor) return;
      
      // إخفاء جميع المقاسات أولاً
      sizeBtns.forEach(btn => {
        btn.style.display = 'none';
      });
      
      // إظهار المقاسات المتاحة للون المختار
      const availableSizes = inventory
        .filter(item => item.color === selectedColor && item.quantity > 0)
        .map(item => item.size);
      
      sizeBtns.forEach(btn => {
        if (availableSizes.includes(btn.dataset.size)) {
          btn.style.display = 'inline-block';
        }
      });
      
      // إعادة تعيين المقاس المختار
      selectedSize = null;
      sizeBtns.forEach(b => b.classList.remove('selected'));
      updateAvailableQuantity();
    }

    // تحديث الكمية المتاحة بناءً على اللون والمقاس
    function updateAvailableQuantity() {
      if (!selectedColor || !selectedSize) {
        quantityInfo.classList.add('hidden');
        availableQuantity = 0;
        return;
      }
      
      const item = inventory.find(i => i.color === selectedColor && i.size === selectedSize);
      if (item) {
        availableQuantity = item.quantity;
        availableQuantityEl.textContent = `الكمية المتاحة: ${availableQuantity} قطعة`;
        quantityInfo.classList.remove('hidden');
      } else {
        availableQuantity = 0;
        quantityInfo.classList.add('hidden');
      }
    }

    // التحقق من اكتمال الاختيار
    function checkSelection() {
      // تفعيل الأزرار دائماً بغض النظر عن اختيار اللون والمقاس
      addCartBtn.disabled = false;
      buyNowBtn.disabled = false;
      if (stockBtn) stockBtn.disabled = false;
      
      addCartBtn.classList.add('active');
      buyNowBtn.classList.add('active');
      if (stockBtn) stockBtn.classList.add('active');
    }

    // إضافة إلى السلة
    addCartBtn.addEventListener('click', () => {
      // إزالة شرط اختيار اللون والمقاس - السماح بالإضافة مباشرة
      const cart = JSON.parse(localStorage.getItem("cart")) || [];
      const newItem = {
        id: product.id,
        name: product.name,
        color: hasInventory ? (selectedColor || null) : null,
        size: hasInventory ? (selectedSize || null) : null,
        price: product.price,
        commission: product.commission,
        special_commission: product.special_commission || 0,
        merchant_commission: product.merchant_commission || 0,
        image: product.image || 'imgs/default-product.jpg',
        quantity: 1,
        maxStock: hasInventory ? (availableQuantity || 0) : (product.stock || 0)
      };
      
      // التحقق إذا كان المنتدلوجود مسبقاً
      let existingItemIndex = -1;
      if (hasInventory && selectedColor && selectedSize) {
        existingItemIndex = cart.findIndex(item => 
          item.id === newItem.id && 
          item.color === newItem.color && 
          item.size === newItem.size
        );
      } else {
        existingItemIndex = cart.findIndex(item => 
          item.id === newItem.id && 
          !item.color && 
          !item.size
        );
      }
      
      if (existingItemIndex > -1) {
        cart[existingItemIndex].quantity += 1;
      } else {
        cart.push(newItem);
      }
      
      localStorage.setItem("cart", JSON.stringify(cart));
      updateCartCount();
      
      if (hasInventory && selectedColor && selectedSize) {
        showSuccess(`تمت إضافة (${selectedColor} - ${selectedSize}) إلى السلة ✅`);
      } else {
        showSuccess(`تمت إضافة ${product.name} إلى السلة ✅`);
      }
    });

    // طلب الآن
    buyNowBtn.addEventListener('click', () => {
      addCartBtn.click();
      setTimeout(() => {
        window.location.href = "cart.html";
      }, 1500);
    });

    // تحديث عداد السلة
    function updateCartCount() {
      const cart = JSON.parse(localStorage.getItem("cart")) || [];
      document.querySelector('.cart-count').textContent = cart.length;
    }

    // عرض المخزون
    if (stockBtn) {
      stockBtn.addEventListener('click', () => {
        document.getElementById('stockModal').style.display = 'flex';
      });
    }

    // غلق نافذة المخزون
    document.getElementById('closeStock').addEventListener('click', () => {
      document.getElementById('stockModal').style.display = 'none';
    });

    // تهيئة الصفحة
    document.addEventListener('DOMContentLoaded', function() {
      updateCartCount();
      checkSelection(); // تفعيل الأزرار عند تحميل الصفحة
    });
  </script>

</body>
</html>