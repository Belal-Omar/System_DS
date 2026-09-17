<?php
/** @var mysqli $conn */
// Trigger sync 2
// admin_support.php - صفحة التواصل والدعم
// يتم تضمينها من admin_panel.php

$current_role = $_SESSION['admin_role'] ?? '';
$is_manager = ($current_role === 'super_admin')
    || (function_exists('admin_can_access_page') && admin_can_access_page('support', $current_role, $_SESSION['admin_allowed_pages'] ?? null) && $current_role !== 'support');
$is_support = ($current_role === 'support');

if (!$is_manager && !$is_support) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded'>غير مصرح</div>";
    return;
}

// التأكد من وجود الأعمدة الجديدة
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS force_locked TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS unlocked_at DATETIME NULL");

$support_id = $_SESSION['admin_id'] ?? 0;
$support_name = $_SESSION['admin_username'] ?? 'unknown';
$view_mode = $_GET['view_sheet'] ?? null;

$product_pricing_map = [];
$sheet_product_code = 'Etala001';
$sheet_price_single = ['total_price' => 150];
$sheet_price_bundle = ['total_price' => 285];

if (!function_exists('support_render_status_select')) {
    function support_render_status_select($name, $selected = '', $extra_attrs = '') {
        $role = $_SESSION['admin_role'] ?? '';
        $is_locked_received = ($role === 'support' && in_array((string) $selected, ['received', 'delivered'], true));

        // لو المدير عيّن تم الاستلام: الدعم يشوفها مقفولة فقط
        if ($is_locked_received) {
            $html = '<div class="w-full p-2 border rounded text-sm bg-emerald-50 text-emerald-800 font-bold text-center">🟢 تم الاستلام</div>';
            $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="received">';
            $html .= '<p class="text-[10px] text-slate-500 mt-1 text-center">لا يمكن تغييرها من الدعم</p>';
            return $html;
        }

        $options = [
            '' => '-- اختر الحالة --',
            'pending' => '⏳ انتظار / لم يتم التواصل',
            'no_answer_1' => '🟡 لم يتم الرد - محاولة أولى',
            'no_answer_2' => '🟡 لم يتم الرد - محاولة تانية',
            'no_answer_3' => '🟡 لم يتم الرد - محاولة أخيرة',
            'contact_later' => '⚪ سيتم التواصل لاحقاً',
            'wrong_number' => '🔴 الرقم غلط',
            'order_confirmed' => '✅ تم تأكيد الطلب',
            'ready_to_ship' => '🔵 جاهز للشحن',
            'order_cancelled' => '❌ العميل الغى الطلب',
        ];
        // الدعم الفني: أقصى حاجة تأكيد الطلب — بدون تم الاستلام
        if ($role !== 'support') {
            $options['received'] = '🟢 تم الاستلام';
        }

        $html = '<select name="' . htmlspecialchars($name) . '" class="w-full p-2 border rounded text-sm order-status-select" ' . $extra_attrs . '>';
        foreach ($options as $val => $label) {
            $sel = ((string) $selected === (string) $val) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

// حفظ الأوردرات يتم في admin_panel.php عبر admin_support_post_early.php قبل أي HTML

if ($is_manager) {
    $sheets = $conn->query("
        SELECT s.*, a.fullname as employee_name,
        (SELECT COUNT(*) FROM support_orders o1 WHERE sheet_id = s.id) as saved_orders_count
        FROM support_sheets s
        JOIN admins a ON s.support_id = a.id
        ORDER BY s.created_at DESC
    ");
} else {
    $sheets = $conn->query("
        SELECT * FROM support_sheets 
        WHERE support_id = $support_id 
        ORDER BY created_at DESC
    ");
}

// عرض شيت محدد
$current_sheet = null;
if ($view_mode && is_numeric($view_mode)) {
    $sid = intval($view_mode);
    if ($is_manager) {
        $current_sheet = $conn->query("
            SELECT s.*, a.fullname as employee_name 
            FROM support_sheets s
            JOIN admins a ON s.support_id = a.id
            WHERE s.id = $sid
        ")->fetch_assoc();
    } else {
        $current_sheet = $conn->query("
            SELECT * FROM support_sheets 
            WHERE id = $sid AND support_id = $support_id
        ")->fetch_assoc();
        
        if ($current_sheet) {
            $time_now = time();
            $created_time = strtotime($current_sheet['created_at']);
            $unlocked_time = !empty($current_sheet['unlocked_at']) ? strtotime($current_sheet['unlocked_at']) : 0;
            $is_locked = false;
            if (!empty($current_sheet['force_locked'])) {
                $is_locked = true;
            } elseif ($time_now - $created_time > 86400) {
                if (empty($current_sheet['is_unlocked']) || ($time_now - $unlocked_time > 86400)) {
                    $is_locked = true;
                }
            }
            if ($is_locked) {
                echo "<div class='container mx-auto p-6'><div class='p-4 bg-red-100 border border-red-400 text-red-700 rounded text-center'><i class='bx bxs-lock-alt text-4xl mb-2'></i><br><strong>هذا الشيت مغلق!</strong><br>هذا الشيت مغلق حالياً. لا يمكنك الوصول إليه إلا بعد طلب فتحه من الإدارة.</div></div>";
                return;
            }
        }
    }
}

// جلب فريق الدعم للمدير
$team = [];
if ($is_manager) {
    $r = $conn->query("SELECT a.*, (SELECT COUNT(*) FROM support_sheets WHERE support_id=a.id) as sheets FROM admins a WHERE role='support'");
    if ($r) while ($row = $r->fetch_assoc()) $team[] = $row;
}

// حساب إحصائيات عامة للمدير
$total_stats = ['orders' => 0, 'members' => count($team), 'sheets' => 0];
if ($is_manager) {
    $stats_result = $conn->query("SELECT SUM(total_orders) as total FROM support_stats");
    if ($stats_result) {
        $total_stats['orders'] = $stats_result->fetch_assoc()['total'] ?? 0;
    }
    $sheets_result = $conn->query("SELECT COUNT(*) as total FROM support_sheets");
    if ($sheets_result) {
        $total_stats['sheets'] = $sheets_result->fetch_assoc()['total'] ?? 0;
    }
}

?>

<!-- حماية من السكرين شوت والتسجيل -->
<style>
@media print { body { display: none !important; } }
.security-alert {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: bold;
}
.watermark {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-30deg);
    font-size: 80px;
    color: rgba(255,0,0,0.08);
    font-weight: bold;
    pointer-events: none;
    z-index: 9998;
    text-transform: uppercase;
    letter-spacing: 15px;
    user-select: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
</script>

<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 text-gray-800">
        <i class='bx <?= $is_manager ? "bx-user-check" : "bx-file" ?> mr-2 text-blue-500'></i>
        <?= $is_manager ? "متابعة فريق الدعم الفني" : "الشيتات المرفوعة" ?>
    </h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['support_flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['support_flash_error'], ENT_QUOTES, 'UTF-8'); ?>
            <?php unset($_SESSION['support_flash_error']); ?>
        </div>
    <?php endif; ?>

<?php if ($is_support): 
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
?>
<!-- تتبع الاتصال والنشاط لموظف الدعم -->
<script>
window.filterSheetRows = function() {
    var filterValue = document.getElementById('sheet-status-filter').value;
    var rows = document.querySelectorAll('#orders-rows tr');
    
    rows.forEach(function(row) {
        if (filterValue === 'all') {
            row.style.display = '';
            return;
        }
        
        var select = row.querySelector('select[name$="[order_status]"]');
        if (!select) return;
        
        var status = select.value;
        if (filterValue === 'no_answer') {
            if (status === 'no_answer_1' || status === 'no_answer_2' || status === 'no_answer_3') {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
};

(function() {
    let lastActivity = Date.now();
    let totalActiveTime = 0;
    let isActive = true;
    let sessionStart = Date.now();
    const INACTIVE_LIMIT = 3 * 60 * 1000;

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart', 'mousedown', 'input'].forEach(function(event) {
        document.addEventListener(event, function() {
            var now = Date.now();
            if (now - lastActivity > INACTIVE_LIMIT && !isActive) {
                isActive = true;
            }
            lastActivity = now;
        }, { passive: true });
    });

    setInterval(function() {
        if (Date.now() - lastActivity > INACTIVE_LIMIT) {
            isActive = false;
        } else {
            totalActiveTime++;
        }
    }, 1000);

    function sendActivity() {
        fetch('support_activity_api.php?action=activity', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                active: isActive,
                total_active_seconds: totalActiveTime,
                session_seconds: Math.floor((Date.now() - sessionStart) / 1000),
                inactive_seconds: Math.floor((Date.now() - lastActivity) / 1000),
                status: isActive ? 'نشط' : 'خمول'
            })
        }).catch(function() {});
    }

    function sendOffline() {
        var blob = new Blob([JSON.stringify({})], { type: 'application/json' });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('support_activity_api.php?action=offline', blob);
        } else {
            fetch('support_activity_api.php?action=offline', { method: 'POST', credentials: 'include', keepalive: true });
        }
    }

    sendActivity();
    setInterval(sendActivity, 30000);
    window.addEventListener('beforeunload', sendOffline);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            sendActivity();
        }
    });
})();
</script>
<?php endif; ?>

<?php if ($current_sheet): ?>
    <!-- عرض شيت محدد -->
    <div class="mb-4">
        <a href="?page=support" class="inline-flex items-center bg-gray-200 px-4 py-2 rounded hover:bg-gray-300">
            <i class='bx bx-arrow-back mr-2'></i> العودة
        </a>
    </div>

    <?php if (!$is_manager): ?>
    <!-- علامة مائية مخفية -->
    <div class="watermark" style="opacity: 0.3;"><?= $_SESSION['admin_username'] ?? 'USER' ?></div>
    
    <!-- حماية من السكرين شوت والتسجيل -->
    <style>
    body.support-secure, body.support-secure * {
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        user-select: none !important;
        -webkit-touch-callout: none !important;
    }
    </style>
    <script>document.body.classList.add('support-secure');</script>
    
    <script>
    document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
    document.addEventListener('copy', function(e) { e.preventDefault(); });
    document.addEventListener('cut', function(e) { e.preventDefault(); });
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && ['c','v','s','p','a','x','u'].includes(e.key.toLowerCase())) { e.preventDefault(); }
        if (e.key === 'PrintScreen' || e.code === 'PrintScreen') {
            e.preventDefault();
            fetch('support_activity_api.php?action=screenshot', { method: 'POST', credentials: 'include' });
            document.body.style.filter = 'blur(20px)';
            setTimeout(function() { document.body.style.filter = ''; }, 1500);
        }
        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['i','j','c'].includes(e.key.toLowerCase()))) {
            e.preventDefault();
        }
    });
    document.addEventListener('keyup', function(e) {
        if (e.key === 'PrintScreen') {
            fetch('support_activity_api.php?action=screenshot', { method: 'POST', credentials: 'include' });
            document.body.style.filter = 'blur(20px)';
            setTimeout(function() { document.body.style.filter = ''; }, 1500);
        }
    });
    </script>
    
    <!-- علامة مائية -->
    <?php endif; ?>

    <!-- رسائل النجاح -->
    <?php if (isset($_GET['saved']) && $_GET['saved'] >= '1'): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
        <i class='bx bx-check-circle mr-2'></i>
        ✅ تم حفظ <?= htmlspecialchars($_GET['saved']) ?> أوردر بنجاح!
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <h3 class="font-bold text-lg"><?= htmlspecialchars($current_sheet['sheet_name']) ?></h3>
        <p class="text-gray-500 text-sm">
            تاريخ الرفع: <?= date('Y-m-d H:i', strtotime($current_sheet['created_at'])) ?> | 
            الأسماء في الملف الأصلي المرفوع: <strong class="text-gray-600"><?= (int) ($sheet_preview['full_count'] ?? 0) ?></strong> | 
            إجمالي الأوردرات بالشيت (شاملاً الإضافات): <strong class="text-blue-600 text-lg"><?= $current_sheet['names_count'] ?? 0 ?></strong>
        </p>
    </div>

    <?php
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }
    if (is_object($conn) && function_exists('support_orders_sync_schema')) {
        @support_orders_sync_schema($conn);
    }

    $sheet_preview = ['sheet_file' => '', 'headers' => [], 'data' => [], 'full_count' => 0, 'ram_truncated' => false, 'read_error' => null];
    try {
        if (function_exists('load_support_sheet_preview')) {
            $sheet_preview = load_support_sheet_preview($current_sheet['file_name'] ?? '');
        } else {
            $sheet_preview['read_error'] = 'تعذر تحميل قارئ الشيت. حدّث ملف helpers.php';
        }
    } catch (Throwable $e) {
        error_log('support sheet preview: ' . $e->getMessage());
        $sheet_preview['read_error'] = 'خطأ أثناء قراءة الشيت: ' . $e->getMessage();
    }

    $sheet_file = $sheet_preview['sheet_file'];
    $sheet_headers = $sheet_preview['headers'];
    $sheet_data = $sheet_preview['data'];
    $sheet_data_full_count = (int) $sheet_preview['full_count'];
    $sheet_read_error = $sheet_preview['read_error'] ?? null;
    $sheet_ram_truncated = (bool) ($sheet_preview['ram_truncated'] ?? false);
    $sheet_data_for_preview = $sheet_data;

    $current_sheet_id = (int) ($current_sheet['id'] ?? 0);
    $sheet_owner_id = (int) ($current_sheet['support_id'] ?? $support_id);
    $auto_agent_code = (function_exists('get_support_agent_code') && $sheet_owner_id > 0)
        ? get_support_agent_code($conn, $sheet_owner_id)
        : '';

    // لو الشيت اترفع قبل الاستيراد التلقائي: أنزل الصفوف الآن
    if ($current_sheet_id > 0 && $sheet_owner_id > 0 && function_exists('import_support_sheet_orders_to_db')) {
        $fn = (string) ($current_sheet['file_name'] ?? '');
        if ($fn !== '') {
            @import_support_sheet_orders_to_db($conn, $current_sheet_id, $sheet_owner_id, $fn);
        }
    }

    $sheet_orders_list = [];
    $saved_orders_by_row = [];
    
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

    if ($current_sheet_id > 0 && is_object($conn)) {
        $orders_where = $is_manager
            ? "sheet_id = $current_sheet_id"
            : "sheet_id = $current_sheet_id AND support_id = $support_id";
        $orders_sql = "SELECT *, (SELECT COUNT(*) FROM support_orders o2 WHERE o2.phone = o1.phone AND o2.phone != '' AND o2.id < o1.id AND o2.order_status NOT IN ('delivered', 'received', 'cancelled', 'order_cancelled', 'return', 'returned')) as active_duplicates FROM support_orders o1 WHERE $orders_where ORDER BY id ASC";
        try {
            $orders_res = $conn->query($orders_sql);
            if (!$orders_res) {
                error_log('support orders query: ' . $conn->error);
            } elseif ($orders_res) {
                $phones_seen = []; // لمنع إضافة نفس الأوردر مكرراً كصف يتيم
                while ($o = $orders_res->fetch_assoc()) {
                    $sheet_orders_list[] = $o;
                    $ri = (int) ($o['sheet_row_index'] ?? -1);
                    $phone_key = $o['phone'] ?? '';
                    if ($ri >= 0 && !isset($saved_orders_by_row[$ri])) {
                        $saved_orders_by_row[$ri] = $o;
                        if ($phone_key !== '') $phones_seen[$phone_key] = true;
                    } elseif ($ri < 0 && $phone_key !== '' && !isset($phones_seen[$phone_key])) {
                        // أوردر بدون row_index ولأول مرة نشوف هذا الهاتف
                        $orphan_row = 0;
                        while ($orphan_row < 50 && isset($saved_orders_by_row[$orphan_row])) {
                            $orphan_row++;
                        }
                        if ($orphan_row < 50) {
                            $saved_orders_by_row[$orphan_row] = $o;
                        }
                        $phones_seen[$phone_key] = true;
                    }
                    // لو نفس phone_key ظهر قبل كده - تجاهل (تجنب التكرار)
                }
            }
        } catch (Throwable $e) {
            error_log('support orders load: ' . $e->getMessage());
        }
    }
    ?>
    

    
    <!-- 🔹 زر تحميل - للمدير الرئيسي فقط -->
    <?php if ($is_manager): ?>
    <div class="mt-4 text-left">
        <a href="support_sheet_download.php?id=<?= $current_sheet['id'] ?>" 
           class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class='bx bx-download mr-2'></i> تحميل الملف الأصلي
        </a>
    </div>
    <?php endif; ?>

    <?php if ($is_manager && !empty($sheet_orders_list)): ?>
    <div class="bg-white rounded-lg shadow p-4 mt-4 mb-4 border border-blue-200">
        <h4 class="font-bold text-blue-700 mb-3"><i class='bx bx-list-check'></i> أوردرات الشيت المحفوظة (<?= count($sheet_orders_list) ?>)</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-blue-50">
                    <tr>
                        <th class="py-2 px-2">#</th>
                        <th class="py-2 px-2 text-right">المستلم</th>
                        <th class="py-2 px-2 text-right">الهاتف</th>
                        <th class="py-2 px-2 text-center">الحالة</th>
                        <th class="py-2 px-2 text-right">المحافظة</th>
                        <th class="py-2 px-2 text-center">القطع</th>
                        <th class="py-2 px-2 text-center">كود المنتج</th>
                        <th class="py-2 px-2 text-center">المرسل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 1; foreach ($sheet_orders_list as $order):
                        $st = $order['order_status'] ?? '';
                        $details = function_exists('get_support_status_details') ? get_support_status_details($st) : ['ar' => $st];
                    ?>
                    <tr class="border-b">
                        <td class="py-2 px-2 text-center"><?= $n++ ?></td>
                        <td class="py-2 px-2">
                            <?= htmlspecialchars($order['recipient_name'] ?? $order['customer_name'] ?? '') ?>
                            <?php if (!empty($order['active_duplicates'])): ?>
                                <span class="inline-flex items-center gap-1 bg-red-100 text-red-800 text-[10px] px-1.5 py-0.5 rounded font-bold" title="يوجد طلب آخر قائم لم يتم الانتهاء منه لهذا الرقم">
                                    <i class='bx bx-error-circle'></i> طلب قائم
                                </span>
                            <?php endif; ?>
                            <?php if (is_customer_blocked($conn, $order['phone'] ?? '', $order['id'] ?? 0)): ?>
                                <span class="inline-flex items-center gap-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded font-bold mt-1" title="هذا العميل تم رفض آخر 3 طلبات له">
                                    <i class='bx bx-block'></i> مرفوض
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-2"><?= htmlspecialchars($order['phone'] ?? '') ?></td>
                        <td class="py-2 px-2 text-center"><span class="px-2 py-1 rounded text-xs bg-slate-100"><?= htmlspecialchars($details['ar']) ?></span></td>
                        <td class="py-2 px-2"><?= htmlspecialchars($order['governorate'] ?? '-') ?></td>
                        <td class="py-2 px-2 text-center"><?= (int)($order['pieces'] ?? 1) ?></td>
                        <td class="py-2 px-2 text-center font-mono text-xs"><?= htmlspecialchars($order['product_code'] ?? 'Etala001') ?></td>
                        <td class="py-2 px-2 text-center text-xs"><?= htmlspecialchars($order['support_name'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- 🔹🔹🔹 شيت أوردرات مرتبط بالشيت - للدعم الفني فقط 🔹🔹🔹 -->
    <?php if ($is_support): 
        // ليبيا - قائمة المحافظات
        $libyan_governorates = get_governorates_list($conn);
        $sheet_month = function_exists('get_support_sheet_month')
            ? get_support_sheet_month($conn, (int) ($current_sheet['id'] ?? 0))
            : date('Y-m', strtotime($current_sheet['created_at'] ?? 'now'));
        $sheet_product_options = function_exists('get_support_products_for_month')
            ? get_support_products_for_month($conn, $sheet_month)
            : [['product_code' => 'Etala001', 'product_name' => 'المنتج الافتراضي', 'product_sale_price' => 150, 'bundle_sale_price' => 285]];

        $sheet_product_code = $sheet_product_options[0]['product_code'] ?? 'Etala001';
        if (!empty($saved_orders_by_row)) {
            foreach ($saved_orders_by_row as $savedRow) {
                $savedCode = trim((string) ($savedRow['product_code'] ?? ''));
                if ($savedCode !== '') {
                    $sheet_product_code = $savedCode;
                    break;
                }
            }
        }

        $product_pricing_map = [];
        foreach ($sheet_product_options as $prodOpt) {
            $pcode = $prodOpt['product_code'];
            $singleP = function_exists('resolve_support_order_pricing')
                ? resolve_support_order_pricing($conn, 1, $pcode, $sheet_month)
                : ['total_price' => (float) ($prodOpt['product_sale_price'] ?? 150)];
            $bundleP = function_exists('resolve_support_order_pricing')
                ? resolve_support_order_pricing($conn, 3, $pcode, $sheet_month)
                : ['total_price' => (float) ($prodOpt['bundle_sale_price'] ?? 285)];
            $product_pricing_map[$pcode] = [
                'name' => $prodOpt['product_name'],
                'single' => (float) $singleP['total_price'],
                'bundle' => (float) $bundleP['total_price'],
            ];
        }
        if (!isset($product_pricing_map[$sheet_product_code])) {
            $sheet_product_code = array_key_first($product_pricing_map) ?: 'Etala001';
        }
        $sheet_price_single = ['total_price' => $product_pricing_map[$sheet_product_code]['single'] ?? 150];
        $sheet_price_bundle = ['total_price' => $product_pricing_map[$sheet_product_code]['bundle'] ?? 285];
    ?>
    <div class="bg-white rounded-lg shadow-lg p-6 mt-6 border-2 border-orange-300">
        <h3 class="text-xl font-bold mb-4 flex items-center text-orange-600">
            <i class='bx bx-cart-alt mr-2'></i>
            أوردرات الشيت: <?= htmlspecialchars($current_sheet['sheet_name']) ?>
        </h3>
        <div class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm">
            <div class="flex flex-wrap gap-4 items-end mb-3">
                <div class="min-w-[260px] flex-1">
                    <label class="block text-xs font-bold text-orange-900 mb-1" for="sheet-product-code">
                        <i class='bx bx-purchase-tag'></i> كود المنتج (أساس الطلب والحسابات)
                    </label>
                    <select name="orders_product_code" id="sheet-product-code"
                            onchange="onSheetProductChange()"
                            class="w-full p-2 border border-orange-300 rounded-lg bg-white font-mono text-sm focus:ring-2 focus:ring-orange-400">
                        <?php foreach ($sheet_product_options as $prodOpt):
                            $optCode = $prodOpt['product_code'];
                            $optSingle = $product_pricing_map[$optCode]['single'] ?? (float) $prodOpt['product_sale_price'];
                            $optBundle = $product_pricing_map[$optCode]['bundle'] ?? (float) $prodOpt['bundle_sale_price'];
                        ?>
                        <option value="<?= htmlspecialchars($optCode) ?>" <?= $sheet_product_code === $optCode ? 'selected' : '' ?>>
                            <?= htmlspecialchars($optCode) ?> — <?= htmlspecialchars($prodOpt['product_name']) ?>
                            (Single: <?= number_format($optSingle, 0) ?> / Bundle: <?= number_format($optBundle, 0) ?> دل)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[10px] text-slate-500 mt-1">المنتجات تُضاف من الحسابات → إعدادات المنتج لكل شهر</p>
                </div>
                <div class="min-w-[260px] flex-1">
                    <label class="block text-xs font-bold text-orange-900 mb-1" for="sheet-status-filter">
                        <i class='bx bx-filter-alt'></i> فلترة الطلبات
                    </label>
                    <select id="sheet-status-filter" onchange="filterSheetRows()"
                            class="w-full p-2 border border-orange-300 rounded-lg bg-white text-sm focus:ring-2 focus:ring-orange-400">
                        <option value="all">عرض الكل</option>
                        <option value="no_answer">لم يتم الرد (محاولة أولى/ثانية/أخيرة)</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 items-center" id="sheet-price-banner">
                <span class="font-bold text-orange-800">أسعار الشهر (<?= htmlspecialchars($sheet_month) ?>):</span>
                <span class="bg-white px-3 py-1 rounded-lg border border-orange-100" id="banner-single-price">
                    <strong>Single</strong> (1 قطعة): <?= number_format((float) $sheet_price_single['total_price'], 2) ?> دل
                </span>
                <span class="bg-white px-3 py-1 rounded-lg border border-orange-100" id="banner-bundle-price">
                    <strong>Bundle</strong> (3 قطع): <?= number_format((float) $sheet_price_bundle['total_price'], 2) ?> دل
                </span>
                <span class="text-xs text-slate-500">يُسحب تلقائياً عند الحفظ حسب المنتج والقطع</span>
            </div>
        </div>
        
        <!-- 🔹🔹 شيت إدخال الأوردرات (شكل جدول) 🔹🔹 -->
        <form method="POST" class="mb-6" id="orders-form">
            <?= csrf_field() ?>
            <input type="hidden" name="sheet_id" value="<?= $current_sheet['id'] ?>">
            <input type="hidden" name="view_sheet_id" value="<?= $current_sheet['id'] ?>">
            <input type="hidden" name="save_orders_batch_v2" value="1">
            
            <div class="border-2 border-orange-200 rounded-lg" style="max-height: 600px; overflow: scroll;">
                <div style="min-width: 1400px; padding: 8px;">
                <table class="text-sm w-full" id="orders-input-table" style="table-layout: fixed;">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="py-3 px-2 text-center" style="width: 40px;">#</th>
                            <th class="py-3 px-2 text-right" style="width: 110px;">التاريخ</th>
                            <th class="py-3 px-2 text-right" style="width: 130px;">إسم المستلم</th>
                            <th class="py-3 px-2 text-center" style="width: 120px;">مكالمة</th>
                            <th class="py-3 px-2 text-right" style="width: 110px;">الهاتف</th>
                            <th class="py-3 px-2 text-center" style="width: 160px;">حالة الطلب</th>
                            <th class="py-3 px-2 text-right" style="width: 110px;">ملاحظات</th>
                            <th class="py-3 px-2 text-right" style="width: 120px;">المحافظة</th>
                            <th class="py-3 px-2 text-right" style="width: 130px;">العنوان</th>
                            <th class="py-3 px-2 text-center" style="width: 70px;">القطع</th>
                            <th class="py-3 px-2 text-right" style="width: 100px;">Agent Code</th>
                            <th class="py-3 px-2 text-center" style="width: 60px;">الوقت</th>
                            <th class="py-3 px-2 text-center" style="width: 60px;">المرسل</th>
                            <th class="py-3 px-2 text-center" style="width: 50px;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="orders-rows">
                        <?php 
                        // 1. Determine columns for Name and Phone from the sheet
                        $name_col = -1;
                        $phone_col = -1;
                        if (!empty($sheet_headers)) {
                            foreach ($sheet_headers as $index => $header) {
                                $header_lower = function_exists('mb_strtolower')
                                    ? mb_strtolower(trim((string) $header), 'UTF-8')
                                    : strtolower(trim((string) $header));
                                if (strpos($header_lower, 'اسم') !== false || strpos($header_lower, 'عميل') !== false || strpos($header_lower, 'مستلم') !== false || stripos($header_lower, 'name') !== false) {
                                    if ($name_col === -1) $name_col = $index;
                                }
                                if (strpos($header_lower, 'هاتف') !== false || strpos($header_lower, 'رقم') !== false || strpos($header_lower, 'تليفون') !== false || strpos($header_lower, 'موبايل') !== false || stripos($header_lower, 'phone') !== false) {
                                    if ($phone_col === -1) $phone_col = $index;
                                }
                            }
                        }
                        // Default to 0 and 1 if we couldn't find matches
                        if ($name_col === -1) $name_col = 0;
                        if ($phone_col === -1) $phone_col = 1;

                        $total_rows_to_display = 50;
                        
                        for ($i = 0; $i < $total_rows_to_display; $i++): 
                            $rowSaved = $saved_orders_by_row[$i] ?? null;
                            if ($rowSaved) {
                                $prefilled_name = htmlspecialchars($rowSaved['recipient_name'] ?? $rowSaved['customer_name'] ?? '');
                                $prefilled_phone = htmlspecialchars($rowSaved['phone'] ?? '');
                            } elseif (isset($sheet_data[$i])) {
                                $prefilled_name = htmlspecialchars($sheet_data[$i][$name_col] ?? '');
                                $prefilled_phone = htmlspecialchars($sheet_data[$i][$phone_col] ?? '');
                            } else {
                                $prefilled_name = '';
                                $prefilled_phone = '';
                            }
                            $row_status = $rowSaved['order_status'] ?? '';
                            $row_date = $rowSaved['order_date'] ?? date('Y-m-d');
                            $row_notes = htmlspecialchars($rowSaved['notes'] ?? '');
                            $row_gov = htmlspecialchars($rowSaved['governorate'] ?? '');
                            $row_address = htmlspecialchars($rowSaved['address'] ?? '');
                            $row_pieces = (int) ($rowSaved['pieces'] ?? 1);
                            $row_bundle_type = $rowSaved['bundle_type'] ?? ($row_pieces >= 3 ? 'bundle' : 'single');
                            $row_total_price = (float) ($rowSaved['total_price'] ?? 0);
                            $row_quantity = (int) ($rowSaved['quantity'] ?? 1);
                            $row_agent = htmlspecialchars($auto_agent_code !== '' ? $auto_agent_code : ($rowSaved['agent_code'] ?? ''));
                            $row_product_code = trim((string) ($rowSaved['product_code'] ?? $sheet_product_code));
                            if ($row_product_code === '' || !isset($product_pricing_map[$row_product_code])) {
                                $row_product_code = $sheet_product_code;
                            }
                            $row_saved_id = (int) ($rowSaved['id'] ?? 0);
                            $row_time = $rowSaved ? date('H:i', strtotime($rowSaved['created_at'])) : '';
                            $row_sender = $rowSaved ? htmlspecialchars(substr($rowSaved['support_name'] ?? 'M', 0, 1)) : '';
                            if ($row_bundle_type === 'custom') {
                                $row_price_hint = 'مخصص';
                            } else {
                                $row_price_hint = ($row_pieces >= 3)
                                    ? number_format((float) ($product_pricing_map[$row_product_code]['bundle'] ?? $sheet_price_bundle['total_price']), 2) . ' دل (Bundle)'
                                    : number_format((float) ($product_pricing_map[$row_product_code]['single'] ?? $sheet_price_single['total_price']), 2) . ' دل (Single)';
                            }
                            $row_bg = '#ffffff';
                            if ($row_status) {
                                $status_colors = ['no_answer_1'=>'#fbbf24','no_answer_2'=>'#f59e0b','no_answer_3'=>'#d97706','contact_later'=>'#f3f4f6','wrong_number'=>'#dc2626','order_confirmed'=>'#059669','received'=>'#10b981','ready_to_ship'=>'#3b82f6','order_cancelled'=>'#991b1b'];
                                $row_bg = $status_colors[$row_status] ?? '#ffffff';
                            }
                            // قيم الشيت الأصلية (لزر المسح: يرجعها ويحذف التعديلات اليدوية فقط)
                            $sheet_orig_name = isset($sheet_data[$i]) ? trim((string) ($sheet_data[$i][$name_col] ?? '')) : '';
                            $sheet_orig_phone = isset($sheet_data[$i]) ? trim((string) ($sheet_data[$i][$phone_col] ?? '')) : '';
                            $sheet_orig_date = date('Y-m-d');
                        ?>
                        <tr class="border-b hover:bg-gray-50" id="row-<?= $i ?>" data-row-id="<?= $i ?>" data-saved-id="<?= $row_saved_id ?>"
                            data-sheet-name="<?= htmlspecialchars($sheet_orig_name, ENT_QUOTES, 'UTF-8') ?>"
                            data-sheet-phone="<?= htmlspecialchars($sheet_orig_phone, ENT_QUOTES, 'UTF-8') ?>"
                            data-sheet-date="<?= htmlspecialchars($sheet_orig_date, ENT_QUOTES, 'UTF-8') ?>"
                            data-agent-code="<?= htmlspecialchars($auto_agent_code, ENT_QUOTES, 'UTF-8') ?>"
                            style="background-color: <?= $row_bg ?>; transition: background-color 0.3s;">
                            <td class="py-2 px-2 text-center text-gray-500">
                                <?= $i + 1 ?>
                                <input type="hidden" name="orders[<?= $i ?>][id]" value="<?= $row_saved_id ?>">
                                <input type="hidden" name="orders[<?= $i ?>][sheet_row_index]" value="<?= $i ?>">
                                <input type="hidden" name="orders[<?= $i ?>][product_code]" id="product-code-<?= $i ?>" value="<?= htmlspecialchars($row_product_code) ?>">
                            </td>
                            <td class="py-2 px-2">
                                <input type="date" name="orders[<?= $i ?>][order_date]" 
                                       value="<?= htmlspecialchars($row_date) ?>"
                                       onchange="saveFormData()"
                                       class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm">
                            </td>
                            <td class="py-2 px-2">
                                <input type="text" name="orders[<?= $i ?>][recipient_name]" 
                                       placeholder="إسم المستلم" value="<?= $prefilled_name ?>"
                                       id="client-name-<?= $i ?>"
                                       oninput="saveFormData()"
                                       class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm">
                                <?php 
                                $current_st = $rowSaved['order_status'] ?? '';
                                $is_current_active = !in_array($current_st, ['delivered', 'received', 'cancelled', 'order_cancelled', 'return', 'returned']);
                                if (!empty($rowSaved['active_duplicates']) && $is_current_active): 
                                ?>
                                    <div class="mt-1 text-center">
                                        <span class="inline-flex items-center gap-1 bg-red-100 text-red-800 text-[10px] px-1.5 py-0.5 rounded font-bold" title="يوجد طلب آخر قائم لم يتم الانتهاء منه لهذا الرقم">
                                            <i class='bx bx-error-circle'></i> طلب قائم
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if (is_customer_blocked($conn, $rowSaved['phone'] ?? '', $rowSaved['id'] ?? 0)): ?>
                                    <div class="mt-1 text-center">
                                        <span class="inline-flex items-center gap-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded font-bold" title="هذا العميل تم رفض آخر 3 طلبات له">
                                            <i class='bx bx-block'></i> مرفوض
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <div class="flex flex-col gap-1 items-center">
                                    <button type="button" id="call-start-<?= $i ?>"
                                        onclick="supportStartCall(<?= $i ?>)"
                                        class="bg-green-600 text-white text-xs px-2 py-1 rounded hover:bg-green-700 w-full">بدء مكالمة</button>
                                    <button type="button" id="call-end-<?= $i ?>"
                                        onclick="supportEndCall(<?= $i ?>)"
                                        class="bg-red-600 text-white text-xs px-2 py-1 rounded hover:bg-red-700 w-full hidden">إنهاء</button>
                                    <span id="call-timer-<?= $i ?>" class="text-xs font-mono text-green-700 hidden">00:00</span>
                                </div>
                            </td>
                            <td class="py-2 px-2">
                                <input type="text" name="orders[<?= $i ?>][phone]" 
                                       placeholder="الهاتف" value="<?= $prefilled_phone ?>"
                                       id="client-phone-<?= $i ?>"
                                       oninput="saveFormData()"
                                       class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm">
                            </td>
                            <td class="py-2 px-2">
                                <?php
                                $status_extra = 'id="status-select-' . (int) $i . '" style="background-color: ' . htmlspecialchars($row_bg) . ';" onchange="var row=this.closest(\'tr\'); var c=row.querySelectorAll(\'td\'); var colors={\'no_answer_1\':\'#fbbf24\',\'no_answer_2\':\'#f59e0b\',\'no_answer_3\':\'#d97706\',\'contact_later\':\'#f3f4f6\',\'wrong_number\':\'#dc2626\',\'order_confirmed\':\'#059669\',\'received\':\'#10b981\',\'ready_to_ship\':\'#3b82f6\',\'order_cancelled\':\'#991b1b\'}; var bg=colors[this.value]||\'#ffffff\'; for(var j=0;j<c.length;j++){c[j].style.backgroundColor=bg;} this.style.backgroundColor=bg; saveFormData();"';
                                echo support_render_status_select('orders[' . $i . '][order_status]', $row_status, $status_extra);
                                ?>
                            </td>
                            <td class="py-2 px-2">
                                <input type="text" name="orders[<?= $i ?>][notes]" 
                                       placeholder="ملاحظات" value="<?= $row_notes ?>"
                                       oninput="saveFormData()"
                                       class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm">
                            </td>
                            <td class="py-2 px-2" style="min-width: 150px;">
                                <input type="text" name="orders[<?= $i ?>][governorate]" 
                                       placeholder="المحافظة" 
                                       list="governorates-list-<?= $i ?>"
                                       value="<?= $row_gov ?>"
                                       class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm"
                                       oninput="saveFormData();">
                                <datalist id="governorates-list-<?= $i ?>">
                                    <?php foreach ($libyan_governorates as $code => $name): ?>
                                        <option value="<?= $name ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </td>
                            <td class="py-2 px-2">
                                <input type="text" name="orders[<?= $i ?>][address]" 
                                       placeholder="العنوان" value="<?= $row_address ?>"
                                       oninput="saveFormData()"
                                       class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm">
                            </td>
                            <td class="py-2 px-2">
                                <select name="orders[<?= $i ?>][pieces]" id="pieces-<?= $i ?>"
                                        class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300 text-sm text-center"
                                        onchange="updatePiecesAndColor(<?= $i ?>, this.value); saveFormData();">
                                    <option value="1" <?= $row_pieces === 1 && $row_bundle_type !== 'custom' ? 'selected' : '' ?>>1 Single</option>
                                    <option value="3" <?= $row_pieces === 3 && $row_bundle_type !== 'custom' ? 'selected' : '' ?>>3 Bundle</option>
                                    <option value="custom" <?= $row_bundle_type === 'custom' ? 'selected' : '' ?>>مخصص (إدخال يدوي)</option>
                                </select>
                                <div id="custom-inputs-<?= $i ?>" class="<?= $row_bundle_type === 'custom' ? 'mt-2 flex flex-col gap-1' : 'hidden mt-2 flex flex-col gap-1' ?>">
                                    <input type="number" name="orders[<?= $i ?>][custom_quantity]" id="custom-qty-<?= $i ?>" value="<?= $row_quantity ?>" placeholder="العدد" class="w-full p-1 border rounded text-xs text-center" oninput="saveFormData()">
                                    <input type="number" name="orders[<?= $i ?>][custom_price]" id="custom-price-<?= $i ?>" value="<?= $row_total_price ?>" placeholder="السعر" class="w-full p-1 border rounded text-xs text-center" oninput="saveFormData()">
                                </div>
                                <input type="hidden" name="orders[<?= $i ?>][bundle_type]" id="bundle-type-<?= $i ?>" value="<?= htmlspecialchars($row_bundle_type) ?>">
                                <span class="text-[10px] text-orange-700 font-bold block mt-1 text-center" id="price-hint-<?= $i ?>"><?= $row_price_hint ?></span>
                            </td>
                            <td class="py-2 px-2">
                                <input type="text" name="orders[<?= $i ?>][agent_code]" 
                                       placeholder="Agent Code" value="<?= $row_agent ?>"
                                       readonly
                                       title="يُولَّد تلقائياً من اسم الدعم وتسلسل الحساب"
                                       class="w-full p-2 border rounded bg-slate-50 text-slate-700 text-sm font-bold text-center cursor-not-allowed">
                            </td>
                            <td class="py-2 px-2 text-center text-gray-500 text-xs">
                                <?= $row_time !== '' ? $row_time : '—' ?>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <?php if ($row_sender !== ''): ?>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-bold"><?= $row_sender ?></span>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <button type="button" onclick="clearOrderRow(<?= $i ?>); return false;" class="text-amber-600 hover:text-amber-800" title="مسح التعديلات اليدوية (يترك الاسم والرقم والتاريخ من الشيت)">
                                    <i class='bx bx-eraser'></i>
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                </div>
            </div>
            
            <div class="flex justify-end items-center mt-4">
                <button type="submit" 
                        class="bg-green-500 text-white px-8 py-3 rounded-lg hover:bg-green-600 font-bold shadow-lg flex items-center">
                    <i class='bx bx-save mr-2'></i> 💾 حفظ كل الأوردرات
                </button>
            </div>
        </form>

        <?php if ($is_support): ?>
        <div class="bg-white rounded-lg shadow p-4 mt-4 mb-4 border border-green-200">
            <h3 class="text-lg font-bold mb-3 text-green-800"><i class='bx bx-phone-call mr-2'></i>سجل المكالمات</h3>
            <div id="my-calls-log" class="space-y-2 max-h-64 overflow-y-auto text-sm">
                <p class="text-gray-400">جاري التحميل...</p>
            </div>
        </div>
        <script>
        (function() {
            var supportCallState = { callId: 0, rowIndex: null, startedAt: 0, timerInterval: null };

            function formatCallTimer(secs) {
                var m = Math.floor(secs / 60);
                var s = secs % 60;
                return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            }

            function getSheetId() {
                var el = document.querySelector('input[name="sheet_id"]');
                return el ? parseInt(el.value, 10) || 0 : 0;
            }

            function resetCallRowUI(rowIndex) {
                var startBtn = document.getElementById('call-start-' + rowIndex);
                var endBtn = document.getElementById('call-end-' + rowIndex);
                var timerEl = document.getElementById('call-timer-' + rowIndex);
                if (startBtn) startBtn.classList.remove('hidden');
                if (endBtn) endBtn.classList.add('hidden');
                if (timerEl) { timerEl.classList.add('hidden'); timerEl.textContent = '00:00'; }
            }

            function showActiveCallUI(rowIndex) {
                document.querySelectorAll('[id^="call-start-"]').forEach(function(btn) {
                    resetCallRowUI(btn.id.replace('call-start-', ''));
                });
                var startBtn = document.getElementById('call-start-' + rowIndex);
                var endBtn = document.getElementById('call-end-' + rowIndex);
                var timerEl = document.getElementById('call-timer-' + rowIndex);
                if (startBtn) startBtn.classList.add('hidden');
                if (endBtn) endBtn.classList.remove('hidden');
                if (timerEl) timerEl.classList.remove('hidden');
            }

            function startCallTimer() {
                if (supportCallState.timerInterval) clearInterval(supportCallState.timerInterval);
                supportCallState.timerInterval = setInterval(function() {
                    if (!supportCallState.rowIndex && supportCallState.rowIndex !== 0) return;
                    var elapsed = Math.floor((Date.now() - supportCallState.startedAt) / 1000);
                    var timerEl = document.getElementById('call-timer-' + supportCallState.rowIndex);
                    if (timerEl) timerEl.textContent = formatCallTimer(elapsed);
                }, 1000);
            }

            function loadMyCalls() {
                fetch('support_activity_api.php?action=my_calls', { credentials: 'include' })
                    .then(function(r) { return r.json(); })
                    .then(function(calls) {
                        var box = document.getElementById('my-calls-log');
                        if (!box) return;
                        if (!calls || !calls.length) {
                            box.innerHTML = '<p class="text-gray-400">لا توجد مكالمات مسجلة بعد</p>';
                            return;
                        }
                        box.innerHTML = calls.map(function(c) {
                            var status = c.status === 'active' ? '<span class="text-green-600 font-bold">جارية</span>' :
                                (c.status === 'completed' ? '<span class="text-blue-600">منتهية</span>' : '<span class="text-gray-500">ملغاة</span>');
                            return '<div class="p-2 bg-gray-50 rounded border-r-4 border-green-500">' +
                                '<div class="flex justify-between"><strong>' + (c.client_name || '—') + '</strong>' + status + '</div>' +
                                '<div class="text-xs text-gray-500 mt-1">📞 ' + (c.client_phone || '—') + ' | ⏱ ' + (c.duration_formatted || '0ث') + '</div>' +
                                '<div class="text-xs text-gray-400">' + (c.started_at || '') + '</div></div>';
                        }).join('');
                    }).catch(function() {});
            }

            window.supportStartCall = function(rowIndex) {
                var nameEl = document.getElementById('client-name-' + rowIndex);
                var phoneEl = document.getElementById('client-phone-' + rowIndex);
                var clientName = nameEl ? nameEl.value.trim() : '';
                var clientPhone = phoneEl ? phoneEl.value.trim() : '';
                if (!clientName && !clientPhone) {
                    uiAlert('أدخل اسم العميل أو رقم الهاتف قبل بدء المكالمة');
                    return;
                }
                fetch('support_activity_api.php?action=call_start', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sheet_id: getSheetId(),
                        sheet_row_index: rowIndex,
                        client_name: clientName,
                        client_phone: clientPhone
                    })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (!data.success) { uiAlert('تعذر بدء المكالمة'); return; }
                    supportCallState.callId = data.call_id;
                    supportCallState.rowIndex = rowIndex;
                    supportCallState.startedAt = Date.now();
                    showActiveCallUI(rowIndex);
                    startCallTimer();
                    loadMyCalls();
                }).catch(function() { uiAlert('خطأ في الاتصال'); });
            };

            window.supportEndCall = function(rowIndex) {
                if (!supportCallState.callId) return;
                var duration = Math.floor((Date.now() - supportCallState.startedAt) / 1000);
                fetch('support_activity_api.php?action=call_end', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ call_id: supportCallState.callId, duration_seconds: duration })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (supportCallState.timerInterval) clearInterval(supportCallState.timerInterval);
                    resetCallRowUI(rowIndex);
                    supportCallState = { callId: 0, rowIndex: null, startedAt: 0, timerInterval: null };
                    loadMyCalls();
                }).catch(function() { uiAlert('خطأ في إنهاء المكالمة'); });
            };

            fetch('support_activity_api.php?action=call_active', { credentials: 'include' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.call) return;
                    var rowIndex = data.call.sheet_row_index;
                    supportCallState.callId = parseInt(data.call.id, 10);
                    supportCallState.rowIndex = rowIndex;
                    var started = new Date((data.call.started_at || '').replace(' ', 'T')).getTime();
                    supportCallState.startedAt = isNaN(started) ? Date.now() : started;
                    showActiveCallUI(rowIndex);
                    startCallTimer();
                });

            loadMyCalls();
            setInterval(loadMyCalls, 30000);
        })();
        </script>
        <?php endif; ?>

    </div>
    <?php endif; ?>

