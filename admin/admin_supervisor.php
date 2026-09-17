<?php
// ملف: admin_supervisor.php
// صفحة المتابعة (مدير رئيسي)

// عرض الأخطاء للتشخيص
error_reporting(E_ALL);
ini_set('display_errors', 1);

// التحقق من صلاحيات المتابعة (مدير رئيسي أو مدير لديه تبويب المتابعة)
$__sup_role = $_SESSION['admin_role'] ?? '';
$__sup_pages = $_SESSION['admin_allowed_pages'] ?? null;
$__can_supervisor = ($__sup_role === 'super_admin')
    || (function_exists('admin_can_access_page') && admin_can_access_page('supervisor', $__sup_role, $__sup_pages));

if (!$__can_supervisor) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <i class='bx bx-error-alt mr-2'></i>
            ليس لديك صلاحية للوصول إلى هذه الصفحة - متاحة للمدير الرئيسي أو من لديه تبويب المتابعة
            <br>دورك الحالي: " . htmlspecialchars($__sup_role ?: 'غير معروف') . "
          </div>";
    return;
}

include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

if (is_object($conn) && function_exists('support_calls_sync_schema')) {
    @support_calls_sync_schema($conn);
}

// تضمين قارئ XLSX لو متاح
if (file_exists('simple_xlsx_reader.php')) {
    include_once('simple_xlsx_reader.php');
}

// دالة بسيطة لقراءة XLSX
function readXLSXSimple($file_path) {
    if (!file_exists($file_path)) {
        error_log("File not found: $file_path");
        return ['headers' => [], 'data' => []];
    }
    if (!class_exists('SimpleXLSXReader')) {
        error_log("SimpleXLSXReader class not found");
        return ['headers' => [], 'data' => []];
    }
    try {
        $reader = new SimpleXLSXReader($file_path);
        $result = $reader->read();
        error_log("Read XLSX: $file_path, rows: " . count($result['data']));
        return $result;
    } catch (Exception $e) {
        error_log("XLSX Error: " . $e->getMessage());
        return ['headers' => [], 'data' => []];
    }
}

// إعادة حساب الشيتات القديمة اللي names_count = 0 (مرة واحدة فقط)
$zero_sheets = $conn->query("SELECT id, file_name FROM support_sheets WHERE names_count = 0");
$updated_count = 0;
while ($sheet = $zero_sheets->fetch_assoc()) {
    $file_path = 'uploads/support_sheets/' . $sheet['file_name'];
    if (file_exists($file_path)) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $count = 0;
        if ($ext === 'csv') {
            $file = fopen($file_path, 'r');
            if ($file) {
                $is_first = true;
                while (($line = fgetcsv($file)) !== false) {
                    if ($is_first) { $is_first = false; continue; }
                    $has_data = false;
                    foreach ($line as $cell) {
                        if (!empty(trim($cell))) { $has_data = true; break; }
                    }
                    if ($has_data) $count++;
                }
                fclose($file);
            }
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            // قراءة XLSX
            $xlsx_data = readXLSXSimple($file_path);
            $count = count($xlsx_data['data']);
        }
        // تحديث العدد في قاعدة البيانات
        if ($count > 0) {
            $conn->query("UPDATE support_sheets SET names_count = $count WHERE id = {$sheet['id']}");
            $updated_count++;
        }
    }
}

// Debug message (مؤقت للتشخيص)
$debug_info = "";

// إجباري: إعادة حساب كل ملفات XLSX (حتى لو names_count != 0)
$all_xlsx = $conn->query("SELECT id, file_name, names_count FROM support_sheets WHERE file_name LIKE '%.xlsx' OR file_name LIKE '%.xls'");
$force_updated = 0;
while ($xfile = $all_xlsx->fetch_assoc()) {
    $xf_path = 'uploads/support_sheets/' . $xfile['file_name'];
    if (file_exists($xf_path)) {
        $xlsx_data = readXLSXSimple($xf_path);
        $real_count = count($xlsx_data['data']);
        if ($real_count != $xfile['names_count']) {
            $conn->query("UPDATE support_sheets SET names_count = $real_count WHERE id = {$xfile['id']}");
            $debug_info .= "✓ {$xfile['file_name']}: {$xfile['names_count']} ← $real_count<br>";
            $force_updated++;
        }
    }
}

if ($force_updated == 0) {
    $debug_info .= "لا توجد ملفات XLSX تحتاج تحديث<br>";
}

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

