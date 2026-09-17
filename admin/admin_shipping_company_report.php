<?php
// admin_shipping_company_report.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Access Denied");
}

$company_id = (int)($_GET['id'] ?? 0);
if (!$company_id) {
    echo "<div class='p-8 text-center text-red-600'>Invalid Company ID</div>";
    exit;
}

// Fetch company info
$stmt = $conn->prepare("SELECT * FROM shipping_accounts WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    echo "<div class='p-8 text-center text-red-600'>Company not found</div>";
    exit;
}

// Fetch order statistics (from support_orders via shipping reps)
$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status IN ('received', 'delivered') THEN 1 ELSE 0 END) as total_delivered,
        SUM(CASE WHEN order_status IN ('order_cancelled', 'cancelled') THEN 1 ELSE 0 END) as total_cancelled,
        SUM(CASE WHEN order_status IN ('returned', 'return') THEN 1 ELSE 0 END) as total_returned,
        SUM(CASE WHEN order_status NOT IN ('received', 'delivered', 'order_cancelled', 'cancelled', 'returned', 'return') THEN 1 ELSE 0 END) as total_pending
    FROM support_orders 
    WHERE shipping_company_id = ? OR shipping_rep_id IN (SELECT id FROM shipping_company_reps WHERE company_id = ?)
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $company_id, $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$total_orders = (int)$stats['total_orders'];
$delivered = (int)$stats['total_delivered'];
$cancelled = (int)$stats['total_cancelled'];
$returned = (int)$stats['total_returned'];
$pending = (int)$stats['total_pending'];

$del_pct = $total_orders > 0 ? round(($delivered / $total_orders) * 100, 1) : 0;
$can_pct = $total_orders > 0 ? round(($cancelled / $total_orders) * 100, 1) : 0;

// Fetch Financial Statistics
$ship = calculate_shipping_collection_balance($conn, null, null, null, $company_id);
$total_creditor = (float) ($ship['creditor_due'] ?? 0);
$total_debtor = (float) ($ship['debtor_received'] ?? 0);
$net_balance = (float) ($ship['remaining'] ?? 0);
?>