<?php elseif ($is_manager): ?>
    <!-- صفحة المدير -->
    
    <!-- إحصائيات -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-500 rounded-lg p-4 text-white">
            <p class="text-sm">الموظفين</p>
            <p class="text-3xl font-bold"><?= count($team) ?></p>
        </div>
        <div class="bg-green-500 rounded-lg p-4 text-white">
            <p class="text-sm">الطلبات</p>
            <p class="text-3xl font-bold"><?= number_format($total_stats['orders']) ?></p>
        </div>
        <div class="bg-orange-500 rounded-lg p-4 text-white">
            <p class="text-sm">الشيتات</p>
            <p class="text-3xl font-bold"><?= $total_stats['sheets'] ?></p>
        </div>
        <div class="bg-purple-500 rounded-lg p-4 text-white">
            <p class="text-sm">متوسط</p>
            <p class="text-3xl font-bold"><?= count($team) > 0 ? round($total_stats['orders'] / count($team)) : 0 ?></p>
        </div>
    </div>

    <!-- جدول الفريق مع وقت النشاط -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4"><i class='bx bx-table mr-2'></i>فريق الدعم الفني - <span class="text-sm text-gray-500">(يتم التحديث تلقائياً)</span></h2>
        
        <?php if (count($team) > 0): ?>
        <table class="w-full" id="team-activity-table">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-3 px-4 text-right">الموظف</th>
                    <th class="text-center py-3 px-4">الحالة</th>
                    <th class="text-center py-3 px-4">مدة النشاط</th>
                    <th class="text-center py-3 px-4">مدة الجلسة</th>
                    <th class="text-center py-3 px-4">الشيتات</th>
                    <th class="text-center py-3 px-4">آخر نشاط</th>
                    <th class="text-center py-3 px-4">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team as $m): ?>
                <tr class="border-b hover:bg-gray-50" data-user-id="<?= $m['id'] ?>">
                    <td class="py-3 px-4">
                        <div class="font-semibold"><?= htmlspecialchars($m['fullname']) ?></div>
                        <div class="text-sm text-gray-500">@<?= htmlspecialchars($m['username']) ?></div>
                    </td>
                    <td class="text-center py-3 px-4" id="status-<?= $m['id'] ?>">
                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">غير متصل</span>
                    </td>
                    <td class="text-center py-3 px-4" id="activity-<?= $m['id'] ?>">
                        <span class="text-gray-400">--</span>
                    </td>
                    <td class="text-center py-3 px-4" id="session-<?= $m['id'] ?>">
                        <span class="text-gray-400">--</span>
                    </td>
                    <td class="text-center py-3 px-4 font-bold text-blue-600"><?= $m['sheets'] ?></td>
                    <td class="text-center py-3 px-4" id="lastseen-<?= $m['id'] ?>">
                        <span class="text-gray-400">--</span>
                    </td>
                    <td class="text-center py-3 px-4">
                        <a href="?page=support_detail&id=<?= $m['id'] ?>" class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">تفاصيل</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Script لتحديث النشاط في الوقت الفعلي -->
        <script>
        function updateActivity() {
            fetch('support_activity_api.php?action=get_activity', { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    document.querySelectorAll('#team-activity-table tbody tr[data-user-id]').forEach(function(row) {
                        var uid = row.getAttribute('data-user-id');
                        var statusCell = document.getElementById('status-' + uid);
                        var activityCell = document.getElementById('activity-' + uid);
                        var sessionCell = document.getElementById('session-' + uid);
                        var lastSeenCell = document.getElementById('lastseen-' + uid);
                        if (statusCell) {
                            statusCell.innerHTML = '<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">غير متصل</span>';
                        }
                        if (activityCell) activityCell.innerHTML = '<span class="text-gray-400">--</span>';
                        if (sessionCell) sessionCell.innerHTML = '<span class="text-gray-400">--</span>';
                        if (lastSeenCell) lastSeenCell.innerHTML = '<span class="text-gray-400">--</span>';
                    });

                    if (!Array.isArray(data)) return;

                    data.forEach(user => {
                        const activityCell = document.getElementById('activity-' + user.id);
                        const sessionCell = document.getElementById('session-' + user.id);
                        const statusCell = document.getElementById('status-' + user.id);
                        const lastSeenCell = document.getElementById('lastseen-' + user.id);
                        
                        if (activityCell) {
                            activityCell.innerHTML = '<span class="font-bold text-blue-600">' + (user.active_time_formatted || '0ث') + '</span>';
                        }
                        if (sessionCell) {
                            sessionCell.innerHTML = '<span class="font-bold text-purple-600">' + (user.session_time_formatted || '0ث') + '</span>';
                        }
                        
                        if (statusCell) {
                            if (user.is_online) {
                                var label = user.is_active ? '🟢 متصل — نشط' : '🟡 متصل — خمول';
                                var cls = user.is_active ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                                statusCell.innerHTML = '<span class="' + cls + ' px-2 py-1 rounded text-xs">' + label + '</span>';
                            } else {
                                statusCell.innerHTML = '<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">⚪ غير متصل</span>';
                            }
                        }
                        
                        if (lastSeenCell) {
                            if (user.is_online) {
                                lastSeenCell.innerHTML = '<span class="text-sm text-green-600">متصل الآن</span>';
                            } else if (user.last_seen && user.last_seen !== '—') {
                                lastSeenCell.innerHTML = '<span class="text-sm">منذ ' + (user.last_seen_ago || '') + '</span><br><span class="text-xs text-gray-400">' + user.last_seen + '</span>';
                            } else {
                                lastSeenCell.innerHTML = '<span class="text-gray-400">—</span>';
                            }
                        }
                    });
                });
        }
        
        // تحديث كل 10 ثواني
        updateActivity();
        setInterval(updateActivity, 10000);
        </script>
        <?php else: ?>
            <p class="text-center py-8 text-gray-400">لا يوجد موظفين</p>
        <?php endif; ?>
    </div>
    
    <!-- محاولات السكرين شوت -->
    <div class="bg-red-50 border border-red-200 rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4 text-red-700"><i class='bx bx-error-alt mr-2'></i>🚨 محاولات السكرين شوت</h2>
        <div id="screenshot-alerts" class="space-y-2">
            <p class="text-gray-500">جاري التحميل...</p>
        </div>
        
        <script>
        function updateScreenshots() {
            fetch('support_activity_api.php?action=get_screenshots')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('screenshot-alerts');
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-green-600">✅ لا توجد محاولات</p>';
                    } else {
                        container.innerHTML = data.map(log => `
                            <div class="bg-red-100 border-r-4 border-red-500 p-3 rounded">
                                <div class="flex justify-between">
                                    <span class="font-bold text-red-800">👤 ${log.user_name}</span>
                                    <span class="text-sm text-gray-600">${log.timestamp}</span>
                                </div>
                                <div class="text-sm text-red-600 mt-1">⚠️ تم الكشف عن محاولة سكرين شوت!</div>
                            </div>
                        `).join('');
                    }
                });
        }
        
        updateScreenshots();
        setInterval(updateScreenshots, 15000); // تحديث كل 15 ثانية
        </script>
    </div>

    <!-- إشعارات للمدير الرئيسي -->
    <?php if ($is_manager && isset($_SESSION['supervisor_notification'])): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <div class="flex items-center">
                <i class='bx bx-bell text-blue-600 text-xl mr-3'></i>
                <div>
                    <h4 class="font-bold text-blue-800">إشعار جديد من فريق الدعم</h4>
                    <p class="text-sm text-blue-600">
                        👤 <?= htmlspecialchars($_SESSION['supervisor_notification']['sender_name']) ?> قام بحفظ 
                        <?= $_SESSION['supervisor_notification']['saved_count'] ?> أوردر في شيت #<?= $_SESSION['supervisor_notification']['sheet_id'] ?>
                        (<?= date('h:i A', strtotime($_SESSION['supervisor_notification']['timestamp'])) ?>)
                    </p>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['supervisor_notification']); ?>
    <?php endif; ?>

    <!-- قائمة الشيتات -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold mb-4"><i class='bx bx-file mr-2'></i>كل الشيتات</h2>
        
        <?php if ($sheets && $sheets->num_rows > 0): 
            $sheets->data_seek(0);
            while ($sheet = $sheets->fetch_assoc()): ?>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-3 hover:bg-blue-50">
                <div>
                    <h4 class="font-semibold"><?= htmlspecialchars($sheet['sheet_name']) ?></h4>
                    <p class="text-sm text-gray-500">
                        <?= date('Y-m-d', strtotime($sheet['created_at'])) ?> | 
                        <?= $sheet['names_count'] ?? 0 ?> اسم بالملف | 
                        <span class="text-blue-600 font-bold"><?= htmlspecialchars($sheet['employee_name'] ?? '') ?></span>
                    </p>
                    <p class="text-xs mt-1 <?= ($sheet['saved_orders_count'] ?? 0) > 0 ? 'text-green-600 font-bold' : 'text-gray-400' ?>">
                        <i class='bx bx-check-double'></i> تم حفظ: <?= $sheet['saved_orders_count'] ?? 0 ?> طلب
                    </p>
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
                        <span class="text-red-500" title="مغلق"><i class='bx bxs-lock-alt text-xl'></i></span>
                        <?php if (!empty($sheet['unlock_request'])): ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="unlock_sheet" value="<?= $sheet['id'] ?>" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600 flex items-center shadow" title="قبول الطلب وفتح الشيت"><i class='bx bx-check mr-1'></i> قبول</button>
                            </form>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="reject_unlock" value="<?= $sheet['id'] ?>" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 flex items-center shadow" title="رفض الطلب" onclick="uiConfirmSubmit(event, this, 'تأكيد', 'هل أنت متأكد من رفض الطلب؟', 'تأكيد', true)"><i class='bx bx-x mr-1'></i> رفض</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="unlock_sheet" value="<?= $sheet['id'] ?>" class="bg-gray-200 text-gray-700 px-3 py-1 rounded text-sm hover:bg-gray-300">فتح القفل</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <button type="submit" name="force_lock_sheet" value="<?= $sheet['id'] ?>" class="bg-gray-500 text-white px-3 py-1 rounded text-sm hover:bg-gray-600 flex items-center shadow" title="إغلاق الشيت فوراً" onclick="uiConfirmSubmit(event, this, 'تأكيد', 'هل أنت متأكد من قفل هذا الشيت للموظف؟', 'تأكيد', true)"><i class='bx bxs-lock-alt mr-1'></i> إغلاق فوري</button>
                        </form>
                    <?php endif; ?>
                    <a href="?page=support&view_sheet=<?= $sheet['id'] ?>" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">عرض التفاصيل</a>
                </div>
            </div>
        <?php endwhile; else: ?>
            <p class="text-center py-8 text-gray-400">لا توجد شيتات</p>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- صفحة فريق الدعم -->
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-bold">الشيتات المخصصة لك</h2>
            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full"><?= $sheets ? $sheets->num_rows : 0 ?> شيت</span>
        </div>
        
        <?php if ($sheets && $sheets->num_rows > 0): 
            $sheets->data_seek(0);
            while ($sheet = $sheets->fetch_assoc()): ?>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-3 hover:bg-orange-50 border border-gray-100">
                <div>
                    <h4 class="font-semibold"><?= htmlspecialchars($sheet['sheet_name']) ?></h4>
                    <p class="text-sm text-gray-500">
                        <?= date('Y-m-d', strtotime($sheet['created_at'])) ?> | 
                        <?= $sheet['names_count'] ?? 0 ?> اسم
                    </p>
                </div>
                
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
                    <div class="flex items-center gap-2">
                        <span class="text-red-500 font-bold flex items-center" title="مغلق لمرور 24 ساعة"><i class='bx bxs-lock-alt text-xl mr-1'></i> مغلق</span>
                        <?php if (!empty($sheet['unlock_request'])): ?>
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-3 py-1 rounded shadow-sm">قيد المراجعة ⏳</span>
                        <?php else: ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="request_unlock" value="<?= $sheet['id'] ?>" class="bg-red-100 text-red-700 px-3 py-1 rounded text-sm hover:bg-red-200 shadow-sm border border-red-200">طلب فتح</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <a href="?page=support&view_sheet=<?= $sheet['id'] ?>" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">عرض الشيت</a>
                <?php endif; ?>
                
            </div>
        <?php endwhile; else: ?>
            <div class="text-center py-12 text-gray-400">
                <i class='bx bx-file-blank text-6xl mb-4'></i>
                <p>لا توجد شيتات مرفوعة لك</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<script>
