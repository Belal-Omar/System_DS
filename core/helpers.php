<?php
// ملف: helpers.php

if (!function_exists('system_web_base')) {
    /**
     * مسار تطبيق النظام على الويب (مثال: /System/ أو /)
     */
    function system_web_base() {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $appRoot = realpath(__DIR__);
        if ($docRoot && $appRoot) {
            $docRoot = str_replace('\\', '/', $docRoot);
            $appRoot = str_replace('\\', '/', $appRoot);
            if (stripos($appRoot, $docRoot) === 0) {
                $rel = substr($appRoot, strlen($docRoot));
                $rel = '/' . trim(str_replace('\\', '/', $rel), '/');
                return ($rel === '/' || $rel === '') ? '/' : ($rel . '/');
            }
        }

        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '/' || $script === '.' || $script === '') {
            return '/';
        } else {
            return rtrim($script, '/') . '/';
        }
    }
}

if (!function_exists('system_brand_logo_url')) {
    /** مسار لوجو النظام (Miskova Global) — عبر PHP عشان يتجنب 404 */
    function system_brand_logo_url($variant = 'logo') {
        $variant = ($variant === 'favicon') ? 'favicon' : 'logo';
        $v = @filemtime(__DIR__ . '/assets/logo.png') ?: time();
        return system_web_base() . 'brand_logo.php?f=' . rawurlencode($variant) . '&v=' . $v;
    }
}

if (!function_exists('system_favicon_html')) {
    /**
     * وسوم الـ favicon لوضعها داخل <head>
     */
    function system_favicon_html() {
        $logo = htmlspecialchars(system_brand_logo_url('logo'), ENT_QUOTES, 'UTF-8');
        $ico = htmlspecialchars(system_brand_logo_url('favicon'), ENT_QUOTES, 'UTF-8');
        return '<link rel="icon" type="image/png" href="' . $ico . '">' . "\n"
            . '    <link rel="shortcut icon" type="image/png" href="' . $ico . '">' . "\n"
            . '    <link rel="apple-touch-icon" href="' . $logo . '">';
    }
}

// التحقق إذا كانت الدوال مُعرَّفة مسبقاً
if (!function_exists('clean_input')) {
    function clean_input($data) {
        global $conn;
        if ($data === null) { return ''; }
        if (!isset($conn) || !$conn) {
            // إذا لم يكن الاتصال متاحاً، إرجاع البيانات بعد تنظيفها فقط
            return trim($data);
        }
        return mysqli_real_escape_string($conn, trim($data));
    }
}

if (!function_exists('json_response')) {
    function json_response($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('check_admin_login')) {
    function check_admin_login() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header("Location: admin_login.php");
            exit;
        }
    }
}

if (!function_exists('ensure_csrf_token')) {
    function ensure_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = ensure_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token = null) {
        ensure_csrf_token();
        $token = $token ?? ($_POST['csrf_token'] ?? '');
        return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
            http_response_code(403);
            die('طلب غير صالح. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
        }
    }
}

if (!function_exists('support_orders_sync_schema')) {
    function support_orders_sync_schema(mysqli $conn): ?string
    {
        $table = $conn->query("SHOW TABLES LIKE 'support_orders'");
        if (!$table || $table->num_rows === 0) {
            return null;
        }

        $res = $conn->query('SHOW COLUMNS FROM `support_orders`');
        if (!$res) {
            return 'تعذر قراءة أعمدة جدول support_orders.';
        }
        $have = [];
        while ($row = $res->fetch_assoc()) {
            $have[strtolower($row['Field'])] = true;
        }

        $fragments = [];
        if (empty($have['sheet_id'])) {
            $fragments[] = 'ADD COLUMN `sheet_id` INT NOT NULL DEFAULT 0';
        }
        if (empty($have['order_date'])) {
            $fragments[] = 'ADD COLUMN `order_date` DATE NULL DEFAULT NULL';
        }
        if (empty($have['recipient_name'])) {
            $fragments[] = 'ADD COLUMN `recipient_name` VARCHAR(200) NULL DEFAULT NULL';
        }
        if (empty($have['notes'])) {
            $fragments[] = 'ADD COLUMN `notes` TEXT NULL';
        }
        if (empty($have['governorate'])) {
            $fragments[] = 'ADD COLUMN `governorate` VARCHAR(100) NULL DEFAULT NULL';
        }
        if (empty($have['pieces'])) {
            $fragments[] = 'ADD COLUMN `pieces` INT NOT NULL DEFAULT 1';
        }
        if (empty($have['customer_status'])) {
            $fragments[] = "ADD COLUMN `customer_status` VARCHAR(50) NOT NULL DEFAULT 'new'";
        }
        if (empty($have['agent_code'])) {
            $fragments[] = 'ADD COLUMN `agent_code` VARCHAR(50) NULL DEFAULT NULL';
        }
        if (empty($have['marketing_agent'])) {
            $fragments[] = 'ADD COLUMN `marketing_agent` VARCHAR(50) NULL DEFAULT NULL';
        }
        if (empty($have['campaign'])) {
            $fragments[] = 'ADD COLUMN `campaign` VARCHAR(100) NULL DEFAULT NULL';
        }
        if (empty($have['version'])) {
            $fragments[] = 'ADD COLUMN `version` VARCHAR(50) NULL DEFAULT NULL';
        }
        if (empty($have['article'])) {
            $fragments[] = 'ADD COLUMN `article` VARCHAR(100) NULL DEFAULT NULL';
        }
        if (empty($have['sheet_row_index'])) {
            $fragments[] = 'ADD COLUMN `sheet_row_index` INT NULL DEFAULT NULL';
        }

        if ($fragments === []) {
            return null;
        }

        foreach ($fragments as $fragment) {
            $sql = 'ALTER TABLE `support_orders` ' . $fragment;
            if (!$conn->query($sql)) {
                return $conn->error;
            }
        }

        return null;
    }
}

if (!function_exists('get_admin_manageable_pages')) {
    /**
     * التبويبات التي يمكن منحها لمدير / marketing
     */
    function get_admin_manageable_pages() {
        return [
            'dashboard' => 'لوحة التحكم',
            'products' => 'إدارة المنتجات',
            'orders' => 'إدارة الطلبات',
            'users' => 'إدارة المستخدمين',
            'customers' => 'بيانات العملاء',
            'withdrawals' => 'طلبات السحب',
            'account_requests' => 'طلبات الحسابات',
            'devices' => 'طلبات الأجهزة (Binding)',
            'reports' => 'التقارير التجارية',
            'marketer_reports' => 'تقارير المسوقين',
            'shipping_cities_statistics' => 'إحصائيات المدن',
            'governorates' => 'المحافظات والمدن',
            'shipping_companies' => 'شركات الشحن',
            'shipping_company_report' => 'تقارير شركات الشحن',
            'accounts' => 'حسابات الأرباح والتكاليف',
            'marketing' => 'التسويق (Marketing)',
            'debts' => 'المديونيات والتحصيل',
            'support' => 'التواصل و الدعم',
            'supervisor' => 'متابعه (مدير رئيسي)',
            'financial' => 'المالية',
            'shipping_reps' => 'مندوب شركة الشحن',
            'your_rank' => 'ترتيبك Your Rank',
            'product_reports' => 'تقارير المنتجات',
            'complaints' => 'الشكاوي والاقتراحات',
        ];
    }
}

if (!function_exists('ensure_admin_permissions_schema')) {
    function ensure_admin_permissions_schema($conn) {
        if (!is_object($conn)) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        @$conn->query("ALTER TABLE admins MODIFY COLUMN role enum('super_admin','admin','support','marketing') DEFAULT 'admin'");

        $col = @$conn->query("SHOW COLUMNS FROM admins LIKE 'allowed_pages'");
        if ($col && $col->num_rows === 0) {
            @$conn->query("ALTER TABLE admins ADD COLUMN allowed_pages TEXT NULL AFTER role");
        }
    }
}

if (!function_exists('decode_admin_allowed_pages')) {
    function decode_admin_allowed_pages($raw) {
        if ($raw === null || $raw === '') {
            return null; // null = صلاحيات الدور الافتراضية
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $valid = array_keys(get_admin_manageable_pages());
        return array_values(array_intersect($decoded, $valid));
    }
}

if (!function_exists('encode_admin_allowed_pages')) {
    function encode_admin_allowed_pages(array $pages) {
        $valid = array_keys(get_admin_manageable_pages());
        $pages = array_values(array_unique(array_intersect($pages, $valid)));
        return json_encode($pages, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('get_admin_effective_pages')) {
    /**
     * الصفحات المسموح بها فعلياً لهذا المدير حسب الدور والصلاحيات المحفوظة
     */
    function get_admin_effective_pages($role, $allowed_pages_raw = null) {
        $role = (string) $role;
        $all = array_keys(get_admin_manageable_pages());

        if ($role === 'super_admin') {
            return array_merge($all, ['admins', 'support_detail', 'support_orders', 'storage', 'bonus_super']);
        }

        if ($role === 'support') {
            return ['support', 'support_orders', 'your_rank', 'complaints', 'bonus_support'];
        }

        if ($role === 'shipping_company') {
            return ['support_orders', 'debts', 'shipping_reps', 'your_rank', 'shipping_storage', 'complaints', 'bonus_shipping'];
        }

        $custom = decode_admin_allowed_pages($allowed_pages_raw);

        if ($role === 'marketing') {
            if ($custom === null) {
                return ['marketing_main', 'marketing', 'your_rank', 'complaints', 'bonus_marketing'];
            }
            $pages = $custom;
        } elseif ($custom === null) {
            // المديرون القدامى بدون قيود = كل التبويبات العادية (بما فيها المتابعة)
            $pages = $all;
        } else {
            $pages = $custom;
        }

        // تفاصيل موظف الدعم تتبع تبويب المتابعة
        if (in_array('supervisor', $pages, true) && !in_array('support_detail', $pages, true)) {
            $pages[] = 'support_detail';
        }

        if ($role === 'admin' && !in_array('bonus_super', $pages, true)) {
            $pages[] = 'bonus_super';
        }
        if ($role === 'marketing') {
            if (!in_array('bonus_marketing', $pages, true)) {
                $pages[] = 'bonus_marketing';
            }
            if (!in_array('marketing_main', $pages, true)) {
                $pages[] = 'marketing_main';
            }
        }

        return $pages;
    }
}

if (!function_exists('can_mark_order_as_received')) {
    /**
     * صلاحية تعيين حالة الطلب إلى «تم الاستلام» (received)
     * - المدير الرئيسي: نعم
     * - الدعم الفني: لا
     * - غيرهم: فقط إن فُتح لهم تبويبا «التواصل و الدعم» و «متابعه (مدير رئيسي)»
     */
    function can_mark_order_as_received($role = null, $allowed_pages_raw = null) {
        $role = $role ?? ($_SESSION['admin_role'] ?? '');
        if ($allowed_pages_raw === null && isset($_SESSION) && array_key_exists('admin_allowed_pages', $_SESSION)) {
            $allowed_pages_raw = $_SESSION['admin_allowed_pages'];
        }
        if ($role === 'super_admin') {
            return true;
        }
        if ($role === 'support') {
            return false;
        }
        $pages = get_admin_effective_pages($role, $allowed_pages_raw);
        return in_array('support', $pages, true) && in_array('supervisor', $pages, true);
    }
}

if (!function_exists('support_agent_code_prefix')) {
    /**
     * أول حرفين من اسم الدعم (بدون مسافات). لو حرف واحد يرجع الحرف فقط.
     */
    function support_agent_code_prefix($name) {
        $name = trim((string) $name);
        $compact = preg_replace('/\s+/u', '', $name);
        if ($compact === null || $compact === '') {
            return 'X';
        }
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            $len = (int) mb_strlen($compact, 'UTF-8');
            $prefix = mb_substr($compact, 0, $len >= 2 ? 2 : 1, 'UTF-8');
        } else {
            $prefix = substr($compact, 0, strlen($compact) >= 2 ? 2 : 1);
        }
        if ($prefix !== '' && preg_match('/^[A-Za-z]+$/', $prefix)) {
            $prefix = strtoupper($prefix);
        }
        return $prefix !== '' ? $prefix : 'X';
    }
}

if (!function_exists('get_support_agent_code')) {
    /**
     * كود الوكيل = أول حرفين من الاسم + رقم تسلسل حسب أسبقية إنشاء حساب الدعم (01، 02، …)
     * مثال: أحمد أول حساب → أح01 | Ali ثاني حساب → AL02 | A → A03
     */
    function get_support_agent_code($conn, $support_id) {
        $support_id = (int) $support_id;
        if ($support_id <= 0 || !is_object($conn)) {
            return '';
        }

        static $cache = [];
        if (isset($cache[$support_id])) {
            return $cache[$support_id];
        }

        $fullname = '';
        $stmt = $conn->prepare("SELECT fullname, username FROM admins WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $support_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $fullname = trim((string) ($row['fullname'] ?? ''));
                if ($fullname === '') {
                    $fullname = trim((string) ($row['username'] ?? ''));
                }
            }
            $stmt->close();
        }

        $seq = 0;
        $found = false;
        $q = $conn->query("SELECT id FROM admins WHERE role = 'support' ORDER BY created_at ASC, id ASC");
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $seq++;
                if ((int) $row['id'] === $support_id) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            $seq = max(1, $seq);
        }

        $code = support_agent_code_prefix($fullname) . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
        $cache[$support_id] = $code;
        return $code;
    }
}

