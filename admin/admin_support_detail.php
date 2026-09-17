<?php
if (!function_exists('is_customer_blocked')) {
    function is_customer_blocked($conn, $phone, $current_id) {
        if (empty($phone)) return false;
        $phone = $conn->real_escape_string($phone);
        $current_id = (int)$current_id;
        $res = $conn->query("SELECT order_status FROM support_orders o1 WHERE phone = '$phone' AND id < $current_id ORDER BY id DESC LIMIT 3");
        if (!$res || $res->num_rows < 3) return false;
        $fails = 0;
        while ($row = $res->fetch_assoc()) {
            if (in_array($row['order_status'], ['cancelled', 'order_cancelled', 'return', 'returned'])) {
                $fails++;
            }
        }
        return $fails === 3;
    }
}
/** @var mysqli $conn */
// Trigger sync 2
// ملف: admin_support_detail.php
// صفحة تفاصيل موظف الدعم الفني (يتم تضمينها من admin_panel.php)

// التحقق من الصلاحيات — مدير رئيسي أو من لديه تبويب المتابعة
$allowed_roles = ['super_admin', 'admin', 'marketing'];
$user_role = $_SESSION['admin_role'] ?? '';
$user_pages = $_SESSION['admin_allowed_pages'] ?? null;
$can_open_detail = ($user_role === 'super_admin')
    || (function_exists('admin_can_access_page') && admin_can_access_page('supervisor', $user_role, $user_pages))
    || (function_exists('admin_can_access_page') && admin_can_access_page('support_detail', $user_role, $user_pages));

if (!in_array($user_role, $allowed_roles, true) || !$can_open_detail) {
    echo "<div class='p-6'><div class='bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded text-center'>
        <i class='bx bx-error-alt text-4xl mb-2'></i>
        <h2 class='text-xl font-bold mb-2'>غير مصرح</h2>
        <p>ليس لديك صلاحية للوصول إلى هذه الصفحة.</p>
        <a href='?page=supervisor' class='mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600'>
            العودة
        </a>
    </div></div>";
} else {
$shipping_companies = [];
$sc_res = $conn->query("SELECT id, name, code FROM shipping_accounts ORDER BY name ASC");
if ($sc_res) {
    $shipping_companies = $sc_res->fetch_all(MYSQLI_ASSOC);
}

$shipping_reps_map = [];
$sr_res = $conn->query("SELECT id, name FROM shipping_company_reps");
if ($sr_res) {
    while ($r = $sr_res->fetch_assoc()) {
        $shipping_reps_map[$r['id']] = $r['name'];
    }
}

$is_manager = true;
$can_mark_received = function_exists('can_mark_order_as_received') && can_mark_order_as_received($user_role, $user_pages);

$flash_ok = $_SESSION['support_detail_flash_success'] ?? null;
$flash_err = $_SESSION['support_detail_flash_error'] ?? null;
unset($_SESSION['support_detail_flash_success'], $_SESSION['support_detail_flash_error']);
if ($flash_ok) {
    echo "<div class='mx-4 mt-4 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl'>" . htmlspecialchars($flash_ok) . "</div>";
}
if ($flash_err) {
    echo "<div class='mx-4 mt-4 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl'>" . htmlspecialchars($flash_err) . "</div>";
}

// التحقق من معرف الموظف
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_panel.php?page=supervisor');
    exit;
}

$support_id = intval($_GET['id']);

// جلب معلومات الموظف
$member = $conn->query("
    SELECT a.*, s.confirmation_rate, s.delivery_rate, s.cancellation_rate, 
           s.total_orders, s.completed_orders, s.cancelled_orders
    FROM admins a
    LEFT JOIN support_stats s ON a.id = s.support_id
    WHERE a.id = $support_id AND a.role = 'support'
")->fetch_assoc();

if (!$member) {
    echo "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <title>الموظف غير موجود</title>
    <link rel='icon' type='image/png' href='brand_logo.php?f=logo'>
    <link rel='shortcut icon' type='image/png' href='brand_logo.php?f=favicon'>
    <link rel='apple-touch-icon' href='brand_logo.php?f=logo'>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-gray-100 min-h-screen flex items-center justify-center'>
        <div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-8 py-6 rounded max-w-md text-center'>
            <i class='bx bx-user-x text-4xl mb-3'></i>
            <h2 class='text-xl font-bold mb-2'>الموظف غير موجود</h2>
            <p>لم يتم العثور على موظف دعم فني بهذا المعرف</p>
            <a href='admin_panel.php?page=supervisor' class='mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600'>
                العودة للمتابعة
            </a>
        </div>
    </body>
    </html>
    ";
    exit;
}

// الفترة من URL
$period = $_GET['period'] ?? 'all';
$valid_periods = ['all', '1day', '1week', '1month', '3months', '6months', '9months', '1year'];
if (!in_array($period, $valid_periods)) {
    $period = 'all';
}

// حساب تاريخ البداية
$date_filter = '';
$start_date = null;
$period_labels = [
    'all' => 'الكل',
    '1day' => 'يوم واحد',
    '1week' => 'أسبوع',
    '1month' => 'شهر',
    '3months' => '3 شهور',
    '6months' => '6 شهور',
    '9months' => '9 شهور',
    '1year' => 'سنة'
];

switch ($period) {
    case '1day': $start_date = date('Y-m-d', strtotime('-1 day')); break;
    case '1week': $start_date = date('Y-m-d', strtotime('-1 week')); break;
    case '1month': $start_date = date('Y-m-d', strtotime('-1 month')); break;
    case '3months': $start_date = date('Y-m-d', strtotime('-3 months')); break;
    case '6months': $start_date = date('Y-m-d', strtotime('-6 months')); break;
    case '9months': $start_date = date('Y-m-d', strtotime('-9 months')); break;
    case '1year': $start_date = date('Y-m-d', strtotime('-1 year')); break;
}

// التحقق من وجود الأعمدة وإضافتها إذا لم تكن موجودة
$conn->query("SET SESSION sql_mode = ''");
$check_column = $conn->query("SHOW COLUMNS FROM support_sheets LIKE 'names_count'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE support_sheets ADD COLUMN names_count INT DEFAULT 0");
    $conn->query("ALTER TABLE support_sheets ADD COLUMN call_duration INT DEFAULT 0");
}
$check_lock = $conn->query("SHOW COLUMNS FROM support_sheets LIKE 'is_unlocked'");
if ($check_lock->num_rows == 0) {
    $conn->query("ALTER TABLE support_sheets ADD COLUMN is_unlocked TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE support_sheets ADD COLUMN unlock_request TINYINT(1) DEFAULT 0");
}

// جلب إحصائيات الشيتات
if ($start_date) {
    $date_filter = " AND created_at >= '$start_date'";
}