// Libya Governorates
var libyanGovernorates = [
    'طرابلس', 'بنغازي', 'مصراتة', 'الزاوية', 'البيضاء', 'سبها', 'طبرق', 'زليتن',
    'سرت', 'الخمس', 'درنة', 'اجدابيا', 'شحات', 'الجفرة', 'الجبل الأخضر', 'المرقب',
    'الكفرة', 'وادي الشاطئ', 'الواحات', 'غات', 'المرج', 'القبة', 'ترهونة', 'غريان',
    'يفرن', 'نالوت', 'جادو', 'الرياينة', 'ميزدة', 'الابيار', 'براك الشاطئ', 'اوباري'
];

// Status colors - all cells will get this color
var orderStatusColors = {
    'no_answer_1': '#fef08a',       // Yellow light
    'no_answer_2': '#fde047',       // Yellow
    'no_answer_3': '#eab308',       // Yellow dark
    'contact_later': '#ffffff',     // White
    'wrong_number': '#ef4444',      // Red
    'order_confirmed': '#166534',   // Dark Green (new)
    'received': '#86efac',          // Green
    'ready_to_ship': '#93c5fd',     // Blue
    'order_cancelled': '#991b1b'    // Dark Red (new)
};

// Text colors for dark backgrounds
var statusTextColors = {
    'no_answer_1': '#000000',
    'no_answer_2': '#000000',
    'no_answer_3': '#000000',
    'contact_later': '#000000',
    'wrong_number': '#ffffff',
    'order_confirmed': '#ffffff',   // White text on dark green
    'received': '#000000',
    'ready_to_ship': '#000000',
    'order_cancelled': '#ffffff'    // White text on dark red
};