if (!function_exists('admin_can_access_page')) {
    function admin_can_access_page($page, $role, $allowed_pages_raw = null) {
        $page = (string) $page;
        $allowed = get_admin_effective_pages($role, $allowed_pages_raw);

        // صفحات فرعية تتبع تبويب أب
        $aliases = [
            'support_detail' => 'supervisor',
            'support_orders' => 'support',
            'shipping_rep_stats' => 'shipping_reps',
            'shipping_company_report' => 'shipping_companies',
            'marketing_insights' => 'marketing',
        ];
        if (isset($aliases[$page]) && in_array($aliases[$page], $allowed, true)) {
            return true;
        }

        return in_array($page, $allowed, true);
    }
}

if (!function_exists('load_session_admin_allowed_pages')) {
    function load_session_admin_allowed_pages($conn) {
        $role = $_SESSION['admin_role'] ?? '';
        if ($role === 'super_admin' || $role === 'support') {
            $_SESSION['admin_allowed_pages'] = null;
            $_SESSION['admin_allowed_pages_loaded'] = true;
            return;
        }
        $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
        $raw = null;
        if ($admin_id > 0 && is_object($conn)) {
            $stmt = $conn->prepare('SELECT allowed_pages, role FROM admins WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $admin_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $raw = $row['allowed_pages'];
                    if (!empty($row['role'])) {
                        $_SESSION['admin_role'] = $row['role'];
                    }
                }
                $stmt->close();
            }
        }
        $_SESSION['admin_allowed_pages'] = $raw;
        $_SESSION['admin_allowed_pages_loaded'] = true;
    }
}

if (!function_exists('ensure_debts_schema')) {
    function ensure_debts_schema($conn) {
        if (!is_object($conn)) {
            return false;
        }
        static $done = false;
        static $has_period = null;
        if ($done) {
            return $has_period === true;
        }
        $done = true;

        @$conn->query("CREATE TABLE IF NOT EXISTS support_financial_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            type ENUM('collected', 'creditor', 'debtor') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            note TEXT,
            period_month VARCHAR(7) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $col = @$conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'period_month'");
        $has_period = ($col && $col->num_rows > 0);
        if (!$has_period) {
            @$conn->query("ALTER TABLE support_financial_records ADD COLUMN period_month VARCHAR(7) NULL AFTER note");
            $col2 = @$conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'period_month'");
            $has_period = ($col2 && $col2->num_rows > 0);
        }

        if ($has_period) {
            @$conn->query("UPDATE support_financial_records
                SET period_month = DATE_FORMAT(entry_date, '%Y-%m')
                WHERE period_month IS NULL OR period_month = ''");
        }

        return $has_period === true;
    }
}

if (!function_exists('debts_period_month_sql')) {
    /**
     * تعبير SQL لشهر الاستحقاق مع توافق السجلات القديمة
     */
    function debts_period_month_sql($alias = '', $has_period_month = true) {
        $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        if (!$has_period_month) {
            return "DATE_FORMAT({$p}entry_date, '%Y-%m')";
        }
        return "COALESCE(NULLIF({$p}period_month, ''), DATE_FORMAT({$p}entry_date, '%Y-%m'))";
    }
}

if (!function_exists('run_admin_migrations')) {
    function run_admin_migrations($conn) {
        if (file_exists(__DIR__ . '/admin_migrations.php')) {
            require_once __DIR__ . '/admin_migrations.php';
        }
        if (is_object($conn) && function_exists('ensure_admin_permissions_schema')) {
            @ensure_admin_permissions_schema($conn);
        }
        if (is_object($conn) && function_exists('ensure_debts_schema')) {
            @ensure_debts_schema($conn);
        }
        if (is_object($conn) && function_exists('support_orders_sync_schema')) {
            @support_orders_sync_schema($conn);
        }
        if (is_object($conn) && function_exists('support_product_monthly_sync_schema')) {
            @support_product_monthly_sync_schema($conn);
        }
        if (is_object($conn) && function_exists('support_calls_sync_schema')) {
            @support_calls_sync_schema($conn);
        }
        static $done = false;
        if ($done) {
            return;
        }
        $flagFile = __DIR__ . DIRECTORY_SEPARATOR . '.admin_migrations_v4';
        if (file_exists($flagFile)) {
            $done = true;
            return;
        }
        $migrationsFile = __DIR__ . DIRECTORY_SEPARATOR . 'admin_migrations.php';
        if (is_readable($migrationsFile)) {
            require_once $migrationsFile;
        }
        @file_put_contents($flagFile, date('c'));
        $done = true;
    }
}

if (!function_exists('get_admin_stats')) {
    function get_admin_stats($conn) {
        $stats = [];

        $stats['total_products'] = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'] ?? 0;
        $stats['total_orders'] = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'] ?? 0;
        $stats['total_users'] = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?? 0;
        $stats['pending_orders'] = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'قيد الانتظار'")->fetch_assoc()['total'] ?? 0;
        $stats['total_revenue'] = $conn->query("SELECT SUM(total) as total FROM orders WHERE status = 'تم التوصيل'")->fetch_assoc()['total'] ?? 0;

        return $stats;
    }
}

if (!function_exists('upload_image')) {
    function upload_image($file, $upload_dir = 'imgs/') {
        if ($file['error'] == 0) {
            $image_name = time() . '_' . basename($file['name']);
            $image_path = $upload_dir . $image_name;

            if (move_uploaded_file($file['tmp_name'], $image_path)) {
                return $image_path;
            }
        }
        return '';
    }
}

if (!function_exists('get_product_counts')) {
    function get_product_counts($conn) {
        $total = 0;
        $available = 0;
        $low_stock = 0;
        $out_of_stock = 0;

        // كود الاستعلام من قاعدة البيانات
        $query = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN stock BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock
            FROM products";

        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $total = $row['total'];
            $available = $row['available'];
            $low_stock = $row['low_stock'];
            $out_of_stock = $row['out_of_stock'];
        }

        return [
            'total' => $total,
            'available' => $available,
            'low_stock' => $low_stock,
            'out_of_stock' => $out_of_stock
        ];
    }
}

if (!function_exists('get_shipping_companies')) {
    function get_shipping_companies($conn) {
        $companies = [];
        $query = "SELECT * FROM shipping_companies WHERE is_active = 1 ORDER BY name";
        $result = mysqli_query($conn, $query);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $companies[] = $row;
            }
        }

        return $companies;
    }
}

if (!function_exists('get_shipping_company_stats')) {
    function get_shipping_company_stats($conn, $company_id = null) {
        $stats = [];

        // Base query for orders with shipping companies through product relationships
        if ($company_id) {
            try {
                // Get company name first
                $company_query = "SELECT name FROM shipping_companies WHERE id = " . (int)$company_id;
                $company_result = mysqli_query($conn, $company_query);

                if (!$company_result) {
                    throw new RuntimeException("Failed to retrieve company name: " . mysqli_error($conn));
                }

                if (mysqli_num_rows($company_result) > 0) {
                    $company = mysqli_fetch_assoc($company_result);
                    // Unused variable removed

                    // Get ALL orders assigned to this shipping company
                    $status_query = "SELECT
                        COUNT(DISTINCT o.id) as total_orders,
                        COUNT(DISTINCT CASE WHEN o.status = 'قيد الانتظار' THEN o.id END) as pending,
                        COUNT(DISTINCT CASE WHEN o.status IN ('قيد التنفيذ', 'في الشحن', 'تحت التحضير') THEN o.id END) as shipping,
                        COUNT(DISTINCT CASE WHEN o.status = 'تم التوصيل' THEN o.id END) as delivered,
                        COUNT(DISTINCT CASE WHEN o.status IN ('ملغي', 'مرفوض') THEN o.id END) as cancelled,
                        COUNT(DISTINCT CASE WHEN o.status IN ('مرتجع', 'محصل') THEN o.id END) as returned
                        FROM orders o
                        WHERE o.shipping_company_id = " . (int)$company_id;

                    $result = mysqli_query($conn, $status_query);

                    if (!$result) {
                        throw new Exception("Failed to retrieve order stats: " . mysqli_error($conn));
                    }

                    if (mysqli_num_rows($result) > 0) {
                        $row = mysqli_fetch_assoc($result);
                        $stats['orders'] = $row;

                        // Calculate percentages
                        $total = $row['total_orders'];
                        if ($total > 0) {
                            $stats['orders']['pending_percent'] = round(($row['pending'] / $total) * 100, 1);
                            $stats['orders']['shipping_percent'] = round(($row['shipping'] / $total) * 100, 1);
                            $stats['orders']['delivered_percent'] = round(($row['delivered'] / $total) * 100, 1);
                            $stats['orders']['cancelled_percent'] = round(($row['cancelled'] / $total) * 100, 1);
                            $stats['orders']['returned_percent'] = round(($row['returned'] / $total) * 100, 1);
                        } else {
                            $stats['orders']['pending_percent'] = 0;
                            $stats['orders']['shipping_percent'] = 0;
                            $stats['orders']['delivered_percent'] = 0;
                            $stats['orders']['cancelled_percent'] = 0;
                            $stats['orders']['returned_percent'] = 0;
                        }
                    }

                    // Get financial stats - filtered by company
                    $financial_query = "SELECT
                        COUNT(o.id) as delivered_count,
                        SUM(o.total) as total_revenue,
                        SUM(o.commission_total) as total_commission
                        FROM orders o
                        WHERE o.status = 'تم التوصيل' AND o.shipping_company_id = " . (int)$company_id;

                    $result = mysqli_query($conn, $financial_query);

                    if (!$result) {
                        throw new Exception("Failed to retrieve financial stats: " . mysqli_error($conn));
                    }

                    if (mysqli_num_rows($result) > 0) {
                        $row = mysqli_fetch_assoc($result);
                        $delivered_count = $row['delivered_count'] ?? 0;
                        $total_revenue = $row['total_revenue'] ?? 0;
                        $total_commission = $row['total_commission'] ?? 0;

                        // Calculate shipping cost: delivered orders * 30
                        $total_shipping_cost = $delivered_count * 30;

                        // Calculate revenue after commission and shipping: total - commission - shipping
                        $revenue_after_deductions = $total_revenue - $total_commission - $total_shipping_cost;

                        $stats['financial'] = [
                            'delivered_count' => $delivered_count,
                            'total_revenue' => $total_revenue,
                            'gross_revenue' => $total_revenue, // للتوافق مع التفاصيل
                            'total_commission' => $total_commission,
                            'total_shipping_cost' => $total_shipping_cost,
                            'revenue_after_deductions' => $revenue_after_deductions,
                            'net_revenue' => $revenue_after_deductions, // للتوافق مع التفاصيل
                            'profit_without_commission' => $total_revenue - $total_shipping_cost // الربح بدون خصم العمولات
                        ];
                    }
                } else {
                    // Company not found, return empty stats
                    $stats['orders'] = [
                        'total_orders' => 0,
                        'pending' => 0,
                        'shipping' => 0,
                        'delivered' => 0,
                        'cancelled' => 0,
                        'returned' => 0,
                        'pending_percent' => 0,
                        'shipping_percent' => 0,
                        'delivered_percent' => 0,
                        'cancelled_percent' => 0,
                        'returned_percent' => 0
                    ];
                    $stats['financial'] = [
                        'delivered_count' => 0,
                        'total_revenue' => 0,
                        'total_commission' => 0,
                        'total_shipping_cost' => 0,
                        'revenue_after_deductions' => 0
                    ];
                }
            } catch (Exception $e) {
                error_log("Error in get_shipping_company_stats: " . $e->getMessage());
                return [
                    'error' => 'Failed to retrieve shipping company stats: ' . $e->getMessage()
                ];
            }
        }

        return $stats;
    }
}

if (!function_exists('get_shipping_company_products_with_orders')) {
    function get_shipping_company_products_with_orders($conn, $company_id) {
        $products = [];

        // Get company name
        $company_query = "SELECT name FROM shipping_companies WHERE id = " . (int)$company_id;
        $company_result = mysqli_query($conn, $company_query);
        if ($company_result && mysqli_num_rows($company_result) > 0) {
            $company = mysqli_fetch_assoc($company_result);
            // Unused variable removed

            // Get products with their order statistics - FILTERED BY COMPANY
            $query = "SELECT
                p.id,
                p.name,
                p.price,
                ps.stock as allocated_stock,
                p.image,
                p.commission,
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'تم التوصيل' THEN o.id END) as delivered_orders,
                SUM(CASE WHEN o.status = 'تم التوصيل' AND o.shipping_company_id = " . (int)$company_id . " THEN oi.quantity ELSE 0 END) as delivered_quantity,
                SUM(CASE WHEN o.shipping_company_id = " . (int)$company_id . " THEN oi.quantity ELSE 0 END) as total_quantity,
                GROUP_CONCAT(DISTINCT CASE WHEN o.shipping_company_id = " . (int)$company_id . " THEN o.status END ORDER BY o.status SEPARATOR ',') as order_statuses
                FROM products p
                JOIN product_shippers ps ON p.id = ps.product_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.shipping_company_id = " . (int)$company_id . "
                WHERE ps.shipping_company_id = " . (int)$company_id . "
                GROUP BY p.id
                ORDER BY p.name";

            $result = mysqli_query($conn, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    // Parse order statuses to get counts
                    $statuses = $row['order_statuses'] ? explode(',', $row['order_statuses']) : [];
                    $status_counts = array_count_values($statuses);

                    $products[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'price' => $row['price'],
                        'stock' => $row['allocated_stock'],
                        'image' => $row['image'],
                        'commission' => $row['commission'],
                        'total_orders' => $row['total_orders'],
                        'delivered_orders' => $row['delivered_orders'],
                        'delivered_quantity' => $row['delivered_quantity'],
                        'total_quantity' => $row['total_quantity'],
                        'status_counts' => $status_counts
                    ];
                }
            }
        }

        return $products;
    }
}