// معالجة حذف شيت
if (isset($_GET['delete_sheet'])) {
    $sheet_id = intval($_GET['delete_sheet']);
    $sheet = $conn->query("SELECT * FROM support_sheets WHERE id = $sheet_id")->fetch_assoc();
    if ($sheet) {
        $file_path = 'uploads/support_sheets/' . $sheet['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $conn->query("DELETE FROM support_sheets WHERE id = $sheet_id");
        $upload_message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                            <i class='bx bx-check-circle mr-2'></i> تم حذف الشيت بنجاح
                          </div>";
    }
}

// جلب كل موظفي الدعم الفني مع إحصائيات محسوبة من الشيتات والأوردرات
$support_team_raw = $conn->query("
    SELECT a.id, a.fullname, a.username, a.email
    FROM admins a
    WHERE a.role = 'support'
    ORDER BY a.fullname ASC
");

$support_members = [];
$team_totals = ['confirmation' => 0, 'delivery' => 0, 'cancellation' => 0, 'count' => 0];

if ($support_team_raw && $support_team_raw->num_rows > 0) {
    while ($row = $support_team_raw->fetch_assoc()) {
        $perf = sync_support_stats($conn, $row['id']);
        $member = array_merge($row, $perf);
        $support_members[] = $member;
        if ($member['total_orders'] > 0) {
            $team_totals['confirmation'] += $member['confirmation_rate'];
            $team_totals['delivery'] += $member['delivery_rate'];
            $team_totals['cancellation'] += $member['cancellation_rate'];
            $team_totals['count']++;
        }
    }
}

$avg_confirm = $team_totals['count'] > 0 ? round($team_totals['confirmation'] / $team_totals['count'], 1) : 0;
$avg_delivery = $team_totals['count'] > 0 ? round($team_totals['delivery'] / $team_totals['count'], 1) : 0;
$avg_cancel = $team_totals['count'] > 0 ? round($team_totals['cancellation'] / $team_totals['count'], 1) : 0;

// جلب بيانات النشاط من الملف
$activity_file = __DIR__ . '/support_activity.json';
$activity_data = [];
if (file_exists($activity_file)) {
    $activity_data = json_decode(file_get_contents($activity_file), true) ?: [];
}

// التحقق من وجود جدول إحصائيات الدعم
$check_table = $conn->query("SHOW TABLES LIKE 'support_stats'");
if ($check_table->num_rows == 0) {
    // إنشاء جدول الإحصائيات
    $conn->query("CREATE TABLE IF NOT EXISTS support_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        support_id INT NOT NULL,
        confirmation_rate DECIMAL(5,2) DEFAULT 0,
        delivery_rate DECIMAL(5,2) DEFAULT 0,
        cancellation_rate DECIMAL(5,2) DEFAULT 0,
        total_orders INT DEFAULT 0,
        completed_orders INT DEFAULT 0,
        cancelled_orders INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_support (support_id)
    )");
    
    // إنشاء جدول الشيتات
    $conn->query("CREATE TABLE IF NOT EXISTS support_sheets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        support_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        sheet_name VARCHAR(255) DEFAULT 'شيت',
        uploaded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}
?>

<?= $upload_message ?>

<!-- إضافة Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- إضافة html2pdf.js للتصدير كـ PDF (يدعم العربية) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="admin_export_helpers.js?v=2"></script>

<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 text-gray-800">
        <i class='bx bx-user-check mr-2 text-blue-500'></i>
        متابعه (مدير رئيسي) - فريق الدعم الفني
    </h1>
    
    <!-- Debug Info (مؤقت) -->
    <?php
    // عرض ملخص الشيتات للتشخيص
    $debug_sheets = $conn->query("SELECT support_id, file_name, names_count FROM support_sheets");
    $sheet_summary = [];
    while ($s = $debug_sheets->fetch_assoc()) {
        $sid = $s['support_id'];
        if (!isset($sheet_summary[$sid])) $sheet_summary[$sid] = ['count' => 0, 'total' => 0];
        $sheet_summary[$sid]['count']++;
        $sheet_summary[$sid]['total'] += intval($s['names_count']);
    }
    ?>
    <!-- debug مخفي -->
    <!--
    <div class="bg-yellow-50 border border-yellow-300 rounded p-3 mb-4 text-sm">
        <strong>🔧 بيانات الشيتات:</strong><br>
        <?php foreach ($sheet_summary as $sid => $data): ?>
            موظف #<?= $sid ?>: <?= $data['count'] ?> شيت، إجمالي <?= $data['total'] ?> اسم<br>
        <?php endforeach; ?>
    </div>
    -->
    
    <!-- محاولات السكرين شوت -->
    <?php
    $screenshot_log = __DIR__ . '/screenshot_attempts.json';
    $screenshots = [];
    if (file_exists($screenshot_log)) {
        $screenshots = json_decode(file_get_contents($screenshot_log), true) ?: [];
        $screenshots = array_slice(array_reverse($screenshots), 0, 20); // آخر 20 محاولة
    }
    ?>
    <?php if (count($screenshots) > 0): ?>
    <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
        <h3 class="text-red-700 font-bold mb-3 flex items-center">
            <i class='bx bx-camera-off mr-2'></i>
            🚨 محاولات التقاط شاشة (آخر 20)
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-red-100">
                    <tr>
                        <th class="py-2 px-3 text-right">الموظف</th>
                        <th class="py-2 px-3 text-right">الوقت</th>
                        <th class="py-2 px-3 text-right">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($screenshots as $shot): ?>
                    <tr class="border-b border-red-100">
                        <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($shot['user_name'] ?? 'غير معروف') ?></td>
                        <td class="py-2 px-3"><?= htmlspecialchars($shot['timestamp'] ?? '--') ?></td>
                        <td class="py-2 px-3 text-gray-500"><?= htmlspecialchars($shot['ip'] ?? '--') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 🔹 تجهيز بيانات الطلبات المؤكدة حسب الموظف والشيت -->
    <?php
    $check_table_exists = $conn->query("SHOW TABLES LIKE 'support_orders'");
    $table_exists = $check_table_exists && $check_table_exists->num_rows > 0;

    $status_labels_supervisor = [
        'order_confirmed' => 'تم تأكيد الطلب',
        'confirmed' => 'تم تأكيد الطلب',
        'no_answer_1' => 'لم يتم الرد - محاولة أولى',
        'no_answer_2' => 'لم يتم الرد - محاولة تانية',
        'no_answer_3' => 'لم يتم الرد - محاولة أخيرة',
        'contact_later' => 'سيتم التواصل لاحقاً',
        'wrong_number' => 'الرقم غلط',
        'received' => 'العميل استلم',
        'ready_to_ship' => 'جاهز للشحن',
        'order_cancelled' => 'العميل ألغى',
        'pending' => 'انتظار',
    ];

    $by_support = [];
    if ($table_exists):
        $confirmed_q = $conn->query("
            SELECT o.*, a.fullname AS support_name, a.username AS support_username,
                   s.sheet_name, s.id AS sheet_ref_id
            FROM support_orders o
            JOIN admins a ON o.support_id = a.id
            LEFT JOIN support_sheets s ON s.id = o.sheet_id
            WHERE o.order_status IN ('order_confirmed', 'confirmed')
            ORDER BY COALESCE(s.id, 0) DESC, o.created_at DESC
            LIMIT 3000
        ");

        if ($confirmed_q) {
            while ($or = $confirmed_q->fetch_assoc()) {
                $sup_id = (int) $or['support_id'];
                $sid = (int) ($or['sheet_id'] ?? 0);
                if (!isset($by_support[$sup_id])) {
                    $by_support[$sup_id] = [];
                }
                if (!isset($by_support[$sup_id][$sid])) {
                    $by_support[$sup_id][$sid] = [
                        'sheet_name' => $sid > 0
                            ? ($or['sheet_name'] ?: ('شيت #' . $sid))
                            : 'بدون شيت مرتبط',
                        'support_name' => $or['support_name'],
                        'support_username' => $or['support_username'] ?? '',
                        'rows' => [],
                    ];
                }
                $by_support[$sup_id][$sid]['rows'][] = $or;
            }
        }
    endif;
    ?>
    <script>
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
    </script>
    
    <!-- إحصائيات عامة للفريق -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <i class='bx bx-headphone text-4xl text-orange-500 mb-3'></i>
            <h3 class="text-lg font-semibold text-gray-700">إجمالي فريق الدعم</h3>
            <p class="text-3xl font-bold text-gray-800 mt-2">
                <?= $conn->query("SELECT COUNT(*) as total FROM admins WHERE role = 'support'")->fetch_assoc()['total'] ?>
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <i class='bx bx-check-circle text-4xl text-green-500 mb-3'></i>
            <h3 class="text-lg font-semibold text-gray-700">متوسط نسبة التأكيد</h3>
            <?php 
            ?>
            <p class="text-3xl font-bold text-green-600 mt-2"><?= number_format($avg_confirm, 1) ?>%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <i class='bx bx-package text-4xl text-blue-500 mb-3'></i>
            <h3 class="text-lg font-semibold text-gray-700">متوسط نسبة التسليم</h3>
            <?php 
            ?>
            <p class="text-3xl font-bold text-blue-600 mt-2"><?= number_format($avg_delivery, 1) ?>%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <i class='bx bx-x-circle text-4xl text-red-500 mb-3'></i>
            <h3 class="text-lg font-semibold text-gray-700">متوسط نسبة الإلغاء</h3>
            <?php 
            ?>
            <p class="text-3xl font-bold text-red-600 mt-2"><?= number_format($avg_cancel, 1) ?>%</p>
        </div>
    </div>
    
    <!-- فريق الدعم الفني - كل موظف في كرت منفصل -->
    <h2 class="text-xl font-bold mb-4 text-gray-800">فريق الدعم الفني</h2>
    
    <?php 
    if (!empty($support_members)): 
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($support_members as $member): ?>
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- هيدر الكرت -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-4 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3">
                            <i class='bx bx-headphone text-2xl'></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg"><?= htmlspecialchars($member['fullname']) ?></h3>
                            <p class="text-orange-100 text-sm">@<?= htmlspecialchars($member['username']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الإحصائيات -->
            <div class="p-4">
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <div class="text-center p-2 bg-green-50 rounded">
                        <p class="text-xs text-gray-600 mb-1">نسبة التأكيد</p>
                        <p class="font-bold text-green-600"><?= number_format($member['confirmation_rate'], 1) ?>%</p>
                    </div>
                    <div class="text-center p-2 bg-blue-50 rounded">
                        <p class="text-xs text-gray-600 mb-1">نسبة التسليم</p>
                        <p class="font-bold text-blue-600"><?= number_format($member['delivery_rate'], 1) ?>%</p>
                    </div>
                    <div class="text-center p-2 bg-red-50 rounded">
                        <p class="text-xs text-gray-600 mb-1">نسبة الإلغاء</p>
                        <p class="font-bold text-red-600"><?= number_format($member['cancellation_rate'], 1) ?>%</p>
                    </div>
                </div>
                
                <!-- تفاصيل الطلبات -->
                <div class="flex justify-between text-sm text-gray-600 mb-4 p-2 bg-gray-50 rounded">
                    <span>إجمالي الطلبات: <strong><?= $member['total_orders'] ?></strong></span>
                    <span>مؤكد: <?= $member['confirmed_orders'] ?> | مسلّم: <?= $member['delivered_orders'] ?> | ملغى: <?= $member['cancelled_orders'] ?></span>
                </div>
                
                <!-- حالة الاتصال والنشاط (تحديث مباشر) -->
                <div class="mb-4 p-3 rounded-lg bg-gray-50 border border-gray-200" id="activity-box-<?= (int) $member['id'] ?>" data-support-id="<?= (int) $member['id'] ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full mr-2 bg-gray-400" id="activity-dot-<?= (int) $member['id'] ?>"></span>
                            <span class="text-sm font-semibold text-gray-600" id="activity-status-<?= (int) $member['id'] ?>">جاري التحميل...</span>
                        </div>
                        <span class="text-xs text-gray-500" id="activity-duration-<?= (int) $member['id'] ?>">—</span>
                    </div>
                    <div class="text-xs text-gray-400 mt-2" id="activity-lastseen-<?= (int) $member['id'] ?>">—</div>
                    <div class="text-xs text-gray-500 mt-1" id="activity-session-<?= (int) $member['id'] ?>">مدة الجلسة: —</div>
                </div>
                
                <!-- زر عرض التفاصيل - يفتح صفحة منفصلة -->
                <a href="?page=support_detail&id=<?= $member['id'] ?>" 
                   class="w-full bg-gradient-to-r from-orange-500 to-orange-600 text-white py-2 rounded hover:from-orange-600 hover:to-orange-700 transition flex items-center justify-center mb-2">
                    <i class='bx bx-detail mr-2'></i>
                    عرض التفاصيل
                </a>
                
                <!-- الشيتات الخاصة بهذا الموظف -->
                <?php
                $sheets = $conn->query("SELECT * FROM support_sheets WHERE support_id = {$member['id']} ORDER BY created_at DESC");
                if ($sheets && $sheets->num_rows > 0):
                ?>
                <div class="mt-4 border-t pt-3">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">الشيتات الخاصة:</h4>
                    <div class="space-y-2 max-h-40 overflow-y-auto">
                        <?php while ($sheet = $sheets->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded text-sm">
                            <div class="flex items-center">
                                <i class='bx bx-file text-blue-500 mr-2'></i>
                                <span class="truncate max-w-[120px]"><?= htmlspecialchars($sheet['sheet_name']) ?></span>
                            </div>
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <a href="uploads/support_sheets/<?= $sheet['file_name'] ?>" 
                                   target="_blank" 
                                   class="text-blue-500 hover:text-blue-700"
                                   title="عرض">
                                    <i class='bx bx-show'></i>
                                </a>
                                <a href="?page=supervisor&delete_sheet=<?= $sheet['id'] ?>" 
                                   class="text-red-500 hover:text-red-700"
                                   title="حذف"
                                   onclick="return confirm('هل أنت متأكد من حذف هذا الشيت؟')">
                                    <i class='bx bx-trash'></i>
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="mt-4 border-t pt-3 text-center text-gray-400 text-sm">
                    <i class='bx bx-file-blank mr-1'></i>
                    لا توجد شيتات
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: 
        // التحقق من السبب
        $check_support = $conn->query("SELECT COUNT(*) as total FROM admins WHERE role = 'support'");
        $support_count = $check_support ? $check_support->fetch_assoc()['total'] : 0;
    ?>
    <div class="bg-white rounded-lg shadow p-8 text-center">
        <i class='bx bx-headphone text-6xl text-gray-300 mb-4'></i>
        <p class="text-gray-500 text-lg mb-2">لا يوجد موظفين دعم فني حالياً</p>
        
        <?php if ($support_count == 0): 
            // التحقق من وجود الجداول
            $check_stats_table = $conn->query("SHOW TABLES LIKE 'support_stats'");
            $stats_table_exists = $check_stats_table && $check_stats_table->num_rows > 0;
        ?>
            <p class="text-orange-500 text-sm mb-4">
                <i class='bx bx-info-circle mr-1'></i>
                لم يتم إضافة أي موظف دعم فني بعد
            </p>
            <?php if (!$stats_table_exists): ?>
                <p class="text-red-500 text-sm mb-4">
                    <i class='bx bx-error-circle mr-1'></i>
                    جداول قاعدة البيانات غير موجودة
                </p>
                <a href="setup_support_tables.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 mr-2">
                    <i class='bx bx-cog mr-1'></i>
                    إعداد الجداول
                </a>
            <?php endif; ?>
            <a href="?page=admins" class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                <i class='bx bx-plus mr-1'></i>
                إضافة موظف دعم فني
            </a>
        <?php else: ?>
            <p class="text-red-500 text-sm mb-4">
                <i class='bx bx-error-circle mr-1'></i>
                يوجد <?= $support_count ?> موظف في قاعدة البيانات لكن لا يمكن عرضهم
            </p>
            <a href="debug_supervisor.php" target="_blank" class="inline-block bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                <i class='bx bx-bug mr-1'></i>
                تشخيص المشكلة
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal التفاصيل الشاملة -->
<div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-lg p-6 w-full max-w-5xl m-4">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6 border-b pb-4">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-gradient-to-r from-orange-500 to-orange-600 rounded-full flex items-center justify-center mr-4">
                    <i class='bx bx-headphone text-3xl text-white'></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-800" id="detailName">اسم الموظف</h3>
                    <p class="text-gray-500" id="detailUsername">@username</p>
                </div>
            </div>
            <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 text-3xl">
                <i class='bx bx-x'></i>
            </button>
        </div>
        
        <!-- فلاتر الفترة الزمنية -->
        <div class="mb-6">
            <h4 class="text-lg font-semibold text-gray-700 mb-3">
                <i class='bx bx-calendar mr-2'></i>
                فلترة حسب الفترة الزمنية
            </h4>
            <div class="flex flex-wrap gap-2">
                <button onclick="filterPeriod('all')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="all">
                    <i class='bx bx-calendar-check mr-1'></i> الكل
                </button>
                <button onclick="filterPeriod('1day')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="1day">
                    <i class='bx bx-calendar-day mr-1'></i> يوم
                </button>
                <button onclick="filterPeriod('1week')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="1week">
                    <i class='bx bx-calendar-week mr-1'></i> أسبوع
                </button>
                <button onclick="filterPeriod('1month')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="1month">
                    <i class='bx bx-calendar-month mr-1'></i> شهر
                </button>
                <button onclick="filterPeriod('3months')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="3months">
                    <i class='bx bx-calendar mr-1'></i> 3 شهور
                </button>
                <button onclick="filterPeriod('6months')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="6months">
                    <i class='bx bx-calendar mr-1'></i> 6 شهور
                </button>
                <button onclick="filterPeriod('9months')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="9months">
                    <i class='bx bx-calendar mr-1'></i> 9 شهور
                </button>
                <button onclick="filterPeriod('1year')" class="period-btn px-4 py-2 rounded-lg bg-gray-100 hover:bg-orange-100 text-gray-700 hover:text-orange-700 transition border border-gray-200" data-period="1year">
                    <i class='bx bx-calendar-star mr-1'></i> سنة
                </button>
            </div>
        </div>
        
        <!-- أزرار التقرير -->
        <div class="mb-6 flex gap-3">
            <button onclick="generateFullReport()" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition flex items-center justify-center shadow-lg">
                <i class='bx bx-file-text mr-2 text-xl'></i>
                <span class="font-bold">عرض التقرير الكامل</span>
            </button>
            <button onclick="exportReport('excel')" class="px-4 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center" title="تنزيل Excel">
                <i class='bx bx-download mr-1'></i>
                Excel
            </button>
            <button onclick="exportReport('pdf')" class="px-4 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600 transition flex items-center" title="تنزيل PDF">
                <i class='bx bx-download mr-1'></i>
                PDF
            </button>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- القسم الأيمن: الرسم البياني والإحصائيات -->
            <div>
                <!-- Pie Chart -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3 text-center">
                        <i class='bx bx-pie-chart-alt-2 mr-2 text-orange-500'></i>
                        توزيع نسب الأداء
                    </h4>
                    <div class="w-64 h-64 mx-auto">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <div class="flex justify-center gap-4 mt-4 text-sm">
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                            <span>تأكيد</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-blue-500 rounded-full mr-1"></span>
                            <span>تسليم</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-3 h-3 bg-red-500 rounded-full mr-1"></span>
                            <span>إلغاء</span>
                        </div>
                    </div>
                </div>
                
                <!-- بطاقات الإحصائيات التفصيلية -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-green-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">نسبة التأكيد</p>
                        <p class="text-2xl font-bold text-green-600" id="detailConfirmRate">0%</p>
                        <p class="text-xs text-green-600 mt-1" id="detailConfirmCount">0 طلب</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">نسبة التسليم</p>
                        <p class="text-2xl font-bold text-blue-600" id="detailDeliveryRate">0%</p>
                        <p class="text-xs text-blue-600 mt-1" id="detailDeliveryCount">0 طلب</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">نسبة الإلغاء</p>
                        <p class="text-2xl font-bold text-red-600" id="detailCancelRate">0%</p>
                        <p class="text-xs text-red-600 mt-1" id="detailCancelCount">0 طلب</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">إجمالي الطلبات</p>
                        <p class="text-2xl font-bold text-purple-600" id="detailTotalOrders">0</p>
                        <p class="text-xs text-purple-600 mt-1">الفترة المحددة</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-600 mb-1">مدة المكالمات</p>
                        <p class="text-2xl font-bold text-orange-600" id="detailCallDuration">0ث</p>
                        <p class="text-xs text-orange-600 mt-1">إجمالي وقت المكالمات</p>
                    </div>
                </div>

                <!-- سجل المكالمات داخل التفاصيل -->
                <div class="bg-gray-50 rounded-lg p-4 mt-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">
                        <i class='bx bx-phone-call mr-2 text-green-600'></i>
                        سجل المكالمات
                    </h4>
                    <div id="detailCallsLog" class="space-y-2 max-h-48 overflow-y-auto text-sm">
                        <p class="text-gray-400 text-center py-4">جاري التحميل...</p>
                    </div>
                </div>
            </div>
            
            <!-- القسم الأيسر: رفع الشيت والشيتات -->
            <div>
                <!-- رفع شيت جديد -->
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 mb-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">
                        <i class='bx bx-upload mr-2 text-blue-500'></i>
                        رفع شيت جديد
                    </h4>
                    <form method="POST" enctype="multipart/form-data" action="admin_panel.php?page=supervisor">
                        <?= csrf_field() ?>
                        <input type="hidden" name="support_id" id="detailSupportId">
                        
                        <div class="mb-3">
                            <input type="text" name="sheet_name" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
                                   placeholder="اسم الشيت">
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
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">
                        <i class='bx bx-file mr-2 text-orange-500'></i>
                        الشيتات الخاصة بالموظف
                    </h4>
                    <div id="sheetsList" class="space-y-2 max-h-60 overflow-y-auto">
                        <!-- سيتم ملؤها بالJavaScript -->
                        <p class="text-gray-400 text-center py-4">جاري تحميل الشيتات...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let performanceChart = null;
let currentSupportId = null;

function openDetailModal(id, fullname, username, confirmRate, deliveryRate, cancelRate, totalOrders, completedOrders, cancelledOrders) {
    currentSupportId = id;
    
    // تعبئة البيانات الأساسية
    document.getElementById('detailSupportId').value = id;
    document.getElementById('detailName').textContent = fullname;
    document.getElementById('detailUsername').textContent = '@' + username;
    
    // تحديث الإحصائيات
    updateStats(confirmRate, deliveryRate, cancelRate, totalOrders, completedOrders, cancelledOrders);
    
    // إنشاء الرسم البياني
    createPieChart(confirmRate, deliveryRate, cancelRate);
    
    // تحميل الشيتات
    loadSheets(id);
    loadDetailCalls(id, 'all');
    
    // عرض الـ Modal
    document.getElementById('detailModal').classList.remove('hidden');
    document.getElementById('detailModal').classList.add('flex');
    
    // تعيين الفلتر الافتراضي للكل
    filterPeriod('all');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
    document.getElementById('detailModal').classList.remove('flex');
    
    // تدمير الرسم البياني لإعادة إنشائه لاحقاً
    if (performanceChart) {
        performanceChart.destroy();
        performanceChart = null;
    }
}

function updateStats(confirmRate, deliveryRate, cancelRate, totalOrders, completedOrders, cancelledOrders) {
    document.getElementById('detailConfirmRate').textContent = confirmRate.toFixed(1) + '%';
    document.getElementById('detailConfirmCount').textContent = Math.round(totalOrders * confirmRate / 100) + ' طلب';
    
    document.getElementById('detailDeliveryRate').textContent = deliveryRate.toFixed(1) + '%';
    document.getElementById('detailDeliveryCount').textContent = completedOrders + ' طلب';
    
    document.getElementById('detailCancelRate').textContent = cancelRate.toFixed(1) + '%';
    document.getElementById('detailCancelCount').textContent = cancelledOrders + ' طلب';
    
    document.getElementById('detailTotalOrders').textContent = totalOrders;
}

function createPieChart(confirmRate, deliveryRate, cancelRate) {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    if (performanceChart) {
        performanceChart.destroy();
    }
    
    performanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['نسبة التأكيد', 'نسبة التسليم', 'نسبة الإلغاء'],
            datasets: [{
                data: [confirmRate, deliveryRate, cancelRate],
                backgroundColor: [
                    '#22c55e', // green-500
                    '#3b82f6', // blue-500
                    '#ef4444'  // red-500
                ],
                borderColor: [
                    '#16a34a',
                    '#2563eb',
                    '#dc2626'
                ],
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '60%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed.toFixed(1) + '%';
                        }
                    }
                }
            },
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });
}

function loadDetailCalls(supportId, period) {
    const box = document.getElementById('detailCallsLog');
    if (!box) return;
    box.innerHTML = '<p class="text-gray-400 text-center py-4"><i class="bx bx-loader-alt bx-spin mr-2"></i>جاري التحميل...</p>';

    fetch(`support_activity_api.php?action=get_calls&support_id=${supportId}&period=${period || 'all'}&limit=30`, { credentials: 'include' })
        .then(r => r.json())
        .then(calls => {
            if (!Array.isArray(calls) || calls.length === 0) {
                box.innerHTML = '<p class="text-gray-400 text-center py-4">لا توجد مكالمات في هذه الفترة</p>';
                return;
            }
            box.innerHTML = calls.map(c => {
                const badge = c.status === 'active'
                    ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">جارية</span>'
                    : (c.status === 'completed'
                        ? `<span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">${c.duration_formatted || ''}</span>`
                        : '<span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded">ملغاة</span>');
                return `<div class="flex items-center justify-between p-2 bg-white rounded border-r-4 ${c.status === 'active' ? 'border-green-500' : 'border-blue-400'}">
                    <div>
                        <p class="font-medium text-sm">${c.client_name || '—'}</p>
                        <p class="text-xs text-gray-500">📞 ${c.client_phone || '—'}</p>
                        <p class="text-xs text-gray-400">${c.started_at || ''}</p>
                    </div>
                    <div>${badge}</div>
                </div>`;
            }).join('');
        })
        .catch(() => {
            box.innerHTML = '<p class="text-red-500 text-center py-4 text-sm">تعذر تحميل سجل المكالمات</p>';
        });
}

function loadSheets(supportId) {
    const sheetsList = document.getElementById('sheetsList');
    sheetsList.innerHTML = '<p class="text-gray-400 text-center py-4"><i class="bx bx-loader-alt bx-spin mr-2"></i>جاري تحميل الشيتات...</p>';
    
    // طلب AJAX لجلب الشيتات
    fetch(`get_support_sheets.php?support_id=${supportId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sheets.length > 0) {
                let html = '';
                data.sheets.forEach(sheet => {
                    const uploadDate = new Date(sheet.created_at).toLocaleDateString('ar-EG');
                    html += `
                        <div class="flex items-center justify-between p-3 bg-white rounded border hover:shadow-md transition">
                            <div class="flex items-center">
                                <i class='bx bx-file text-blue-500 mr-2 text-xl'></i>
                                <div>
                                    <p class="font-medium text-sm">${sheet.sheet_name}</p>
                                    <p class="text-xs text-gray-400">${uploadDate}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="uploads/support_sheets/${sheet.file_name}" 
                                   target="_blank" 
                                   class="text-blue-500 hover:text-blue-700 p-1 rounded hover:bg-blue-50"
                                   title="عرض الشيت">
                                    <i class='bx bx-show text-lg'></i>
                                </a>
                                <button onclick="deleteSheet(${sheet.id}, ${supportId})" 
                                        class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50"
                                        title="حذف الشيت">
                                    <i class='bx bx-trash text-lg'></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                sheetsList.innerHTML = html;
            } else {
                sheetsList.innerHTML = `
                    <div class="text-center py-6">
                        <i class='bx bx-file-blank text-4xl text-gray-300 mb-2'></i>
                        <p class="text-gray-400 text-sm">لا توجد شيتات لهذا الموظف</p>
                        <p class="text-gray-400 text-xs mt-1">يمكنك رفع شيت جديد من الأعلى</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading sheets:', error);
            sheetsList.innerHTML = `
                <div class="text-center py-4">
                    <i class='bx bx-error text-red-500 text-2xl mb-2'></i>
                    <p class="text-red-500 text-sm">حدث خطأ في تحميل الشيتات</p>
                    <button onclick="loadSheets(${supportId})" class="text-blue-500 text-xs mt-2 underline">
                        إعادة المحاولة
                    </button>
                </div>
            `;
        });
}

function deleteSheet(sheetId, supportId) {
    if (!confirm('هل أنت متأكد من حذف هذا الشيت؟')) {
        return;
    }
    
    fetch(`delete_support_sheet.php?sheet_id=${sheetId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إعادة تحميل قائمة الشيتات
                loadSheets(supportId);
                // إظهار رسالة نجاح
                const sheetsList = document.getElementById('sheetsList');
                const successMsg = document.createElement('div');
                successMsg.className = 'bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded text-sm mb-2';
                successMsg.innerHTML = '<i class="bx bx-check-circle mr-1"></i> تم حذف الشيت بنجاح';
                sheetsList.insertBefore(successMsg, sheetsList.firstChild);
                setTimeout(() => successMsg.remove(), 3000);
            } else {
                alert('فشل في حذف الشيت: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error deleting sheet:', error);
            alert('حدث خطأ في حذف الشيت');
        });
}

// إغلاق الـ Modal عند النقر خارجها
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailModal();
    }
});

