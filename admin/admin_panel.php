<?php
// ملف: admin_panel.php
include(__DIR__ . '/../core/config.php");
session_start();

// 🔹 CSP Headers - لمنع تدخل الـ Extensions
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src 'self' https://unpkg.com data:; img-src 'self' data: https: blob:; connect-src 'self' https: blob:; worker-src 'self' blob:;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
/** @var mysqli $conn */
include(__DIR__ . '/../core/helpers.php"); // إضافة ملف المساعدات
include("setup_inventory_tables.php");

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// TEMP MIGRATION
$conn->query("CREATE TABLE IF NOT EXISTS shipping_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE admins ADD COLUMN shipping_company_id INT DEFAULT NULL AFTER role");
$conn->query("ALTER TABLE support_orders ADD COLUMN shipping_company_id INT DEFAULT NULL AFTER sheet_id");
$conn->query("UPDATE admins SET role = 'shipping_company' WHERE (role = '' OR role = 'admin') AND shipping_company_id IS NOT NULL AND shipping_company_id > 0");

$conn->query("CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('admin', 'user') NOT NULL,
    recipient_id INT NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_type, recipient_id, is_read)
)");

// Fix session if it's outdated
if (isset($_SESSION['admin_role']) && ($_SESSION['admin_role'] === 'admin' || $_SESSION['admin_role'] === '') && !empty($_SESSION['shipping_company_id'])) {
    $_SESSION['admin_role'] = 'shipping_company';
}
// END TEMP MIGRATION

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

ensure_csrf_token();
if (isset($conn) && is_object($conn)) {
    run_admin_migrations($conn);
    load_session_admin_allowed_pages($conn);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

$sessionRole = $_SESSION['admin_role'] ?? '';
$sessionAllowedRaw = $_SESSION['admin_allowed_pages'] ?? null;

// حفظ حسابات الأرباح والليدات — قبل أي HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_daily_lead']) || isset($_POST['save_product_monthly']))) {
    if ($sessionRole === 'super_admin' || admin_can_access_page('accounts', $sessionRole, $sessionAllowedRaw)) {
        $earlyAccounts = __DIR__ . DIRECTORY_SEPARATOR . 'admin_accounts_post_early.php';
        if (is_readable($earlyAccounts)) {
            require $earlyAccounts;
        }
    }
}

// تسجيل تحصيل المديونيات — قبل أي HTML (عشان الـ redirect يشتغل)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_payment']) || isset($_POST['delete_record']))) {
    if ($sessionRole === 'super_admin' || admin_can_access_page('debts', $sessionRole, $sessionAllowedRaw)) {
        $earlyDebts = __DIR__ . DIRECTORY_SEPARATOR . 'admin_debts_post_early.php';
        if (is_readable($earlyDebts)) {
            require $earlyDebts;
        }
    }
}

// تعيين / إلغاء «تم الاستلام» من متابعة المدير
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['mark_order_received']) || isset($_POST['unmark_order_received']))) {
    if ($sessionRole === 'super_admin' || (function_exists('can_mark_order_as_received') && can_mark_order_as_received($sessionRole, $sessionAllowedRaw))) {
        $earlyReceived = __DIR__ . DIRECTORY_SEPARATOR . 'admin_support_received_post_early.php';
        if (is_readable($earlyReceived)) {
            require $earlyReceived;
        }
    } else {
        $_SESSION['support_detail_flash_error'] = 'ليس لديك صلاحية تعديل حالة «تم الاستلام».';
        $back = (int) ($_POST['support_member_id'] ?? 0);
        header('Location: admin_panel.php?page=support_detail&id=' . max(0, $back));
        exit;
    }
}

// تعيين شركة شحن للأوردر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shipping_company'])) {
    if ($sessionRole === 'super_admin' || $sessionRole === 'admin') {
        require_csrf();
        $order_id = (int)($_POST['order_id'] ?? 0);
        $shipping_company_id = (int)($_POST['shipping_company_id'] ?? 0);
        $support_member_id = (int)($_POST['support_member_id'] ?? 0);
        if ($order_id > 0) {
            $sc_id_val = $shipping_company_id > 0 ? $shipping_company_id : "NULL";
            $conn->query("UPDATE support_orders SET shipping_company_id = $sc_id_val WHERE id = $order_id");
            $_SESSION['support_detail_flash_success'] = 'تم تحديث شركة الشحن بنجاح!';
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            unset($_SESSION['support_detail_flash_success']);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        $r = $_SERVER['HTTP_REFERER'] ?? "?page=support_detail&id=$support_member_id";
        header("Location: $r");
        exit;
    }
}