if (!function_exists('get_shipping_company_products_stats')) {
    function get_shipping_company_products_stats($conn, $company_id) {
        $stats = [];

        // Get company name
        $company_query = "SELECT name FROM shipping_companies WHERE id = " . (int)$company_id;
        $company_result = mysqli_query($conn, $company_query);
        if ($company_result && mysqli_num_rows($company_result) > 0) {
            $company = mysqli_fetch_assoc($company_result);
            // Unused variable removed

            // Get product counts based on allocation
            $product_query = "SELECT
                COUNT(*) as total_products,
                SUM(CASE WHEN ps.stock > 0 THEN 1 ELSE 0 END) as available_products,
                SUM(CASE WHEN ps.stock = 0 THEN 1 ELSE 0 END) as out_of_stock_products,
                SUM(CASE WHEN ps.stock > 0 AND ps.stock <= 10 THEN 1 ELSE 0 END) as low_stock_products,
                SUM(ps.stock) as total_stock
                FROM product_shippers ps
                JOIN products p ON ps.product_id = p.id
                WHERE ps.shipping_company_id = " . (int)$company_id;

            $result = mysqli_query($conn, $product_query);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $stats['products'] = $row;

                // Calculate percentages
                $total = $row['total_products'];
                if ($total > 0) {
                    $stats['products']['available_percent'] = round(($row['available_products'] / $total) * 100, 1);
                    $stats['products']['out_of_stock_percent'] = round(($row['out_of_stock_products'] / $total) * 100, 1);
                    $stats['products']['low_stock_percent'] = round(($row['low_stock_products'] / $total) * 100, 1);
                } else {
                    $stats['products']['available_percent'] = 0;
                    $stats['products']['out_of_stock_percent'] = 0;
                    $stats['products']['low_stock_percent'] = 0;
                }
            }
        }

        return $stats;
    }
}

if (!function_exists('get_shipping_company_orders')) {
    function get_shipping_company_orders($conn, $company_id, $limit = 50, $offset = 0) {
        $orders = [];

        // Get company name
        $company_query = "SELECT name FROM shipping_companies WHERE id = " . (int)$company_id;
        $company_result = mysqli_query($conn, $company_query);
        if ($company_result && mysqli_num_rows($company_result) > 0) {
            $company = mysqli_fetch_assoc($company_result);
            // Unused variable removed

            // Use the same query as admin_orders.php to get all orders with product info
            $query = "SELECT o.*,
                     u.fullname as marketer_name,
                     sc.city_name as shipping_city_name,
                     COUNT(oi.id) as items_count,
                     GROUP_CONCAT(DISTINCT p.name SEPARATOR '، ') as product_names,
                     GROUP_CONCAT(DISTINCT p.shipping_company SEPARATOR '، ') as shipping_companies
                     FROM orders o
                     LEFT JOIN order_items oi ON o.id = oi.order_id
                     LEFT JOIN products p ON oi.product_id = p.id
                     LEFT JOIN users u ON o.user_id = u.id
                     LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
                     WHERE o.shipping_company_id = " . (int)$company_id . "
                     GROUP BY o.id
                     ORDER BY o.created_at DESC
                     LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $result = mysqli_query($conn, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $orders[] = $row;
                }
            }
        }

        return $orders;
    }
}

if (!function_exists('add_shipping_company')) {
    function add_shipping_company($conn, $name, $phone = '', $email = '', $address = '') {
        $name = clean_input($name);
        $phone = clean_input($phone);
        $email = clean_input($email);
        $address = clean_input($address);

        $query = "INSERT INTO shipping_companies (name, phone, email, address) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $name, $phone, $email, $address);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $result;
        }

        return false;
    }
}

if (!function_exists('update_shipping_company')) {
    function update_shipping_company($conn, $id, $name, $phone = '', $email = '', $address = '') {
        $id = (int)$id;
        $name = clean_input($name);
        $phone = clean_input($phone);
        $email = clean_input($email);
        $address = clean_input($address);

        $query = "UPDATE shipping_companies SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssi", $name, $phone, $email, $address, $id);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $result;
        }

        return false;
    }
}

if (!function_exists('delete_shipping_company')) {
    function delete_shipping_company($conn, $id) {
        $id = (int)$id;

        // Check if company has orders before deleting
        $check_query = "SELECT COUNT(*) as count FROM orders WHERE shipping_company_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($row['count'] > 0) {
                return false; // Cannot delete company with existing orders
            }
        }

        // Delete the company
        $delete_query = "DELETE FROM shipping_companies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $result;
        }

        return false;
    }
}

if (!function_exists('get_shipping_company_by_id')) {
    function get_shipping_company_by_id($conn, $id) {
        $id = (int)$id;
        $query = "SELECT * FROM shipping_companies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $company = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            return $company;
        }

        return null;
    }
}

// User-specific statistics functions
if (!function_exists('get_user_product_stats')) {
    function get_user_product_stats($conn, $user_id, $user_type = 'all') {
        $stats = [];
        $user_id = (int)$user_id;

        if ($user_type == 'all' || $user_type == 'merchant') {
            // For merchants - get their own active products only
            $query = "SELECT
                COUNT(*) as total_products,
                SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN stock BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock
                FROM products
                WHERE user_id = $user_id AND status = 'active'";

            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $stats['products'] = $row;
            }
        } else {
            // For marketers - get all active products they can sell
            $query = "SELECT
                COUNT(*) as total_products,
                SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN stock BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock
                FROM products
                WHERE status = 'active'";

            $result = mysqli_query($conn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $stats['products'] = $row;
            }
        }

        return $stats;
    }
}

if (!function_exists('get_user_cities_stats')) {
    function get_user_cities_stats($conn, $user_id) {
        $stats = [];
        $user_id = (int)$user_id;

        // Simple query - get cities where this user has orders
        $query = "SELECT
            sc.city_name,
            sc.shipping_cost,
            COUNT(o.id) as total_orders,
            COUNT(CASE WHEN o.status IN ('completed', 'completed', 'completed') THEN 1 END) as completed_orders,
            SUM(o.total) as total_revenue,
            SUM(o.commission_total) as total_commission
            FROM shipping_cities sc
            LEFT JOIN orders o ON sc.id = o.shipping_city_id AND o.user_id = $user_id
            WHERE sc.is_active = 1
            GROUP BY sc.id, sc.city_name, sc.shipping_cost
            ORDER BY total_orders DESC";

        $result = mysqli_query($conn, $query);
        $cities = [];

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row['total_orders'] > 0) {
                    $cities[] = $row;
                }
            }
        }

        $stats['cities'] = $cities;

        // Get summary stats
        $summary_query = "SELECT
            COUNT(DISTINCT o.shipping_city_id) as cities_with_orders,
            COUNT(o.id) as total_orders,
            SUM(o.total) as total_revenue
            FROM orders o
            WHERE o.user_id = $user_id";

        $summary_result = mysqli_query($conn, $summary_query);
        if ($summary_result && mysqli_num_rows($summary_result) > 0) {
            $summary = mysqli_fetch_assoc($summary_result);
            $stats['summary'] = $summary;
        }

        return $stats;
    }
}

if (!function_exists('get_user_orders_stats')) {
    function get_user_orders_stats($conn, $user_id) {
        $stats = [];
        $user_id = (int)$user_id;

        $query = "SELECT
            COUNT(*) as total_orders,
            COUNT(CASE WHEN o.status IN ('completed', 'completed', 'completed') THEN 1 END) as completed_orders,
            COUNT(CASE WHEN o.status NOT IN ('completed', 'completed', 'completed', 'cancelled', 'rejected') THEN 1 END) as pending_orders,
            COUNT(CASE WHEN o.status IN ('cancelled', 'rejected') THEN 1 END) as cancelled_orders,
            SUM(o.total) as total_revenue,
            SUM(o.commission_total) as total_commission
            FROM orders o
            WHERE o.user_id = $user_id";

        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $stats = mysqli_fetch_assoc($result);
        }

        return $stats;
    }
}

if (!function_exists('get_support_member_performance')) {
    function get_support_member_performance($conn, $support_id, $start_date = null) {
        $support_id = (int) $support_id;
        $sheet_filter = '';
        $order_filter = '';

        if ($start_date) {
            $start_date = $conn->real_escape_string($start_date);
            $sheet_filter = " AND created_at >= '$start_date'";
            $order_filter = " AND created_at >= '$start_date'";
        }

        $total_orders = 0;
        $total_orders_result = $conn->query("SELECT COUNT(id) as total FROM support_orders WHERE support_id = $support_id $order_filter");
        if ($total_orders_result) {
            $total_orders = (int) ($total_orders_result->fetch_assoc()['total'] ?? 0);
        }

        $stats_row = $conn->query("
            SELECT
                SUM(CASE WHEN order_status IN ('order_confirmed', 'confirmed', 'تم التأكيد') THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN order_status IN ('received', 'delivered', 'تم التوصيل', 'محصل') THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN order_status IN ('order_cancelled', 'cancelled', 'ملغي', 'مرفوض') THEN 1 ELSE 0 END) as cancelled_count
            FROM support_orders
            WHERE support_id = $support_id $order_filter
        ")->fetch_assoc();

        $confirmed = (int) ($stats_row['confirmed_count'] ?? 0);
        $delivered = (int) ($stats_row['delivered_count'] ?? 0);
        $cancelled = (int) ($stats_row['cancelled_count'] ?? 0);

        $confirmation_rate = $total_orders > 0 ? round(($confirmed / $total_orders) * 100, 1) : 0;
        $delivery_rate = $total_orders > 0 ? round(($delivered / $total_orders) * 100, 1) : 0;
        $cancellation_rate = $total_orders > 0 ? round(($cancelled / $total_orders) * 100, 1) : 0;

        $personal_rating = 0;
        if ($total_orders > 0) {
            $conf_score = ($confirmation_rate / 100) * 5 * 0.4;
            $del_score = ($delivery_rate / 100) * 5 * 0.6;
            $personal_rating = min(5.0, round($conf_score + $del_score, 1));
        }

        return [
            'total_orders' => $total_orders,
            'confirmed_orders' => $confirmed,
            'delivered_orders' => $delivered,
            'cancelled_orders' => $cancelled,
            'completed_orders' => $delivered,
            'confirmation_rate' => $confirmation_rate,
            'delivery_rate' => $delivery_rate,
            'cancellation_rate' => $cancellation_rate,
            'personal_rating' => $personal_rating,
        ];
    }
}

if (!function_exists('sync_support_stats')) {
    function sync_support_stats($conn, $support_id) {
        $perf = get_support_member_performance($conn, $support_id);
        $support_id = (int) $support_id;

        $stmt = $conn->prepare("INSERT INTO support_stats
            (support_id, confirmation_rate, delivery_rate, cancellation_rate, total_orders, completed_orders, cancelled_orders, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            confirmation_rate = VALUES(confirmation_rate),
            delivery_rate = VALUES(delivery_rate),
            cancellation_rate = VALUES(cancellation_rate),
            total_orders = VALUES(total_orders),
            completed_orders = VALUES(completed_orders),
            cancelled_orders = VALUES(cancelled_orders),
            updated_at = NOW()");

        if ($stmt) {
            $stmt->bind_param(
                'idddiii',
                $support_id,
                $perf['confirmation_rate'],
                $perf['delivery_rate'],
                $perf['cancellation_rate'],
                $perf['total_orders'],
                $perf['delivered_orders'],
                $perf['cancelled_orders']
            );
            $stmt->execute();
            $stmt->close();
        }

        return $perf;
    }
}

if (!function_exists('normalize_activity_timestamp')) {
    function normalize_activity_timestamp($timestamp) {
        $ts = (int) $timestamp;
        if ($ts < 1577836800 || $ts > time() + 300) {
            return 0;
        }
        return $ts;
    }
}

if (!function_exists('support_activity_file_path')) {
    function support_activity_file_path(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'support_activity.json';
    }
}

if (!function_exists('support_activity_load')) {
    function support_activity_load(): array
    {
        $file = support_activity_file_path();
        if (!file_exists($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('support_activity_save')) {
    function support_activity_save(array $data): bool
    {
        return file_put_contents(support_activity_file_path(), json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
    }
}

if (!function_exists('support_mark_offline')) {
    function support_mark_offline(int $user_id, string $user_name = ''): void
    {
        if ($user_id <= 0) {
            return;
        }
        $data = support_activity_load();
        $key = isset($data[$user_id]) ? (string) $user_id : (string) $user_id;
        if (!isset($data[$key])) {
            $data[$key] = [];
        }
        $now = time();
        $data[$key]['is_online'] = false;
        $data[$key]['is_active'] = false;
        $data[$key]['status'] = 'غير متصل';
        $data[$key]['last_update'] = $now;
        $data[$key]['offline_at'] = $now;
        if ($user_name !== '') {
            $data[$key]['name'] = $user_name;
        }
        support_activity_save($data);
    }
}

if (!function_exists('format_activity_duration')) {
    function format_activity_duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $mins = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        if ($hours > 0) {
            return sprintf('%dس %02dد', $hours, $mins);
        }
        if ($mins > 0) {
            return sprintf('%dد %02dث', $mins, $secs);
        }
        return $secs . 'ث';
    }
}

if (!function_exists('support_activity_is_online')) {
    function support_activity_is_online(array $activity, int $threshold_seconds = 90): bool
    {
        $last = normalize_activity_timestamp($activity['last_update'] ?? 0);
        if ($last <= 0) {
            return false;
        }
        if (($activity['is_online'] ?? false) === false && isset($activity['offline_at'])) {
            return false;
        }
        return (time() - $last) <= $threshold_seconds;
    }
}

if (!function_exists('support_calls_sync_schema')) {
    function support_calls_sync_schema(mysqli $conn): ?string
    {
        $sql = "CREATE TABLE IF NOT EXISTS support_calls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            support_id INT NOT NULL,
            support_name VARCHAR(120) NOT NULL DEFAULT '',
            sheet_id INT NOT NULL DEFAULT 0,
            sheet_row_index INT NOT NULL DEFAULT -1,
            client_name VARCHAR(200) NOT NULL DEFAULT '',
            client_phone VARCHAR(80) NOT NULL DEFAULT '',
            started_at DATETIME NOT NULL,
            ended_at DATETIME NULL DEFAULT NULL,
            duration_seconds INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_support_started (support_id, started_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($sql)) {
            return $conn->error ?: 'فشل إنشاء جدول support_calls';
        }
        return null;
    }
}

if (!function_exists('support_get_total_call_seconds')) {
    function support_get_total_call_seconds(mysqli $conn, int $support_id, ?string $start_date = null): int
    {
        if ($support_id <= 0) {
            return 0;
        }
        @support_calls_sync_schema($conn);

        $where = "support_id = $support_id AND status IN ('completed', 'active')";
        if ($start_date) {
            $esc = $conn->real_escape_string($start_date);
            $where .= " AND started_at >= '$esc'";
        }

        $sql = "SELECT COALESCE(SUM(
            CASE
                WHEN status = 'active' THEN GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW()))
                ELSE GREATEST(0, duration_seconds)
            END
        ), 0) AS total_seconds FROM support_calls WHERE $where";
        $row = $conn->query($sql);
        if (!$row) {
            return 0;
        }
        $data = $row->fetch_assoc();
        return (int) ($data['total_seconds'] ?? 0);
    }
}

if (!function_exists('support_get_calls_list')) {
    function support_get_calls_list(mysqli $conn, int $support_id, ?string $start_date = null, int $limit = 30): array
    {
        if ($support_id <= 0) {
            return [];
        }
        @support_calls_sync_schema($conn);

        $where = "support_id = $support_id";
        if ($start_date) {
            $esc = $conn->real_escape_string($start_date);
            $where .= " AND started_at >= '$esc'";
        }
        $limit = max(1, min(100, $limit));
        $q = $conn->query("SELECT * FROM support_calls WHERE $where ORDER BY id DESC LIMIT $limit");
        $calls = [];
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $secs = (int) ($row['duration_seconds'] ?? 0);
                if (($row['status'] ?? '') === 'active' && !empty($row['started_at'])) {
                    $started = strtotime((string) $row['started_at']);
                    if ($started > 0) {
                        $secs = max(0, time() - $started);
                    }
                }
                $row['duration_seconds'] = $secs;
                $row['duration_formatted'] = format_activity_duration($secs);
                $calls[] = $row;
            }
        }
        return $calls;
    }
}