// Update row color based on order status - SIMPLE VERSION
window.updateOrderRowColor = function(rowId, status) {
    try {
        var row = document.getElementById('row-' + rowId);
        if (!row) {
            alert('Row not found: row-' + rowId);
            return;
        }
        
        var bgColor = orderStatusColors[status] || '#ffffff';
        var textColor = statusTextColors[status] || '#000000';
        
        // Color all cells
        var cells = row.getElementsByTagName('td');
        for (var i = 0; i < cells.length; i++) {
            cells[i].style.backgroundColor = bgColor;
            cells[i].style.color = textColor;
        }
        
        // Update select
        var select = document.getElementById('status-select-' + rowId);
        if (select) {
            select.style.backgroundColor = bgColor;
            select.style.color = textColor;
        }
    } catch(e) {
        uiAlert('Error coloring row: ' + e.message);
    }
};

// أسعار المنتجات من إعدادات الحسابات
window.productPricing = <?= json_encode($product_pricing_map, JSON_UNESCAPED_UNICODE) ?>;
window.sheetPricing = {
    single: <?= json_encode((float) ($product_pricing_map[$sheet_product_code]['single'] ?? $sheet_price_single['total_price'])) ?>,
    bundle: <?= json_encode((float) ($product_pricing_map[$sheet_product_code]['bundle'] ?? $sheet_price_bundle['total_price'])) ?>
};