// متغيرات التقرير
let currentPeriod = 'all';
let currentReportData = null;
let currentEmployeeName = '';

function filterPeriod(period) {
    currentPeriod = period;
    
    // تحديث الأزرار النشطة
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('bg-orange-500', 'text-white', 'border-orange-500');
        btn.classList.add('bg-gray-100', 'text-gray-700', 'border-gray-200');
    });
    
    const activeBtn = document.querySelector(`[data-period="${period}"]`);
    if (activeBtn) {
        activeBtn.classList.remove('bg-gray-100', 'text-gray-700', 'border-gray-200');
        activeBtn.classList.add('bg-orange-500', 'text-white', 'border-orange-500');
    }
    
    // تحميل البيانات الحقيقية حسب الفترة
    loadRealReportData(period);
}

function loadRealReportData(period) {
    const supportId = currentSupportId;
    
    // إظهار مؤشر التحميل
    const statsContainer = document.querySelector('#detailModal .grid.grid-cols-3');
    if (statsContainer) {
        statsContainer.style.opacity = '0.5';
    }
    
    // طلب AJAX لجلب البيانات الحقيقية
    fetch(`get_support_report.php?support_id=${supportId}&period=${period}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                currentReportData = result.data;
                
                // تحديث الإحصائيات المعروضة
                updateStats(
                    currentReportData.confirmation_rate,
                    currentReportData.delivery_rate,
                    currentReportData.cancellation_rate,
                    currentReportData.total_orders,
                    currentReportData.delivered_orders,
                    currentReportData.cancelled_orders
                );
                createPieChart(
                    currentReportData.confirmation_rate,
                    currentReportData.delivery_rate,
                    currentReportData.cancellation_rate
                );

                const callDurEl = document.getElementById('detailCallDuration');
                if (callDurEl) {
                    callDurEl.textContent = currentReportData.call_duration_formatted || '0ث';
                }
                loadDetailCalls(supportId, period);
            }
            
            if (statsContainer) {
                statsContainer.style.opacity = '1';
            }
        })
        .catch(error => {
            console.error('Error loading report data:', error);
            if (statsContainer) {
                statsContainer.style.opacity = '1';
            }
        });
}

function generateFullReport() {
    // استخدام البيانات الحقيقية أو القيم الافتراضية
    const data = currentReportData || {
        total_orders: 0,
        confirmed_orders: 0,
        delivered_orders: 0,
        cancelled_orders: 0,
        confirmation_rate: 0,
        delivery_rate: 0,
        cancellation_rate: 0,
        call_duration: 0,
        customer_satisfaction: 0,
        sheets_count: 0
    };
    
    const periodLabel = getPeriodLabel(currentPeriod);
    const employeeName = document.getElementById('detailName').textContent;
    
    const reportHTML = `
        <div id="fullReportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl m-4 max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6 border-b pb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            <i class='bx bx-file-text mr-2 text-green-500'></i>
                            التقرير الكامل
                        </h2>
                        <p class="text-gray-500 mt-1">الموظف: <span id="reportEmployeeName"></span> | الفترة: ${data.period}</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="downloadReport('excel')" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            <i class='bx bx-download mr-1'></i> Excel
                        </button>
                        <button onclick="downloadReport('pdf')" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                            <i class='bx bx-download mr-1'></i> PDF
                        </button>
                        <button onclick="closeFullReport()" class="text-gray-400 hover:text-gray-600 text-2xl">
                            <i class='bx bx-x'></i>
                        </button>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <i class='bx bx-package text-3xl text-blue-500 mb-2'></i>
                        <p class="text-sm text-gray-600">إجمالي الطلبات</p>
                        <p class="text-2xl font-bold text-blue-600">${data.total_orders}</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <i class='bx bx-check-circle text-3xl text-green-500 mb-2'></i>
                        <p class="text-sm text-gray-600">طلبات مؤكدة</p>
                        <p class="text-2xl font-bold text-green-600">${data.confirmed_orders}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                        <i class='bx bx-check-double text-3xl text-purple-500 mb-2'></i>
                        <p class="text-sm text-gray-600">طلبات مكتملة</p>
                        <p class="text-2xl font-bold text-purple-600">${data.delivered_orders}</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4 text-center">
                        <i class='bx bx-x-circle text-3xl text-red-500 mb-2'></i>
                        <p class="text-sm text-gray-600">طلبات ملغاة</p>
                        <p class="text-2xl font-bold text-red-600">${data.cancelled_orders}</p>
                    </div>
                </div>
                
                <!-- Performance Metrics -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class='bx bx-trending-up mr-2 text-orange-500'></i>
                        مؤشرات الأداء
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg p-4 shadow">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-gray-600">نسبة التأكيد</span>
                                <span class="text-green-600 font-bold">${data.confirmation_rate}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: ${data.confirmation_rate}%"></div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-gray-600">نسبة التسليم</span>
                                <span class="text-blue-600 font-bold">${data.delivery_rate}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: ${data.delivery_rate}%"></div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 shadow">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-gray-600">نسبة الإلغاء</span>
                                <span class="text-red-600 font-bold">${data.cancellation_rate}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" style="width: ${data.cancellation_rate}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <i class='bx bx-time text-2xl text-orange-500 mb-2'></i>
                        <p class="text-sm text-gray-600">مدة المكالمات</p>
                        <p class="text-xl font-bold text-gray-800">${data.call_duration_formatted || '0ث'}</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <i class='bx bx-star text-2xl text-yellow-500 mb-2'></i>
                        <p class="text-sm text-gray-600">تقييم العملاء</p>
                        <p class="text-xl font-bold text-gray-800">${data.customer_satisfaction}/5</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <i class='bx bx-file text-2xl text-blue-500 mb-2'></i>
                        <p class="text-sm text-gray-600">عدد الشيتات المرفوعة</p>
                        <p class="text-xl font-bold text-gray-800">${data.sheets_count}</p>
                    </div>
                </div>
                
                <!-- Chart Section -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 text-center">الرسم البياني للأداء</h3>
                    <div class="w-72 h-72 mx-auto">
                        <canvas id="reportChart"></canvas>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center text-gray-400 text-sm border-t pt-4">
                    <p>تم إنشاء هذا التقرير بواسطة نظام إدارة الدعم الفني | ${new Date().toLocaleDateString('ar-EG')}</p>
                </div>
            </div>
        </div>
    `;
    
    // إضافة الـ Modal للصفحة
    const existingModal = document.getElementById('fullReportModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', reportHTML);
    
    // إضافة اسم الموظف
    document.getElementById('reportEmployeeName').textContent = document.getElementById('detailName').textContent;
    
    // رسم الـ Chart
    setTimeout(() => {
        const ctx = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['تأكيد', 'تسليم', 'إلغاء'],
                datasets: [{
                    data: [data.confirmation_rate, data.delivery_rate, data.cancellation_rate],
                    backgroundColor: ['#22c55e', '#3b82f6', '#ef4444'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }, 100);
}

function closeFullReport() {
    const modal = document.getElementById('fullReportModal');
    if (modal) {
        modal.remove();
    }
}

function exportReport(format) {
    const supportId = currentSupportId;
    const period = currentPeriod;
    const employeeName = document.getElementById('detailName').textContent;
    const data = currentReportData || {};
    
    if (format === 'excel') {
        // تحويل البيانات لـ Excel/CSV
        const reportData = [
            ['التقرير الكامل للموظف', employeeName],
            ['الفترة', getPeriodLabel(period)],
            ['تاريخ التقرير', new Date().toLocaleDateString('ar-EG')],
            [''],
            ['المؤشر', 'القيمة'],
            ['إجمالي الطلبات (من الشيتات)', data.total_orders || 0],
            ['الطلبات المؤكدة', data.confirmed_orders || 0],
            ['الطلبات المكتملة (مسلمة)', data.delivered_orders || 0],
            ['الطلبات الملغاة', data.cancelled_orders || 0],
            ['نسبة التأكيد', (data.confirmation_rate || 0) + '%'],
            ['نسبة التسليم', (data.delivery_rate || 0) + '%'],
            ['نسبة الإلغاء', (data.cancellation_rate || 0) + '%'],
            ['مدة المكالمات', (data.call_duration_formatted || '0ث')],
            ['تقييم العملاء', (data.customer_satisfaction || 0) + '/5'],
            ['عدد الشيتات المرفوعة', data.sheets_count || 0]
        ];
        
        // إنشاء CSV
        let csv = '\ufeff'; // BOM for UTF-8
        reportData.forEach(row => {
            csv += row.map(cell => `"${cell}"`).join(',') + '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `تقرير_${employeeName}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
    } else if (format === 'pdf') {
        // إنشاء PDF باستخدام html2pdf.js (يدعم العربية)
        // إنشاء عنصر HTML مؤقت للتقرير
        const tempDiv = document.createElement('div');
        tempDiv.style.cssText = 'width: 800px; padding: 40px; background: white; font-family: Arial, sans-serif; direction: rtl;';
        
        const reportDate = new Date().toLocaleDateString('ar-EG');
        
        tempDiv.innerHTML = `
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid #3b82f6; padding-bottom: 20px;">
                <h1 style="color: #1f2937; font-size: 28px; margin: 0;">📊 التقرير الكامل</h1>
                <p style="color: #6b7280; margin-top: 10px; font-size: 16px;">
                    الموظف: <strong>${employeeName}</strong> | 
                    الفترة: <strong>${getPeriodLabel(period)}</strong> | 
                    التاريخ: <strong>${reportDate}</strong>
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
                <div style="background: #dbeafe; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 5px;">📦</div>
                    <div style="color: #6b7280; font-size: 14px;">إجمالي الطلبات</div>
                    <div style="color: #2563eb; font-size: 24px; font-weight: bold;">${data.total_orders || 0}</div>
                </div>
                <div style="background: #dcfce7; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 5px;">✅</div>
                    <div style="color: #6b7280; font-size: 14px;">طلبات مؤكدة</div>
                    <div style="color: #16a34a; font-size: 24px; font-weight: bold;">${data.confirmed_orders || 0}</div>
                </div>
                <div style="background: #e9d5ff; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 5px;">🚚</div>
                    <div style="color: #6b7280; font-size: 14px;">طلبات مسلمة</div>
                    <div style="color: #9333ea; font-size: 24px; font-weight: bold;">${data.delivered_orders || 0}</div>
                </div>
                <div style="background: #fee2e2; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 5px;">❌</div>
                    <div style="color: #6b7280; font-size: 14px;">طلبات ملغاة</div>
                    <div style="color: #dc2626; font-size: 24px; font-weight: bold;">${data.cancelled_orders || 0}</div>
                </div>
            </div>
            
            <div style="background: #f9fafb; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h3 style="color: #1f2937; margin-bottom: 20px; font-size: 18px;">📈 مؤشرات الأداء</h3>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #6b7280;">نسبة التأكيد</span>
                            <span style="color: #16a34a; font-weight: bold;">${data.confirmation_rate || 0}%</span>
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px;">
                            <div style="background: #22c55e; height: 100%; border-radius: 4px; width: ${data.confirmation_rate || 0}%;"></div>
                        </div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #6b7280;">نسبة التسليم</span>
                            <span style="color: #2563eb; font-weight: bold;">${data.delivery_rate || 0}%</span>
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px;">
                            <div style="background: #3b82f6; height: 100%; border-radius: 4px; width: ${data.delivery_rate || 0}%;"></div>
                        </div>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #6b7280;">نسبة الإلغاء</span>
                            <span style="color: #dc2626; font-weight: bold;">${data.cancellation_rate || 0}%</span>
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px;">
                            <div style="background: #ef4444; height: 100%; border-radius: 4px; width: ${data.cancellation_rate || 0}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; margin-bottom: 5px;">⏱️</div>
                    <div style="color: #6b7280; font-size: 14px;">مدة المكالمات</div>
                    <div style="color: #1f2937; font-size: 20px; font-weight: bold;">${data.call_duration_formatted || '0ث'}</div>
                </div>
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; margin-bottom: 5px;">⭐</div>
                    <div style="color: #6b7280; font-size: 14px;">تقييم العملاء</div>
                    <div style="color: #1f2937; font-size: 20px; font-weight: bold;">${data.customer_satisfaction || 0}/5</div>
                </div>
                <div style="border: 1px solid #e5e7eb; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; margin-bottom: 5px;">📄</div>
                    <div style="color: #6b7280; font-size: 14px;">عدد الشيتات</div>
                    <div style="color: #1f2937; font-size: 20px; font-weight: bold;">${data.sheets_count || 0}</div>
                </div>
            </div>
            
            <div style="text-align: center; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px;">
                تم إنشاء هذا التقرير بواسطة نظام إدارة الدعم الفني | ${reportDate}
            </div>
        `;
        
        // إضافة العنصر للصفحة مؤقتاً
        document.body.appendChild(tempDiv);
        
        // إعدادات التصدير
        const opt = {
            margin: 10,
            filename: `تقرير_${employeeName}_${new Date().toISOString().split('T')[0]}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait'
            }
        };
        
        // تصدير PDF
        html2pdf().set(opt).from(tempDiv).save().then(() => {
            // إزالة العنصر المؤقت
            document.body.removeChild(tempDiv);
        });
    }
}

function downloadReport(format) {
    exportReport(format);
}

function getPeriodLabel(period) {
    const labels = {
        'all': 'الكل',
        '1day': 'يوم واحد',
        '1week': 'أسبوع',
        '1month': 'شهر',
        '3months': '3 شهور',
        '6months': '6 شهور',
        '9months': '9 شهور',
        '1year': 'سنة'
    };
    return labels[period] || period;
}

function updateSupervisorLiveData() {
    fetch('support_activity_api.php?action=get_activity', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(users) {
            if (!Array.isArray(users)) return;
            users.forEach(function(user) {
                var box = document.getElementById('activity-box-' + user.id);
                var dot = document.getElementById('activity-dot-' + user.id);
                var statusEl = document.getElementById('activity-status-' + user.id);
                var durationEl = document.getElementById('activity-duration-' + user.id);
                var lastEl = document.getElementById('activity-lastseen-' + user.id);
                var sessionEl = document.getElementById('activity-session-' + user.id);
                if (!box || !statusEl) return;

                if (user.is_online) {
                    box.className = 'mb-4 p-3 rounded-lg ' + (user.is_active ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200');
                    if (dot) dot.className = 'w-3 h-3 rounded-full mr-2 ' + (user.is_active ? 'bg-green-500 animate-pulse' : 'bg-yellow-500');
                    statusEl.className = 'text-sm font-semibold ' + (user.is_active ? 'text-green-700' : 'text-yellow-700');
                    statusEl.textContent = user.is_active ? '🟢 متصل — نشط' : '🟡 متصل — خمول';
                    if (lastEl) lastEl.textContent = 'متصل الآن';
                } else {
                    box.className = 'mb-4 p-3 rounded-lg bg-gray-50 border border-gray-200';
                    if (dot) dot.className = 'w-3 h-3 rounded-full mr-2 bg-gray-400';
                    statusEl.className = 'text-sm font-semibold text-gray-600';
                    statusEl.textContent = '⚪ غير متصل';
                    if (lastEl) {
                        lastEl.textContent = user.last_seen && user.last_seen !== '—'
                            ? 'آخر نشاط: ' + user.last_seen + ' (منذ ' + (user.last_seen_ago || '') + ')'
                            : 'لا يوجد نشاط مسجل';
                    }
                }
                if (durationEl) durationEl.textContent = 'نشاط: ' + (user.active_time_formatted || '0ث');
                if (sessionEl) sessionEl.textContent = 'مدة الجلسة: ' + (user.session_time_formatted || '0ث');
            });
        }).catch(function() {});
}

updateSupervisorLiveData();
setInterval(updateSupervisorLiveData, 10000);
</script>