if (!function_exists('count_support_sheet_rows')) {
    function count_support_sheet_rows($file_path) {
        if (!file_exists($file_path)) {
            return 0;
        }

        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $count = 0;
            $file = fopen($file_path, 'r');
            if (!$file) {
                return 0;
            }
            $is_first = true;
            while (($line = fgetcsv($file)) !== false) {
                if ($is_first) {
                    $is_first = false;
                    continue;
                }
                foreach ($line as $cell) {
                    if (!empty(trim((string) $cell))) {
                        $count++;
                        break;
                    }
                }
            }
            fclose($file);
            return $count;
        }

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $reader_file = __DIR__ . DIRECTORY_SEPARATOR . 'simple_xlsx_reader.php';
            if (is_readable($reader_file)) {
                require_once $reader_file;
            }
            if (class_exists('SimpleXLSXReader')) {
                try {
                    $reader = new SimpleXLSXReader($file_path);
                    $result = $reader->read();
                    return count($result['data'] ?? []);
                } catch (Throwable $e) {
                    error_log('count_support_sheet_rows: ' . $e->getMessage());
                }
            }
        }

        return 0;
    }
}

if (!function_exists('load_support_sheet_preview')) {
    function load_support_sheet_preview($file_name, $max_rows = 300) {
        $sheet_file = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'support_sheets' . DIRECTORY_SEPARATOR . ltrim((string) $file_name, '/\\');
        $headers = [];
        $data = [];
        $full_count = 0;

        if (!is_readable($sheet_file)) {
            return [
                'sheet_file' => $sheet_file,
                'headers' => $headers,
                'data' => $data,
                'full_count' => 0,
                'ram_truncated' => false,
                'read_error' => 'ملف الشيت غير موجود على السيرفر.',
            ];
        }

        $ext = strtolower(pathinfo($sheet_file, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $sf = fopen($sheet_file, 'r');
            if ($sf) {
                $is_first = true;
                while (($line = fgetcsv($sf)) !== false) {
                    if ($is_first) {
                        $headers = $line;
                        $is_first = false;
                        continue;
                    }
                    $has_data = false;
                    foreach ($line as $cell) {
                        if (!empty(trim((string) $cell))) {
                            $has_data = true;
                            break;
                        }
                    }
                    if ($has_data) {
                        $full_count++;
                        if (count($data) < $max_rows) {
                            $data[] = $line;
                        }
                    }
                }
                fclose($sf);
            }
        } elseif (in_array($ext, ['xlsx', 'xls'], true)) {
            if (!class_exists('ZipArchive')) {
                return [
                    'sheet_file' => $sheet_file,
                    'headers' => $headers,
                    'data' => $data,
                    'full_count' => 0,
                    'ram_truncated' => false,
                    'read_error' => 'امتداد ZipArchive غير مفعّل على السيرفر — فعّله من php.ini لقراءة ملفات Excel.',
                ];
            }
            $reader_file = __DIR__ . DIRECTORY_SEPARATOR . 'simple_xlsx_reader.php';
            if (is_readable($reader_file)) {
                require_once $reader_file;
            }
            if (class_exists('SimpleXLSXReader')) {
                try {
                    $reader = new SimpleXLSXReader($sheet_file);
                    $xlsx = $reader->read();
                    $headers = $xlsx['headers'] ?? [];
                    $data = $xlsx['data'] ?? [];
                    $full_count = count($data);
                    if ($full_count > $max_rows) {
                        $data = array_slice($data, 0, $max_rows);
                    }
                } catch (Throwable $e) {
                    error_log('load_support_sheet_preview: ' . $e->getMessage());
                    return [
                        'sheet_file' => $sheet_file,
                        'headers' => $headers,
                        'data' => $data,
                        'full_count' => 0,
                        'ram_truncated' => false,
                        'read_error' => 'تعذر قراءة ملف Excel.',
                    ];
                }
            }
        }

        return [
            'sheet_file' => $sheet_file,
            'headers' => $headers,
            'data' => $data,
            'full_count' => $full_count > 0 ? $full_count : count($data),
            'ram_truncated' => ($full_count > count($data)),
            'read_error' => null,
        ];
    }
}

if (!function_exists('support_sheet_upload_error_message')) {
    function support_sheet_upload_error_message($code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف أكبر من المسموح على السيرفر.',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف أكبر من المسموح في النموذج.',
            UPLOAD_ERR_PARTIAL => 'تم رفع جزء من الملف فقط. حاول مرة أخرى.',
            UPLOAD_ERR_NO_FILE => 'لم يتم اختيار ملف.',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد التحميل المؤقت غير موجود على السيرفر.',
            UPLOAD_ERR_CANT_WRITE => 'تعذر حفظ الملف على السيرفر.',
            UPLOAD_ERR_EXTENSION => 'امتداد الملف مرفوض من السيرفر.',
        ];
        return $messages[$code] ?? 'فشل رفع الملف.';
    }
}

if (!function_exists('support_sheet_detect_name_phone_cols')) {
    /**
     * @param array $headers
     * @return array [name_col, phone_col, product_col, agent_col, pieces_col, price_col, campaign_col, notes_col, gov_col, address_col]
     */
    function support_sheet_detect_name_phone_cols(array $headers) {
        $cols = [
            'name' => -1, 'phone' => -1, 'product' => -1, 'agent' => -1,
            'pieces' => -1, 'price' => -1, 'campaign' => -1,
            'notes' => -1, 'gov' => -1, 'address' => -1
        ];

        foreach ($headers as $index => $header) {
            $h = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $header), 'UTF-8') : strtolower(trim((string) $header));
            
            if ($cols['name'] === -1 && (strpos($h, 'اسم') !== false || strpos($h, 'عميل') !== false || strpos($h, 'مستلم') !== false || stripos($h, 'name') !== false)) $cols['name'] = (int) $index;
            elseif ($cols['phone'] === -1 && (strpos($h, 'هاتف') !== false || strpos($h, 'رقم') !== false || strpos($h, 'تليفون') !== false || strpos($h, 'موبايل') !== false || stripos($h, 'phone') !== false)) $cols['phone'] = (int) $index;
            elseif ($cols['product'] === -1 && (strpos($h, 'منتج') !== false || strpos($h, 'كود') !== false || stripos($h, 'product') !== false || stripos($h, 'code') !== false)) $cols['product'] = (int) $index;
            elseif ($cols['agent'] === -1 && (strpos($h, 'agent') !== false || strpos($h, 'مسوق') !== false)) $cols['agent'] = (int) $index;
            elseif ($cols['pieces'] === -1 && (strpos($h, 'قطع') !== false || stripos($h, 'pieces') !== false)) $cols['pieces'] = (int) $index;
            elseif ($cols['price'] === -1 && (strpos($h, 'سعر') !== false || stripos($h, 'price') !== false)) $cols['price'] = (int) $index;
            elseif ($cols['campaign'] === -1 && (stripos($h, 'campaign') !== false || strpos($h, 'حملة') !== false)) $cols['campaign'] = (int) $index;
            elseif ($cols['notes'] === -1 && strpos($h, 'ملاحظات') !== false && strpos($h, 'شحن') === false) $cols['notes'] = (int) $index;
            elseif ($cols['gov'] === -1 && (strpos($h, 'محافظة') !== false || stripos($h, 'gov') !== false)) $cols['gov'] = (int) $index;
            elseif ($cols['address'] === -1 && (strpos($h, 'عنوان') !== false || stripos($h, 'address') !== false)) $cols['address'] = (int) $index;
        }
        if ($cols['name'] === -1) $cols['name'] = 0;
        if ($cols['phone'] === -1) $cols['phone'] = 1;

        return array_values($cols);
    }
}

if (!function_exists('import_support_sheet_orders_to_db')) {
    /**
     * يستورد كل صفوف الشيت إلى support_orders فور الرفع (حتى بدون تعديل حالات).
     * @return int عدد الصفوف المستوردة
     */
    function import_support_sheet_orders_to_db($conn, $sheet_id, $support_id, $file_name) {
        $sheet_id = (int) $sheet_id;
        $support_id = (int) $support_id;
        if (!is_object($conn) || $sheet_id <= 0 || $support_id <= 0 || $file_name === '') {
            return 0;
        }

        if (function_exists('support_orders_sync_schema')) {
            @support_orders_sync_schema($conn);
        }

        $existing_phones = [];
        $res = $conn->query("SELECT phone FROM support_orders WHERE sheet_id = $sheet_id");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $clean_phone = preg_replace('/[^0-9]/', '', $row['phone']);
                if ($clean_phone) {
                    $existing_phones[$clean_phone] = true;
                }
            }
        }

        if (!function_exists('load_support_sheet_preview')) {
            return 0;
        }
        $preview = load_support_sheet_preview($file_name, 20000);
        $headers = $preview['headers'] ?? [];
        $data = $preview['data'] ?? [];
        if (!is_array($data) || count($data) === 0) {
            return 0;
        }

        list($name_col, $phone_col, $product_col, $agent_col, $pieces_col, $price_col, $campaign_col, $notes_col, $gov_col, $address_col) = support_sheet_detect_name_phone_cols(is_array($headers) ? $headers : []);

        $support_name = 'support';
        $sn = $conn->query("SELECT fullname, username FROM admins WHERE id = $support_id LIMIT 1");
        if ($sn && ($srow = $sn->fetch_assoc())) {
            $support_name = trim((string) ($srow['fullname'] ?? ''));
            if ($support_name === '') {
                $support_name = trim((string) ($srow['username'] ?? 'support'));
            }
        }

        $agent_code = function_exists('get_support_agent_code')
            ? get_support_agent_code($conn, $support_id)
            : '';

        $order_date = date('Y-m-d');
        $product_code = 'Etala001';
        $unit_price = 150.0;
        $total_price = 150.0;
        $snap_product_cost = 0.0;
        $snap_bundle_cost = 0.0;
        $snap_dom_shipping = 0.0;
        if (function_exists('resolve_support_order_pricing') && function_exists('get_support_sheet_month')) {
            $month = get_support_sheet_month($conn, $sheet_id);
            $pricing = resolve_support_order_pricing($conn, 1, $product_code, $month);
            $unit_price = (float) ($pricing['unit_price'] ?? 150.0);
            $total_price = (float) ($pricing['total_price'] ?? 150.0);
            $snap_product_cost = (float) ($pricing['snap_product_cost'] ?? 0.0);
            $snap_bundle_cost = (float) ($pricing['snap_bundle_cost'] ?? 0.0);
            $snap_dom_shipping = (float) ($pricing['snap_dom_shipping'] ?? 0.0);
        }

        $stmt = $conn->prepare("INSERT INTO support_orders
            (support_id, sheet_id, sheet_row_index, support_name, product_code,
            order_date, recipient_name, customer_name, phone,
            order_status, notes, governorate, address,
            pieces, quantity, unit_price, total_price,
            customer_status, agent_code, marketing_agent, campaign, version, article,
            bundle_type, snap_product_cost, snap_bundle_cost, snap_dom_shipping, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, '', '', 'single', ?, ?, ?, NOW())");
        if (!$stmt) {
            error_log('import_support_sheet_orders_to_db prepare: ' . $conn->error);
            return 0;
        }

        $imported = 0;
        foreach ($data as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row[$name_col] ?? ''));
            $phone = trim((string) ($row[$phone_col] ?? ''));
            if ($name === '' && $phone === '') {
                continue;
            }

            $clean_phone = preg_replace('/[^0-9]/', '', $phone);
            if ($clean_phone && isset($existing_phones[$clean_phone])) {
                continue;
            }

            $row_product_code = 'Etala001';
            if ($product_col !== -1 && isset($row[$product_col]) && trim((string)$row[$product_col]) !== '') {
                $row_product_code = trim((string)$row[$product_col]);
            }

            $marketing_agent = '';
            if ($agent_col !== -1 && isset($row[$agent_col])) {
                $marketing_agent = trim((string)$row[$agent_col]);
            }

            $row_pieces = 1;
            if ($pieces_col !== -1 && isset($row[$pieces_col]) && is_numeric(trim((string)$row[$pieces_col]))) {
                $row_pieces = (int) trim((string)$row[$pieces_col]);
                if ($row_pieces <= 0) $row_pieces = 1;
            }

            $row_total_price = $total_price;
            if ($price_col !== -1 && isset($row[$price_col]) && is_numeric(trim((string)$row[$price_col]))) {
                $row_total_price = (float) trim((string)$row[$price_col]);
            }
            $row_unit_price = $row_total_price / $row_pieces;

            $row_campaign = '';
            if ($campaign_col !== -1 && isset($row[$campaign_col])) {
                $row_campaign = trim((string)$row[$campaign_col]);
            }

            $row_notes = '';
            if ($notes_col !== -1 && isset($row[$notes_col])) {
                $row_notes = trim((string)$row[$notes_col]);
            }

            $row_gov = '';
            if ($gov_col !== -1 && isset($row[$gov_col])) {
                $row_gov = trim((string)$row[$gov_col]);
            }

            $row_address = '';
            if ($address_col !== -1 && isset($row[$address_col])) {
                $row_address = trim((string)$row[$address_col]);
            }

            $idx = (int) $i;
            $stmt->bind_param(
                'iiisssssssssiiddsssddd',
                $support_id,
                $sheet_id,
                $idx,
                $support_name,
                $row_product_code,
                $order_date,
                $name,
                $name,
                $phone,
                $row_notes,
                $row_gov,
                $row_address,
                $row_pieces,
                $row_pieces,
                $row_unit_price,
                $row_total_price,
                $agent_code,
                $marketing_agent,
                $row_campaign,
                $snap_product_cost,
                $snap_bundle_cost,
                $snap_dom_shipping
            );
            if ($stmt->execute()) {
                $imported++;
                
                // الخصم من المخزون الرئيسي
                if (function_exists('main_inventory_deduct')) {
                    main_inventory_deduct($conn, $row_product_code, $row_pieces);
                }

                if ($clean_phone) {
                    $existing_phones[$clean_phone] = true;
                }
            }
        }
        $stmt->close();

        if ($imported > 0 && function_exists('sync_support_stats')) {
            @sync_support_stats($conn, $support_id);
        }

        return $imported;
    }
}