window.onSheetProductChange = function() {
    var select = document.getElementById('sheet-product-code');
    if (!select) return;
    var code = select.value;
    var pricing = window.productPricing[code];
    if (!pricing) return;

    window.sheetPricing = { single: pricing.single, bundle: pricing.bundle };
    document.querySelectorAll('input[id^="product-code-"]').forEach(function(inp) {
        inp.value = code;
    });

    var bannerSingle = document.getElementById('banner-single-price');
    var bannerBundle = document.getElementById('banner-bundle-price');
    if (bannerSingle) {
        bannerSingle.innerHTML = '<strong>Single</strong> (1 قطعة): ' + pricing.single.toFixed(2) + ' دل';
    }
    if (bannerBundle) {
        bannerBundle.innerHTML = '<strong>Bundle</strong> (3 قطع): ' + pricing.bundle.toFixed(2) + ' دل';
    }

    document.querySelectorAll('[id^="pieces-"]').forEach(function(sel) {
        var rowId = sel.id.replace('pieces-', '');
        updatePiecesAndColor(rowId, sel.value);
    });
};

// Update pieces value and bundle type + price hint
window.updatePiecesAndColor = function(rowId, value) {
    var bundleTypeInput = document.getElementById('bundle-type-' + rowId);
    var hint = document.getElementById('price-hint-' + rowId);
    var customInputs = document.getElementById('custom-inputs-' + rowId);
    
    if (value === 'custom') {
        if (bundleTypeInput) bundleTypeInput.value = 'custom';
        if (hint) hint.textContent = 'مخصص';
        if (customInputs) customInputs.classList.remove('hidden');
    } else {
        if (bundleTypeInput) {
            bundleTypeInput.value = (value == '3') ? 'bundle' : 'single';
        }
        if (customInputs) customInputs.classList.add('hidden');
        if (hint && window.sheetPricing) {
            if (value == '3') {
                hint.textContent = sheetPricing.bundle.toFixed(2) + ' دل (Bundle)';
            } else {
                hint.textContent = sheetPricing.single.toFixed(2) + ' دل (Single)';
            }
        }
    }
};

