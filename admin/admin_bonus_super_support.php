<?php
// ملف: admin_bonus_super.php
if (!isset($_SESSION['admin_logged_in']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) { 
    header("Location: admin_panel.php"); exit; 
}

// 1. Create withdrawals table if not exists (safeguard)
$conn->query("CREATE TABLE IF NOT EXISTS support_bonus_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (support_id) REFERENCES admins(id) ON DELETE CASCADE
)");

// Handle Approve / Reject
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['withdrawal_id'])) {
    $w_id = (int) $_POST['withdrawal_id'];
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    
    $stmt = $conn->prepare("UPDATE support_bonus_withdrawals SET status = ? WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("si", $action, $w_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_msg = "تم " . ($action === 'approved' ? "الموافقة على" : "رفض") . " طلب السحب رقم #$w_id بنجاح.";
    } else {
        $error_msg = "حدث خطأ، ربما تمت معالجة الطلب مسبقاً.";
    }
}

// Get all support members
$support_members = [];
$q_sup = $conn->query("SELECT id, fullname, username FROM admins WHERE role = 'support'");
if ($q_sup) {
    while ($r = $q_sup->fetch_assoc()) {
        $support_members[$r['id']] = $r;
    }
}

// Get all stats
$delivered_statuses = "'delivered', 'received'";
$confirmed_statuses = "'confirmed', 'delivered', 'received', 'handed_to_rep', 'ready_to_ship', 'out_for_delivery'";

$stats_data = [];

// Base data
foreach ($support_members as $id => $member) {
    $stats_data[$id] = [
        'name' => $member['fullname'],
        'single_delivered' => 0,
        'bundle_delivered' => 0,
        'bundle_assigned' => 0,
        'bundle_revenue' => 0,
        'confirmed_total' => 0,
        'delivered_total' => 0,
        'total_approved' => 0,
        'total_pending' => 0,
    ];
}

// Single delivered
$q_single = $conn->query("SELECT support_id, COUNT(*) as c FROM support_orders WHERE bundle_type = 'single' AND order_status IN ($delivered_statuses) GROUP BY support_id");
while ($r = $q_single->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) $stats_data[$r['support_id']]['single_delivered'] = (int)$r['c'];
}

// Bundle delivered
$q_bundle = $conn->query("SELECT support_id, COUNT(*) as c FROM support_orders WHERE bundle_type = 'bundle' AND order_status IN ($delivered_statuses) GROUP BY support_id");
while ($r = $q_bundle->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) $stats_data[$r['support_id']]['bundle_delivered'] = (int)$r['c'];
}

// Bundle assigned
$q_bundle_assigned = $conn->query("SELECT support_id, COUNT(*) as c FROM support_orders WHERE bundle_type = 'bundle' GROUP BY support_id");
while ($r = $q_bundle_assigned->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) $stats_data[$r['support_id']]['bundle_assigned'] = (int)$r['c'];
}

// Bundle revenue
$q_rev = $conn->query("SELECT support_id, SUM(IFNULL(NULLIF(total_price,0), 0)) as total FROM support_orders WHERE bundle_type = 'bundle' AND order_status IN ($delivered_statuses) GROUP BY support_id");
while ($r = $q_rev->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) $stats_data[$r['support_id']]['bundle_revenue'] = (float)$r['total'];
}

// Confirmed & Delivered for discount rate
$q_conf = $conn->query("SELECT support_id, COUNT(*) as c FROM support_orders WHERE order_status IN ($confirmed_statuses) GROUP BY support_id");
while ($r = $q_conf->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) $stats_data[$r['support_id']]['confirmed_total'] = (int)$r['c'];
}

$q_del = $conn->query("SELECT support_id, COUNT(*) as c FROM support_orders WHERE order_status IN ($delivered_statuses) GROUP BY support_id");
while ($r = $q_del->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) $stats_data[$r['support_id']]['delivered_total'] = (int)$r['c'];
}