if (!function_exists('accounts_delivered_statuses_sql')) {
    function accounts_delivered_statuses_sql() {
        return "'received', 'delivered', 'تم التوصيل', 'مكتمل', 'مسلم', 'تم الاستلام'";
    }
}

if (!function_exists('accounts_confirmed_statuses_sql')) {
    function accounts_confirmed_statuses_sql() {
        return "'confirmed', 'order_confirmed', 'ready_to_ship', 'received', 'delivered', 'تم التأكيد', 'تم التوصيل', 'مكتمل', 'محصل'";
    }
}

if (!function_exists('accounts_cancelled_statuses_sql')) {
    function accounts_cancelled_statuses_sql() {
        return "'cancelled', 'canceled', 'order_cancelled', 'ملغي', 'مرفوض', 'تم الإلغاء', 'قيد الإلغاء'";
    }
}

if (!function_exists('accounts_returned_statuses_sql')) {
    function accounts_returned_statuses_sql() {
        return "'returned', 'مرتجع', 'مسترجع', 'تم الاسترجاع'";
    }
}

if (!function_exists('get_support_status_details')) {
    function get_support_status_details($status) {
        $map = [
            'pending' => ['ar' => 'قيد الانتظار', 'color' => '#94a3b8'],
            'confirmed' => ['ar' => 'تم التأكيد', 'color' => '#4f46e5'],
            'order_confirmed' => ['ar' => 'تم التأكيد', 'color' => '#4f46e5'],
            'تم التأكيد' => ['ar' => 'تم التأكيد', 'color' => '#4f46e5'],
            'ready_to_ship' => ['ar' => 'جاهز للشحن', 'color' => '#3b82f6'],
            'delivered' => ['ar' => 'تم التسليم', 'color' => '#10b981'],
            'received' => ['ar' => 'تم التسليم', 'color' => '#10b981'],
            'تم التوصيل' => ['ar' => 'تم التسليم', 'color' => '#10b981'],
            'مكتمل' => ['ar' => 'تم التسليم', 'color' => '#10b981'],
            'محصل' => ['ar' => 'تم التسليم', 'color' => '#10b981'],
            'cancelled' => ['ar' => 'ملغي', 'color' => '#e11d48'],
            'canceled' => ['ar' => 'ملغي', 'color' => '#e11d48'],
            'order_cancelled' => ['ar' => 'ملغي', 'color' => '#991b1b'],
            'ملغي' => ['ar' => 'ملغي', 'color' => '#e11d48'],
            'مرفوض' => ['ar' => 'مرفوض', 'color' => '#e11d48'],
            'تم الإلغاء' => ['ar' => 'ملغي', 'color' => '#e11d48'],
            'returned' => ['ar' => 'مرتجع', 'color' => '#f59e0b'],
            'مرتجع' => ['ar' => 'مرتجع', 'color' => '#f59e0b'],
            'مسترجع' => ['ar' => 'مرتجع', 'color' => '#f59e0b'],
            'no_answer_1' => ['ar' => 'عدم رد 1', 'color' => '#fef08a'],
            'no_answer_2' => ['ar' => 'عدم رد 2', 'color' => '#fde047'],
            'no_answer_3' => ['ar' => 'عدم رد 3', 'color' => '#facc15'],
            'contact_later' => ['ar' => 'اتصال لاحقاً', 'color' => '#f3f4f6'],
            'wrong_number' => ['ar' => 'رقم خاطئ', 'color' => '#475569'],
        ];
        return $map[$status] ?? ['ar' => $status ?: 'غير محدد', 'color' => '#cbd5e1'];
    }
}

if (!function_exists('support_orders_period_sql')) {
    function support_orders_period_sql($start_date, $end_date, $alias = 'o') {
        $start_date = addslashes($start_date);
        $end_date = addslashes($end_date);
        return "DATE(COALESCE(s.created_at, {$alias}.created_at)) BETWEEN '$start_date' AND '$end_date'";
    }
}

