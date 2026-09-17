<?php
// Include Chart.js for visualization (this will be added dynamically if missing, but we'll put it in HTML)
$is_super_admin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin');
$weekly_seed = "SystemCustomers2026Secret!";
$week_number = date("Y-W");
// Generate a 6-character uppercase password
$weekly_password = strtoupper(substr(md5($weekly_seed . $week_number), 0, 6));

$access_granted = false;
$show_new_password_alert = false;

if ($is_super_admin) {
    // Check if super_admin has seen this week's password
    if (!isset($_COOKIE['customers_pwd_seen_week']) || $_COOKIE['customers_pwd_seen_week'] !== $week_number) {
        // First time this week!
        $show_new_password_alert = true;
        $access_granted = true;
        // Use JS to set cookie since headers are already sent
        echo "<script>document.cookie = 'customers_pwd_seen_week={$week_number}; path=/; max-age=' + (86400 * 30);</script>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_password'])) {
    if (strtoupper(trim($_POST['customer_password'])) === $weekly_password) {
        $_SESSION['customers_unlocked_week'] = $week_number;
        $access_granted = true;
    } else {
        $pass_error = "الرقم السري غير صحيح.";
    }
} elseif (isset($_SESSION['customers_unlocked_week']) && $_SESSION['customers_unlocked_week'] === $week_number) {
    $access_granted = true;
}

if (!$access_granted):
?>
<div class="max-w-md mx-auto mt-20 bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-500 mb-4">
            <i class='bx bx-lock-alt text-3xl'></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">منطقة محمية</h3>
        <p class="text-gray-500 text-sm">يرجى إدخال الرقم السري الخاص بهذا الأسبوع للوصول إلى بيانات العملاء. اطلب الرقم من المدير الرئيسي.</p>
    </div>
    
    <?php if (isset($pass_error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 p-3 rounded-lg mb-4 text-sm text-center font-medium"><?= htmlspecialchars($pass_error) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <input type="text" name="customer_password" class="w-full border-gray-300 rounded-xl p-3 focus:ring-blue-500 focus:border-blue-500 text-center text-lg uppercase tracking-widest font-mono shadow-sm" placeholder="XXXXXX" required autocomplete="off">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white rounded-xl p-3 font-bold hover:bg-blue-700 transition shadow-md">فتح البيانات</button>
    </form>
</div>
<?php
return; // Stop rendering the rest of the page for unauthorized users
endif;
?>

<?php if ($is_super_admin && $show_new_password_alert): ?>
    <div class="mb-6 bg-green-50 border border-green-200 text-green-800 p-6 rounded-xl shadow-md">
        <h3 class="text-xl font-bold mb-2 flex items-center"><i class='bx bx-check-shield text-2xl mr-2'></i> أهلاً بك في الأسبوع الجديد!</h3>
        <p class="text-green-700 mb-4">لقد تم إدخالك مباشرة هذه المرة فقط (لأن الرقم السري تغير). <strong>الرقم السري الجديد</strong> الخاص ببيانات العملاء لهذا الأسبوع هو:</p>
        <div class="inline-block bg-white px-6 py-2 rounded-lg border border-green-300 shadow-sm font-mono text-3xl font-bold tracking-widest text-green-900 mb-2">
            <?= htmlspecialchars($weekly_password) ?>
        </div>
        <p class="text-sm font-bold text-red-600 mt-2"><i class='bx bx-error'></i> يرجى الاحتفاظ بهذا الرقم! في المرة القادمة التي تفتح فيها هذه الصفحة، سيُطلب منك إدخاله.</p>
    </div>
<?php endif; ?>

<?php
// Helper function to get status indicator
function get_status_indicator($status) {
    $success = ['delivered', 'received'];
    $fail = ['cancelled', 'order_cancelled', 'return', 'returned'];
    $confirmed = ['order_confirmed'];
    if (in_array($status, $success)) return ['icon' => "<i class='bx bxs-check-circle text-green-500'></i>", 'type' => 'success'];
    if (in_array($status, $fail)) return ['icon' => "<i class='bx bxs-x-circle text-red-500'></i>", 'type' => 'fail'];
    if (in_array($status, $confirmed)) return ['icon' => "<i class='bx bx-check-double text-blue-500'></i>", 'type' => 'confirmed'];
    return ['icon' => "<i class='bx bxs-time-five text-yellow-500'></i>", 'type' => 'pending'];
}

$view_phone = $_GET['phone'] ?? '';
$search_q = $_GET['q'] ?? '';

if (!empty($view_phone)):
    // ==========================================
    // DETAIL VIEW
    // ==========================================
    $phone_safe = $conn->real_escape_string($view_phone);
    $orders_res = $conn->query("SELECT * FROM support_orders WHERE phone = '$phone_safe' ORDER BY created_at DESC");
    
    $all_orders = [];
    $total_spent = 0;
    $customer_name = '';
    
    while ($row = $orders_res->fetch_assoc()) {
        $all_orders[] = $row;
        if (empty($customer_name) && !empty($row['recipient_name'])) $customer_name = $row['recipient_name'];
        if (empty($customer_name) && !empty($row['customer_name'])) $customer_name = $row['customer_name'];
        
        // Sum total spent for successful orders
        if (in_array($row['order_status'], ['delivered', 'received'])) {
            $total_spent += (float)$row['total_price'];
        }
    }
    
    $last_3_orders = array_slice($all_orders, 0, 3);
    $indicators = '';
    $fail_count = 0;
    $ind_success = 0;
    $ind_fail = 0;
    $ind_pending = 0;
    
    foreach ($all_orders as $ord) {
        $st = $ord['order_status'];
        if (in_array($st, ['delivered', 'received'])) $ind_success++;
        elseif (in_array($st, ['cancelled', 'order_cancelled', 'return', 'returned', 'no_answer_1', 'no_answer_2', 'no_answer_3', 'wrong_number'])) $ind_fail++;
        else $ind_pending++;
    }

    foreach ($last_3_orders as $lo) {
        $ind = get_status_indicator($lo['order_status']);
        $indicators .= $ind['icon'];
        if ($ind['type'] === 'fail') $fail_count++;
    }
    $is_rejected = ($fail_count === 3 && count($last_3_orders) === 3);

    $ind_total = $ind_success + $ind_pending + $ind_fail;
    $ind_succ_p = $ind_total > 0 ? round(($ind_success / $ind_total) * 100) : 0;
    $ind_pend_p = $ind_total > 0 ? round(($ind_pending / $ind_total) * 100) : 0;
    $ind_fail_p = $ind_total > 0 ? round(($ind_fail / $ind_total) * 100) : 0;

    // Status map definition
    $status_map = [
        'order_confirmed' => 'تم تأكيد الطلب',
        'no_answer_1' => 'لا يرد (1)',
        'no_answer_2' => 'لا يرد (2)',
        'no_answer_3' => 'لا يرد (3)',
        'contact_later' => 'التواصل لاحقاً',
        'wrong_number' => 'رقم خطأ',
        'cancelled' => 'ملغي',
        'order_cancelled' => 'ملغي',
        'received' => 'محصل',
        'delivered' => 'تم التوصيل',
        'ready_to_ship' => 'جاهز للشحن',
        'pending' => 'قيد الانتظار',
        'in_progress' => 'جاري التجهيز',
        'handed_to_rep' => 'مسلم للمندوب',
        'return' => 'مرتجع',
        'returned' => 'مرتجع'
    ];
?>
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="admin_panel.php?page=customers" class="bg-gray-100 text-gray-700 hover:bg-gray-200 px-3 py-2 rounded-lg transition"><i class='bx bx-arrow-back'></i> عودة</a>
                <h2 class="text-2xl font-bold text-gray-800">تفاصيل العميل</h2>
            </div>
        </div>
    </div>
    
    <!-- Summary Card & Chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="md:col-span-3 bg-white rounded-xl shadow-sm border <?= $is_rejected ? 'border-red-500 bg-red-50' : 'border-gray-100' ?> overflow-hidden p-6 flex flex-col md:flex-row gap-6 items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-3xl font-bold shadow-inner">
                    <i class='bx bx-user'></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800 <?= $is_rejected ? 'text-red-700' : '' ?>">
                        <?= htmlspecialchars($customer_name ?: 'غير معروف') ?>
                        <?php if ($is_rejected): ?>
                            <span class="inline-flex items-center ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full font-bold shadow-sm"><i class='bx bx-error-circle mr-1'></i> عميل مرفوض</span>
                        <?php endif; ?>
                    </h3>
                    <p class="text-gray-500 font-mono text-lg mt-1" dir="ltr"><?= htmlspecialchars($view_phone) ?></p>
                </div>
            </div>
            
            <div class="flex gap-6">
                <div class="text-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <p class="text-sm text-gray-500 mb-1 font-semibold">التقييم (آخر 3 طلبات)</p>
                    <div class="flex items-center justify-center gap-1 text-2xl"><?= $indicators ?: '-' ?></div>
                </div>
                <div class="text-center bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <p class="text-sm text-gray-500 mb-1 font-semibold">إجمالي الطلبات</p>
                    <p class="text-2xl font-bold text-blue-600"><?= count($all_orders) ?></p>
                </div>
                <div class="text-center bg-green-50 p-3 rounded-lg border border-green-100">
                    <p class="text-sm text-green-700 mb-1 font-semibold">المشتريات الناجحة</p>
                    <p class="text-2xl font-bold text-green-700"><?= number_format($total_spent, 0) ?> ج.م</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-3 flex flex-col items-center justify-center">
            <h3 class="text-sm font-bold text-gray-700 mb-2 border-b w-full text-center pb-1">معدل نجاح العميل</h3>
            <div class="w-full max-w-[130px] relative">
                <canvas id="customerChart"></canvas>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new Chart(document.getElementById('customerChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['مستلم (<?= $ind_succ_p ?>%)', 'انتظار (<?= $ind_pend_p ?>%)', 'ملغي (<?= $ind_fail_p ?>%)'],
                    datasets: [{
                        data: [<?= $ind_success ?>, <?= $ind_pending ?>, <?= $ind_fail ?>],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '70%',
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { family: 'Tahoma', size: 10 }, usePointStyle: true, padding: 10 } }
                    }
                }
            });
        });
    </script>
    
    <!-- Orders Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <h3 class="font-bold text-gray-700">سجل الطلبات التاريخي</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3"># الطلب</th>
                        <th class="px-4 py-3">التاريخ</th>
                        <th class="px-4 py-3">المنتج</th>
                        <th class="px-4 py-3 text-center">القطع</th>
                        <th class="px-4 py-3">المبلغ</th>
                        <th class="px-4 py-3 text-center">الحالة</th>
                        <th class="px-4 py-3">المحافظة</th>
                        <th class="px-4 py-3">دعم / تسويق</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($all_orders as $ord): 
                        $st = $ord['order_status'] ?? '';
                        $stTxt = $status_map[$st] ?? $st;
                    ?>
                    <tr class="hover:bg-blue-50/30 transition">
                        <td class="px-4 py-3 font-bold text-gray-700">#<?= $ord['id'] ?></td>
                        <td class="px-4 py-3 font-mono text-xs" dir="ltr"><?= date('Y-m-d h:i A', strtotime($ord['created_at'])) ?></td>
                        <td class="px-4 py-3 font-medium text-blue-800"><?= htmlspecialchars($ord['product_code'] ?? 'غير محدد') ?></td>
                        <td class="px-4 py-3 text-center font-bold"><?= (int)($ord['pieces'] ?? 1) ?></td>
                        <td class="px-4 py-3 font-bold text-gray-700"><?= number_format((float)($ord['total_price'] ?? 0), 2) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-semibold"><?= htmlspecialchars($stTxt) ?></span>
                        </td>
                        <td class="px-4 py-3 text-xs"><?= htmlspecialchars($ord['governorate'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-xs">
                            <div class="text-blue-600 font-bold"><?= htmlspecialchars($ord['support_name'] ?? '-') ?></div>
                            <div class="text-green-600 font-bold"><?= htmlspecialchars($ord['agent_code'] ?? '-') ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: 
    // ==========================================
    // LIST VIEW
    // ==========================================
    
    // Build query
    $filter_status = $_GET['status'] ?? '';
    $where_clause = "WHERE phone IS NOT NULL AND phone != ''";
    if (!empty($search_q)) {
        $safe_q = $conn->real_escape_string($search_q);
        $where_clause .= " AND (phone LIKE '%$safe_q%' OR recipient_name LIKE '%$safe_q%' OR customer_name LIKE '%$safe_q%')";
    }
    if (!empty($filter_status)) {
        $st_in = '';
        if ($filter_status === 'success') {
            $st_in = "'delivered', 'received'";
        } elseif ($filter_status === 'fail') {
            $st_in = "'cancelled', 'order_cancelled', 'return', 'returned'";
        } elseif ($filter_status === 'pending') {
            $st_in = "'pending', 'in_progress', 'ready_to_ship'"; // Add other pending-like statuses if needed, or just 'pending'
        } elseif ($filter_status === 'confirmed') {
            $st_in = "'order_confirmed'";
        }
        
        if (!empty($st_in)) {
            $where_clause .= " AND EXISTS (SELECT 1 FROM support_orders so2 WHERE so2.phone = support_orders.phone AND so2.order_status IN ($st_in))";
        }
    }
    
    // We group by phone to get unique customers
    $page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $limit = 50;
    $offset = ($page_num - 1) * $limit;
    
    // Get total count
    $count_res = $conn->query("SELECT COUNT(DISTINCT phone) as c FROM support_orders $where_clause");
    $total_customers = $count_res ? (int)$count_res->fetch_assoc()['c'] : 0;
    $total_pages = ceil($total_customers / $limit);
    
    // Get global stats for charts
    $stats_res = $conn->query("
        SELECT 
            SUM(CASE WHEN order_status IN ('delivered', 'received') THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN order_status IN ('cancelled', 'order_cancelled', 'return', 'returned', 'no_answer_1', 'no_answer_2', 'no_answer_3', 'wrong_number') THEN 1 ELSE 0 END) as fail_count,
            SUM(CASE WHEN order_status IN ('pending', 'in_progress', 'ready_to_ship', 'handed_to_rep', 'order_confirmed', 'contact_later') OR order_status IS NULL OR order_status = '' THEN 1 ELSE 0 END) as pending_count,
            COUNT(*) as total_orders
        FROM support_orders
        $where_clause
    ");
    $global_stats = $stats_res ? $stats_res->fetch_assoc() : ['success_count'=>0, 'fail_count'=>0, 'pending_count'=>0, 'total_orders'=>0];
    
    $g_succ = (int)$global_stats['success_count'];
    $g_pend = (int)$global_stats['pending_count'];
    $g_fail = (int)$global_stats['fail_count'];
    $g_total = $g_succ + $g_pend + $g_fail;
    $g_succ_p = $g_total > 0 ? round(($g_succ / $g_total) * 100) : 0;
    $g_pend_p = $g_total > 0 ? round(($g_pend / $g_total) * 100) : 0;
    $g_fail_p = $g_total > 0 ? round(($g_fail / $g_total) * 100) : 0;

    // Fetch unique phones paginated
    $customers_res = $conn->query("
        SELECT phone, 
               MAX(COALESCE(NULLIF(recipient_name, ''), customer_name)) as name,
               COUNT(id) as total_orders,
               MAX(created_at) as last_order_date
        FROM support_orders
        $where_clause
        GROUP BY phone
        ORDER BY last_order_date DESC
        LIMIT $limit OFFSET $offset
    ");
?>

    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">بيانات العملاء</h2>
            <p class="text-gray-500 text-sm mt-1">إجمالي العملاء المسجلين: <strong class="text-blue-600"><?= number_format($total_customers) ?></strong> عميل</p>
        </div>
        
        <form id="customerSearchForm" action="admin_panel.php" method="GET" class="flex flex-wrap gap-2 items-center">
            <input type="hidden" name="page" value="customers">
            
            <select name="status" onchange="doLiveSearch();" class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none shadow-sm text-sm">
                <option value="">جميع الحالات</option>
                <option value="success" <?= $filter_status == 'success' ? 'selected' : '' ?>>تسليم / محصل</option>
                <option value="fail" <?= $filter_status == 'fail' ? 'selected' : '' ?>>إلغاء / مرتجع (كل الـ X)</option>
                <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>انتظار</option>
                <option value="confirmed" <?= $filter_status == 'confirmed' ? 'selected' : '' ?>>تأكيد</option>
            </select>

            <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" 
                   oninput="doLiveSearch()" 
                   placeholder="بحث بالاسم أو الهاتف..." 
                   class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500 outline-none w-64 shadow-sm text-sm">
            
            <?php if(!empty($search_q) || !empty($filter_status)): ?>
                <a href="admin_panel.php?page=customers" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition font-bold shadow-sm text-sm">مسح</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Charts Section for Overall Stats -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <div class="mb-6 flex flex-wrap justify-center gap-6">
        <div class="w-full max-w-[280px] bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col justify-center items-center relative overflow-hidden">
            <div class="absolute top-0 right-0 w-1 h-full bg-blue-500"></div>
            <h3 class="text-sm font-bold text-gray-700 mb-2 w-full border-b border-gray-100 pb-2 text-center">التوزيع الإجمالي</h3>
            <div class="w-[180px] h-[180px] relative">
                <canvas id="overallDoughnutChart"></canvas>
            </div>
        </div>
        <div class="w-full max-w-[340px] bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col justify-center items-center relative overflow-hidden">
            <div class="absolute top-0 right-0 w-1 h-full bg-orange-500"></div>
            <h3 class="text-sm font-bold text-gray-700 mb-2 w-full border-b border-gray-100 pb-2 text-center">مقارنة الحالات</h3>
            <div class="w-full h-[150px] relative">
                <canvas id="overallBarChart"></canvas>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Chart.defaults.font.family = 'Tahoma, Arial, sans-serif';
            
            const ctxDoughnut = document.getElementById('overallDoughnutChart').getContext('2d');
            new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: ['نجاح (<?= $g_succ_p ?>%)', 'قيد الانتظار (<?= $g_pend_p ?>%)', 'فشل (<?= $g_fail_p ?>%)'],
                    datasets: [{
                        data: [<?= $g_succ ?>, <?= $g_pend ?>, <?= $g_fail ?>],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: { 
                    cutout: '70%', 
                    responsive: true, 
                    plugins: { 
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15, font: { weight: 'bold' } } },
                        tooltip: { backgroundColor: 'rgba(17, 24, 39, 0.9)', padding: 12, cornerRadius: 8 }
                    } 
                }
            });

            const ctxBar = document.getElementById('overallBarChart').getContext('2d');
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: ['الناجحة (<?= $g_succ ?>)', 'الانتظار (<?= $g_pend ?>)', 'الملغاة (<?= $g_fail ?>)'],
                    datasets: [{
                        label: 'إجمالي الطلبات',
                        data: [<?= $g_succ ?>, <?= $g_pend ?>, <?= $g_fail ?>],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderRadius: 8,
                        barThickness: 40
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: { backgroundColor: 'rgba(17, 24, 39, 0.9)', padding: 12, cornerRadius: 8 }
                    }, 
                    scales: { 
                        y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f3f4f6' }, border: { display: false } }, 
                        x: { grid: { display: false }, border: { display: false }, ticks: { font: { weight: 'bold' } } } 
                    } 
                }
            });
        });
    </script>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" id="customerTableContainer">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-50 text-gray-600 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 font-bold">اسم العميل</th>
                        <th class="px-6 py-4 font-bold text-left">الهاتف</th>
                        <th class="px-6 py-4 font-bold text-center">إجمالي الطلبات</th>
                        <th class="px-6 py-4 font-bold text-center">آخر ظهور</th>
                        <th class="px-6 py-4 font-bold text-center">التقييم (آخر 3 طلبات)</th>
                        <th class="px-6 py-4 font-bold text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php 
                    if ($customers_res && $customers_res->num_rows > 0):
                        while ($c = $customers_res->fetch_assoc()): 
                            $phone_safe = $conn->real_escape_string($c['phone']);
                            // Fetch last 3 orders for this phone
                            $last3_res = $conn->query("SELECT order_status FROM support_orders WHERE phone = '$phone_safe' ORDER BY created_at DESC LIMIT 3");
                            $indicators = '';
                            $fail_count = 0;
                            $order_count = 0;
                            while ($l3 = $last3_res->fetch_assoc()) {
                                $order_count++;
                                $ind = get_status_indicator($l3['order_status']);
                                $indicators .= $ind['icon'];
                                if ($ind['type'] === 'fail') $fail_count++;
                            }
                            $is_rejected = ($fail_count === 3 && $order_count === 3);
                    ?>
                        <tr class="hover:bg-blue-50/50 transition <?= $is_rejected ? 'bg-red-50/50' : '' ?>">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800 <?= $is_rejected ? 'text-red-700' : '' ?>">
                                    <?= htmlspecialchars($c['name'] ?: 'غير معروف') ?>
                                    <?php if ($is_rejected): ?>
                                        <span class="inline-flex items-center ml-2 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded font-bold shadow-sm"><i class='bx bx-x mr-0.5'></i> مرفوض</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-gray-600 text-left" dir="ltr"><?= htmlspecialchars($c['phone']) ?></td>
                            <td class="px-6 py-4 text-center font-bold text-blue-600"><?= $c['total_orders'] ?></td>
                            <td class="px-6 py-4 text-center text-xs text-gray-500 font-mono" dir="ltr"><?= date('Y-m-d', strtotime($c['last_order_date'])) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-1 text-2xl">
                                    <?= $indicators ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="admin_panel.php?page=customers&phone=<?= urlencode($c['phone']) ?>" class="inline-flex items-center bg-white border border-blue-200 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">
                                    <i class='bx bx-user-pin mr-1'></i> التقرير الكامل
                                </a>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                        <tr><td colspan="6" class="p-8 text-center text-gray-400">لا توجد بيانات عملاء متطابقة مع البحث.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="p-4 border-t border-gray-100 flex justify-center">
            <div class="flex gap-1 overflow-x-auto max-w-full pb-2 items-center">
                <?php 
                $query_string = (!empty($search_q) ? '&q='.urlencode($search_q) : '') . (!empty($filter_status) ? '&status='.urlencode($filter_status) : '');
                ?>
                <!-- First Page -->
                <a href="admin_panel.php?page=customers&p=1<?= $query_string ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition shadow-sm" title="الصفحة الأولى">
                    <i class='bx bx-chevrons-right'></i>
                </a>

                <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                    <a href="admin_panel.php?page=customers&p=<?= $i ?><?= $query_string ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold <?= $i === $page_num ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?> transition shadow-sm">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- Last Page -->
                <a href="admin_panel.php?page=customers&p=<?= $total_pages ?><?= $query_string ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition shadow-sm" title="الصفحة الأخيرة">
                    <i class='bx bx-chevrons-left'></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