// حفظ أوردرات الدعم: لازم يشتغل قبل أي HTML عشان header(Location) ينجح
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sheet_file'])) {
    if ($sessionRole === 'super_admin' || admin_can_access_page('support', $sessionRole, $sessionAllowedRaw)) {
        $earlySheet = __DIR__ . DIRECTORY_SEPARATOR . 'admin_support_sheet_upload_early.php';
        if (is_readable($earlySheet)) {
            require $earlySheet;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_orders_batch_v2']) || isset($_POST['save_orders_edit']) || isset($_POST['add_order']) || isset($_POST['request_unlock']) || isset($_POST['unlock_sheet']) || isset($_POST['reject_unlock']) || isset($_POST['force_lock_sheet'])) {
        $earlySave = __DIR__ . DIRECTORY_SEPARATOR . 'admin_support_post_early.php';
        if (is_readable($earlySave)) {
            require $earlySave;
        } else {
            $_SESSION['support_flash_error'] = 'ملف admin_support_post_early.php غير موجود على السيرفر؛ ارفعه مع التحديث.';
            $vs = isset($_POST['view_sheet_id']) ? (int) $_POST['view_sheet_id'] : 0;
            header('Location: admin_panel.php?page=support' . ($vs > 0 ? '&view_sheet=' . $vs : ''));
            exit;
        }
    }
}

// الحصول على الإحصائيات
$db_connection_failed = false;
if (isset($conn) && is_object($conn) && method_exists($conn, 'query')) {
    $stats = get_admin_stats($conn);
} else {
    $stats = ['total_products' => 0, 'total_orders' => 0, 'total_users' => 0, 'pending_orders' => 0, 'total_revenue' => 0];
    $db_connection_failed = true;
}

// تحديد الصفحة والصلاحيات قبل أي HTML
$effectivePages = get_admin_effective_pages($sessionRole, $sessionAllowedRaw);

if ($sessionRole === 'support') {
    $default_page = 'support';
} elseif (!empty($effectivePages)) {
    $default_page = in_array('dashboard', $effectivePages, true) ? 'dashboard' : $effectivePages[0];
} else {
    $default_page = 'dashboard';
}

$page = $_GET['page'] ?? $default_page;
$support_allowed = ['support', 'support_orders', 'your_rank', 'complaints', 'bonus_support'];
if ($sessionRole === 'support' && !in_array($page, $support_allowed, true)) {
    header('Location: admin_panel.php?page=support');
    exit;
}

if ($sessionRole !== 'super_admin' && !admin_can_access_page($page, $sessionRole, $sessionAllowedRaw)) {
    if (admin_can_access_page($default_page, $sessionRole, $sessionAllowedRaw)) {
        header('Location: admin_panel.php?page=' . urlencode($default_page));
        exit;
    }
    $page = null; // لا توجد صفحات مسموحة
}