if (!function_exists('get_support_sheet_leads_total')) {
    function get_support_sheet_leads_total($conn, $start_date, $end_date) {
        if (!is_object($conn)) {
            return 0;
        }
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        $res = @$conn->query("SELECT COALESCE(SUM(names_count), 0) as total FROM support_sheets WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'");
        if (!$res) {
            return 0;
        }
        $row = $res->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('get_accounts_period_dates')) {
    function get_accounts_period_dates($range, $month, $year) {
        $range = $range === 'year' ? 'year' : 'month';
        if ($range === 'year') {
            $y = preg_match('/^\d{4}$/', (string) $year) ? (string) $year : date('Y');
            return [$y . '-01-01', $y . '-12-31', $y, 'year'];
        }
        $m = preg_match('/^\d{4}-\d{2}$/', (string) $month) ? (string) $month : date('Y-m');
        return [
            date('Y-m-01', strtotime($m . '-01')),
            date('Y-m-t', strtotime($m . '-01')),
            substr($m, 0, 4),
            'month',
        ];
    }
}

if (!function_exists('migrate_legacy_product_monthly')) {
    function migrate_legacy_product_monthly($conn) {
        $legacy = $conn->query("SELECT * FROM support_monthly_expenses");
        if (!$legacy) {
            return;
        }
        while ($row = $legacy->fetch_assoc()) {
            $month = $conn->real_escape_string($row['month']);
            $code = 'Etala001';
            $check = $conn->query("SELECT id FROM support_product_monthly WHERE month = '$month' AND product_code = '$code' LIMIT 1");
            if ($check && $check->num_rows > 0) {
                continue;
            }
            $name = $conn->real_escape_string('المنتج الافتراضي');
            $conn->query("INSERT INTO support_product_monthly
                (month, product_code, product_name, stock_quantity, product_sale_price, product_cost, intl_shipping, dom_shipping, ops_cost)
                VALUES (
                    '$month', '$code', '$name',
                    " . (int) ($row['stock_quantity'] ?? 0) . ",
                    " . (float) ($row['product_sale_price'] ?? 0) . ",
                    " . (float) ($row['product_cost'] ?? 0) . ",
                    " . (float) ($row['intl_shipping'] ?? 0) . ",
                    " . (float) ($row['dom_shipping'] ?? 0) . ",
                    " . (float) ($row['ops_cost'] ?? 0) . "
                )");
        }
    }
}

if (!function_exists('get_support_product_codes_list')) {
    function get_support_product_codes_list($conn) {
        $codes = [];
        $sql = "SELECT DISTINCT product_code FROM shipping_inventory_products WHERE status = 'active' ORDER BY product_code ASC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $code = trim((string) ($row['product_code'] ?? ''));
                if ($code !== '' && !in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
        }
        if (empty($codes)) {
            $codes[] = 'Etala001';
        }
        return $codes;
    }
}

if (!function_exists('get_support_products_for_month')) {
  /**
   * @return array<int, array{product_code:string, product_name:string, product_sale_price:float, bundle_sale_price:float}>
   */
    function get_support_products_for_month(mysqli $conn, string $month): array
    {
        if (function_exists('support_product_monthly_sync_schema')) {
            @support_product_monthly_sync_schema($conn);
        }
        $month = trim($month) !== '' ? trim($month) : date('Y-m');
        $esc = $conn->real_escape_string($month);
        $products = [];
        $q = $conn->query("SELECT product_code, product_name, product_sale_price, bundle_sale_price
            FROM support_product_monthly
            WHERE month = '$esc'
            ORDER BY product_code ASC");
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $code = trim((string) ($row['product_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $products[] = [
                    'product_code' => $code,
                    'product_name' => trim((string) ($row['product_name'] ?? $code)),
                    'product_sale_price' => (float) ($row['product_sale_price'] ?? 0),
                    'bundle_sale_price' => (float) ($row['bundle_sale_price'] ?? 0),
                ];
            }
        }
        if (empty($products)) {
            foreach (get_support_product_codes_list($conn) as $code) {
                $row = get_product_monthly_row($conn, $month, $code);
                $single = (float) ($row['product_sale_price'] ?? ($row['sale_price'] ?? 150));
                $bundle = (float) ($row['bundle_sale_price'] ?? 0);
                if ($bundle <= 0) {
                    $bundle = $single * 3;
                }
                $products[] = [
                    'product_code' => $code,
                    'product_name' => trim((string) ($row['product_name'] ?? $code)),
                    'product_sale_price' => $single,
                    'bundle_sale_price' => $bundle,
                ];
            }
        }
        if (empty($products)) {
            $products[] = [
                'product_code' => 'Etala001',
                'product_name' => 'المنتج الافتراضي',
                'product_sale_price' => 150.0,
                'bundle_sale_price' => 285.0,
            ];
        }
        return $products;
    }
}

if (!function_exists('get_product_monthly_row')) {
    function get_product_monthly_row($conn, $month, $product_code) {
        $month = $conn->real_escape_string($month);
        $product_code = $conn->real_escape_string($product_code);
        $row = $conn->query("SELECT * FROM support_product_monthly WHERE month = '$month' AND product_code = '$product_code' LIMIT 1");
        if ($row && $row->num_rows > 0) {
            return $row->fetch_assoc();
        }
        if ($product_code === 'Etala001') {
            $legacy = $conn->query("SELECT * FROM support_monthly_expenses WHERE month = '$month' LIMIT 1");
            if ($legacy && $legacy->num_rows > 0) {
                $old = $legacy->fetch_assoc();
                return [
                    'month' => $month,
                    'product_code' => 'Etala001',
                    'product_name' => 'المنتج الافتراضي',
                    'stock_quantity' => (int) ($old['stock_quantity'] ?? 0),
                    'product_sale_price' => (float) ($old['product_sale_price'] ?? 0),
                    'bundle_sale_price' => (float) ($old['product_sale_price'] ?? 0) * 3,
                    'product_cost' => (float) ($old['product_cost'] ?? 0),
                    'bundle_cost' => (float) ($old['product_cost'] ?? 0) * 3,
                    'intl_shipping' => (float) ($old['intl_shipping'] ?? 0),
                    'dom_shipping' => (float) ($old['dom_shipping'] ?? 0),
                    'ops_cost' => (float) ($old['ops_cost'] ?? 0),
                ];
            }
        }
        return null;
    }
}

if (!function_exists('support_product_monthly_sync_schema')) {
    function support_product_monthly_sync_schema(mysqli $conn): ?string
    {
        $table = $conn->query("SHOW TABLES LIKE 'support_product_monthly'");
        if (!$table || $table->num_rows === 0) {
            return null;
        }

        $res = $conn->query('SHOW COLUMNS FROM `support_product_monthly`');
        if (!$res) {
            return 'تعذر قراءة أعمدة جدول support_product_monthly.';
        }
        $have = [];
        while ($row = $res->fetch_assoc()) {
            $have[strtolower($row['Field'])] = true;
        }

        $fragments = [];
        if (empty($have['bundle_sale_price'])) {
            $fragments[] = 'ADD COLUMN `bundle_sale_price` DECIMAL(10,2) NOT NULL DEFAULT 0';
        }
        if (empty($have['bundle_cost'])) {
            $fragments[] = 'ADD COLUMN `bundle_cost` DECIMAL(10,2) NOT NULL DEFAULT 0';
        }

        foreach ($fragments as $fragment) {
            if (!$conn->query('ALTER TABLE `support_product_monthly` ' . $fragment)) {
                return $conn->error ?: 'فشل تحديث جدول support_product_monthly.';
            }
        }

        return null;
    }
}

if (!function_exists('get_support_sheet_month')) {
    function get_support_sheet_month(mysqli $conn, int $sheet_id): string
    {
        if ($sheet_id <= 0) {
            return date('Y-m');
        }
        $q = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as m FROM support_sheets WHERE id = $sheet_id LIMIT 1");
        if ($q && ($row = $q->fetch_assoc()) && !empty($row['m'])) {
            return (string) $row['m'];
        }
        return date('Y-m');
    }
}

if (!function_exists('resolve_support_order_pricing')) {
    /**
     * حساب سعر الطلب من إعدادات المنتج الشهرية حسب Single (1 قطعة) أو Bundle (3 قطع).
     *
     * @return array{bundle_type:string,pieces:int,quantity:int,unit_price:float,total_price:float,line_cost:float,single_sale:float,bundle_sale:float}
     */
    function resolve_support_order_pricing($conn, int $pieces, string $product_code = 'Etala001', ?string $month = null): array
    {
        $pieces = max(1, $pieces);
        $bundle_type = ($pieces >= 3) ? 'bundle' : 'single';
        $month = $month ?: date('Y-m');
        $product_code = trim($product_code) !== '' ? trim($product_code) : 'Etala001';

        $row = get_product_monthly_row($conn, $month, $product_code) ?? [];
        $single_sale = (float) ($row['product_sale_price'] ?? 0);
        $bundle_sale = (float) ($row['bundle_sale_price'] ?? 0);
        $single_cost = (float) ($row['product_cost'] ?? 0);
        $bundle_cost = (float) ($row['bundle_cost'] ?? 0);

        if ($single_sale <= 0) {
            $single_sale = 150.0;
        }
        if ($bundle_sale <= 0) {
            $bundle_sale = $single_sale * 3;
        }
        if ($bundle_cost <= 0 && $single_cost > 0) {
            $bundle_cost = $single_cost * 3;
        }

        if ($bundle_type === 'bundle') {
            $quantity = 3;
            $total_price = $bundle_sale;
            $unit_price = round($bundle_sale / 3, 2);
            $line_cost = $bundle_cost > 0 ? $bundle_cost : ($single_cost > 0 ? $single_cost * 3 : 0);
            $pieces_out = 3;
        } else {
            $quantity = 1;
            $pieces_out = 1;
            $unit_price = $single_sale;
            $total_price = $single_sale;
            $line_cost = $single_cost;
        }

        return [
            'bundle_type' => $bundle_type,
            'pieces' => $pieces_out,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $total_price,
            'line_cost' => $line_cost,
            'single_sale' => $single_sale,
            'bundle_sale' => $bundle_sale,
            'snap_product_cost' => $single_cost,
            'snap_bundle_cost' => $bundle_cost,
            'snap_dom_shipping' => (float) ($row['dom_shipping'] ?? 0),
        ];
    }
}

if (!function_exists('accounts_order_bundle_type_sql')) {
    function accounts_order_bundle_type_sql($alias = 'o')
    {
        return "COALESCE(NULLIF({$alias}.bundle_type, ''), IF(IFNULL({$alias}.pieces, 1) >= 3, 'bundle', 'single'))";
    }
}

if (!function_exists('accounts_order_sale_price_sql')) {
    function accounts_order_sale_price_sql($alias = 'o', $exp = 'e')
    {
        $bt = accounts_order_bundle_type_sql($alias);
        return "COALESCE(NULLIF({$alias}.total_price, 0),
            CASE WHEN {$bt} = 'bundle'
                THEN IFNULL(NULLIF({$exp}.bundle_sale_price, 0), IFNULL({$exp}.product_sale_price, 0) * 3)
                ELSE IFNULL({$exp}.product_sale_price, 0)
            END)";
    }
}

if (!function_exists('accounts_order_product_cost_sql')) {
    function accounts_order_product_cost_sql($alias = 'o', $exp = 'e')
    {
        $bt = accounts_order_bundle_type_sql($alias);
        return "CASE WHEN {$bt} = 'bundle'
            THEN COALESCE({$alias}.snap_bundle_cost, NULLIF({$exp}.bundle_cost, 0), IFNULL({$exp}.product_cost, 0) * 3)
            ELSE COALESCE({$alias}.snap_product_cost, {$exp}.product_cost, 0)
        END";
    }
}

if (!function_exists('calculate_support_leads_data')) {
    function calculate_support_leads_data($conn, $start_date, $end_date, $product_code = null, $marketer_code = null) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);

        // 1. Get lead costs for all products (or the specific one)
        $lead_costs = [];
        $leads_filter = "";
        if ($product_code && $product_code !== 'all') {
            $leads_filter .= " AND product_code = '" . $conn->real_escape_string($product_code) . "'";
        }
        if ($marketer_code) {
            $leads_filter .= " AND marketer_code = '" . $conn->real_escape_string($marketer_code) . "'";
        }
        $leads_q = $conn->query("SELECT date, product_code, lead_cost FROM support_daily_leads WHERE date BETWEEN '$start_date' AND '$end_date' $leads_filter");
        if ($leads_q) {
            while ($row = $leads_q->fetch_assoc()) {
                $lead_costs[$row['date']][$row['product_code']] = (float) $row['lead_cost'];
            }
        }

        // 2. Get order counts per day per product
        $order_counts = [];
        $orders_filter = "";
        if ($product_code && $product_code !== 'all') {
            $orders_filter = " AND product_code = '" . $conn->real_escape_string($product_code) . "'";
        }
        $orders_q = $conn->query("SELECT DATE(created_at) as order_date, product_code, COUNT(*) as daily_count FROM support_orders WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' $orders_filter GROUP BY DATE(created_at), product_code");
        if ($orders_q) {
            while ($row = $orders_q->fetch_assoc()) {
                $p_code = $row['product_code'] ?: 'unknown';
                $order_counts[$row['order_date']][$p_code] = (int) $row['daily_count'];
            }
        }

        // 3. Fallback for support_sheets
        $sheet_counts = [];
        $sheet_q = $conn->query("SELECT DATE(created_at) as order_date, SUM(names_count) as daily_count FROM support_sheets WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' GROUP BY DATE(created_at)");
        if ($sheet_q) {
            while ($row = $sheet_q->fetch_assoc()) {
                $sheet_counts[$row['order_date']] = (int) $row['daily_count'];
            }
        }

        $daily_table = [];
        $total_lead_cost = 0;
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day');

        while ($current < $end) {
            $d_str = $current->format('Y-m-d');

            $day_total_cost = 0;
            $day_total_count = 0;
            $day_price_display = 0; // Just for display if it's a single product

            if ($product_code && $product_code !== 'all') {
                $count = max($sheet_counts[$d_str] ?? 0, $order_counts[$d_str][$product_code] ?? 0);
                $price = $lead_costs[$d_str][$product_code] ?? 0;
                $day_total_cost = $price; // Treat lead_cost as total spend
                $day_total_count = $count;
                $day_price_display = $price;
            } else {
                // All products
                $products_today = isset($order_counts[$d_str]) ? array_keys($order_counts[$d_str]) : [];
                $spend_products_today = isset($lead_costs[$d_str]) ? array_keys($lead_costs[$d_str]) : [];
                $all_products = array_unique(array_merge($products_today, $spend_products_today));
                
                foreach ($all_products as $pcode) {
                    $cnt = $order_counts[$d_str][$pcode] ?? 0;
                    $price = $lead_costs[$d_str][$pcode] ?? 0;
                    $day_total_cost += $price; // Treat lead_cost as total spend
                    $day_total_count += $cnt;
                }
                
                // If there are sheets but no orders? (Legacy fallback)
                if ($day_total_count == 0 && isset($sheet_counts[$d_str]) && $sheet_counts[$d_str] > 0) {
                    $cnt = $sheet_counts[$d_str];
                    $price = $lead_costs[$d_str]['all'] ?? 0;
                    $day_total_cost += $price;
                    $day_total_count = $cnt;
                }
                $day_price_display = 0;
            }

            $total_lead_cost += $day_total_cost;
            $daily_table[] = [
                'date' => $d_str,
                'count' => $day_total_count,
                'price' => $day_price_display,
                'cost' => $day_total_cost,
            ];
            $current->modify('+1 day');
        }

        return [
            'daily_table' => array_reverse($daily_table),
            'total_lead_cost' => $total_lead_cost
        ];
    }
}

if (!function_exists('calculate_support_accounts_financials')) {
    function calculate_support_accounts_financials($conn, $start_date, $end_date, $product_code = null, $marketer_code = null) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        $delivered = accounts_delivered_statuses_sql();
        $confirmed = accounts_confirmed_statuses_sql();

        $product_filter = '';
        if ($product_code) {
            $product_filter = " AND COALESCE(NULLIF(o.product_code, ''), 'Etala001') = '" . $conn->real_escape_string($product_code) . "'";
        }
        if ($marketer_code) {
            $product_filter .= " AND o.marketing_agent = '" . $conn->real_escape_string($marketer_code) . "'";
        }

        $period_sql = support_orders_period_sql($start_date, $end_date, 'o');
        $cancelled = accounts_cancelled_statuses_sql();

        $sale_sql = accounts_order_sale_price_sql('o', 'e');
        $cost_sql = accounts_order_product_cost_sql('o', 'e');

        $stats_query = $conn->query("
            SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN o.order_status IN ($confirmed) THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN o.order_status IN ($delivered) THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN o.order_status IN ($cancelled) THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN o.order_status IN ($delivered) THEN ($sale_sql) ELSE 0 END) as total_revenue,
                SUM(CASE WHEN o.order_status IN ($delivered) THEN ($cost_sql) ELSE 0 END) as total_product_cost,
                SUM(CASE WHEN o.order_status IN ($confirmed) THEN COALESCE(o.snap_dom_shipping, e.dom_shipping, 0) ELSE 0 END) as total_dom_shipping
            FROM support_orders o
            LEFT JOIN support_sheets s ON o.sheet_id = s.id
            LEFT JOIN support_product_monthly e
                ON DATE_FORMAT(COALESCE(s.created_at, o.created_at), '%Y-%m') = e.month
                AND e.product_code = COALESCE(NULLIF(o.product_code, ''), 'Etala001')
            WHERE $period_sql
            $product_filter
        ");

        $stats = $stats_query ? $stats_query->fetch_assoc() : [];
        $leads = calculate_support_leads_data($conn, $start_date, $end_date, $product_code, $marketer_code);
        $total_sheet_leads = get_support_sheet_leads_total($conn, $start_date, $end_date);
        $total_orders_saved = (int) ($stats['total_orders'] ?? 0);
        
        if ($marketer_code || $product_code) {
            $total_leads_pool = $total_orders_saved;
        } else {
            $total_leads_pool = $total_sheet_leads > 0 ? $total_sheet_leads : $total_orders_saved;
        }

        $total_proportional_intl = 0;
        $total_proportional_ops = 0;
        $current_ptr = new DateTime($start_date);
        $end_ptr = new DateTime($end_date);
        $end_ptr->modify('+1 day');
        $monthly_cache = [];

        while ($current_ptr < $end_ptr) {
            $m = $current_ptr->format('Y-m');
            $days_in_m = (int) $current_ptr->format('t');

            if (!isset($monthly_cache[$m])) {
                $monthly_cache[$m] = [];
                $res = $conn->query("SELECT product_code, stock_quantity, intl_shipping, ops_cost FROM support_product_monthly WHERE month = '$m'");
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        $monthly_cache[$m][$r['product_code']] = $r;
                    }
                }
                if (empty($monthly_cache[$m])) {
                    $legacy = $conn->query("SELECT stock_quantity, intl_shipping, ops_cost FROM support_monthly_expenses WHERE month = '$m' LIMIT 1");
                    if ($legacy && $legacy->num_rows > 0) {
                        $monthly_cache[$m]['Etala001'] = $legacy->fetch_assoc();
                    }
                }
            }

            foreach ($monthly_cache[$m] as $code => $row) {
                if ($product_code && $code !== $product_code) {
                    continue;
                }
                $total_proportional_intl += ((int) ($row['stock_quantity'] ?? 0) * (float) ($row['intl_shipping'] ?? 0)) / $days_in_m;
                $total_proportional_ops += (float) ($row['ops_cost'] ?? 0) / $days_in_m;
            }

            $current_ptr->modify('+1 day');
        }

        $sum_revenue = (float) ($stats['total_revenue'] ?? 0);
        $sum_product_cost = (float) ($stats['total_product_cost'] ?? 0);
        $sum_dom_shipping = (float) ($stats['total_dom_shipping'] ?? 0);
        $total_lead_cost = (float) $leads['total_lead_cost'];
        
        // حساب البونص للفترة
        $bonuses = calculate_department_bonuses($conn, $start_date, $end_date, $product_code);
        $total_costs = $total_lead_cost + $sum_product_cost + $total_proportional_intl + $sum_dom_shipping + $total_proportional_ops + $bonuses['total'];

        return [
            'total_orders' => $total_orders_saved,
            'total_sheet_leads' => $total_sheet_leads,
            'total_leads_pool' => $total_leads_pool,
            'confirmed_count' => (int) ($stats['confirmed_count'] ?? 0),
            'delivered_count' => (int) ($stats['delivered_count'] ?? 0),
            'cancelled_count' => (int) ($stats['cancelled_count'] ?? 0),
            'sum_revenue' => $sum_revenue,
            'sum_product_cost' => $sum_product_cost,
            'sum_dom_shipping' => $sum_dom_shipping,
            'total_lead_cost' => $total_lead_cost,
            'total_proportional_intl' => $total_proportional_intl,
            'total_proportional_ops' => $total_proportional_ops,
            'bonuses' => $bonuses,
            'total_costs' => $total_costs,
            'net_profit' => $sum_revenue - $total_costs,
            'daily_table' => $leads['daily_table'],
        ];
    }
}

