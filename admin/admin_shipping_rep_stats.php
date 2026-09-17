<?php
// admin_shipping_rep_stats.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Access Denied");
}

$rep_id = (int)($_GET['id'] ?? 0);
if (!$rep_id) {
    echo "<div class='p-8 text-center text-red-600'>Invalid Representative ID</div>";
    exit;
}

// Fetch rep info
$stmt = $conn->prepare("
    SELECT r.*, c.name as company_name 
    FROM shipping_company_reps r 
    JOIN shipping_accounts c ON r.company_id = c.id 
    WHERE r.id = ?
");
$stmt->bind_param("i", $rep_id);
$stmt->execute();
$rep = $stmt->get_result()->fetch_assoc();

if (!$rep) {
    echo "<div class='p-8 text-center text-red-600'>Representative not found</div>";
    exit;
}

// Fetch order statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status IN ('received', 'delivered') THEN 1 ELSE 0 END) as total_delivered,
        SUM(CASE WHEN order_status IN ('order_cancelled', 'cancelled') THEN 1 ELSE 0 END) as total_cancelled,
        SUM(CASE WHEN order_status IN ('returned', 'return') THEN 1 ELSE 0 END) as total_returned,
        SUM(CASE WHEN order_status NOT IN ('received', 'delivered', 'order_cancelled', 'cancelled', 'returned', 'return') THEN 1 ELSE 0 END) as total_pending
    FROM support_orders 
    WHERE shipping_rep_id = $rep_id
")->fetch_assoc();

$total = (int)$stats['total_orders'];
$delivered = (int)$stats['total_delivered'];
$cancelled = (int)$stats['total_cancelled'];
$returned = (int)$stats['total_returned'];
$pending = (int)$stats['total_pending'];

$del_pct = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;
$can_pct = $total > 0 ? round(($cancelled / $total) * 100, 1) : 0;
$ret_pct = $total > 0 ? round(($returned / $total) * 100, 1) : 0;
$pen_pct = $total > 0 ? round(($pending / $total) * 100, 1) : 0;

// Render stats cards
?>
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8 font-sans" dir="rtl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl ml-4 shadow-lg shadow-indigo-100">
                    <i class='bx bx-bar-chart-alt-2'></i>
                </span>
                إحصائيات المندوب: <?= htmlspecialchars($rep['name']) ?>
            </h1>
            <p class="text-slate-500 mt-2 font-medium">شركة الشحن: <?= htmlspecialchars($rep['company_name']) ?> | الهاتف: <span dir="ltr"><?= htmlspecialchars($rep['phone']) ?></span></p>
        </div>
        <?php
        $back_url = 'admin_panel.php?page=shipping_reps';
        $back_text = 'رجوع للمناديب';
        if ($_SESSION['admin_role'] === 'super_admin' || $_SESSION['admin_role'] === 'admin') {
            $back_url = 'admin_panel.php?page=shipping_accounts';
            $back_text = 'رجوع لشركات الشحن';
        }
        ?>
        <a href="<?= $back_url ?>" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200 transition font-bold">
            <i class='bx bx-arrow-right'></i> <?= $back_text ?>
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <!-- Total -->
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center text-center hover:shadow-md transition">
            <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-package'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">إجمالي الطلبات</h3>
            <p class="text-3xl font-bold text-slate-800"><?= $total ?></p>
        </div>

        <!-- Delivered -->
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center text-center hover:shadow-md transition">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-check-double'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">تم التسليم</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-emerald-600"><?= $delivered ?></p>
                <p class="text-emerald-500 text-sm font-bold mb-1">(<?= $del_pct ?>%)</p>
            </div>
        </div>

        <!-- Cancelled -->
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center text-center hover:shadow-md transition">
            <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-x-circle'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">ملغاة</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-rose-600"><?= $cancelled ?></p>
                <p class="text-rose-500 text-sm font-bold mb-1">(<?= $can_pct ?>%)</p>
            </div>
        </div>

        <!-- Returned -->
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center text-center hover:shadow-md transition">
            <div class="w-16 h-16 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-undo'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">مرتجع</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-orange-600"><?= $returned ?></p>
                <p class="text-orange-500 text-sm font-bold mb-1">(<?= $ret_pct ?>%)</p>
            </div>
        </div>

        <!-- Pending -->
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col justify-center items-center text-center hover:shadow-md transition">
            <div class="w-16 h-16 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-time-five'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">قيد التنفيذ</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-slate-600"><?= $pending ?></p>
                <p class="text-slate-500 text-sm font-bold mb-1">(<?= $pen_pct ?>%)</p>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mt-8">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">أحدث الطلبات المسندة (آخر 100)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="p-4">رقم الطلب</th>
                        <th class="p-4">العميل</th>
                        <th class="p-4">الهاتف</th>
                        <th class="p-4">الحالة</th>
                        <th class="p-4">السعر الإجمالي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php
                    $orders = $conn->query("SELECT * FROM support_orders WHERE shipping_rep_id = $rep_id ORDER BY id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
                    if(empty($orders)):
                    ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-400">لا توجد طلبات مسندة لهذا المندوب.</td>
                    </tr>
                    <?php else: foreach($orders as $o): 
                        $st = $o['order_status'];
                        $isDel = in_array($st, ['received', 'delivered']);
                        $isCan = in_array($st, ['order_cancelled', 'cancelled']);
                        $isRet = in_array($st, ['returned', 'return']);
                        $badge = 'bg-slate-100 text-slate-700';
                        if ($isDel) $badge = 'bg-emerald-100 text-emerald-800';
                        if ($isCan) $badge = 'bg-rose-100 text-rose-800';
                        if ($isRet) $badge = 'bg-orange-100 text-orange-800';
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-4 font-mono font-bold text-slate-800">#<?= $o['id'] ?></td>
                        <td class="p-4 font-medium"><?= htmlspecialchars($o['customer_name'] ?? '') ?></td>
                        <td class="p-4" dir="ltr"><?= htmlspecialchars($o['phone'] ?? '') ?></td>
                        <td class="p-4">
                            <span class="px-3 py-1 rounded-full font-bold text-xs <?= $badge ?>">
                                <?= htmlspecialchars($st) ?>
                            </span>
                        </td>
                        <td class="p-4 font-bold"><?= htmlspecialchars($o['total_price'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
