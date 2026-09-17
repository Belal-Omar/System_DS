<?php
session_start();
include(__DIR__ . '/core/config.php");

// Check if user is logged in and is a marketer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'مسوق') {
    header("Location: login.html");
    exit;
}

// Get user ID
$user_id = (int)$_SESSION['user_id'];

// جلب بيانات المستخدم للـ navbar
$user = null;
$user_name = '';
if ($user_id) {
    $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user_query) {
        $user = $user_query->fetch_assoc();
        $user_name = $user['fullname'] ?? '';
    }
}

// الحصول على الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);
$current_page = str_replace(['.php', '.html'], '', $current_page);

// Get only products related to current user's orders
$query = "
    SELECT 
        p.id,
        p.name,
        p.stock,
        p.status,
        pi.image_path,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل', 'completed') THEN oi.quantity ELSE 0 END), 0) as total_sold,
        COUNT(DISTINCT CASE WHEN o.status IN ('ملغي', 'مرفوض', 'cancelled', 'rejected') THEN o.id END) as cancelled_orders,
        COUNT(DISTINCT CASE WHEN o.status IN ('مرتجع', 'returned') THEN o.id END) as returned_items,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل', 'completed') THEN (oi.quantity * (oi.price - p.commission)) ELSE 0 END), 0) as total_profit
    FROM 
        products p
    LEFT JOIN 
        product_images pi ON p.id = pi.product_id AND pi.is_main = 1
    INNER JOIN
        order_items oi ON p.id = oi.product_id
    INNER JOIN
        orders o ON oi.order_id = o.id
    WHERE
        o.user_id = $user_id
    GROUP BY 
        p.id, p.name, p.stock, p.status, pi.image_path
    ORDER BY 
        p.name ASC
";

$products = $conn->query($query);

// Export to Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="product_statistics_'.date('Y-m-d').'.xls"');
    
    $output = "<html><head><meta charset='utf-8'></head><body><table border='1'>
        <tr>
            <th>اسم المنتج</th>
            <th>حالة المنتج</th>
            <th>المخزون الحالي</th>
            <th>الكمية المباعة</th>
            <th>الطلبات الملغاة</th>
            <th>المرتجعات</th>
            <th>إجمالي الربح</th>
        </tr>";
    
    while($row = $products->fetch_assoc()) {
        $output .= "
        <tr>
            <td>" . htmlspecialchars($row['name']) . "</td>
            <td>" . ($row['status'] == 'active' ? 'مفعل' : 'غير مفعل') . "</td>
            <td>" . $row['stock'] . "</td>
            <td>" . $row['total_sold'] . "</td>
            <td>" . $row['cancelled_orders'] . "</td>
            <td>" . $row['returned_items'] . "</td>
            <td>" . number_format($row['total_profit'], 2) . " د.ل</td>
        </tr>";
    }
    
    $output .= "</table></body></html>";
    echo "\xEF\xBB\xBF";
    echo $output;
    exit;
}

// Export to PDF (requires TCPDF library)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once(__DIR__ . '/tcpdf/tcpdf.php');
    
    // Create new PDF document with RTL support
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('System');
    $pdf->SetAuthor('System');
    $pdf->SetTitle('إحصائيات المنتجات');
    
    // Set RTL language
    $pdf->setRTL(true);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font for the title
    $pdf->SetFont('dejavusans', 'B', 16);
    
    // Add title
    $pdf->Cell(0, 10, 'تقرير إحصائيات المنتجات', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Set font for the table
    $pdf->SetFont('dejavusans', 'B', 10);
    $header = array('اسم المنتج', 'الحالة', 'المخزون', 'المباع', 'ملغي', 'مرتجع', 'الربح');
    $w = array(50, 20, 20, 15, 15, 15, 25);
    
    // Header
    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 10, $header[$i], 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Data
    $pdf->SetFont('dejavusans', '', 9);
    $products_data = $conn->query($query);
    while($row = $products_data->fetch_assoc()) {
        $pdf->Cell($w[0], 10, $row['name'], 'LR', 0, 'R');
        $pdf->Cell($w[1], 10, ($row['status'] == 'active' ? 'مفعل' : 'غير مفعل'), 'LR', 0, 'C');
        $pdf->Cell($w[2], 10, $row['stock'], 'LR', 0, 'C');
        $pdf->Cell($w[3], 10, $row['total_sold'], 'LR', 0, 'C');
        $pdf->Cell($w[4], 10, $row['cancelled_orders'], 'LR', 0, 'C');
        $pdf->Cell($w[5], 10, $row['returned_items'], 'LR', 0, 'C');
        $pdf->Cell($w[6], 10, number_format($row['total_profit'], 2) . ' د.ل', 'LR', 0, 'L');
        $pdf->Ln();
    }
    
    $pdf->Output('product_statistics_'.date('Y-m-d').'.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائيات المنتجات - لوحة المسوق</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 100px;
            font-family: 'Tajawal', sans-serif;
        }
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .export-btn {
            transition: all 0.3s ease;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
<body class="bg-gray-50">
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

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">إحصائيات المنتجات</h1>
            <div class="flex space-x-4 space-x-reverse">
                <a href="?export=excel" class="bg-green-600 text-white px-4 py-2 rounded-lg export-btn flex items-center">
                    <i class='bx bx-export mr-2'></i>
                    تصدير Excel
                </a>
                <a href="?export=pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg export-btn flex items-center">
                    <i class='bx bxs-file-pdf mr-2'></i>
                    تصدير PDF
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المنتج</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">المخزون الحالي</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">الكمية المباعة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">الطلبات الملغاة</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">المرتجعات</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">إجمالي الربح</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($product = $products->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if($product['image_path']): ?>
                                        <img class="w-10 h-10 rounded-full object-cover mr-3" src="<?= $product['image_path'] ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 mr-3">
                                            <i class='bx bx-image'></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs rounded-full <?= $product['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                    <?= $product['status'] == 'active' ? 'مفعل' : 'غير مفعل' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                <?= $product['stock'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                <?= $product['total_sold'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                <?= $product['cancelled_orders'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                <?= $product['returned_items'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium text-green-600">
                                <?= number_format($product['total_profit'], 2) ?> د.ل
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
</body>
</html>