// مسح التعديلات اليدوية فقط — يسيب الاسم والرقم والتاريخ من الشيت، ومتيمسحش الصف
window.clearOrderRow = function(rowId) {
    try {
        var row = document.getElementById('row-' + rowId);
        if (!row) {
            alert('Row not found: row-' + rowId);
            return;
        }

        var sheetName = row.getAttribute('data-sheet-name') || '';
        var sheetPhone = row.getAttribute('data-sheet-phone') || '';
        var agentCode = row.getAttribute('data-agent-code') || '';

        var inputs = row.getElementsByTagName('input');
        for (var i = 0; i < inputs.length; i++) {
            var inp = inputs[i];
            var n = (inp.name || '');
            var t = (inp.type || '').toLowerCase();

            // لا تلمس الحقول المخفية (id / sheet_row_index / product_code)
            if (t === 'hidden') continue;

            // التاريخ: سيب القيمة الحالية زي ما هي
            if (n.indexOf('[order_date]') !== -1) {
                continue;
            }
            // أرجِع الاسم والهاتف لقيم الشيت الأصلية
            if (n.indexOf('[recipient_name]') !== -1) {
                inp.value = sheetName;
                continue;
            }
            if (n.indexOf('[phone]') !== -1) {
                inp.value = sheetPhone;
                continue;
            }
            // Agent Code تلقائي — أرجعه ولا تمسحه
            if (n.indexOf('[agent_code]') !== -1) {
                inp.value = agentCode;
                continue;
            }

            // امسح الحقول اليدوية فقط
            if (t === 'text' || t === 'number' || t === 'tel') {
                if (n.indexOf('[notes]') !== -1 ||
                    n.indexOf('[governorate]') !== -1 ||
                    n.indexOf('[address]') !== -1 ||
                    n.indexOf('[customer_status]') !== -1 ||
                    n.indexOf('[campaign]') !== -1 ||
                    n.indexOf('[version]') !== -1 ||
                    n.indexOf('[article]') !== -1) {
                    inp.value = '';
                }
            }
        }

        // إعادة حالة الطلب والقطع للافتراضي
        var statusSel = document.getElementById('status-select-' + rowId);
        if (statusSel) {
            statusSel.selectedIndex = 0;
            statusSel.style.backgroundColor = '#ffffff';
        }
        var piecesSel = document.getElementById('pieces-' + rowId);
        if (piecesSel) {
            piecesSel.value = '1';
            if (typeof updatePiecesAndColor === 'function') {
                updatePiecesAndColor(rowId, '1');
            }
        }
        var bundleInput = document.getElementById('bundle-type-' + rowId);
        if (bundleInput) bundleInput.value = 'single';

        // إعادة لون الصف
        var cells = row.getElementsByTagName('td');
        for (var c = 0; c < cells.length; c++) {
            cells[c].style.backgroundColor = '#ffffff';
            cells[c].style.color = '#000000';
        }
        row.style.backgroundColor = '#ffffff';

        if (typeof saveFormData === 'function') {
            saveFormData();
        }
    } catch (e) {
        uiAlert('Error clearing row: ' + e.message);
    }
    return false;
};

