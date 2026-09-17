<?php
// ملف: support_sheet_proxy.php
// بروكسي للملفات - يعرض CSV و XLSX كـ HTML table

session_start();
include(__DIR__ . '/core/config.php");

// تضمين قارئ XLSX
if (file_exists('simple_xlsx_reader.php')) {
    include_once('simple_xlsx_reader.php');
}

// دالة لقراءة XLSX
function readXLSXSimple($file_path) {
    if (!file_exists($file_path)) return ['headers' => [], 'data' => []];
    if (!class_exists('SimpleXLSXReader')) return ['headers' => [], 'data' => []];
    try {
        $reader = new SimpleXLSXReader($file_path);
        return $reader->read();
    } catch (Exception $e) {
        return ['headers' => [], 'data' => []];
    }
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('غير مصرح');
}

// التحقق من معرف الشيت
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('معرف غير صالح');
}

$sheet_id = intval($_GET['id']);
$current_user_id = $_SESSION['admin_id'] ?? 0;
$current_role = $_SESSION['admin_role'] ?? '';

// جلب معلومات الشيت
$sheet = $conn->query("SELECT * FROM support_sheets WHERE id = $sheet_id")->fetch_assoc();

if (!$sheet) {
    http_response_code(404);
    die('الشيت غير موجود');
}

// التحقق من الصلاحيات:
// - المدير الرئيسي (super_admin): يمكنه الوصول لكل الشيتات
// - الموظف (support): يمكنه الوصول فقط لشيتاته الخاصة

$has_access = false;
$is_manager = ($current_role === 'super_admin');

if ($is_manager) {
    $has_access = true; // المدير يصل لكل شيء
} elseif ($current_role === 'support' && $sheet['support_id'] == $current_user_id) {
    $has_access = true; // الموظف يصل لشيتاته فقط
}

if (!$has_access) {
    http_response_code(403);
    die('غير مصرح بالوصول لهذا الشيت');
}

$file_path = 'uploads/support_sheets/' . $sheet['file_name'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die('الملف غير موجود');
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// قراءة محتوى الملف وعرضه كـ HTML table
$headers = [];
$data = [];

if ($ext === 'csv') {
    $file = fopen($file_path, 'r');
    if ($file) {
        $is_first = true;
        while (($line = fgetcsv($file)) !== false) {
            if ($is_first) {
                $headers = $line;
                $is_first = false;
            } else {
                // التحقق من أن السطر فيه بيانات
                $has_data = false;
                foreach ($line as $cell) {
                    if (!empty(trim($cell))) { $has_data = true; break; }
                }
                if ($has_data) $data[] = $line;
            }
        }
        fclose($file);
    }
} elseif (in_array($ext, ['xlsx', 'xls'])) {
    $xlsx = readXLSXSimple($file_path);
    $headers = $xlsx['headers'];
    $data = $xlsx['data'];
}

// عرض كـ HTML
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sheet['sheet_name']) ?></title>
    <link rel="icon" type="image/png" href="brand_logo.php?f=logo">
    <link rel="shortcut icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="apple-touch-icon" href="brand_logo.php?f=logo">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f3f4f6; }
        .sheet-container { max-width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th { background: #3b82f6; color: white; padding: 12px; text-align: right; font-weight: 600; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; text-align: right; }
        tr:hover { background: #f9fafb; }
        tr:nth-child(even) { background: #f8fafc; }
        .download-btn { position: fixed; top: 20px; left: 20px; z-index: 100; }
    </style>
</head>
<body>
    <!-- زر تحميل الملف الأصلي -->
    <a href="support_sheet_download.php?id=<?= $sheet_id ?>" class="download-btn bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 flex items-center">
        <i class='bx bx-download mr-2'></i> تحميل الملف
    </a>
    
    <div class="p-6">
        <div class="bg-white rounded-lg shadow mb-4 p-4">
            <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($sheet['sheet_name']) ?></h1>
            <p class="text-gray-500 text-sm mt-1">
                <i class='bx bx-calendar mr-1'></i> <?= date('Y-m-d H:i', strtotime($sheet['created_at'])) ?> |
                <i class='bx bx-user mr-1'></i> <?= $sheet['names_count'] ?? count($data) ?> صف |
                <i class='bx bx-file mr-1'></i> <?= strtoupper($ext) ?>
            </p>
        </div>
        
        <div class="sheet-container bg-white rounded-lg shadow">
            <?php if (count($data) > 0 || count($headers) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <?php foreach ($headers as $header): ?>
                        <th><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $index => $row): ?>
                    <tr>
                        <td class="text-gray-500 font-medium"><?= $index + 1 ?></td>
                        <?php foreach ($row as $cell): ?>
                        <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-8 text-center text-gray-500">
                <i class='bx bx-file-blank text-4xl mb-2'></i>
                <p>لا توجد بيانات في الملف</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
exit;
?>