// حساب الإجمالي من الشيتات - مع إعادة حساب الملفات القديمة
$sheets_result = $conn->query("
    SELECT * FROM support_sheets 
    WHERE support_id = $support_id $date_filter
    ORDER BY created_at DESC
");

$total_orders = 0;
$sheets_count = 0;
$sheets_to_update = [];

while ($sheet = $sheets_result->fetch_assoc()) {
    $sheets_count++;
    $count = intval($sheet['names_count']);
    
    // لو العدد 0، نحاول نعد من الملف مباشرة
    if ($count === 0) {
        $file_path = 'uploads/support_sheets/' . $sheet['file_name'];
        if (file_exists($file_path)) {
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if ($ext === 'csv') {
                $file = fopen($file_path, 'r');
                if ($file) {
                    $is_first = true;
                    $file_count = 0;
                    while (($line = fgetcsv($file)) !== false) {
                        if ($is_first) { $is_first = false; continue; }
                        $has_data = false;
                        foreach ($line as $cell) {
                            if (!empty(trim($cell))) { $has_data = true; break; }
                        }
                        if ($has_data) $file_count++;
                    }
                    fclose($file);
                    $count = $file_count;
                    // نحفظ القيمة الصحيحة في قاعدة البيانات
                    $sheets_to_update[] = ['id' => $sheet['id'], 'count' => $count];
                }
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                $count = count_support_sheet_rows($file_path);
                $sheets_to_update[] = ['id' => $sheet['id'], 'count' => $count];
            }
        }
    }
    
    $total_orders += $count;
}

// تحديث الشيتات القديمة بالعدد الصحيح
foreach ($sheets_to_update as $update) {
    $conn->query("UPDATE support_sheets SET names_count = {$update['count']} WHERE id = {$update['id']}");
}

if (is_object($conn) && function_exists('support_calls_sync_schema')) {
    @support_calls_sync_schema($conn);
}
$total_call_seconds = support_get_total_call_seconds($conn, $support_id, $start_date);
$call_duration_formatted = format_activity_duration($total_call_seconds);
$support_calls_log = support_get_calls_list($conn, $support_id, $start_date, 50);

$perf = get_support_member_performance($conn, $support_id, $start_date);
$final_total = $perf['total_orders'];
$confirmed_orders = $perf['confirmed_orders'];
$delivered_orders = $perf['delivered_orders'];
$cancelled_orders = $perf['cancelled_orders'];
$confirmation_rate = $perf['confirmation_rate'];
$delivery_rate = $perf['delivery_rate'];
$cancellation_rate = $perf['cancellation_rate'];
$personal_rating = $perf['personal_rating'];

// جلب الشيتات
$sheets = $conn->query("
    SELECT * FROM support_sheets 
    WHERE support_id = $support_id 
    ORDER BY created_at DESC
    LIMIT 10
");

// معالجة رفع الشيت — تتم في admin_support_sheet_upload_early.php عبر admin_panel.php
$upload_message = '';
if (!empty($_SESSION['support_sheet_flash_success'])) {
    $upload_message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
        <i class='bx bx-check-circle mr-2'></i> " . htmlspecialchars($_SESSION['support_sheet_flash_success'], ENT_QUOTES, 'UTF-8') . "
    </div>";
    unset($_SESSION['support_sheet_flash_success']);
} elseif (!empty($_SESSION['support_sheet_flash_error'])) {
    $upload_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
        <i class='bx bx-error-alt mr-2'></i> " . htmlspecialchars($_SESSION['support_sheet_flash_error'], ENT_QUOTES, 'UTF-8') . "
    </div>";
    unset($_SESSION['support_sheet_flash_error']);
}

// تضمين قارئ XLSX البسيط
if (file_exists('simple_xlsx_reader.php')) {
    include_once('simple_xlsx_reader.php');
}

// دالة بسيطة لقراءة XLSX
function readXLSX($file_path) {
    $data = [];
    $headers = [];
    
    // نحاول نستخدم PhpSpreadsheet لو متاح
    if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $is_first = true;
            foreach ($rows as $row) {
                if ($is_first) {
                    $headers = $row;
                    $is_first = false;
                } else {
                    // التحقق من أن السطر مش فاضي
                    $has_data = false;
                    foreach ($row as $cell) {
                        if (!empty(trim($cell))) {
                            $has_data = true;
                            break;
                        }
                    }
                    if ($has_data) {
                        $data[] = $row;
                    }
                }
            }
            return ['headers' => $headers, 'data' => $data];
        } catch (Exception $e) {
            // لو فشلت PhpSpreadsheet، نحاول الطريقة التانية
        }
    }
    
    // طريقة تانية: استخدام SimpleXLSXReader
    if (class_exists('SimpleXLSXReader')) {
        try {
            $reader = new SimpleXLSXReader($file_path);
            return $reader->read();
        } catch (Exception $e) {
            // نحاول الطريقة التالتة
        }
    }
    
    // طريقة تالتة: تحويل XLSX لـ CSV باستخدام أمر shell (لو متاح)
    $temp_csv = tempnam(sys_get_temp_dir(), 'xlsx_') . '.csv';
    $cmd = "libreoffice --headless --convert-to csv --outdir " . dirname($temp_csv) . " " . escapeshellarg($file_path) . " 2>&1";
    exec($cmd, $output, $return_code);
    
    // ندور على الملف المحول
    $converted_file = dirname($temp_csv) . '/' . basename($file_path, '.xlsx') . '.csv';
    if (file_exists($converted_file)) {
        $file = fopen($converted_file, 'r');
        if ($file) {
            $row_count = 0;
            while (($row = fgetcsv($file)) !== false) {
                if ($row_count === 0) {
                    $headers = $row;
                } else {
                    $has_data = false;
                    foreach ($row as $cell) {
                        if (!empty(trim($cell))) {
                            $has_data = true;
                            break;
                        }
                    }
                    if ($has_data) {
                        $data[] = $row;
                    }
                }
                $row_count++;
            }
            fclose($file);
            unlink($converted_file);
        }
    }
    unlink($temp_csv);
    
    return ['headers' => $headers, 'data' => $data];
}

// معالجة عرض الشيت
$view_sheet = null;
$csv_data = [];
$csv_headers = [];
if (isset($_GET['view_sheet']) && is_numeric($_GET['view_sheet'])) {
    $sheet_id = intval($_GET['view_sheet']);
    // المدير يستطيع مشاهدة أي شيت خاص بهذا الموظف
    $view_sheet = $conn->query("SELECT * FROM support_sheets WHERE id = $sheet_id AND support_id = $support_id")->fetch_assoc();
    // لو ما اتلاقاش (ممكن support_id في DB مختلف قديم) - نحاول بدون شرط الموظف
    if (!$view_sheet) {
        $view_sheet = $conn->query("SELECT * FROM support_sheets WHERE id = $sheet_id")->fetch_assoc();
    }
    
    // قراءة محتويات الملف
    if ($view_sheet) {
        $file_path = 'uploads/support_sheets/' . $view_sheet['file_name'];
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if (file_exists($file_path)) {
            if ($ext === 'csv') {
                // قراءة CSV
                $file = fopen($file_path, 'r');
                if ($file) {
                    $row_count = 0;
                    while (($row = fgetcsv($file)) !== false) {
                        if ($row_count === 0) {
                            $csv_headers = $row;
                        } else {
                            // التحقق من أن السطر مش فاضي
                            $has_data = false;
                            foreach ($row as $cell) {
                                if (!empty(trim($cell))) {
                                    $has_data = true;
                                    break;
                                }
                            }
                            if ($has_data) {
                                $csv_data[] = $row;
                            }
                        }
                        $row_count++;
                    }
                    fclose($file);
                }
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                // قراءة Excel
                $result = readXLSX($file_path);
                $csv_headers = $result['headers'];
                $csv_data = $result['data'];
            }
        }
    }
}