// Counter for new rows
var nextOrderRowId = 1000;
var defaultSupportAgentCode = <?= json_encode($auto_agent_code ?? '', JSON_UNESCAPED_UNICODE) ?>;

// Add new order row - ULTRA SIMPLE VERSION
window.addNewOrderRow = function() {
    try {
        var tbody = document.getElementById('orders-rows');
        if (!tbody) {
            uiAlert('خطأ: لا يمكن العثور على الجدول');
            return false;
        }
        
        var rows = tbody.getElementsByTagName('tr');
        var rowNum = rows.length + 1;
        var id = nextOrderRowId++;
        var today = new Date().toISOString().split('T')[0];
        
        // Build datalist
        var datalistId = 'gov-list-' + id;
        var datalistHTML = '<datalist id="' + datalistId + '">';
        for (var i = 0; i < libyanGovernorates.length; i++) {
            datalistHTML += '<option value="' + libyanGovernorates[i] + '"></option>';
        }
        datalistHTML += '</datalist>';
        
        // Create HTML
        var html = 
            '<td class="py-2 px-2 text-center text-gray-500">' + rowNum + '</td>' +
            '<td class="py-2 px-2"><input type="date" name="orders[' + id + '][order_date]" value="' + today + '" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][recipient_name]" id="client-name-' + id + '" placeholder="إسم المستلم" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2 text-center"><div class="flex flex-col gap-1 items-center">' +
                '<button type="button" id="call-start-' + id + '" onclick="supportStartCall(\'' + id + '\')" class="bg-green-600 text-white text-xs px-2 py-1 rounded hover:bg-green-700 w-full">بدء مكالمة</button>' +
                '<button type="button" id="call-end-' + id + '" onclick="supportEndCall(\'' + id + '\')" class="bg-red-600 text-white text-xs px-2 py-1 rounded hover:bg-red-700 w-full hidden">إنهاء</button>' +
                '<span id="call-timer-' + id + '" class="text-xs font-mono text-green-700 hidden">00:00</span></div></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][phone]" id="client-phone-' + id + '" placeholder="رقم الهاتف" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2">' +
                '<select name="orders[' + id + '][order_status]" id="status-select-' + id + '" class="w-full p-2 border rounded text-sm" onchange="updateOrderRowColor(' + id + ', this.value)" style="background-color: #ffffff;">' +
                    '<option value="">-- اختر الحالة --</option>' +
                    '<option value="pending">⏳ انتظار</option>' +
                    '<option value="no_answer_1">🟡 محاولة أولى</option>' +
                    '<option value="no_answer_2">🟡 محاولة تانية</option>' +
                    '<option value="no_answer_3">🟡 محاولة أخيرة</option>' +
                    '<option value="contact_later">⚪ تواصل لاحقاً</option>' +
                    '<option value="wrong_number">🔴 رقم غلط</option>' +
                    '<option value="order_confirmed">✅ تم التأكيد</option>' +
                    <?php if (!$is_support): ?>
                    '<option value="received">🟢 تم الاستلام</option>' +
                    <?php endif; ?>
                    '<option value="ready_to_ship">🔵 جاهز للشحن</option>' +
                    '<option value="order_cancelled">❌ تم الإلغاء</option>' +
                '</select>' +
            '</td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][notes]" placeholder="ملاحظات" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][governorate]" list="' + datalistId + '" placeholder="المحافظة..." class="w-full p-2 border rounded text-sm">' + datalistHTML + '</td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][address]" placeholder="العنوان" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2">' +
                '<select name="orders[' + id + '][pieces]" id="pieces-' + id + '" class="w-full p-2 border rounded text-sm text-center" onchange="updatePiecesAndColor(' + id + ', this.value)">' +
                    '<option value="1" selected>1 Single</option>' +
                    '<option value="3">3 Bundle</option>' +
                    '<option value="custom">مخصص (إدخال يدوي)</option>' +
                '</select>' +
                '<div id="custom-inputs-' + id + '" class="hidden mt-2 flex flex-col gap-1">' +
                    '<input type="number" name="orders[' + id + '][custom_quantity]" id="custom-qty-' + id + '" value="1" placeholder="العدد" class="w-full p-1 border rounded text-xs text-center" oninput="if(typeof saveFormData === \'function\') saveFormData()">' +
                    '<input type="number" name="orders[' + id + '][custom_price]" id="custom-price-' + id + '" value="0" placeholder="السعر" class="w-full p-1 border rounded text-xs text-center" oninput="if(typeof saveFormData === \'function\') saveFormData()">' +
                '</div>' +
                '<input type="hidden" name="orders[' + id + '][bundle_type]" id="bundle-type-' + id + '" value="single">' +
                '<span class="text-[10px] text-orange-700 font-bold block mt-1 text-center" id="price-hint-' + id + '"></span>' +
            '</td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][customer_status]" placeholder="حالة العميل" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][agent_code]" value="' + (typeof defaultSupportAgentCode !== 'undefined' ? defaultSupportAgentCode : '') + '" readonly placeholder="Agent" class="w-full p-2 border rounded text-sm bg-slate-50 text-center font-bold cursor-not-allowed"></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][campaign]" placeholder="Campaign" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][version]" placeholder="Version" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2"><input type="text" name="orders[' + id + '][article]" placeholder="Article" class="w-full p-2 border rounded text-sm"></td>' +
            '<td class="py-2 px-2 text-center" style="min-width: 60px;"><button type="button" onclick="clearOrderRow(' + id + '); return false;" class="text-amber-600 hover:text-amber-800" title="مسح التعديلات اليدوية" style="font-size: 20px; padding: 5px;"><i class="bx bx-eraser"></i></button></td>';
        
        var tr = document.createElement('tr');
        tr.id = 'row-' + id;
        tr.setAttribute('data-row-id', String(id));
        tr.setAttribute('data-sheet-name', '');
        tr.setAttribute('data-sheet-phone', '');
        tr.setAttribute('data-sheet-date', today);
        tr.setAttribute('data-agent-code', typeof defaultSupportAgentCode !== 'undefined' ? defaultSupportAgentCode : '');
        tr.style.backgroundColor = '#ffffff';
        tr.innerHTML = html;
        tbody.appendChild(tr);
        
        return false;
    } catch(e) {
        uiAlert('Error adding row: ' + e.message);
        return false;
    }
};