<script>
let searchTimeout;
function doLiveSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const form = document.getElementById('customerSearchForm');
        const url = new URL(form.action);
        const params = new URLSearchParams(new FormData(form));
        
        // Remove page number when typing a new search
        params.delete('p');
        url.search = params.toString();
        
        // Indicate AJAX request
        url.searchParams.set('ajax', '1');
        
        const container = document.getElementById('customerTableContainer');
        if (container) container.style.opacity = '0.5';
        
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContainer = doc.getElementById('customerTableContainer');
                
                if (newContainer && container) {
                    container.innerHTML = newContainer.innerHTML;
                }
                if (container) container.style.opacity = '1';
                
                // Update URL without reload
                const newUrl = new URL(url);
                newUrl.searchParams.delete('ajax');
                window.history.pushState({}, '', newUrl);
            });
    }, 400); // 400ms delay for smooth typing
}

// Intercept pagination clicks for AJAX
document.addEventListener('click', function(e) {
    const link = e.target.closest('#customerTableContainer a');
    // Only intercept if it's a pagination link (contains p=) and NOT a detail link
    if (link && link.href && link.href.includes('p=') && !link.href.includes('phone=')) {
        e.preventDefault();
        const url = new URL(link.href);
        url.searchParams.set('ajax', '1');
        
        const container = document.getElementById('customerTableContainer');
        if (container) container.style.opacity = '0.5';
        
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContainer = doc.getElementById('customerTableContainer');
                
                if (newContainer && container) {
                    container.innerHTML = newContainer.innerHTML;
                }
                if (container) container.style.opacity = '1';
                
                // Update URL without reload
                url.searchParams.delete('ajax');
                window.history.pushState({}, '', url);
            });
    }
});
</script>

<?php endif; ?>