// معالجة حذف شيت
if (isset($_GET['delete_sheet'])) {
    $sheet_id = intval($_GET['delete_sheet']);
    $sheet = $conn->query("SELECT file_name FROM support_sheets WHERE id = $sheet_id AND support_id = $support_id")->fetch_assoc();
    
    if ($sheet) {
        $file_path = 'uploads/support_sheets/' . $sheet['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $conn->query("DELETE FROM support_sheets WHERE id = $sheet_id");
        echo "<script>window.location.href='?page=support_detail&id=$support_id&period=$period&message=deleted';</script>";
        exit;
    }
}

// رسائل النجاح
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'uploaded') {
        $upload_message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
            <i class='bx bx-check-circle mr-2'></i> تم رفع الشيت بنجاح
        </div>";
    } elseif ($_GET['message'] === 'deleted') {
        $upload_message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
            <i class='bx bx-check-circle mr-2'></i> تم حذف الشيت بنجاح
        </div>";
    } elseif ($_GET['message'] === 'appended') {
        $count = intval($_GET['count'] ?? 0);
        $upload_message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
            <i class='bx bx-check-circle mr-2'></i> تم إضافة $count طلب جديد بنجاح وتجاهل الطلبات المكررة!
        </div>";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الموظف - <?= htmlspecialchars($member['fullname']) ?></title>
    <link rel="icon" type="image/png" href="brand_logo.php?f=logo">
    <link rel="shortcut icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="apple-touch-icon" href="brand_logo.php?f=logo">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="admin_export_helpers.js?v=2"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap');
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    
    <!-- Header -->
    <div class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="admin_panel.php?page=supervisor" class="text-gray-500 hover:text-gray-700 mr-4">
                        <i class='bx bx-arrow-back text-2xl'></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class='bx bx-user mr-2 text-blue-500'></i>
                            <?= htmlspecialchars($member['fullname']) ?>
                        </h1>
                        <p class="text-gray-500 text-sm">@<?= htmlspecialchars($member['username']) ?> | موظف دعم فني</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="support_member_export.php?id=<?= (int) $support_id ?>&period=<?= urlencode($period) ?>&format=csv"
                       class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 flex items-center">
                        <i class='bx bx-download mr-1'></i> Excel
                    </a>
                    <a href="support_member_export.php?id=<?= (int) $support_id ?>&period=<?= urlencode($period) ?>&format=pdf"
                       class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 flex items-center">
                        <i class='bx bx-download mr-1'></i> PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mx-auto px-6 py-6">
        <?= $upload_message ?>
        
        <?php if ($view_sheet): ?>
        <!-- عرض الشيت - وضع المدير (بدون قيود) -->
        <div class="mb-4 flex justify-between items-center">
            <a href="?page=support_detail&id=<?= $support_id ?>&period=<?= $period ?>" 
               class="inline-flex items-center bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition">
                <i class='bx bx-arrow-back mr-2'></i>
                العودة للتفاصيل
            </a>
            <div class="flex gap-2">
                <a href="support_sheet_download.php?id=<?= $view_sheet['id'] ?>" 
                   class="inline-flex items-center bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                    <i class='bx bx-download mr-2'></i>
                    تحميل
                </a>
            </div>
        </div>
        
        <!-- معلومات الشيت -->
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <h3 class="font-bold text-lg"><?= htmlspecialchars($view_sheet['sheet_name']) ?></h3>
            <p class="text-gray-500 text-sm">
                تاريخ الرفع: <?= date('Y-m-d H:i', strtotime($view_sheet['created_at'])) ?> | 
                الأسماء في الملف الأصلي المرفوع: <strong class="text-gray-600"><?= count($csv_data) ?></strong> | 
                إجمالي الأوردرات بالشيت (شاملاً الإضافات): <strong class="text-blue-600 text-lg"><?= $view_sheet['names_count'] ?></strong> | 
                <span class="text-green-600">✓ المدير له صلاحية كاملة</span>
            </p>
        </div>
        

        
        <!-- جدول الأوردرات المدخلة لهذا الشيت -->
        <?php
        $current_sheet_id = intval($view_sheet['id']);
        $sheet_owner_id = intval($view_sheet['support_id']);
        $sheet_created_at = $conn->real_escape_string($view_sheet['created_at'] ?? date('Y-m-d H:i:s'));
        $sheet_orders = $conn->query("
            SELECT DISTINCT o1.*, 
                   (SELECT COUNT(*) FROM support_orders o2 WHERE o2.phone = o1.phone AND o2.phone != '' AND o2.id < o1.id AND o2.order_status NOT IN ('delivered', 'received', 'cancelled', 'order_cancelled', 'return', 'returned')) as active_duplicates 
            FROM support_orders o1
            WHERE o1.sheet_id = $current_sheet_id
               OR (o1.support_id = $sheet_owner_id 
                   AND (o1.sheet_id = 0 OR o1.sheet_id IS NULL)
                   AND o1.created_at >= '$sheet_created_at' 
                   AND o1.created_at < DATE_ADD('$sheet_created_at', INTERVAL 48 HOUR))
            ORDER BY o1.created_at DESC
        ");
        ?>


        
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6 border-2 border-green-300">
            <h3 class="text-xl font-bold mb-4 flex items-center text-green-600">
                <i class='bx bx-cart-alt mr-2'></i>
                الأوردرات المحفوظة لهذا الشيت (<?= $sheet_orders->num_rows ?> أوردر)
            </h3>
            
            <?php if ($sheet_orders && $sheet_orders->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="py-3 px-2 text-center">#</th>
                            <th class="py-3 px-2 text-right">التاريخ</th>
                            <th class="py-3 px-2 text-right">إسم المستلم</th>
                            <th class="py-3 px-2 text-right">الهاتف</th>
                            <th class="py-3 px-2 text-center">حالة الطلب</th>
                            <th class="py-3 px-2 text-right">ملاحظات</th>
                            <th class="py-3 px-2 text-right">المحافظة</th>
                            <th class="py-3 px-2 text-right">العنوان</th>
                            <th class="py-3 px-2 text-center">القطع</th>
                            <th class="py-3 px-2 text-right">Agent Code</th>
                            <th class="py-3 px-2 text-center">الوقت</th>
                            <th class="py-3 px-2 text-center">المرسل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $order_num = 1;
                        while ($order = $sheet_orders->fetch_assoc()): 
                            $status_map = [
                                'no_answer_1' => ['class' => 'bg-yellow-400 text-yellow-900', 'text' => '🟡 محاولة أولى', 'color' => '#fbbf24'],
                                'no_answer_2' => ['class' => 'bg-yellow-500 text-yellow-900', 'text' => '🟡 محاولة تانية', 'color' => '#f59e0b'],
                                'no_answer_3' => ['class' => 'bg-yellow-600 text-white', 'text' => '🟡 محاولة أخيرة', 'color' => '#d97706'],
                                'contact_later' => ['class' => 'bg-gray-100 text-gray-800 border', 'text' => '⚪ تواصل لاحقاً', 'color' => '#f3f4f6'],
                                'wrong_number' => ['class' => 'bg-red-600 text-white', 'text' => '🔴 رقم غلط', 'color' => '#dc2626'],
                                'order_confirmed' => ['class' => 'bg-green-600 text-white font-bold', 'text' => '✅ تم التأكيد', 'color' => '#059669'],
                                'received' => ['class' => 'bg-green-400 text-green-900', 'text' => '🟢 العميل استلم', 'color' => '#10b981'],
                                'ready_to_ship' => ['class' => 'bg-blue-500 text-white', 'text' => '🔵 جاهز للشحن', 'color' => '#3b82f6'],
                                'order_cancelled' => ['class' => 'bg-red-800 text-white font-bold', 'text' => '❌ تم الإلغاء', 'color' => '#991b1b'],
                                'confirmed' => ['class' => 'bg-blue-300 text-blue-900', 'text' => '🔵 تأكيد', 'color' => '#93c5fd'],
                                'delivered' => ['class' => 'bg-green-300 text-green-900', 'text' => '🟢 تسليم', 'color' => '#86efac'],
                                'cancelled' => ['class' => 'bg-red-400 text-white', 'text' => '🔴 إلغاء', 'color' => '#ef4444'],
                                'out_for_delivery' => ['class' => 'bg-purple-300 text-purple-900', 'text' => '🟣 توصيل', 'color' => '#d8b4fe'],
                                'pending' => ['class' => 'bg-gray-200 text-gray-800', 'text' => '⏳ انتظار', 'color' => '#e5e7eb']
                            ];
                            
                            $status_key = $order['order_status'] ?? 'pending';
                            $status_info = $status_map[$status_key] ?? $status_map['pending'];
                            $row_color = $status_info['color'];
                        ?>
                        <tr class="border-b hover:bg-gray-50" style="background-color: <?= $row_color ?>">
                            <td class="py-2 px-2 text-center"><?= $order_num++ ?></td>
                            <td class="py-2 px-2"><?= htmlspecialchars($order['order_date'] ?? date('Y-m-d', strtotime($order['created_at']))) ?></td>
                            <td class="py-2 px-2 font-semibold">
                                <?= htmlspecialchars($order['recipient_name'] ?? $order['customer_name'] ?? '') ?>
                                <?php 
                                $current_st = $order['order_status'] ?? '';
                                $is_current_active = !in_array($current_st, ['delivered', 'received', 'cancelled', 'order_cancelled', 'return', 'returned']);
                                if (!empty($order['active_duplicates']) && $is_current_active): 
                                ?>
                                    <span class="inline-flex items-center gap-1 bg-red-100 text-red-800 text-[10px] px-1.5 py-0.5 rounded font-bold" title="يوجد طلب آخر قائم لم يتم الانتهاء منه لهذا الرقم">
                                        <i class='bx bx-error-circle'></i> طلب قائم
                                    </span>
                                <?php endif; ?>
                                <?php if (is_customer_blocked($conn, $order['phone'] ?? '', $order['id'] ?? 0)): ?>
                                    <span class="inline-flex items-center gap-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded font-bold" title="هذا العميل تم رفض آخر 3 طلبات له">
                                        <i class='bx bx-block'></i> مرفوض
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-2"><?= htmlspecialchars($order['phone']) ?></td>
                            <td class="py-2 px-2 text-center">
                                <span class="px-2 py-1 rounded text-xs <?= $status_info['class'] ?>">
                                    <?= $status_info['text'] ?>
                                </span>
                            </td>
                            <td class="py-2 px-2"><?= htmlspecialchars($order['notes'] ?? '-') ?></td>
                            <td class="py-2 px-2"><?= htmlspecialchars($order['governorate'] ?? '-') ?></td>
                            <td class="py-2 px-2"><?= htmlspecialchars($order['address'] ?? '-') ?></td>
                            <td class="py-2 px-2 text-center"><?= $order['pieces'] ?? 1 ?></td>
                            <td class="py-2 px-2"><?= htmlspecialchars($order['agent_code'] ?? '-') ?></td>
                            <td class="py-2 px-2 text-center text-gray-500">
                                <?= date('H:i', strtotime($order['created_at'])) ?>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-bold">
                                    <?= htmlspecialchars($order['support_name'] ?? 'غير معروف') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-400 bg-gray-50 rounded">
                <i class='bx bx-cart-alt text-4xl mb-2'></i>
                <p>لم يتم إدخال/حفظ أي أوردرات لهذا الشيت بعد</p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- فلاتر الفترة -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-3">
                <i class='bx bx-calendar mr-2'></i> فلترة حسب الفترة الزمنية
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($period_labels as $key => $label): ?>
                <a href="?page=support_detail&id=<?= $support_id ?>&period=<?= $key ?>" 
                   class="px-4 py-2 rounded-lg border transition <?= $period === $key ? 'bg-orange-500 text-white border-orange-500' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-orange-100 hover:text-orange-700' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                الفترة المحددة: <strong class="text-orange-600"><?= $period_labels[$period] ?></strong>
            </p>
        </div>
        
        <!-- الإحصائيات الرئيسية -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" id="reportSection">
            <div class="bg-blue-50 rounded-lg p-6 text-center shadow-sm">
                <i class='bx bx-package text-4xl text-blue-500 mb-2'></i>
                <p class="text-gray-600 text-sm">إجمالي الطلبات</p>
                <p class="text-3xl font-bold text-blue-600"><?= $final_total ?></p>
                <p class="text-xs text-gray-400 mt-1">من الشيتات المرفوعة</p>
            </div>
            <div class="bg-green-50 rounded-lg p-6 text-center shadow-sm">
                <i class='bx bx-check-circle text-4xl text-green-500 mb-2'></i>
                <p class="text-gray-600 text-sm">طلبات مؤكدة</p>
                <p class="text-3xl font-bold text-green-600"><?= $confirmed_orders ?></p>
                <p class="text-xs text-green-600 mt-1"><?= number_format($confirmation_rate, 1) ?>%</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-6 text-center shadow-sm">
                <i class='bx bx-check-double text-4xl text-purple-500 mb-2'></i>
                <p class="text-gray-600 text-sm">طلبات مسلمة</p>
                <p class="text-3xl font-bold text-purple-600"><?= $delivered_orders ?></p>
                <p class="text-xs text-purple-600 mt-1"><?= number_format($delivery_rate, 1) ?>%</p>
            </div>
            <div class="bg-red-50 rounded-lg p-6 text-center shadow-sm">
                <i class='bx bx-x-circle text-4xl text-red-500 mb-2'></i>
                <p class="text-gray-600 text-sm">طلبات ملغاة</p>
                <p class="text-3xl font-bold text-red-600"><?= $cancelled_orders ?></p>
                <p class="text-xs text-red-600 mt-1"><?= number_format($cancellation_rate, 1) ?>%</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- القسم الأيسر: الرسم والنسب -->
            <div class="space-y-6">
                <!-- Pie Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 text-center">
                        <i class='bx bx-pie-chart-alt-2 mr-2 text-orange-500'></i>
                        توزيع نسب الأداء
                    </h3>
                    <div class="w-64 h-64 mx-auto">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <div class="flex justify-center gap-4 mt-4 text-sm">
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                            <span>تأكيد (<?= number_format($confirmation_rate, 1) ?>%)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span>
                            <span>تسليم (<?= number_format($delivery_rate, 1) ?>%)</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-red-500 rounded-full mr-1"></span>
                            <span>إلغاء (<?= number_format($cancellation_rate, 1) ?>%)</span>
                        </div>
                    </div>
                </div>
                
                <!-- مؤشرات الأداء -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">
                        <i class='bx bx-trending-up mr-2 text-orange-500'></i>
                        مؤشرات الأداء
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">نسبة التأكيد</span>
                                <span class="text-green-600 font-bold"><?= number_format($confirmation_rate, 1) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= $confirmation_rate ?>"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">نسبة التسليم</span>
                                <span class="text-blue-600 font-bold"><?= number_format($delivery_rate, 1) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?= $delivery_rate ?>"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-gray-600">نسبة الإلغاء</span>
                                <span class="text-red-600 font-bold"><?= number_format($cancellation_rate, 1) ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-red-500 h-2.5 rounded-full" style="width: <?= $cancellation_rate ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات إضافية -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg shadow p-4 text-center">
                        <i class='bx bx-time text-3xl text-orange-500 mb-2'></i>
                        <p class="text-gray-500 text-xs">مدة المكالمات</p>
                        <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($call_duration_formatted) ?></p>
                        <p class="text-xs text-gray-400 mt-1">إجمالي وقت المكالمات</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 text-center">
                        <i class='bx bx-star text-3xl text-yellow-500 mb-2'></i>
                        <p class="text-gray-500 text-xs">التقييم الشخصي</p>
                        <p class="text-xl font-bold text-gray-800"><?= number_format($personal_rating, 1) ?>/5</p>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 text-center">
                        <i class='bx bx-file text-3xl text-blue-500 mb-2'></i>
                        <p class="text-gray-500 text-xs">عدد الشيتات</p>
                        <p class="text-xl font-bold text-gray-800"><?= $sheets_count ?></p>
                    </div>
                </div>

                <!-- سجل المكالمات -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">
                        <i class='bx bx-phone-call mr-2 text-green-600'></i>
                        سجل المكالمات
                    </h3>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        <?php if (!empty($support_calls_log)): ?>
                            <?php foreach ($support_calls_log as $call): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded border-r-4 <?= ($call['status'] ?? '') === 'active' ? 'border-green-500' : 'border-blue-400' ?>">
                                <div>
                                    <p class="font-semibold text-sm"><?= htmlspecialchars($call['client_name'] ?: '—') ?></p>
                                    <p class="text-xs text-gray-500">📞 <?= htmlspecialchars($call['client_phone'] ?: '—') ?></p>
                                    <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($call['started_at'] ?? '') ?></p>
                                </div>
                                <div class="text-left">
                                    <?php if (($call['status'] ?? '') === 'active'): ?>
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">جارية</span>
                                    <?php elseif (($call['status'] ?? '') === 'completed'): ?>
                                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded"><?= htmlspecialchars($call['duration_formatted'] ?? '') ?></span>
                                    <?php else: ?>
                                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded">ملغاة</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-gray-400 py-6">لا توجد مكالمات مسجلة في هذه الفترة</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- القسم الأيمن: رفع الشيتات والقائمة -->
            <div class="space-y-6">
                <!-- رفع شيت جديد -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">
                        <i class='bx bx-upload mr-2 text-blue-500'></i>
                        رفع شيت جديد
                    </h3>
                    <form method="POST" enctype="multipart/form-data" action="admin_panel.php?page=support_detail&id=<?= $support_id ?>&period=<?= urlencode($period) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="support_id" value="<?= $support_id ?>">
                        <div class="mb-3">
                            <input type="text" name="sheet_name" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                                   placeholder="اسم الشيت (اختياري)">
                        </div>
                        <div class="mb-3">
                            <input type="file" name="sheet_file" accept=".xlsx,.xls,.csv" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">الصيغ المسموحة: .xlsx, .xls, .csv</p>
                        </div>
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition flex items-center justify-center">
                            <i class='bx bx-upload mr-2'></i>
                            رفع الشيت
                        </button>
                    </form>
                </div>
                
                <!-- قائمة الشيتات -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">
                        <i class='bx bx-file mr-2 text-orange-500'></i>
                        الشيتات المرفوعة
                    </h3>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        <?php if ($sheets && $sheets->num_rows > 0): ?>
                            <?php while ($sheet = $sheets->fetch_assoc()): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                                <div class="flex items-center">
                                    <i class='bx bx-file text-blue-500 mr-2 text-xl'></i>
                                    <div>
                                        <p class="font-medium text-sm"><?= htmlspecialchars($sheet['sheet_name']) ?></p>
                                        <p class="text-xs text-gray-400">
                                            <?= date('Y-m-d', strtotime($sheet['created_at'])) ?> | 
                                            <?= $sheet['names_count'] ?? 0 ?> اسم
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php
                                    $time_now = time();
                                    $created_time = strtotime($sheet['created_at']);
                                    $unlocked_time = !empty($sheet['unlocked_at']) ? strtotime($sheet['unlocked_at']) : 0;
                                    $is_locked = false;
                                    if (!empty($sheet['force_locked'])) {
                                        $is_locked = true;
                                    } elseif ($time_now - $created_time > 86400) {
                                        if (empty($sheet['is_unlocked']) || ($time_now - $unlocked_time > 86400)) {
                                            $is_locked = true;
                                        }
                                    }
                                    if ($is_locked):
                                    ?>
                                        <span class="text-red-500" title="مغلق لمرور 24 ساعة"><i class='bx bxs-lock-alt text-lg'></i></span>
                                        <?php if (!empty($sheet['unlock_request'])): ?>
                                            <form method="POST" class="inline m-0 p-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="from_detail" value="<?= $support_id ?>">
                                                <button type="submit" name="unlock_sheet" value="<?= $sheet['id'] ?>" class="bg-green-500 text-white px-2 py-1 rounded text-xs hover:bg-green-600 flex items-center shadow" title="قبول الطلب وفتح الشيت"><i class='bx bx-check mr-1'></i> قبول</button>
                                            </form>
                                            <form method="POST" class="inline m-0 p-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="from_detail" value="<?= $support_id ?>">
                                                <button type="submit" name="reject_unlock" value="<?= $sheet['id'] ?>" class="bg-red-500 text-white px-2 py-1 rounded text-xs hover:bg-red-600 flex items-center shadow" title="رفض الطلب" onclick="uiConfirmSubmit(event, this, 'تأكيد الرفض', 'هل أنت متأكد من رفض الطلب؟', 'نعم، أرفض', true)"><i class='bx bx-x mr-1'></i> رفض</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="inline m-0 p-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="from_detail" value="<?= $support_id ?>">
                                                <button type="submit" name="unlock_sheet" value="<?= $sheet['id'] ?>" class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-xs hover:bg-gray-300" title="فتح القفل للموظف">فتح القفل</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="POST" class="inline m-0 p-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="from_detail" value="<?= $support_id ?>">
                                            <button type="submit" name="force_lock_sheet" value="<?= $sheet['id'] ?>" class="bg-gray-500 text-white px-2 py-1 rounded text-xs hover:bg-gray-600 flex items-center shadow" title="إغلاق الشيت فوراً" onclick="uiConfirmSubmit(event, this, 'تأكيد الإغلاق', 'هل أنت متأكد من قفل هذا الشيت للموظف؟', 'نعم، إغلاق', true)"><i class='bx bxs-lock-alt mr-1'></i> إغلاق فوري</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" enctype="multipart/form-data" action="admin_support_sheet_append.php" class="inline m-0 p-0" id="append_form_<?= $sheet['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                                        <input type="hidden" name="support_id" value="<?= $support_id ?>">
                                        <input type="hidden" name="period" value="<?= $period ?>">
                                        <input type="file" name="append_file" id="append_file_<?= $sheet['id'] ?>" accept=".csv,.xlsx,.xls" class="hidden" onchange="document.getElementById('append_form_<?= $sheet['id'] ?>').submit();">
                                        <label for="append_file_<?= $sheet['id'] ?>" class="text-green-500 hover:text-green-700 p-1 cursor-pointer" title="تعديل (إضافة سطور جديدة)">
                                            <i class='bx bx-edit text-lg'></i>
                                        </label>
                                    </form>

                                    <a href="?page=support_detail&id=<?= $support_id ?>&period=<?= $period ?>&view_sheet=<?= $sheet['id'] ?>" 
                                       class="text-blue-500 hover:text-blue-700 p-1"
                                       title="عرض">
                                        <i class='bx bx-show text-lg'></i>
                                    </a>
                                    <a href="?page=support_detail&id=<?= $support_id ?>&period=<?= $period ?>&delete_sheet=<?= $sheet['id'] ?>" 
                                       class="text-red-500 hover:text-red-700 p-1"
                                       title="حذف"
                                       onclick="uiConfirmLink(event, this.href, 'تأكيد الحذف', 'هل أنت متأكد من حذف هذا الشيت؟', 'نعم، احذف', true)">
                                        <i class='bx bx-trash text-lg'></i>
                                    </a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-400">
                                <i class='bx bx-file-blank text-4xl mb-2'></i>
                                <p>لا توجد شيتات مرفوعة</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        

        
        <!-- عرض الجداول الخاصة بشيتات هذا الموظف -->
        <?php

        $status_labels_supervisor = [
            'pending' => 'انتظار / لم يتم التواصل',
            'order_confirmed' => 'تم تأكيد الطلب',
            'confirmed' => 'تم تأكيد الطلب',
            'no_answer_1' => 'لم يتم الرد - محاولة أولى',
            'no_answer_2' => 'لم يتم الرد - محاولة تانية',
            'no_answer_3' => 'لم يتم الرد - محاولة أخيرة',
            'contact_later' => 'سيتم التواصل لاحقاً',
            'wrong_number' => 'الرقم غلط',
            'received' => 'تم الاستلام',
            'ready_to_ship' => 'جاهز للشحن',
            'order_cancelled' => 'العميل ألغى',
            'cancelled' => 'إلغاء',
        ];

        $check_table_exists = $conn->query("SHOW TABLES LIKE 'support_orders'");
        $by_sheet_all = [];
        $by_sheet_confirmed = [];
        $status_filter = $_GET['status_filter'] ?? 'all';
        
        // استيراد رجعي: شيتات مرفوعة بدون أوردرات في الجدول → تنزل في سجل كل الطلبات
        if (function_exists('import_support_sheet_orders_to_db') && $check_table_exists && $check_table_exists->num_rows > 0) {
            $miss_q = $conn->query("
                SELECT s.id, s.file_name
                FROM support_sheets s
                LEFT JOIN support_orders o ON o.sheet_id = s.id
                WHERE s.support_id = $support_id
                GROUP BY s.id, s.file_name, s.names_count
                HAVING COUNT(o.id) < s.names_count
            ");
            if ($miss_q) {
                while ($ms = $miss_q->fetch_assoc()) {
                    @import_support_sheet_orders_to_db(
                        $conn,
                        (int) ($ms['id'] ?? 0),
                        $support_id,
                        (string) ($ms['file_name'] ?? '')
                    );
                }
            }
            
            // تحديث الشيتات التي عدد الأوردرات الفعلي فيها أكبر من names_count
            $fix_q = $conn->query("
                SELECT s.id, COUNT(o.id) as actual_count
                FROM support_sheets s
                INNER JOIN support_orders o ON o.sheet_id = s.id
                WHERE s.support_id = $support_id
                GROUP BY s.id, s.names_count
                HAVING COUNT(o.id) > s.names_count
            ");
            if ($fix_q) {
                while ($fx = $fix_q->fetch_assoc()) {
                    $conn->query("UPDATE support_sheets SET names_count = {$fx['actual_count']} WHERE id = {$fx['id']}");
                }
            }
        }

        if ($check_table_exists && $check_table_exists->num_rows > 0) {
            $all_q = $conn->query("
                SELECT o.*, 
                       s.sheet_name,
                       (SELECT COUNT(*) FROM support_orders o2 WHERE o2.phone = o.phone AND o2.phone != '' AND o2.id < o.id AND o2.order_status NOT IN ('delivered', 'received', 'cancelled', 'order_cancelled', 'return', 'returned')) as active_duplicates
                FROM support_orders o
                LEFT JOIN support_sheets s ON s.id = o.sheet_id
                WHERE o.support_id = $support_id
                ORDER BY COALESCE(s.id, 0) DESC, o.created_at DESC
                LIMIT 5000
            ");
            
            if ($all_q) {
                while ($or = $all_q->fetch_assoc()) {
                    $st = $or['order_status'] ?? '';
                    
                    if ($status_filter !== 'all' && $st !== $status_filter) {
                        continue;
                    }

                    $sid = (int) ($or['sheet_id'] ?? 0);
                    $sheetName = $sid > 0 ? ($or['sheet_name'] ?: ('شيت #' . $sid)) : 'بدون شيت مرتبط';
                    
                    if (!isset($by_sheet_all[$sid])) {
                        $by_sheet_all[$sid] = ['sheet_name' => $sheetName, 'rows' => []];
                    }
                    $by_sheet_all[$sid]['rows'][] = $or;
                    
                    if (in_array($st, ['order_confirmed', 'confirmed', 'received', 'delivered'])) {
                        $conf_date_str = !empty($or['confirmed_at']) ? $or['confirmed_at'] : ($or['updated_at'] ?? $or['created_at'] ?? '');
                        $conf_date = !empty($conf_date_str) ? date('Y-m-d', strtotime($conf_date_str)) : 'غير محدد';
                        $groupKey = 'conf_' . $conf_date;
                        $groupName = 'تأكيدات يوم ' . $conf_date;
                        
                        if (!isset($by_sheet_confirmed[$groupKey])) {
                            $by_sheet_confirmed[$groupKey] = ['sheet_name' => $groupName, 'rows' => [], 'conf_date' => $conf_date];
                        }
                        $by_sheet_confirmed[$groupKey]['rows'][] = $or;
                    }
                }
            }
        }
        
        // دالة مساعدة لطباعة الجدول عشان مانكررش الكود
        function render_orders_table($pack, $support_id, $sheetKey, $status_labels_supervisor, $is_confirmed, $can_mark_received = false) {
            global $is_manager, $shipping_companies, $shipping_reps_map, $conn;
            
            $is_date_group = strpos($sheetKey, 'conf_') === 0;
            $conf_date = $is_date_group ? substr($sheetKey, 5) : '';
            $sheet_id = $is_date_group ? 0 : (int)$sheetKey;
            
            $exportId = 'sheet-' . ($is_confirmed ? 'conf' : 'all') . '-' . $support_id . '-' . ($is_date_group ? $conf_date : $sheet_id);
            $safeFile = ($is_confirmed ? 'confirmed_' : 'all_') . ($is_date_group ? 'date_' . $conf_date : 'sheet_' . $sheet_id) . '_' . date('Y-m-d');
            $headerColor = $is_confirmed ? 'from-emerald-600 to-teal-600' : 'from-blue-600 to-indigo-600';
            $btnColor = $is_confirmed ? 'text-emerald-700 hover:bg-emerald-50' : 'text-blue-700 hover:bg-blue-50';
            $titlePrefix = $is_confirmed ? 'طلب مؤكد' : 'طلب (الكل)';
            $showReceivedAction = ($is_confirmed && $can_mark_received);
            ?>
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden mb-6">
                <div class="bg-gradient-to-l <?= $headerColor ?> px-4 py-3 text-white flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-bold text-lg"><?= htmlspecialchars($pack['sheet_name']) ?></div>
                        <div class="text-white/80 text-sm">
                            <span class="font-semibold"><?= count($pack['rows']) ?> <?= $titlePrefix ?></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                        <button type="button" onclick="deleteTable('<?= htmlspecialchars((string)$sheetKey) ?>')" class="inline-flex items-center bg-red-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-red-600 shadow transition">
                            <i class='bx bx-trash mr-1'></i> حذف الجدول
                        </button>
                        <?php endif; ?>
                        <?php if ($is_date_group): ?>
                        <a href="support_export_confirmed.php?support_id=<?= $support_id ?>&conf_date=<?= $conf_date ?>"
                           class="inline-flex items-center bg-white <?= $btnColor ?> px-3 py-2 rounded-lg text-sm font-semibold shadow transition">
                            <i class='bx bx-spreadsheet mr-1'></i> Excel (CSV)
                        </a>
                        <a href="support_orders_export_pdf.php?support_id=<?= $support_id ?>&conf_date=<?= $conf_date ?>"
                           class="inline-flex items-center bg-white/90 text-gray-800 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-white shadow transition">
                            <i class='bx bxs-file-pdf mr-1'></i> PDF
                        </a>
                        <?php else: ?>
                        <a href="support_export_confirmed.php?sheet_id=<?= $sheet_id ?><?= !$is_confirmed ? '&all=1' : '' ?>"
                           class="inline-flex items-center bg-white <?= $btnColor ?> px-3 py-2 rounded-lg text-sm font-semibold shadow transition">
                            <i class='bx bx-spreadsheet mr-1'></i> Excel (CSV)
                        </a>
                        <a href="support_orders_export_pdf.php?sheet_id=<?= $sheet_id ?><?= !$is_confirmed ? '&all=1' : '' ?>"
                           class="inline-flex items-center bg-white/90 text-gray-800 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-white shadow transition">
                            <i class='bx bxs-file-pdf mr-1'></i> PDF
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-3 overflow-x-auto overflow-y-auto" style="max-height: 70vh;">
                    <div id="<?= htmlspecialchars($exportId, ENT_QUOTES, 'UTF-8') ?>" class="export-pdf-root" dir="rtl" style="font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                        <table class="w-full text-sm border-collapse">
                            <thead class="sticky top-0 bg-gray-100 shadow-sm z-10">
                                <tr class="bg-gray-100 text-gray-800">
                                    <th class="border border-gray-200 p-2 text-center">#</th>
                                    <th class="border border-gray-200 p-2 text-right">التاريخ</th>
                                    <th class="border border-gray-200 p-2 text-right">إسم المستلم</th>
                                    <th class="border border-gray-200 p-2 text-right">رقم الهاتف</th>
                                    <th class="border border-gray-200 p-2 text-right">حالة الطلب</th>
                                    <?php if ($is_manager): ?>
                                    <th class="border border-gray-200 p-2 text-center">إسناد شركة شحن</th>
                                    <th class="border border-gray-200 p-2 text-center">المندوب</th>
                                    <?php endif; ?>
                                    <?php if ($showReceivedAction): ?>
                                    <th class="border border-gray-200 p-2 text-center">تم الاستلام</th>
                                    <?php endif; ?>
                                    <th class="border border-gray-200 p-2 text-right">ملاحظات الشحن</th>
                                    <th class="border border-gray-200 p-2 text-right">ملاحظات</th>
                                    <th class="border border-gray-200 p-2 text-right">المحافظة</th>
                                    <th class="border border-gray-200 p-2 text-right">العنوان</th>
                                    <th class="border border-gray-200 p-2 text-center">القطع</th>
                                    <th class="border border-gray-200 p-2 text-right">حالة العميل</th>
                                    <th class="border border-gray-200 p-2 text-right" title="Call Center Agent">C.C Agent</th>
                                    <th class="border border-gray-200 p-2 text-right" title="Marketing Agent">M. Agent</th>
                                    <th class="border border-gray-200 p-2 text-right">Campaign</th>
                                    <th class="border border-gray-200 p-2 text-center">السعر</th>
                                    <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                                    <th class="border border-gray-200 p-2 text-center" title="حذف الطلب"><i class='bx bx-trash text-red-500'></i></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rn = 1;
                                foreach ($pack['rows'] as $order):
                                    $dt = $order['order_date'] ?? (substr((string) ($order['created_at'] ?? ''), 0, 10));
                                    $nm = ($order['recipient_name'] !== null && $order['recipient_name'] !== '')
                                        ? $order['recipient_name']
                                        : ($order['customer_name'] ?? '');
                                    $stKey = $order['order_status'] ?? '';
                                    $stTxt = ($stKey === 'received') ? 'تم الاستلام' : ($status_labels_supervisor[$stKey] ?? $stKey);
                                    $oid = (int) ($order['id'] ?? 0);
                                    $alreadyReceived = ($stKey === 'received' || $stKey === 'delivered');
                                ?>
                                <tr class="hover:bg-gray-50 <?= $alreadyReceived ? 'bg-emerald-50/40' : '' ?>">
                                    <td class="border border-gray-200 p-2 text-center text-gray-500 align-top"><?= $rn++ ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs" style="min-width: 80px;"><?= htmlspecialchars((string) $dt) ?></td>
                                    <td class="border border-gray-200 p-2 font-medium align-top" style="word-break: break-word; min-width: 120px;">
                                        <div class="font-bold text-gray-800">
                                            <?= htmlspecialchars((string) $nm) ?>
                                            <?php 
                                            $current_st = $order['order_status'] ?? '';
                                            $is_current_active = !in_array($current_st, ['delivered', 'received', 'cancelled', 'order_cancelled', 'return', 'returned']);
                                            if (!empty($order['active_duplicates']) && $is_current_active): 
                                            ?>
                                                <span class="inline-flex items-center gap-1 bg-red-100 text-red-800 text-[10px] px-1.5 py-0.5 rounded font-bold ml-1" title="يوجد طلب آخر قائم لم يتم الانتهاء منه لهذا الرقم">
                                                    <i class='bx bx-error-circle'></i> طلب قائم
                                                </span>
                                            <?php endif; ?>
                                            <?php if (is_customer_blocked($conn, $order['phone'] ?? '', $order['id'] ?? 0)): ?>
                                                <span class="inline-flex items-center gap-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded font-bold ml-1" title="هذا العميل تم رفض آخر 3 طلبات له">
                                                    <i class='bx bx-block'></i> مرفوض
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="border border-gray-200 p-2 align-top text-left text-xs" dir="ltr" style="min-width: 100px;"><?= htmlspecialchars((string) ($order['phone'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top font-bold text-xs <?= $alreadyReceived ? 'text-emerald-700' : ($is_confirmed ? 'text-green-600' : 'text-blue-600') ?>" style="min-width: 100px;"><?= htmlspecialchars($stTxt) ?></td>
                                    <?php if ($is_manager): ?>
                                    <td class="border border-gray-200 p-2 text-center align-top" style="min-width: 150px;">
                                        <?php if ($oid > 0): ?>
                                        <form method="POST" class="inline m-0 p-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="assign_shipping_company" value="1">
                                            <input type="hidden" name="order_id" value="<?= $oid ?>">
                                            <input type="hidden" name="support_member_id" value="<?= (int) $support_id ?>">
                                            <select name="shipping_company_id" onchange="submitShippingAjax(this.form, this)" class="w-full text-xs px-2 py-1.5 border border-gray-300 rounded shadow-sm focus:outline-none focus:border-indigo-500 <?= ($order['shipping_company_id'] ?? 0) > 0 ? 'bg-indigo-50 text-indigo-800 font-bold' : 'bg-gray-50' ?>">
                                                <option value="">-- شركة الشحن --</option>
                                                <?php foreach ($shipping_companies as $sc): ?>
                                                    <option value="<?= $sc['id'] ?>" <?= ($order['shipping_company_id'] ?? 0) == $sc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sc['name']) ?> (<?= htmlspecialchars($sc['code']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-gray-200 p-2 text-center align-top" style="min-width: 120px;">
                                        <?php if (($order['shipping_rep_id'] ?? 0) > 0 && isset($shipping_reps_map[$order['shipping_rep_id']])): ?>
                                            <div class="text-xs text-indigo-700 bg-indigo-50 px-2 py-1.5 rounded inline-block w-full text-center font-semibold border border-indigo-100 shadow-sm">
                                                <i class='bx bx-user text-indigo-500'></i> <?= htmlspecialchars($shipping_reps_map[$order['shipping_rep_id']]) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($showReceivedAction): ?>
                                    <td class="border border-gray-200 p-2 text-center align-top" style="min-width: 140px;">
                                        <?php if ($alreadyReceived): ?>
                                            <div class="flex flex-col gap-1 items-center">
                                                <span class="inline-block px-2 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-[11px] font-bold">✓ تم الاستلام</span>
                                                <?php if ($oid > 0): ?>
                                                <form method="POST" class="inline" onsubmit="submitReceivedAjax(event, this, false);">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="unmark_order_received" value="1">
                                                    <input type="hidden" name="order_id" value="<?= $oid ?>">
                                                    <input type="hidden" name="support_member_id" value="<?= (int) $support_id ?>">
                                                    <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white text-[11px] font-bold px-2 py-1 rounded-lg shadow-sm">
                                                        إلغاء تم الاستلام
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($oid > 0): ?>
                                            <form method="POST" class="inline" onsubmit="submitReceivedAjax(event, this, true);">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="mark_order_received" value="1">
                                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                                <input type="hidden" name="support_member_id" value="<?= (int) $support_id ?>">
                                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold px-2 py-1.5 rounded-lg shadow-sm">
                                                    تم الاستلام
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td class="border border-gray-200 p-2 align-top text-xs text-indigo-700 bg-indigo-50/30" style="word-break: break-word; min-width: 150px;"><?= htmlspecialchars((string) ($order['shipping_notes'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs text-gray-600" style="word-break: break-word; min-width: 150px;"><?= htmlspecialchars((string) ($order['notes'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs" style="word-break: break-word; min-width: 90px;"><?= htmlspecialchars((string) ($order['governorate'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs text-gray-600" style="word-break: break-word; min-width: 150px;"><?= htmlspecialchars((string) ($order['address'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-center"><?= (int) ($order['pieces'] ?? $order['quantity'] ?? 1) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs" style="min-width: 90px;"><?= htmlspecialchars((string) ($order['customer_status'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs text-gray-600 font-mono"><?= htmlspecialchars((string) ($order['agent_code'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs text-indigo-600 font-mono"><?= htmlspecialchars((string) ($order['marketing_agent'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-xs"><?= htmlspecialchars((string) ($order['campaign'] ?? '')) ?></td>
                                    <td class="border border-gray-200 p-2 align-top text-center font-semibold text-xs"><?= htmlspecialchars((string) ($order['total_price'] ?? '')) ?></td>
                                    <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
                                    <td class="border border-gray-200 p-2 align-top text-center">
                                        <button type="button" onclick="deleteOrder(<?= $oid ?>)" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-1.5 rounded transition shadow-sm" title="مسح الطلب">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>

        <!-- فلتر حالة الطلب للجداول -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-3">
                <i class='bx bx-filter-alt mr-2 text-indigo-500'></i> فلترة الجداول حسب حالة الطلب
            </h3>
            <form method="GET" action="admin_panel.php" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="page" value="support_detail">
                <input type="hidden" name="id" value="<?= $support_id ?>">
                <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
                
                <div class="flex-1 max-w-xs">
                    <label class="block text-sm text-gray-600 mb-1">حالة الطلب</label>
                    <select name="status_filter" class="w-full border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 border">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>الكل</option>
                        <?php foreach ($status_labels_supervisor as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $status_filter === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    تطبيق الفلتر
                </button>
            </form>
        </div>

        <!-- عرض قسم الطلبات المؤكدة فقط -->
        <?php if (!empty($by_sheet_confirmed)): ?>
        <div class="mt-8 mb-4 border-b-2 border-green-500 pb-2">
            <h3 class="text-xl font-bold flex items-center text-green-700">
                <i class='bx bx-check-shield mr-2 text-2xl'></i>
                سجل الطلبات المؤكدة فقط
            </h3>
        </div>
        <div class="space-y-6">
            <?php foreach ($by_sheet_confirmed as $sheetKey => $pack): ?>
                <?php render_orders_table($pack, $support_id, $sheetKey, $status_labels_supervisor, true, !empty($can_mark_received)); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- عرض قسم كل الطلبات (الجدول كامل) -->
        <?php if (!empty($by_sheet_all)): ?>
        <div class="mt-12 mb-4 border-b-2 border-blue-500 pb-2">
            <h3 class="text-xl font-bold flex items-center text-blue-700">
                <i class='bx bx-list-ul mr-2 text-2xl'></i>
                سجل كل الطلبات (الجدول كامل)
            </h3>
            <?php if (!empty($can_mark_received)): ?>
            <p class="text-sm text-slate-500 mt-1">تعيين أو إلغاء «تم الاستلام» من العمود المخصص. بعد التعيين الدعم لا يقدر يغيّرها.</p>
            <?php endif; ?>
        </div>
        <div class="space-y-6">
            <?php foreach ($by_sheet_all as $sheetKey => $pack): ?>
                <?php render_orders_table($pack, $support_id, $sheetKey, $status_labels_supervisor, false, false); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($by_sheet_all) || !empty($by_sheet_confirmed)): ?>
        <script>
        if (typeof window.supervisorExportPdf === 'undefined') {
            window.supervisorExportPdf = function(exportId, fileBase) {
                if (window.AdminExport && typeof AdminExport.exportElementToPdf === 'function') {
                    AdminExport.exportElementToPdf(exportId, fileBase || ('export_' + exportId), {
                        orientation: 'landscape',
                        scale: 1.4,
                        margin: 6
                    });
                    return;
                }
                var el = document.getElementById(exportId);
                if (!el || typeof html2pdf === 'undefined') {
                    alert('تعذر التصدير: حمّل الصفحة بالكامل ثم أعد المحاولة.');
                    return;
                }
                var opt = {
                    margin: 6,
                    filename: (fileBase || 'export') + '.pdf',
                    image: { type: 'jpeg', quality: 0.95 },
                    html2canvas: { scale: 1.5, useCORS: true, scrollY: 0 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
                };
                html2pdf().set(opt).from(el).save().catch(function () {
                    alert('فشل تصدير PDF. جرّب Excel.');
                });
            };
        }
        </script>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // إنشاء الرسم البياني - مع check لو الـ canvas موجود
        const canvasElement = document.getElementById('performanceChart');
        if (!canvasElement) {
            console.log('⚠️ Canvas not found - chart will not be rendered');
        } else {
        const ctx = canvasElement.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['تأكيد', 'تسليم', 'إلغاء'],
                datasets: [{
                    data: [<?= $confirmation_rate ?>, <?= $delivery_rate ?>, <?= $cancellation_rate ?>],
                    backgroundColor: ['#22c55e', '#3b82f6', '#ef4444'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
        } // إقفال الـ else block
    </script>

    <!-- زر العودة للمتابعة -->
    <div class="fixed bottom-6 left-6">
        <a href="admin_panel.php?page=supervisor" 
           class="bg-gray-800 text-white px-6 py-3 rounded-full shadow-lg hover:bg-gray-700 transition flex items-center">
            <i class='bx bx-arrow-back mr-2 text-xl'></i>
            <span>العودة للمتابعة</span>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['unlock_req']) && $_GET['unlock_req'] == 1): ?>
            Swal.fire({
                icon: 'success',
                title: 'تم الإرسال',
                text: 'تم إرسال طلب الفتح للإدارة بنجاح.',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'حسناً'
            });
            
            // Remove param from url
            var url = new URL(window.location);
            url.searchParams.delete('unlock_req');
            window.history.replaceState({}, document.title, url);
        <?php endif; ?>

        <?php if (isset($_GET['unlocked']) && $_GET['unlocked'] == 1): ?>
            Swal.fire({
                icon: 'success',
                title: 'تم الفتح',
                text: 'تم فتح الشيت بنجاح.',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'حسناً'
            });
            
            // Remove param from url
            var url = new URL(window.location);
            url.searchParams.delete('unlocked');
            window.history.replaceState({}, document.title, url);
        <?php endif; ?>

        <?php if (isset($_GET['unlock_rej']) && $_GET['unlock_rej'] == 1): ?>
            Swal.fire({
                icon: 'info',
                title: 'تم الرفض',
                text: 'تم رفض طلب فتح الشيت.',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'حسناً'
            });
            
            // Remove param from url
            var url = new URL(window.location);
            url.searchParams.delete('unlock_rej');
            window.history.replaceState({}, document.title, url);
        <?php endif; ?>

        <?php if (isset($_GET['force_locked']) && $_GET['force_locked'] == 1): ?>
            Swal.fire({
                icon: 'success',
                title: 'تم الإغلاق',
                text: 'تم إغلاق الشيت على الموظف بنجاح.',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'حسناً'
            });
            
            // Remove param from url
            var url = new URL(window.location);
            url.searchParams.delete('force_locked');
            window.history.replaceState({}, document.title, url);
        <?php endif; ?>
    });

    function submitShippingAjax(form, selectElement) {
        const formData = new FormData(form);
        const originalOpacity = selectElement.style.opacity;
        selectElement.style.opacity = '0.5';
        selectElement.disabled = true;

        fetch('', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            selectElement.style.opacity = originalOpacity;
            selectElement.disabled = false;
            if (data.success) {
                if (selectElement.value !== "") {
                    selectElement.classList.remove('bg-gray-50');
                    selectElement.classList.add('bg-indigo-50', 'text-indigo-800', 'font-bold');
                } else {
                    selectElement.classList.remove('bg-indigo-50', 'text-indigo-800', 'font-bold');
                    selectElement.classList.add('bg-gray-50');
                }
            }
        })
        .catch(error => {
            console.error(error);
            selectElement.style.opacity = originalOpacity;
            selectElement.disabled = false;
        });
    }
    </script>
    <script>
    async function submitReceivedAjax(event, form, isMark) {
        event.preventDefault();
        let actionTxt = isMark ? 'تعيين كـ تم الاستلام' : 'إلغاء تم الاستلام';
        let result = await Swal.fire({
            title: 'تأكيد',
            text: 'هل أنت متأكد من ' + actionTxt + '؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، نفذ',
            cancelButtonText: 'إلغاء'
        });
        if (!result.isConfirmed) return;

        let formData = new FormData(form);
        formData.append('ajax', '1');
        
        let btn = form.querySelector('button[type=submit]');
        let originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';
        btn.disabled = true;

        try {
            let response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            let data = await response.json();
            if (data.success) {
                Swal.fire({ title: 'نجاح', text: data.message, icon: 'success', timer: 1500, showConfirmButton: false });
                let td = form.closest('td');
                let tr = td.closest('tr');
                let statusTd = tr.cells[4];
                let oid = formData.get('order_id');
                let support_id = formData.get('support_member_id');
                let csrf = form.querySelector('input[name="csrf_token"]').value;

                if (isMark) {
                    td.innerHTML = `<div class="flex flex-col gap-1 items-center">
                        <span class="inline-block px-2 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-[11px] font-bold">✔️ تم الاستلام</span>
                        <form method="POST" class="inline" onsubmit="submitReceivedAjax(event, this, false);">
                            <input type="hidden" name="csrf_token" value="${csrf}">
                            <input type="hidden" name="unmark_order_received" value="1">
                            <input type="hidden" name="order_id" value="${oid}">
                            <input type="hidden" name="support_member_id" value="${support_id}">
                            <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white text-[11px] font-bold px-2 py-1 rounded-lg shadow-sm">إلغاء تم الاستلام</button>
                        </form>
                    </div>`;
                    statusTd.textContent = '✔️ مستلم (تم)';
                    statusTd.className = 'border border-gray-200 p-2 align-top font-bold text-xs text-emerald-700';
                    tr.classList.add('bg-emerald-50/40');
                } else {
                    td.innerHTML = `<form method="POST" class="inline" onsubmit="submitReceivedAjax(event, this, true);">
                        <input type="hidden" name="csrf_token" value="${csrf}">
                        <input type="hidden" name="mark_order_received" value="1">
                        <input type="hidden" name="order_id" value="${oid}">
                        <input type="hidden" name="support_member_id" value="${support_id}">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold px-2 py-1.5 rounded-lg shadow-sm">تم الاستلام</button>
                    </form>`;
                    statusTd.textContent = '✔️ تأكيد الطلب';
                    statusTd.className = 'border border-gray-200 p-2 align-top font-bold text-xs text-green-600';
                    tr.classList.remove('bg-emerald-50/40');
                }
            } else {
                Swal.fire('خطأ', data.message || 'حدث خطأ غير معروف', 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        } catch (e) {
            Swal.fire('خطأ', 'فشل الاتصال بالخادم', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    
    function deleteOrder(orderId) {
        if (!confirm('هل أنت متأكد من مسح هذا الطلب؟ لن يمكنك التراجع.')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_order');
        formData.append('order_id', orderId);
        
        fetch('ajax_delete_support_order.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if(res.success) {
                location.reload();
            } else {
                alert('حدث خطأ: ' + (res.error || 'غير معروف'));
            }
        }).catch(err => {
            alert('خطأ في الاتصال');
        });
    }

    function deleteTable(sheetKey) {
        if (!confirm('هل أنت متأكد من مسح هذا الجدول بالكامل بجميع طلباته؟ هذه الخطوة نهائية!')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_table');
        formData.append('sheet_id', sheetKey);
        
        fetch('ajax_delete_support_order.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if(res.success) {
                location.reload();
            } else {
                alert('حدث خطأ: ' + (res.error || 'غير معروف'));
            }
        }).catch(err => {
            alert('خطأ في الاتصال');
        });
    }
    </script>
</body>
</html>

<?php } // end permission check ?>