if (!function_exists('calculate_support_marketing_metrics')) {
    function calculate_support_marketing_metrics($conn, $start_date, $end_date, $product_code = null) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        $delivered = accounts_delivered_statuses_sql();
        $confirmed = accounts_confirmed_statuses_sql();
        $cancelled = accounts_cancelled_statuses_sql();
        $returned = accounts_returned_statuses_sql();
        $period_sql = support_orders_period_sql($start_date, $end_date, 'o');

        $product_filter = '';
        if ($product_code) {
            $product_filter = " AND COALESCE(NULLIF(o.product_code, ''), 'Etala001') = '" . $conn->real_escape_string($product_code) . "'";
        }

        $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_code);
        $total_leads = $fin['total_leads_pool'];
        $confirmed_count = $fin['confirmed_count'];
        $delivered_count = $fin['delivered_count'];
        $cancelled_count = $fin['cancelled_count'];
        $total_lead_cost = $fin['total_lead_cost'];

        $returned_count = 0;
        $returned_q = $conn->query("
            SELECT COUNT(*) as cnt
            FROM support_orders o
            LEFT JOIN support_sheets s ON o.sheet_id = s.id
            WHERE $period_sql $product_filter AND o.order_status IN ($returned)
        ");
        if ($returned_q) {
            $returned_count = (int) ($returned_q->fetch_assoc()['cnt'] ?? 0);
        }

        $status_data = [];
        $status_q = $conn->query("
            SELECT o.order_status, COUNT(*) as count
            FROM support_orders o
            LEFT JOIN support_sheets s ON o.sheet_id = s.id
            WHERE $period_sql $product_filter
            GROUP BY o.order_status
        ");
        if ($status_q) {
            while ($row = $status_q->fetch_assoc()) {
                $st = $row['order_status'] ?? '';
                $cnt = (int) $row['count'];
                $details = get_support_status_details($st);
                $status_data[] = [
                    'original' => $st,
                    'ar' => $details['ar'],
                    'color' => $details['color'],
                    'count' => $cnt,
                ];
            }
        }

        $confirmation_rate = $total_leads > 0 ? round(($confirmed_count / $total_leads) * 100, 1) : 0;
        $delivery_from_confirmed = $confirmed_count > 0 ? round(($delivered_count / $confirmed_count) * 100, 1) : 0;
        $cancellation_rate = $total_leads > 0 ? round(($cancelled_count / $total_leads) * 100, 1) : 0;
        $return_rate = $total_leads > 0 ? round(($returned_count / $total_leads) * 100, 1) : 0;
        $cost_per_lead = $total_leads > 0 ? $total_lead_cost / $total_leads : 0;
        $cost_per_order = $confirmed_count > 0 ? $total_lead_cost / $confirmed_count : 0;

        return [
            'total_leads' => $total_leads,
            'total_sheet_leads' => $fin['total_sheet_leads'],
            'total_orders_saved' => $fin['total_orders'],
            'confirmed_count' => $confirmed_count,
            'delivered_count' => $delivered_count,
            'cancelled_count' => $cancelled_count,
            'returned_count' => $returned_count,
            'confirmation_rate' => $confirmation_rate,
            'delivery_from_confirmed' => $delivery_from_confirmed,
            'cancellation_rate' => $cancellation_rate,
            'return_rate' => $return_rate,
            'cost_per_lead' => $cost_per_lead,
            'cost_per_order' => $cost_per_order,
            'total_lead_cost' => $total_lead_cost,
            'status_data' => $status_data,
        ];
    }
}

if (!function_exists('calculate_creative_performance')) {
    function calculate_creative_performance($conn, $start_date, $end_date, $marketer_code = null) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        $period_sql = support_orders_period_sql($start_date, $end_date, 'o');
        $delivered = accounts_delivered_statuses_sql();
        $confirmed = accounts_confirmed_statuses_sql();

        $marketer_filter = '';
        if ($marketer_code) {
            $marketer_filter = " AND o.marketing_agent = '" . $conn->real_escape_string($marketer_code) . "'";
        }

        // Get all unique product codes active in this period (from orders or leads)
        $products = [];
        $res = $conn->query("
            SELECT DISTINCT COALESCE(NULLIF(o.product_code, ''), 'Etala001') as code 
            FROM support_orders o 
            LEFT JOIN support_sheets s ON o.sheet_id = s.id 
            WHERE $period_sql $marketer_filter
            UNION 
            SELECT DISTINCT product_code as code 
            FROM support_daily_leads 
            WHERE date BETWEEN '$start_date' AND '$end_date'
        ");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['code']) && $row['code'] !== 'all') {
                    $products[$row['code']] = true;
                }
            }
        }
        
        $creative_data = [];
        foreach (array_keys($products) as $pcode) {
            $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $pcode, $marketer_code);
            $confirmed_count = (int) ($fin['confirmed_count'] ?? 0);
            $delivered_count = (int) ($fin['delivered_count'] ?? 0);
            $spend = (float) ($fin['total_lead_cost'] ?? 0);
            
            // Get Raw (Total Orders) directly from support_orders (landing page sheets)
            $raw = 0;
            $raw_q = $conn->query("
                SELECT COUNT(*) as c
                FROM support_orders o
                LEFT JOIN support_sheets s ON o.sheet_id = s.id
                WHERE $period_sql $marketer_filter
                AND COALESCE(NULLIF(o.product_code, ''), 'Etala001') = '" . $conn->real_escape_string($pcode) . "'
            ");
            if ($raw_q) {
                $raw = (int) $raw_q->fetch_assoc()['c'];
            }
            
            // If filtering by marketer, only include products that actually have stats for them
            if ($marketer_code && $raw == 0 && $spend == 0) {
                continue;
            }
            
            // Get Bundle Count for this product (from Delivered only to match Bundle%)
            $bundle_count = 0;
            $bundle_q = $conn->query("
                SELECT COUNT(*) as c
                FROM support_orders o
                LEFT JOIN support_sheets s ON o.sheet_id = s.id
                WHERE $period_sql $marketer_filter
                AND COALESCE(NULLIF(o.product_code, ''), 'Etala001') = '" . $conn->real_escape_string($pcode) . "'
                AND IFNULL(o.pieces, 1) > 1
                AND o.order_status IN ($delivered)
            ");
            if ($bundle_q) {
                $bundle_count = (int) $bundle_q->fetch_assoc()['c'];
            }
            
            // Get Creative Link from products table (fallback by name if no exact code match)
            $creative_link = '';
            $link_q = $conn->query("SELECT creative_link FROM products WHERE code = '" . $conn->real_escape_string($pcode) . "' OR name = '" . $conn->real_escape_string($pcode) . "' LIMIT 1");
            if ($link_q && $link_q->num_rows > 0) {
                $creative_link = $link_q->fetch_assoc()['creative_link'] ?? '';
            }

            if ($raw > 0 || $spend > 0) {
                $conf_rate = $raw > 0 ? round(($confirmed_count / $raw) * 100, 1) : 0;
                $bundle_rate = $delivered_count > 0 ? round(($bundle_count / $delivered_count) * 100, 1) : 0; // Bundle% from delivered
                
                $cpl = $raw > 0 ? round($spend / $raw, 2) : 0;
                $cpo = $confirmed_count > 0 ? round($spend / $confirmed_count, 2) : 0;
                $cpd = $delivered_count > 0 ? round($spend / $delivered_count, 2) : 0;
                
                // Signal logic based on marketing bonus
                $signal = 'gray'; // default
                if ($raw > 0 || $spend > 0) {
                    if ($cpl <= 50 || $cpo <= 200) {
                        $signal = 'green';
                    } elseif ($cpd <= 400) {
                        $signal = 'yellow';
                    } else {
                        $signal = 'red';
                    }
                }
                
                $creative_data[] = [
                    'source_code' => $pcode,
                    'raw' => $raw,
                    'confirmed' => $confirmed_count,
                    'conf_percent' => $conf_rate,
                    'delivered' => $delivered_count,
                    'bundle_percent' => $bundle_rate,
                    'spend' => $spend,
                    'cpo' => $cpo,
                    'cpd' => $cpd,
                    'signal' => $signal,
                    'creative_link' => $creative_link
                ];
            }
        }
        
        // Sort by spend DESC
        usort($creative_data, function($a, $b) {
            return $b['spend'] <=> $a['spend'];
        });
        
        return $creative_data;
    }
}

if (!function_exists('get_product_lead_cost_share')) {
    /**
     * تخصيص تكلفة الليدات لمنتج خلال فترة (نسبة طلبات المنتدلن إجمالي الليدات).
     *
     * @return array{lead_cost:float,leads_count:float,cost_per_lead:float}
     */
    function get_product_lead_cost_share($conn, $start_date, $end_date, $product_code) {
        $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_code);
        $total_lead_cost = (float) ($fin['total_lead_cost'] ?? 0);
        $total_leads_pool = (int) ($fin['total_leads_pool'] ?? 0);
        $cost_per_lead = $total_leads_pool > 0 ? $total_lead_cost / $total_leads_pool : 0;

        return [
            'lead_cost' => $total_lead_cost,
            'leads_count' => $total_leads_pool,
            'cost_per_lead' => $cost_per_lead,
        ];
    }
}

if (!function_exists('calculate_inventory_standing_cost')) {
    function calculate_inventory_standing_cost($conn, $month, $product_code = null, $start_date = null, $end_date = null) {
        $month = $conn->real_escape_string($month);
        $items = [];
        $total = 0;
        $total_base = 0;
        $total_lead_alloc = 0;

        if ($product_code) {
            $codes = [$product_code];
        } else {
            $codes = get_support_product_codes_list($conn);
        }

        foreach ($codes as $code) {
            $row = get_product_monthly_row($conn, $month, $code);
            if (!$row) {
                continue;
            }
            $stock = (int) ($row['stock_quantity'] ?? 0);
            $unit_base = (float) ($row['product_cost'] ?? 0) + (float) ($row['intl_shipping'] ?? 0);
            $inventory_base = $stock * $unit_base;

            $lead_alloc = ['lead_cost' => 0.0, 'leads_count' => 0.0, 'cost_per_lead' => 0.0];
            if ($start_date && $end_date) {
                $lead_alloc = get_product_lead_cost_share($conn, $start_date, $end_date, $code);
            }

            $lead_cost_allocated = (float) $lead_alloc['lead_cost'];
            $standing = $inventory_base + $lead_cost_allocated;

            $items[] = [
                'product_code' => $code,
                'product_name' => $row['product_name'] ?? $code,
                'stock_quantity' => $stock,
                'unit_cost' => $unit_base,
                'inventory_base' => $inventory_base,
                'lead_cost_allocated' => $lead_cost_allocated,
                'leads_count_allocated' => (float) $lead_alloc['leads_count'],
                'cost_per_lead' => (float) $lead_alloc['cost_per_lead'],
                'standing_cost' => $standing,
            ];
            $total += $standing;
            $total_base += $inventory_base;
            $total_lead_alloc += $lead_cost_allocated;
        }

        return [
            'items' => $items,
            'total' => $total,
            'total_base' => $total_base,
            'total_lead_alloc' => $total_lead_alloc,
        ];
    }
}

if (!function_exists('get_accounts_product_breakdown')) {
    function get_accounts_product_breakdown($conn, $start_date, $end_date) {
        $breakdown = [];
        $inv_month = substr($end_date, 0, 7);
        foreach (get_support_product_codes_list($conn) as $code) {
            $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $code);
            $inv = calculate_inventory_standing_cost($conn, $inv_month, $code, $start_date, $end_date);
            $breakdown[] = array_merge(['product_code' => $code], $fin, [
                'inventory_standing' => $inv['total'],
                'inventory_base' => $inv['total_base'],
                'inventory_lead_alloc' => $inv['total_lead_alloc'],
            ]);
        }
        return $breakdown;
    }
}

