<?php
// ملف: product_statistics1.php
// إحصائيات المنتجات للتاجر
session_start();
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

// التحقق من تسجيل الدخول ومن صلاحية التاجر
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'تاجر') {
    header("Location: login.php");
    exit();
}

$merchant_id = $_SESSION['user_id'];

// جلب بيانات المستخدم للـ navbar
$user = null;
$user_name = '';
if ($merchant_id) {
    $user_query = $conn->query("SELECT * FROM users WHERE id = $merchant_id");
    if ($user_query) {
        $user = $user_query->fetch_assoc();
        $user_name = $user['fullname'] ?? '';
    }
}

// الحصول على الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);
$current_page = str_replace(['.php', '.html'], '', $current_page);

// معالجة التصفية
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// بناء استعلام المنتجات
$query = "SELECT p.*, 
                 (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as product_image
          FROM products p 
          WHERE p.user_id = $merchant_id";

if (!empty($filter_status)) {
    $status_value = ($filter_status == 'مفعل') ? 1 : 0;
    $query .= " AND p.status = $status_value";
}

$query .= " ORDER BY p.created_at DESC";

$products_result = $conn->query($query);
// جلب الفئات إذا كان الجدول موجوداً
$categories_result = null;
$categories_table_exists = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'categories'")->fetch_assoc()['count'] > 0;
if ($categories_table_exists) {
    $categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
}

// جلب الإحصائيات
$statistics = [];
if ($products_result && $products_result->num_rows > 0) {
    while ($product = $products_result->fetch_assoc()) {
        $product_id = $product['id'];
        
        // عدد الطلبات
        $orders_query = $conn->query("SELECT COUNT(*) as total_orders, 
                                            SUM(CASE WHEN o.status IN ('ملغي', 'مرفوض') THEN 1 ELSE 0 END) as cancelled_orders,
                                            SUM(CASE WHEN o.status = 'مرتجع' THEN 1 ELSE 0 END) as returned_orders,
                                            SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل') THEN 1 ELSE 0 END) as completed_orders
                                     FROM orders o
                                     JOIN order_items oi ON o.id = oi.order_id
                                     WHERE oi.product_id = $product_id");
        $orders_stats = $orders_query->fetch_assoc();
        
        // عدد القطع المباعة والملغاة والمرتجعة
        $items_query = $conn->query("SELECT SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل') THEN oi.quantity ELSE 0 END) as sold_quantity,
                                           SUM(CASE WHEN o.status IN ('ملغي', 'مرفوض') THEN oi.quantity ELSE 0 END) as cancelled_quantity,
                                           SUM(CASE WHEN o.status = 'مرتجع' THEN oi.quantity ELSE 0 END) as returned_quantity
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.id
                                    WHERE oi.product_id = $product_id");
        $items_stats = $items_query->fetch_assoc();
        
        // إجمالي الربح (ناقص العمولة)
        $profit_query = $conn->query("SELECT SUM(oi.quantity * (p.price - p.commission)) as total_revenue
                                     FROM order_items oi
                                     JOIN orders o ON oi.order_id = o.id
                                     JOIN products p ON oi.product_id = p.id
                                     WHERE oi.product_id = $product_id AND o.status IN ('تم التوصيل', 'محصل')");
        $profit_stats = $profit_query->fetch_assoc();
        
        $statistics[$product_id] = [
            'product' => $product,
            'orders' => $orders_stats,
            'items' => $items_stats,
            'profit' => $profit_stats
        ];
    }
}

// معالجة التصدير
if (isset($_GET['export']) && in_array($_GET['export'], ['excel', 'pdf'])) {
    $export_type = $_GET['export'];
    
    if ($export_type == 'excel') {
        // تصدير Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="product_statistics_' . date('Y-m-d') . '.xls"');
        
        echo "إحصائيات المنتجات - " . date('Y-m-d') . "\n\n";
        echo "اسم المنتج\tالصورة\tالحالة\tالمخزون\tعدد الطلبات\tالطلبات المكتملة\tالطلبات الملغاة\tالطلبات المرتجعة\tالكمية المباعة\tالكمية الملغاة\tالكمية المرتجعة\tصافي الإيرادات\n";
        
        foreach ($statistics as $stat) {
            $product = $stat['product'];
            $orders = $stat['orders'];
            $items = $stat['items'];
            $profit = $stat['profit'];
            
            $status = $product['status'] ? 'مفعل' : 'غير مفعل';
            $image = $product['product_image'] ? basename($product['product_image']) : 'لا توجد صورة';
            
            echo $product['name'] . "\t" . $image . "\t" . $status . "\t" .
                 $product['stock'] . "\t" . $orders['total_orders'] . "\t" . $orders['completed_orders'] . "\t" .
                 $orders['cancelled_orders'] . "\t" . $orders['returned_orders'] . "\t" . 
                 ($items['sold_quantity'] ?: 0) . "\t" . ($items['cancelled_quantity'] ?: 0) . "\t" . 
                 ($items['returned_quantity'] ?: 0) . "\t" . ($profit['total_revenue'] ?: 0) . "\n";
        }
        exit();
    } elseif ($export_type == 'pdf') {
        // تصدير PDF
        require_once('tcpdf/tcpdf.php');
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('System');
        $pdf->SetAuthor('Merchant Panel');
        $pdf->SetTitle('إحصائيات المنتجات');
        $pdf->SetSubject('تقرير إحصائيات المنتجات');
        
        $pdf->setHeaderFont(Array('aefurat', '', 12));
        $pdf->setFooterFont(Array('aefurat', '', 8));
        $pdf->SetFont('aefurat', '', 10);
        
        $pdf->AddPage();
        $pdf->Cell(0, 10, 'إحصائيات المنتجات - ' . date('Y-m-d'), 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        
        // إضافة الجدول
        $pdf->Ln(10);
        $pdf->SetFont('aefurat', '', 8);
        
        $html = '<table border="1" cellpadding="3">
            <tr style="background-color:#f0f0f0;">
                <th>اسم المنتج</th>
                <th>الحالة</th>
                <th>المخزون</th>
                <th>عدد الطلبات</th>
                <th>المكتملة</th>
                <th>الملغاة</th>
                <th>المرتجعة</th>
                <th>المباعة</th>
                <th>صافي الإيرادات</th>
            </tr>';
        
        foreach ($statistics as $stat) {
            $product = $stat['product'];
            $orders = $stat['orders'];
            $items = $stat['items'];
            $profit = $stat['profit'];
            
            $status = $product['status'] ? 'مفعل' : 'غير مفعل';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($product['name']) . '</td>
                <td>' . $status . '</td>
                <td>' . $product['stock'] . '</td>
                <td>' . $orders['total_orders'] . '</td>
                <td>' . $orders['completed_orders'] . '</td>
                <td>' . $orders['cancelled_orders'] . '</td>
                <td>' . $orders['returned_orders'] . '</td>
                <td>' . ($items['sold_quantity'] ?: 0) . '</td>
                <td>' . number_format($profit['total_revenue'] ?: 0, 2) . '</td>
            </tr>';
        }
        
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $pdf->Output('product_statistics_' . date('Y-m-d') . '.pdf', 'D');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائيات المنتجات - لوحة التاجر</title>
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
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-gray-50">
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
            
            <?php if ($merchant_id && $user): ?>
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
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">إحصائيات المنتجات</h1>
            <p class="text-gray-600">عرض إحصائيات مفصلة لجميع منتجاتك</p>
        </div>

            <!-- فلاتر البحث -->
            <div class="stat-card p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الحالة</label>
                        <select name="status" class="w-full p-2 border rounded-lg">
                            <option value="">جميع الحالات</option>
                            <option value="مفعل" <?= $filter_status == 'مفعل' ? 'selected' : '' ?>>مفعل</option>
                            <option value="غير مفعل" <?= $filter_status == 'غير مفعل' ? 'selected' : '' ?>>غير مفعل</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            <i class='bx bx-search ml-2'></i> بحث
                        </button>
                        <a href="product_statistics1.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                            <i class='bx bx-refresh ml-2'></i> إعادة تعيين
                        </a>
                    </div>
                </form>
            </div>

            <!-- أزرار التصدير -->
            <div class="mb-6 flex gap-2">
                <a href="?export=excel" class="export-btn bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                    <i class='bx bx-download ml-2'></i> تصدير Excel
                </a>
                <a href="?export=pdf" class="export-btn bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                    <i class='bx bx-download ml-2'></i> تصدير PDF
                </a>
            </div>

            <!-- الإحصائيات -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                <?php foreach ($statistics as $product_id => $stat): ?>
                    <?php 
                    $product = $stat['product'];
                    $orders = $stat['orders'];
                    $items = $stat['items'];
                    $profit = $stat['profit'];
                    
                    $status_class = $product['status'] ? 'status-active' : 'status-inactive';
                    $status_text = $product['status'] ? 'مفعل' : 'غير مفعل';
                    ?>
                    
                    <div class="product-card stat-card p-6">
                        <div class="flex items-start mb-4">
                            <img src="<?= $product['product_image'] ?: 'imgs/default-product.jpg' ?>" alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="w-16 h-16 rounded-lg object-cover ml-4" onerror="this.src='imgs/default-product.jpg'">
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($product['name']) ?></h3>
                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                            </div>
                        </div>
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">المخزون:</span>
                                <span class="font-medium"><?= $product['stock'] ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">عدد الطلبات:</span>
                                <span class="font-medium"><?= $orders['total_orders'] ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">الكمية المباعة:</span>
                                <span class="font-medium text-green-600"><?= $items['sold_quantity'] ?: 0 ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">الكمية الملغاة:</span>
                                <span class="font-medium text-red-600"><?= $items['cancelled_quantity'] ?: 0 ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">الكمية المرتجعة:</span>
                                <span class="font-medium text-yellow-600"><?= $items['returned_quantity'] ?: 0 ?></span>
                            </div>
                            <div class="flex justify-between pt-2 border-t">
                                <span class="text-gray-800 font-medium">صافي الإيرادات:</span>
                                <span class="font-bold text-green-600"><?= number_format($profit['total_revenue'] ?: 0, 2) ?> د.ل</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($statistics)): ?>
                <div class="text-center py-12">
                    <i class='bx bx-bar-chart text-6xl text-gray-300 mb-4'></i>
                    <p class="text-gray-500">لا توجد منتجات لعرض إحصائياتها</p>
                </div>
            <?php endif; ?>
    </div>
</body>
</html>