// Withdrawals
$q_w = $conn->query("SELECT support_id, 
    SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending
    FROM support_bonus_withdrawals GROUP BY support_id");
while ($r = $q_w->fetch_assoc()) {
    if (isset($stats_data[$r['support_id']])) {
        $stats_data[$r['support_id']]['total_approved'] = (float)$r['total_approved'];
        $stats_data[$r['support_id']]['total_pending'] = (float)$r['total_pending'];
    }
}

// Process calculation for each
foreach ($stats_data as $id => &$d) {
    $single_bonus = $d['single_delivered'] * 10;
    $bundle_bonus = $d['bundle_delivered'] * 15;
    
    $overall_rate = $d['confirmed_total'] > 0 ? ($d['delivered_total'] / $d['confirmed_total']) : 0;
    $d['overall_rate'] = $overall_rate;

    $extra_bonus = ($overall_rate >= 0.5) ? ($d['bundle_revenue'] * 0.01) : 0;
    $d['extra_bonus'] = $extra_bonus;
    
    $base_bonus = $single_bonus + $bundle_bonus + $extra_bonus;
    $d['final_bonus'] = $base_bonus;
    
    if ($overall_rate <= 0.30) {
        $d['final_bonus'] = 0;
    } elseif ($overall_rate < 0.40) {
        $d['final_bonus'] = $base_bonus * 0.5;
    }
    
    $d['available'] = max(0, $d['final_bonus'] - $d['total_approved'] - $d['total_pending']);
}
unset($d); // break ref

// Get all pending requests
$pending_requests = $conn->query("SELECT w.*, a.fullname FROM support_bonus_withdrawals w JOIN admins a ON w.support_id = a.id WHERE w.status = 'pending' ORDER BY w.created_at ASC");
?>
<div class="space-y-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-3xl font-bold text-gray-800"><i class='bx bx-gift text-indigo-500'></i> إدارة مكافآت (بونص) الدعم الفني</h2>
    </div>

    <?php if ($success_msg): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class='bx bx-check-circle mr-2'></i> <?= $success_msg ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class='bx bx-x-circle mr-2'></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <!-- Pending Requests -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="p-4 border-b bg-orange-50 flex items-center">
            <i class='bx bx-time text-orange-500 text-xl mr-2'></i>
            <h3 class="font-bold text-orange-700">طلبات السحب المعلقة</h3>
        </div>
        <div class="overflow-x-auto p-4">
            <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="p-3">رقم الطلب</th>
                        <th class="p-3">الموظف</th>
                        <th class="p-3">المبلغ المطلوب</th>
                        <th class="p-3">تاريخ الطلب</th>
                        <th class="p-3 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($req = $pending_requests->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 font-mono text-gray-500">#<?= $req['id'] ?></td>
                        <td class="p-3 font-bold text-gray-700"><?= htmlspecialchars($req['fullname']) ?></td>
                        <td class="p-3 font-bold text-indigo-600"><?= number_format($req['amount'], 2) ?> دل</td>
                        <td class="p-3 text-gray-500" dir="ltr"><?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></td>
                        <td class="p-3 flex justify-center gap-2">
                            <form method="POST" onsubmit="uiConfirmSubmit(event, this.querySelector('button[name=action][value=approve]'), 'تأكيد الموافقة', 'هل أنت متأكد من الموافقة على سحب مبلغ <?= number_format($req['amount'], 2) ?> دل؟', 'نعم، أوافق')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="withdrawal_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="action" value="approve" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-bold transition">
                                    موافقة
                                </button>
                            </form>
                            <form method="POST" onsubmit="uiConfirmSubmit(event, this.querySelector('button[name=action][value=reject]'), 'تأكيد الرفض', 'هل أنت متأكد من رفض هذا الطلب؟', 'نعم، ارفض', true)">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="withdrawal_id" value="<?= $req['id'] ?>">
                                <button type="submit" name="action" value="reject" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-bold transition">
                                    رفض
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p class="text-center text-gray-500">لا توجد طلبات سحب معلقة حالياً.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Support Members Stats -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="font-bold text-gray-700">إحصائيات بونص موظفي الدعم الفني</h3>
        </div>
        <div class="overflow-x-auto p-4">
            <table class="w-full text-right text-sm table-auto border-collapse">
                <thead class="bg-gray-100 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="p-3 border">الموظف</th>
                        <th class="p-3 border text-center">بونص الـ Single</th>
                        <th class="p-3 border text-center">بونص الـ Bundle</th>
                        <th class="p-3 border text-center">إضافي (1%)</th>
                        <th class="p-3 border text-center" title="نسبة المسلم إلى المؤكد">نسبة التسليم (المؤكد)</th>
                        <th class="p-3 border text-center bg-indigo-50">البونص المكتسب</th>
                        <th class="p-3 border text-center text-green-700 bg-green-50">مسحوب</th>
                        <th class="p-3 border text-center text-indigo-700 bg-indigo-50 font-bold">متاح للسحب</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_data as $id => $d): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 font-bold text-gray-700 border"><?= htmlspecialchars($d['name']) ?></td>
                        <td class="p-3 text-center border">
                            <span class="block text-gray-800"><?= number_format($d['single_delivered'] * 10, 2) ?></span>
                            <span class="text-[10px] text-gray-400"><?= $d['single_delivered'] ?> طلب</span>
                        </td>
                        <td class="p-3 text-center border">
                            <span class="block text-gray-800"><?= number_format($d['bundle_delivered'] * 15, 2) ?></span>
                            <span class="text-[10px] text-gray-400"><?= $d['bundle_delivered'] ?> طلب</span>
                        </td>
                        <td class="p-3 text-center border">
                            <span class="block <?= $d['extra_bonus'] > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                                <?= number_format($d['extra_bonus'], 2) ?>
                            </span>
                            <span class="text-[10px] text-gray-400">النسبة العامة: <?= round($d['overall_rate'] * 100, 1) ?>%</span>
                        </td>
                        <td class="p-3 text-center border">
                            <?php
                            $rate_pct = round($d['overall_rate'] * 100, 1);
                            $color = $rate_pct > 40 ? 'text-green-600' : ($rate_pct > 30 ? 'text-orange-500' : 'text-red-500');
                            ?>
                            <span class="font-bold <?= $color ?>"><?= $rate_pct ?>%</span>
                            <span class="text-[10px] text-gray-400 block">(<?= $d['delivered_total'] ?>/<?= $d['confirmed_total'] ?>)</span>
                        </td>
                        <td class="p-3 text-center font-bold text-indigo-600 border bg-indigo-50/30">
                            <?= number_format($d['final_bonus'], 2) ?>
                        </td>
                        <td class="p-3 text-center font-bold text-green-600 border bg-green-50/30">
                            <?= number_format($d['total_approved'], 2) ?>
                        </td>
                        <td class="p-3 text-center font-bold text-indigo-700 border bg-indigo-50">
                            <?= number_format($d['available'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