if (!function_exists('calculate_shipping_collection_balance')) {
    /**
     * مستحق التحصيل من شركات الشحن = سعر بيع الأوردرات المسلّمة − الشحن الداخلي (للمسلّم).
     * يستخدم نفس ربط إعدادات المنتج الشهري المستخدم في حسابات الأرباح.
     *
     * @return array{delivered_count:int,total_sale:float,total_dom_shipping:float,creditor_due:float,debtor_received:float,remaining:float}
     */
    function calculate_shipping_collection_balance($conn, $start_date = null, $end_date = null, $product_code = null, $sc_id_filter = null) {
        $delivered = accounts_delivered_statuses_sql();
        $start_date = $start_date ? $conn->real_escape_string($start_date) : null;
        $end_date = $end_date ? $conn->real_escape_string($end_date) : null;

        $period_filter = '';
        if ($start_date && $end_date) {
            $period_filter = ' AND ' . support_orders_period_sql($start_date, $end_date, 'o');
        }

        $product_filter = '';
        if ($product_code) {
            $product_filter = " AND COALESCE(NULLIF(o.product_code, ''), 'Etala001') = '" . $conn->real_escape_string($product_code) . "'";
        }

        $sc_filter = '';
        if ($sc_id_filter !== null) {
            $sc_id = (int)$sc_id_filter;
            $sc_filter = " AND (o.shipping_company_id = $sc_id OR o.shipping_rep_id IN (SELECT id FROM shipping_company_reps WHERE company_id = $sc_id))";
        }

        $sale_sql = accounts_order_sale_price_sql('o', 'e');

        $row = $conn->query("
            SELECT
                SUM(CASE WHEN o.order_status IN ($delivered) THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN o.order_status IN ($delivered) THEN ($sale_sql) ELSE 0 END) as total_sale,
                SUM(CASE WHEN o.order_status IN ($delivered) THEN COALESCE(o.snap_dom_shipping, e.dom_shipping, 0) ELSE 0 END) as total_dom_shipping
            FROM support_orders o
            LEFT JOIN support_sheets s ON o.sheet_id = s.id
            LEFT JOIN support_product_monthly e
                ON DATE_FORMAT(COALESCE(s.created_at, o.created_at), '%Y-%m') = e.month
                AND e.product_code = COALESCE(NULLIF(o.product_code, ''), 'Etala001')
            WHERE 1=1 $period_filter $product_filter $sc_filter
        ");
        $stats = $row ? $row->fetch_assoc() : [];

        $total_sale = (float) ($stats['total_sale'] ?? 0);
        $total_dom = (float) ($stats['total_dom_shipping'] ?? 0);
        $creditor_due = $total_sale - $total_dom;

        $debtor_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM support_financial_records WHERE type = 'debtor'";
        if ($sc_id_filter !== null) {
            $debtor_sql .= " AND shipping_company_id = " . (int)$sc_id_filter;
        }
        if ($start_date && $end_date) {
            // الربط بشهر الاستحقاق (period_month) وليس تاريخ الاستلام الفعلي
            $has_pm = function_exists('ensure_debts_schema') ? ensure_debts_schema($conn) : false;
            $pm = debts_period_month_sql('', $has_pm);
            $start_m = substr($start_date, 0, 7);
            $end_m = substr($end_date, 0, 7);
            $debtor_sql .= " AND $pm BETWEEN '" . $conn->real_escape_string($start_m) . "' AND '" . $conn->real_escape_string($end_m) . "'";
        }
        $debtor_row = @$conn->query($debtor_sql);
        $debtor_received = $debtor_row ? (float) ($debtor_row->fetch_assoc()['total'] ?? 0) : 0;

        return [
            'delivered_count' => (int) ($stats['delivered_count'] ?? 0),
            'total_sale' => $total_sale,
            'total_dom_shipping' => $total_dom,
            'creditor_due' => $creditor_due,
            'debtor_received' => $debtor_received,
            'remaining' => $creditor_due - $debtor_received,
        ];
    }
}

if (!function_exists('get_accounts_reconciliation_summary')) {
    /**
     * يربط ربح الحسابات بمستحق كشف الشحن لنفس الفترة.
     */
    function get_accounts_reconciliation_summary($conn, $start_date, $end_date, $product_code = null, $sc_id_filter = null) {
        $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_code);
        $ship = calculate_shipping_collection_balance($conn, $start_date, $end_date, $product_code, $sc_id_filter);

        $other_costs = $fin['total_costs'] - $fin['sum_dom_shipping'];
        $gap = $ship['creditor_due'] - $fin['net_profit'];

        return array_merge($fin, $ship, [
            'other_costs_excl_dom' => $other_costs,
            'shipping_vs_profit_gap' => $gap,
        ]);
    }
}

if (!function_exists('get_accounts_monthly_breakdown')) {
    function get_accounts_monthly_breakdown($conn, $year, $product_code = null) {
        $year = preg_match('/^\d{4}$/', (string) $year) ? (string) $year : date('Y');
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $month = sprintf('%s-%02d', $year, $m);
            [$start_date, $end_date] = get_accounts_period_dates('month', $month, $year);
            $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_code);
            $inv = calculate_inventory_standing_cost($conn, $month, $product_code, $start_date, $end_date);
            $rows[] = array_merge([
                'month' => $month,
                'month_label' => $month,
            ], $fin, [
                'inventory_standing' => $inv['total'],
                'inventory_base' => $inv['total_base'],
                'inventory_lead_alloc' => $inv['total_lead_alloc'],
            ]);
        }
        return $rows;
    }
}

// Trigger SFTP upload 2

if (!function_exists('send_notification')) {
    function send_notification($conn, $recipient_type, $recipient_id, $title, $message, $link = '') {
        $type = $conn->real_escape_string($recipient_type);
        $id = (int)$recipient_id;
        $t = $conn->real_escape_string($title);
        $m = $conn->real_escape_string($message);
        $l = $conn->real_escape_string($link);
        return $conn->query("INSERT INTO system_notifications (recipient_type, recipient_id, title, message, link) VALUES ('$type', $id, '$t', '$m', '$l')");
    }
}

if (!function_exists('shipping_inventory_deduct')) {
    function shipping_inventory_deduct($conn, $company_id, $product_code, $pieces = 1) {
        $company_id = (int)$company_id;
        $product_code = $conn->real_escape_string($product_code);
        $pieces = (int)$pieces;
        if ($pieces <= 0) return true;

        $res = $conn->query("SELECT quantity FROM shipping_inventory WHERE shipping_company_id = $company_id AND product_code = '$product_code'");
        
        $shortfall = 0;
        if (!$res || $res->num_rows === 0) {
            $conn->query("INSERT INTO shipping_inventory (shipping_company_id, product_code, quantity) VALUES ($company_id, '$product_code', -$pieces)");
            $shortfall = $pieces;
        } else {
            $row = $res->fetch_assoc();
            $old_qty = (int)$row['quantity'];
            
            $conn->query("UPDATE shipping_inventory SET quantity = quantity - $pieces WHERE shipping_company_id = $company_id AND product_code = '$product_code'");
            
            if ($old_qty <= 0) {
                $shortfall = $pieces;
            } else if ($old_qty < $pieces) {
                $shortfall = $pieces - $old_qty;
            }
        }

        $res_new = $conn->query("SELECT quantity FROM shipping_inventory WHERE shipping_company_id = $company_id AND product_code = '$product_code'");
        if ($res_new && ($row_new = $res_new->fetch_assoc()) && $row_new['quantity'] <= 0) {
            $qty = $row_new['quantity'];
            $msg = "تنبيه: مخزون شركة الشحن للمنتج $product_code نفد أو أصبح بالسالب. المخزون الحالي: $qty";
            if (function_exists('send_notification')) {
                send_notification($conn, 'admin', 0, 'نفد مخزون شركة الشحن', $msg, 'admin_panel.php?page=storage');
                send_notification($conn, 'shipping_company', $company_id, 'نفد مخزون شركة الشحن', $msg, 'admin_panel.php?page=shipping_storage');
            }
        }

        if ($shortfall > 0 && function_exists('main_inventory_deduct')) {
            main_inventory_deduct($conn, $product_code, $shortfall);
        }

        return true;
    }
}

if (!function_exists('shipping_inventory_add')) {
    function shipping_inventory_add($conn, $company_id, $product_code, $pieces = 1) {
        $company_id = (int)$company_id;
        $product_code = $conn->real_escape_string($product_code);
        $pieces = (int)$pieces;
        if ($pieces <= 0) return;
        
        $conn->query("UPDATE shipping_inventory SET quantity = quantity + $pieces WHERE shipping_company_id = $company_id AND product_code = '$product_code'");
    }
}

if (!function_exists('shipping_inventory_check_low_stock')) {
    function shipping_inventory_check_low_stock($conn, $company_id, $product_code) {
        $company_id = (int)$company_id;
        $product_code = $conn->real_escape_string($product_code);
        $res = $conn->query("SELECT quantity FROM shipping_inventory WHERE shipping_company_id = $company_id AND product_code = '$product_code'");
        if ($res && ($row = $res->fetch_assoc()) && $row['quantity'] <= 10) {
                $qty = $row['quantity'];
                $msg = "تنبيه: مخزون المنتج $product_code قارب على الانتهاء. المتبقي: $qty";
                if (function_exists('send_notification')) {
                    send_notification($conn, 'admin', 0, 'نقص في المخزون', $msg, 'admin_panel.php?page=storage');
                    send_notification($conn, 'shipping_company', $company_id, 'نقص في المخزون', $msg, 'admin_panel.php?page=shipping_storage');
                }
        }
    }
}

if (!function_exists('main_inventory_deduct')) {
    function main_inventory_deduct($conn, $product_code, $pieces) {
        $product_code = $conn->real_escape_string($product_code);
        $pieces = (int)$pieces;
        if ($pieces <= 0) return true; // nothing to deduct
        
        $conn->query("UPDATE shipping_inventory_products SET stock_quantity = stock_quantity - $pieces WHERE product_code = '$product_code'");
        
        // Check if stock became negative and send warning
        $res = $conn->query("SELECT stock_quantity, product_name FROM shipping_inventory_products WHERE product_code = '$product_code'");
        if ($res && ($row = $res->fetch_assoc())) {
            if ($row['stock_quantity'] < 0) {
                $qty = $row['stock_quantity'];
                $name = $conn->real_escape_string($row['product_name']);
                $msg = "تحذير: المخزون الرئيسي للمنتج $name ($product_code) أصبح بالسالب! الرصيد الحالي: $qty";
                if (function_exists('send_notification')) {
                    send_notification($conn, 'admin', 0, 'عجز في المخزن الرئيسي', $msg, 'admin_panel.php?page=storage');
                }
            }
        }
        return true;
    }
}

if (!function_exists('main_inventory_refund')) {
    function main_inventory_refund($conn, $product_code, $pieces) {
        $product_code = $conn->real_escape_string($product_code);
        $pieces = (int)$pieces;
        if ($pieces <= 0) return true; // nothing to refund
        
        $conn->query("UPDATE shipping_inventory_products SET stock_quantity = stock_quantity + $pieces WHERE product_code = '$product_code'");
        return true;
    }
}

if (!function_exists('calculate_department_bonuses')) {
    function calculate_department_bonuses($conn, $start_date, $end_date, $product_code = null) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        
        // حساب إجمالي بونص الدعم الفني المسحوب (الموافق عليه) خلال هذه الفترة
        $q_sup = $conn->query("
            SELECT SUM(amount) as total
            FROM support_bonus_withdrawals
            WHERE status = 'approved'
            AND DATE(updated_at) BETWEEN '$start_date' AND '$end_date'
        ");
        $total_support_bonus = $q_sup ? (float) $q_sup->fetch_assoc()['total'] : 0;
        
        // سيتم ربط جداول التسويق والشحن لاحقاً بنفس الطريقة
        $total_marketing_bonus = 0; 
        $total_shipping_bonus = 0; 
        
        // تخصيص تكلفة البونص للمنتج (بالتساوي على المنتجات النشطة إذا تم تحديد منتدلعين لتجنب تكرار الخصم)
        if ($product_code && $product_code !== 'all') {
            $prod_q = $conn->query("SELECT COUNT(DISTINCT product_code) as c FROM support_product_monthly");
            $prod_count = $prod_q ? max(1, (int) $prod_q->fetch_assoc()['c']) : 1;
            $total_support_bonus = $total_support_bonus / $prod_count;
            $total_marketing_bonus = $total_marketing_bonus / $prod_count;
            $total_shipping_bonus = $total_shipping_bonus / $prod_count;
        }
        
        return [
            'support' => $total_support_bonus,
            'marketing' => $total_marketing_bonus,
            'shipping' => $total_shipping_bonus,
            'total' => $total_support_bonus + $total_marketing_bonus + $total_shipping_bonus
        ];
    }
}

if (!function_exists('ensure_governorates_schema')) {
    function ensure_governorates_schema($conn) {
        if (!is_object($conn)) return false;
        
        $table_check = $conn->query("SHOW TABLES LIKE 'governorates'");
        if ($table_check && $table_check->num_rows > 0) {
            return true; // Already exists
        }
        
        $conn->query("CREATE TABLE IF NOT EXISTS governorates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $default_govs = [
            'طرابلس', 'بنغازي', 'مصراتة', 'الزاوية', 'البيضاء', 'سبها', 'طبرق', 'زليتن', 'سرت', 
            'الخمس', 'درنة', 'اجدابيا', 'شحات', 'الجفرة', 'الجبل الأخضر', 'المرقب', 'الكفرة', 
            'وادي الشاطئ', 'الواحات', 'غات', 'المرج', 'القبة', 'ترهونة', 'غريان', 'يفرن', 
            'نالوت', 'جادو', 'الرياينة', 'ميزدة', 'الابيار', 'براك الشاطئ', 'اوباري', 
            'البريقة', 'العجيلات'
        ];
        
        foreach ($default_govs as $gov) {
            $name = $conn->real_escape_string($gov);
            $conn->query("INSERT IGNORE INTO governorates (name) VALUES ('$name')");
        }
        return true;
    }
}

if (!function_exists('get_governorates_list')) {
    function get_governorates_list($conn) {
        ensure_governorates_schema($conn);
        $govs = [];
        $res = $conn->query("SELECT name FROM governorates ORDER BY name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $govs[$row['name']] = $row['name'];
            }
        }
        return $govs;
    }
}