// ============================================
// LOCAL STORAGE FUNCTIONS - AUTO SAVE FORM DATA
// ============================================

// Save form data to localStorage
window.saveFormData = function() {
    try {
        var form = document.getElementById('orders-form');
        if (!form) return;
        
        var formData = {};
        var inputs = form.querySelectorAll('input[type="text"], input[type="date"], select');
        
        for (var i = 0; i < inputs.length; i++) {
            var name = inputs[i].name;
            if (name && name.includes('orders[')) {
                formData[name] = inputs[i].value;
            }
        }
        
        // Also save select backgrounds (colors)
        var selects = form.querySelectorAll('select[name*="[order_status]"]');
        var colors = {};
        for (var i = 0; i < selects.length; i++) {
            var id = selects[i].id;
            colors[id] = selects[i].style.backgroundColor;
        }
        formData['_colors'] = colors;
        
        // Save with timestamp
        formData['_timestamp'] = new Date().toISOString();
        formData['_sheetId'] = document.querySelector('input[name="sheet_id"]')?.value || '';
        
        localStorage.setItem('supportOrdersFormData', JSON.stringify(formData));
    } catch(e) {
        // Silent fail - don't interrupt user
    }
};

// Load form data from localStorage
window.loadFormData = function() {
    try {
        if (document.querySelector('tr[data-saved-id]:not([data-saved-id="0"])')) {
            localStorage.removeItem('supportOrdersFormData');
            return;
        }

        var saved = localStorage.getItem('supportOrdersFormData');
        if (!saved) return;
        
        var formData = JSON.parse(saved);
        var currentSheetId = document.querySelector('input[name="sheet_id"]')?.value || '';
        
        if (formData['_sheetId'] && formData['_sheetId'] !== currentSheetId) {
            localStorage.removeItem('supportOrdersFormData');
            return;
        }
        
        for (var key in formData) {
            if (key.startsWith('_')) continue;
            // Agent Code تلقائي — متسترجعش قيمة قديمة من localStorage
            if (key.indexOf('[agent_code]') !== -1) continue;
            
            var input = document.querySelector('[name="' + key + '"]');
            if (!input || !formData[key]) continue;

            var row = input.closest('tr');
            if (row && parseInt(row.getAttribute('data-saved-id') || '0', 10) > 0) {
                continue;
            }

            input.value = formData[key];

            if (input.tagName === 'SELECT' && input.name.includes('order_status')) {
                var statusRow = input.closest('tr');
                if (statusRow) {
                    var c = statusRow.querySelectorAll('td');
                    var colors = {'no_answer_1':'#fbbf24','no_answer_2':'#f59e0b','no_answer_3':'#d97706','contact_later':'#f3f4f6','wrong_number':'#dc2626','order_confirmed':'#059669','received':'#10b981','ready_to_ship':'#3b82f6','order_cancelled':'#991b1b'};
                    var bg = colors[input.value] || '#ffffff';
                    for (var ci = 0; ci < c.length; ci++) {
                        c[ci].style.backgroundColor = bg;
                    }
                    input.style.backgroundColor = bg;
                }
            }
        }

        // ثبّت Agent Code التلقائي على كل الصفوف
        if (typeof defaultSupportAgentCode !== 'undefined' && defaultSupportAgentCode) {
            document.querySelectorAll('input[name*="[agent_code]"]').forEach(function(inp) {
                inp.value = defaultSupportAgentCode;
            });
        }
        
        if (formData['_colors']) {
            for (var id in formData['_colors']) {
                var select = document.getElementById(id);
                if (select) {
                    select.style.backgroundColor = formData['_colors'][id];
                }
            }
        }
        
    } catch(e) {
        // Silent fail
    }
};

// Clear saved form data
window.clearSavedFormData = function() {
    localStorage.removeItem('supportOrdersFormData');
};

// Auto-load on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadFormData);
} else {
    loadFormData();
}

// Clear saved data on form submit (successful save)
document.addEventListener('submit', function(e) {
    if (e.target.id === 'orders-form') {
        clearSavedFormData();
    }
});

// Simple function to add new row (cleaner approach using CloneNode)
window.addNewRowSimple = function() {
    try {
        var tbody = document.getElementById('orders-rows');
        if (!tbody) {
            uiAlert('Table not found!');
            return false;
        }
        
        var rows = tbody.querySelectorAll('tr[id^="row-"]');
        if (rows.length === 0) {
            uiAlert('No rows available to clone!');
            return false;
        }
        
        // Find last row and clone
        var lastRow = rows[rows.length - 1];
        var clone = lastRow.cloneNode(true);
        
        var rowNum = rows.length + 1;
        var newIdStr = Date.now().toString() + Math.floor(Math.random() * 1000);
        
        // Update Row IDs
        clone.id = 'row-' + newIdStr;
        clone.setAttribute('data-row-id', newIdStr);
        
        // Update cell 1 with Row Number
        var cells = clone.getElementsByTagName('td');
        if (cells.length > 0) {
            cells[0].innerHTML = rowNum;
        }
        
        // Reset Inputs
        var inputs = clone.querySelectorAll('input, select');
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            
            // Rename input name[] to map correctly
            var name = input.getAttribute('name');
            if (name) {
                var newName = name.replace(/orders\[.*?\]/, 'orders[' + newIdStr + ']');
                input.setAttribute('name', newName);
            }
            
            // Rename IDs properly
            var id = input.getAttribute('id');
            if (id) {
                var newId = id.replace(/-\d+$/, '-' + newIdStr);
                input.setAttribute('id', newId);
            }
            
            // Clear Values
            if (input.type === 'text' || input.type === 'date') {
                if(input.type === 'text') input.value = '';
                if(input.type === 'date') input.value = new Date().toISOString().split('T')[0];
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
                input.style.backgroundColor = '#ffffff';
                input.style.color = '#000000';
            } else if (input.type === 'hidden' && name && name.includes('[bundle_type]')) {
                input.value = 'single';
            }
        }
        
        // Reset backgrounds
        clone.style.backgroundColor = '#ffffff';
        for (var i = 0; i < cells.length; i++) {
            cells[i].style.backgroundColor = '#ffffff';
            cells[i].style.color = '#000000';
        }
        
        // Fix call buttons after clone
        var callStart = clone.querySelector('[id^="call-start-"]');
        var callEnd = clone.querySelector('[id^="call-end-"]');
        var callTimer = clone.querySelector('[id^="call-timer-"]');
        if (callStart) {
            callStart.id = 'call-start-' + newIdStr;
            callStart.classList.remove('hidden');
            callStart.setAttribute('onclick', "supportStartCall('" + newIdStr + "')");
        }
        if (callEnd) {
            callEnd.id = 'call-end-' + newIdStr;
            callEnd.classList.add('hidden');
            callEnd.setAttribute('onclick', "supportEndCall('" + newIdStr + "')");
        }
        if (callTimer) {
            callTimer.id = 'call-timer-' + newIdStr;
            callTimer.classList.add('hidden');
            callTimer.textContent = '00:00';
        }
        var nameInput = clone.querySelector('input[name*="[recipient_name]"]');
        if (nameInput) nameInput.id = 'client-name-' + newIdStr;
        var phoneInput = clone.querySelector('input[name*="[phone]"]');
        if (phoneInput) phoneInput.id = 'client-phone-' + newIdStr;
        var productInput = clone.querySelector('input[name*="[product_code]"]');
        if (productInput) {
            productInput.id = 'product-code-' + newIdStr;
            var sheetSelect = document.getElementById('sheet-product-code');
            if (sheetSelect) productInput.value = sheetSelect.value;
        }
        
        // Fix Datalist links for Governorate
        var govInput = clone.querySelector('input[list]');
        var datalist = clone.querySelector('datalist');
        if (govInput && datalist) {
            var newDatalistId = 'governorates-list-' + newIdStr;
            govInput.setAttribute('list', newDatalistId);
            datalist.setAttribute('id', newDatalistId);
        }
        
        // Append row to tbody
        tbody.appendChild(clone);
        
        // Save state
        if (typeof window.saveFormData === 'function') {
            window.saveFormData();
        }
        
        return false;
    } catch(e) {
        uiAlert('Error adding row: ' + e.message);
        return false;
    }
};

// Add event listener to button
document.addEventListener('DOMContentLoaded', function() {
    var addBtn = document.getElementById('add-row-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.addNewRowSimple();
        });
    }
});
</script>

<style>
/* Custom scrollbar styles */
::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 6px;
}
::-webkit-scrollbar-thumb {
    background: #4b6b2f;
    border-radius: 6px;
    border: 2px solid #f1f1f1;
}
::-webkit-scrollbar-thumb:hover {
    background: #3a5524;
}
/* Firefox */
* {
    scrollbar-width: auto;
    scrollbar-color: #4b6b2f #f1f1f1;
}
</style>