$allowed_pages = ['dashboard', 'products', 'orders', 'users', 'customers', 'admins', 'withdrawals', 'account_requests', 'devices', 'reports', 'marketer_reports', 'marketing_insights', 'shipping_cities_statistics', 'governorates', 'shipping_companies', 'support', 'support_orders', 'financial', 'accounts', 'supervisor', 'support_detail', 'marketing', 'marketing_main', 'debts', 'shipping_accounts', 'shipping_reps', 'shipping_rep_stats', 'shipping_company_report', 'your_rank', 'storage', 'shipping_storage', 'product_reports', 'complaints', 'bonus_super', 'bonus_support', 'bonus_marketing', 'bonus_shipping', 'sheets', 'currency_exchange'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة</title>
    <?= function_exists('system_favicon_html') ? system_favicon_html() : '<link rel="icon" type="image/png" href="brand_logo.php?f=logo">' ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(ensure_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js?v=2"></script>
    <script>
    // Global UI Helpers using SweetAlert2
    function uiAlert(message, icon = 'error', title = 'تنبيه') {
        Swal.fire({
            icon: icon,
            title: title,
            text: message,
            confirmButtonColor: '#3b82f6',
            confirmButtonText: 'حسناً'
        });
    }

    function uiConfirmSubmit(event, button, title, text, confirmText, isDestructive = false) {
        event.preventDefault();
        Swal.fire({
            title: title,
            text: text,
            icon: isDestructive ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDestructive ? '#ef4444' : '#3b82f6',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: confirmText,
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                var form = button.closest('form');
                if (button.name) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = button.name;
                    hidden.value = button.value;
                    form.appendChild(hidden);
                }
                form.submit();
            }
        });
    }

    function uiConfirmLink(event, url, title, text, confirmText, isDestructive = false) {
        event.preventDefault();
        Swal.fire({
            title: title,
            text: text,
            icon: isDestructive ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDestructive ? '#ef4444' : '#3b82f6',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: confirmText,
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
    </script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
        body { 
            font-family: 'Cairo', sans-serif; 
            background: #f6f8f4;
        }
        .sidebar { 
            background: #ffffff;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            border-left: 1px solid #e5e7eb;
        }
        .sidebar a { 
            transition: all 0.3s;
            border-right: 3px solid transparent;
            color: #1f2937;
        }
        .sidebar a:hover { 
            background: #f8fafc;
            border-right-color: #4b6b2f;
            color: #4b6b2f;
        }
        .sidebar a.active { 
            background: #f1f5f1;
            border-right-color: #4b6b2f;
            color: #4b6b2f;
            font-weight: 700;
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
        .nav-header {
            background: linear-gradient(135deg, #4b6b2f 0%, #3a5524 100%);
            color: white;
        }
    </style>
</head>
<body class="flex flex-col md:flex-row min-h-screen bg-gray-50">
    <!-- الشريط الجانبي -->
    <div class="sidebar w-full md:w-64 p-0 shrink-0 border-b md:border-b-0 md:border-l border-gray-200">
        <!-- الهيدر -->
        <div class="nav-header p-6 text-white">
            <div class="text-center">
                <div class="mx-auto mb-4 flex items-center justify-center">
                    <img src="<?= htmlspecialchars(function_exists('system_brand_logo_url') ? system_brand_logo_url() : 'brand_logo.php?f=logo', ENT_QUOTES, 'UTF-8') ?>"
                         alt="Miskova Global"
                         class="mx-auto"
                         style="max-width: 140px; max-height: 90px; width: auto; height: auto; object-fit: contain;">
                </div>
                <h2 class="text-xl font-bold">لوحة الإدارة</h2>
                <p class="text-green-200 text-sm"><?= htmlspecialchars($_SESSION['admin_fullname'] ?? 'مدير') ?></p>
                <p class="text-green-300 text-xs">
                    <?php
                    $adminRole = $_SESSION['admin_role'] ?? 'admin';
                      if ($adminRole == 'super_admin') {
                          echo 'مدير رئيسي';
                      } elseif ($adminRole == 'support') {
                          echo 'دعم فني';
                      } elseif ($adminRole == 'marketing') {
                          echo 'Marketing';
                      } elseif ($adminRole == 'shipping_company') {
                          echo 'مسئول شركة الشحن';
                          if (!empty($_SESSION['shipping_company_id'])) {
                              $sc_id = (int)$_SESSION['shipping_company_id'];
                              $sc_res = $conn->query("SELECT name FROM shipping_accounts WHERE id = $sc_id");
                              if ($sc_res && $sc_row = $sc_res->fetch_assoc()) {
                                  echo '<br><span class="text-[10px] bg-green-800/50 px-2 py-0.5 rounded mt-1 inline-block text-green-100"><i class=\'bx bx-buildings\'></i> ' . htmlspecialchars($sc_row['name']) . '</span>';
                              }
                          }
                      } else {
                          echo 'مدير';
                      }
                      ?>
                </p>
            </div>
        </div>
        
        <!-- القائمة -->
        <nav class="p-4 space-y-1">
            <?php
            $navRole = $_SESSION['admin_role'] ?? 'admin';
            $navAllowedRaw = $_SESSION['admin_allowed_pages'] ?? null;
            $can = function ($pageKey) use ($navRole, $navAllowedRaw) {
                return admin_can_access_page($pageKey, $navRole, $navAllowedRaw);
            };
            $currentPage = $_GET['page'] ?? '';
            ?>
            <?php if ($navRole !== 'support'): ?>
            <?php if ($can('dashboard')): ?>
            <a href="?page=dashboard" class="block p-3 rounded-lg <?= ($currentPage === '' || $currentPage == 'dashboard') ? 'active' : '' ?>">
                <i class='bx bx-dashboard mr-3'></i> لوحة التحكم
            </a>
            <?php endif; ?>
            <?php if ($can('products')): ?>
            <a href="?page=products" class="block p-3 rounded-lg <?= $currentPage == 'products' ? 'active' : '' ?>">
                <i class='bx bx-package mr-3'></i> إدارة المنتجات
            </a>
            <?php endif; ?>
            <?php if ($can('orders')): ?>
            <a href="?page=orders" class="block p-3 rounded-lg <?= $currentPage == 'orders' ? 'active' : '' ?>">
                <i class='bx bx-cart mr-3'></i> إدارة الطلبات
            </a>
            <?php endif; ?>
            <?php if ($can('users')): ?>
            <a href="?page=users" class="block p-3 rounded-lg <?= $currentPage == 'users' ? 'active' : '' ?>">
                <i class='bx bx-user mr-3'></i> إدارة المستخدمين
            </a>
            <?php endif; ?>
            <?php if ($navRole === 'super_admin'): ?>
            <a href="?page=admins" class="block p-3 rounded-lg <?= $currentPage == 'admins' ? 'active' : '' ?>">
                <i class='bx bx-user-circle mr-3'></i> إدارة المديرين
            </a>
            <?php endif; ?>
            <?php if ($can('withdrawals')): ?>
            <a href="?page=withdrawals" class="block p-3 rounded-lg <?= $currentPage == 'withdrawals' ? 'active' : '' ?>">
                <i class='bx bx-money mr-3'></i> طلبات السحب
            </a>
            <?php endif; ?>
            <?php if ($can('account_requests')): ?>
            <a href="?page=account_requests" class="block p-3 rounded-lg <?= $currentPage == 'account_requests' ? 'active' : '' ?>">
                <i class='bx bx-user-plus mr-3'></i> طلبات الحسابات
            </a>
            <?php endif; ?>
            <?php if ($can('devices') && ($sessionRole === 'super_admin' || $sessionRole === 'manager')): ?>
            <a href="?page=devices" class="block p-3 rounded-lg <?= $currentPage == 'devices' ? 'active' : '' ?>">
                <i class='bx bx-laptop mr-3'></i> طلبات الأجهزة (Binding)
            </a>
            <?php endif; ?>
            <?php if ($can('reports')): ?>
            <a href="?page=reports" class="block p-3 rounded-lg <?= $currentPage == 'reports' ? 'active' : '' ?>">
                <i class='bx bx-bar-chart mr-3'></i> التقارير التجارية
            </a>
            <?php endif; ?>
            <?php if ($can('marketer_reports')): ?>
            <a href="?page=marketer_reports" class="block p-3 rounded-lg <?= $currentPage == 'marketer_reports' ? 'active' : '' ?>">
                <i class='bx bx-line-chart mr-3'></i> تقارير المسوقين
            </a>
            <?php endif; ?>

            <?php if ($can('shipping_cities_statistics')): ?>
            <a href="?page=shipping_cities_statistics" class="block p-3 rounded-lg <?= $currentPage == 'shipping_cities_statistics' ? 'active' : '' ?>">
                <i class='bx bx-map-pin mr-3'></i> إحصائيات المدن
            </a>
            <?php endif; ?>
            <?php if ($can('governorates')): ?>
            <a href="?page=governorates" class="block p-3 rounded-lg <?= $currentPage == 'governorates' ? 'active' : '' ?>">
                <i class='bx bx-map-alt mr-3'></i> المحافظات والمدن
            </a>
            <?php endif; ?>
            <?php if ($can('shipping_companies')): ?>
            <a href="?page=shipping_companies" class="block p-3 rounded-lg <?= $currentPage == 'shipping_companies' ? 'active' : '' ?>">
                <i class='bx bxs-truck mr-3'></i> شركات الشحن
            </a>
            <?php endif; ?>

            <?php if ($can('accounts')): ?>
            <a href="?page=accounts" class="block p-3 rounded-lg <?= $currentPage == 'accounts' ? 'active' : '' ?>">
                <i class='bx bx-wallet mr-3'></i> حسابات الأرباح والتكاليف
            </a>
            <?php endif; ?>
            
            <?php if (in_array($_SESSION['admin_role'], ['super_admin', 'admin', 'manager', 'accountant'])): ?>
            <a href="?page=currency_exchange" class="block p-3 rounded-lg <?= $currentPage == 'currency_exchange' ? 'active' : '' ?>">
                <i class='bx bx-transfer-alt mr-3'></i> تحويل عملة
            </a>
            <?php endif; ?>
              <?php if ($can('marketing')): ?>
              <a href="?page=marketing" class="block p-3 rounded-lg <?= $currentPage == 'marketing' ? 'active' : '' ?>">
                <i class='bx bx-bullseye mr-3'></i> إحصائيات التسويق (Marketing Insights)
              </a>
              <a href="?page=marketing_main" class="block p-3 rounded-lg <?= $currentPage == 'marketing_main' ? 'active' : '' ?>">
                <i class='bx bx-store-alt mr-3'></i> التسويق Marketing
              </a>
              <?php endif; ?>

              <?php if (in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'manager'])): ?>
              <a href="?page=sheets" class="block p-3 rounded-lg <?= $currentPage == 'sheets' ? 'active' : '' ?>">
                <i class='bx bx-table mr-3'></i> شيتات Sheets
              </a>
              <?php endif; ?>
            <?php if ($can('debts')): ?>
              <a href="?page=debts" class="block p-3 rounded-lg <?= $currentPage == 'debts' ? 'active' : '' ?>">
                  <i class='bx bx-wallet-alt mr-3'></i> المديونيات والتحصيل
              </a>
              <?php endif; ?>
              
              <?php if ($navRole === 'super_admin'): ?>
              <a href="?page=shipping_accounts" class="block p-3 rounded-lg <?= $currentPage == 'shipping_accounts' ? 'active' : '' ?>">
                  <i class='bx bx-book-content mr-3'></i> حسابات شركات الشحن
              </a>
              <?php if ($can('product_reports')): ?>
              <a href="?page=product_reports" class="block p-3 rounded-lg <?= $currentPage == 'product_reports' ? 'active' : '' ?>">
                  <i class='bx bx-bar-chart-alt-2 mr-3'></i> تقارير المنتجات
              </a>
              <?php endif; ?>
              <a href="?page=storage" class="block p-3 rounded-lg <?= $currentPage == 'storage' ? 'active' : '' ?>">
                  <i class='bx bx-box mr-3'></i> المخزن Storage
              </a>
              <?php if ($can('customers')): ?>
              <a href="?page=customers" class="block p-3 rounded-lg <?= $currentPage == 'customers' ? 'active' : '' ?>">
                  <i class='bx bx-group mr-3'></i> بيانات العملاء
              </a>
              <?php endif; ?>
              <?php endif; ?>

              <?php if ($navRole === 'shipping_company'): ?>
              <a href="?page=support_orders" class="block p-3 rounded-lg <?= $currentPage == 'support_orders' ? 'active' : '' ?>">
                  <i class='bx bx-package mr-3'></i> طلبات شركة الشحن
              </a>
              <a href="?page=shipping_reps" class="block p-3 rounded-lg <?= $currentPage == 'shipping_reps' ? 'active' : '' ?>">
                  <i class='bx bx-group mr-3'></i> مندوب شركة الشحن
              </a>
              <a href="?page=shipping_storage" class="block p-3 rounded-lg <?= $currentPage == 'shipping_storage' ? 'active' : '' ?>">
                  <i class='bx bx-store-alt mr-3'></i> المخزن
              </a>
              <?php endif; ?>

              <?php if ($navRole === 'super_admin' || $can('supervisor')): ?>
            <a href="?page=supervisor" class="block p-3 rounded-lg <?= $currentPage == 'supervisor' ? 'active' : '' ?>">
                <i class='bx bx-user-check mr-3'></i> متابعه (مدير رئيسي)
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($can('support')): ?>
            <a href="?page=support" class="block p-3 rounded-lg <?= $currentPage == 'support' ? 'active' : '' ?>">
                <i class='bx bx-chat mr-3'></i> التواصل و الدعم
            </a>
            <?php endif; ?>

            <?php if ($can('complaints')): ?>
            <a href="?page=complaints" class="block p-3 rounded-lg <?= $currentPage == 'complaints' ? 'active' : '' ?>">
                <i class='bx bx-message-error mr-3'></i> الشكاوي و الاقتراحات
            </a>
            <?php endif; ?>

            <?php if ($can('your_rank')): ?>
            <a href="?page=your_rank" class="block p-3 rounded-lg <?= $currentPage == 'your_rank' ? 'active' : '' ?>">
                <i class='bx bx-trophy mr-3'></i> ترتيبك Your Rank
            </a>
            <?php endif; ?>

            <?php 
            $bonusPage = '';
            if ($navRole === 'super_admin' || $navRole === 'admin') {
                $bonusPage = 'bonus_super';
            } elseif ($navRole === 'support') {
                $bonusPage = 'bonus_support';
            } elseif ($navRole === 'shipping_company') {
                $bonusPage = 'bonus_shipping';
            } elseif ($navRole === 'marketing' || $can('marketing')) {
                $bonusPage = 'bonus_marketing';
            }
            if ($bonusPage !== ''):
            ?>
            <a href="?page=<?= $bonusPage ?>" class="block p-3 rounded-lg <?= $currentPage == $bonusPage ? 'active' : '' ?>">
                <i class='bx bx-gift mr-3'></i> مكافاتك Bonus
            </a>
            <?php endif; ?>

            <!-- تسجيل الخروج -->
            <div class="pt-4 mt-4 border-t border-gray-200">
                <a href="admin_logout.php" class="block p-3 rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 transition">
                    <i class='bx bx-log-out mr-3'></i> تسجيل الخروج
                </a>
            </div>
        </nav>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="flex-1 p-4 md:p-6 bg-gray-50 min-h-screen relative w-full overflow-x-hidden">
        <!-- شريط الإشعارات -->
        <div class="absolute top-4 left-6 z-[99999]">
            <div class="relative">
                <button id="nav-notification-bell" class="relative p-2 text-gray-500 hover:bg-gray-200 rounded-full transition bg-white shadow-sm border border-gray-100">
                    <i class='bx bx-bell text-2xl'></i>
                    <span id="nav-notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center hidden shadow">0</span>
                </button>
                <div id="nav-notification-dropdown" class="absolute left-0 mt-2 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden hidden z-[99999]" style="width: 350px; min-width: 350px;">
                    <div class="p-3 bg-gray-50 border-b flex justify-between items-center">
                        <span class="font-bold text-gray-700">الإشعارات</span>
                        <button id="nav-notification-mark-all" class="text-xs text-indigo-600 hover:underline">تحديد الكل كمقروء</button>
                    </div>
                    <div id="nav-notification-list" class="max-h-80 overflow-y-auto">
                        <div class="p-4 text-center text-gray-500 text-sm">جاري التحميل...</div>
                    </div>
                </div>
            </div>
        </div>
        <script src="bell_notifications.js?v=<?= time() ?>"></script>

        <?php if (!empty($db_connection_failed)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            تعذر الاتصال بقاعدة البيانات. تأكد أن MySQL شغال وأن بيانات الاتصال في config.php صحيحة.
        </div>
        <?php endif; ?>
        <?php
        if ($page === null) {
            echo "<div class='bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded'>لم يتم تعيين أي تبويبات لحسابك. تواصل مع المدير الرئيسي.</div>";
        } elseif (in_array($page, $allowed_pages, true)) {
            $page_file = "admin_{$page}.php";
            if (file_exists($page_file)) {
                try {
                    include $page_file;
                } catch (Throwable $e) {
                    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>";
                    echo "<strong>خطأ في الصفحة:</strong> " . htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            } else {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>ملف الصفحة غير موجود: {$page_file}</div>";
            }
        } elseif (admin_can_access_page('dashboard', $sessionRole, $sessionAllowedRaw) && file_exists('admin_dashboard.php')) {
            include_once("admin_dashboard.php");
        }
        ?>
    </div>
    <script>
        // Keep session alive
        setInterval(function() {
            fetch('keep_alive.php').catch(() => {});
        }, 600000); // 10 minutes
    </script>
</body>
</html>