<div class="max-w-7xl mx-auto px-4 py-8 font-sans" dir="rtl">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl ml-4 shadow-lg shadow-indigo-100">
                    <i class='bx bx-building-house'></i>
                </span>
                تقرير شركة الشحن: <?= htmlspecialchars($company['name']) ?>
            </h1>
            <p class="text-slate-500 mt-2 font-medium">الكود: <?= htmlspecialchars($company['code']) ?></p>
        </div>
        <a href="admin_panel.php?page=shipping_accounts" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200 transition font-bold flex items-center">
            <i class='bx bx-arrow-right ml-2'></i> رجوع لشركات الشحن
        </a>
    </div>


    <!-- Orders Section -->
    <h2 class="text-xl font-bold text-slate-800 mb-4"><i class='bx bx-package text-indigo-500 ml-2'></i> إحصائيات الطلبات (من فريق الدعم)</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-list-ul'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">إجمالي الطلبات</h3>
            <p class="text-3xl font-bold text-slate-800"><?= $total_orders ?></p>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-check-double'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">تم التسليم</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-emerald-600"><?= $delivered ?></p>
                <p class="text-emerald-500 text-sm font-bold mb-1">(<?= $del_pct ?>%)</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-x-circle'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">تم الإلغاء</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold text-rose-600"><?= $cancelled ?></p>
                <p class="text-rose-500 text-sm font-bold mb-1">(<?= $can_pct ?>%)</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-undo'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">مرتجع</h3>
            <p class="text-3xl font-bold text-orange-600"><?= $returned ?></p>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col items-center text-center">
            <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-3xl mb-4">
                <i class='bx bx-time-five'></i>
            </div>
            <h3 class="text-slate-500 font-medium mb-1">قيد الانتظار</h3>
            <p class="text-3xl font-bold text-amber-600"><?= $pending ?></p>
        </div>
    </div>

    <!-- Financials Section -->
    <h2 class="text-xl font-bold text-slate-800 mb-4"><i class='bx bx-wallet text-indigo-500 ml-2'></i> السجل المالي والمحاسبة</h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center">
            <div class="w-14 h-14 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center text-2xl ml-4">
                <i class='bx bx-money'></i>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium mb-1">إجمالي المديونية (مدين)</p>
                <p class="text-2xl font-bold text-slate-800"><?= number_format($total_debtor, 2) ?> د.ل</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center">
            <div class="w-14 h-14 rounded-full bg-slate-50 text-slate-600 flex items-center justify-center text-2xl ml-4">
                <i class='bx bx-credit-card-front'></i>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium mb-1">إجمالي المستحقات (دائن)</p>
                <p class="text-2xl font-bold text-slate-800"><?= number_format($total_creditor, 2) ?> د.ل</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center">
            <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl ml-4">
                <i class='bx bx-check-shield'></i>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-medium mb-1">ما تم تحصيله فعلياً</p>
                <p class="text-2xl font-bold text-emerald-600"><?= number_format($total_debtor, 2) ?> د.ل</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center <?= $net_balance > 0 ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' ?>">
            <div class="w-14 h-14 rounded-full <?= $net_balance > 0 ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600' ?> flex items-center justify-center text-2xl ml-4">
                <i class='bx bx-wallet-alt'></i>
            </div>
            <div>
                <p class="<?= $net_balance > 0 ? 'text-red-500' : 'text-emerald-500' ?> text-sm font-medium mb-1">الرصيد المتبقي المستحق</p>
                <p class="text-2xl font-bold <?= $net_balance > 0 ? 'text-red-700' : 'text-emerald-700' ?>"><?= number_format($net_balance, 2) ?> د.ل</p>
            </div>
        </div>
    </div>

    <!-- Company Orders Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mt-12 mb-4 gap-4">
        <h2 class="text-xl font-bold text-slate-800"><i class='bx bx-list-ol text-indigo-500 ml-2'></i> كل طلبات الشركة</h2>
        <div class="relative w-full md:w-96">
            <input type="text" id="orderSearch" placeholder="ابحث برقم الطلب، العميل، الهاتف، المندوب..." 
                   class="w-full bg-white border border-slate-200 rounded-xl pl-4 pr-10 py-2 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all shadow-sm">
            <i class='bx bx-search absolute right-3 top-2.5 text-slate-400 text-lg'></i>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-10">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">رقم الطلب</th>
                        <th class="px-4 py-3">العميل</th>
                        <th class="px-4 py-3">المنتج</th>
                        <th class="px-4 py-3">المبلغ</th>
                        <th class="px-4 py-3 text-center">الحالة</th>
                        <th class="px-4 py-3">المندوب المسئول</th>
                        <th class="px-4 py-3">الدعم الفني</th>
                        <th class="px-4 py-3">الماركتينج</th>
                        <th class="px-4 py-3">تاريخ الطلب</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody" class="divide-y divide-slate-100">
                    <?php
                    $orders_query = "
                        SELECT o.id, o.customer_name, o.phone, o.address, o.governorate, o.order_status, 
                               o.support_name, o.agent_code, o.total_price, r.name as rep_name, o.created_at,
                               o.pieces, o.bundle_type, o.product_code
                        FROM support_orders o
                        LEFT JOIN shipping_company_reps r ON o.shipping_rep_id = r.id
                        WHERE o.shipping_company_id = ? 
                           OR o.shipping_rep_id IN (SELECT id FROM shipping_company_reps WHERE company_id = ?)
                        ORDER BY o.created_at DESC
                    ";
                    $stmt_orders = $conn->prepare($orders_query);
                    $stmt_orders->bind_param("ii", $company_id, $company_id);
                    $stmt_orders->execute();
                    $res_orders = $stmt_orders->get_result();
                    $status_map = [
                        'pending' => 'قيد الانتظار',
                        'in_progress' => 'جاري التجهيز',
                        'ready_to_ship' => 'جاهز للشحن',
                        'handed_to_rep' => 'مسلم للمندوب',
                        'delivered' => 'تم التوصيل',
                        'received' => 'محصل',
                        'return' => 'مرتجع',
                        'returned' => 'مرتجع',
                        'cancelled' => 'ملغي',
                        'order_cancelled' => 'ملغي'
                    ];

                    if ($res_orders->num_rows > 0):
                        while ($ord = $res_orders->fetch_assoc()):
                            $ord_status_ar = $status_map[$ord['order_status']] ?? $ord['order_status'];
                    ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-4 font-bold text-slate-700">#<?= $ord['id'] ?></td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($ord['customer_name'] ?? 'غير معروف') ?></div>
                                <div class="text-xs text-slate-500 font-mono mt-1" dir="ltr"><?= htmlspecialchars($ord['phone'] ?? '') ?></div>
                                <?php $full_addr = trim(($ord['governorate'] ?? '') . ' - ' . ($ord['address'] ?? ''), ' -'); ?>
                                <div class="text-xs text-slate-400 mt-1 max-w-xs truncate" title="<?= htmlspecialchars($full_addr) ?>"><?= htmlspecialchars($full_addr) ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($ord['product_code'] ?? 'غير محدد') ?></div>
                                <div class="text-xs text-slate-500 mt-1">
                                    النوع: <span class="font-semibold"><?= htmlspecialchars($ord['bundle_type'] === 'single' ? 'Single' : ($ord['bundle_type'] === 'bundle' ? 'Bundle' : ($ord['bundle_type'] === 'custom' ? 'طلب مخصص' : ($ord['bundle_type'] ?: 'Single')))) ?></span>
                                    | العدد: <span class="font-semibold text-indigo-600"><?= (int)($ord['pieces'] ?? 1) ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-4 font-bold text-indigo-600"><?= number_format($ord['total_price'] ?? 0, 2) ?></td>
                            <td class="px-4 py-4 text-center">
                                <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($ord_status_ar) ?></span>
                            </td>
                            <td class="px-4 py-4 font-bold text-slate-700"><?= htmlspecialchars($ord['rep_name'] ?? 'غير محدد') ?></td>
                            <td class="px-4 py-4 font-bold text-blue-600"><?= htmlspecialchars($ord['support_name'] ?? 'غير محدد') ?></td>
                            <td class="px-4 py-4 font-bold text-emerald-600"><?= htmlspecialchars($ord['agent_code'] ?? 'غير محدد') ?></td>
                            <td class="px-4 py-4 text-xs text-slate-500 font-mono" dir="ltr"><?= date('Y-m-d h:i A', strtotime($ord['created_at'])) ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500 font-bold">لا توجد طلبات مرتبطة بهذه الشركة بعد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('orderSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#ordersTableBody tr');
        
        rows.forEach(row => {
            // تخطي صف "لا توجد طلبات"
            if (row.children.length === 1) return; 
            
            const textContent = row.textContent.toLowerCase();
            if (textContent.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>
