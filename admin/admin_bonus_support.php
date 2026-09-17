<?php
// ملف: admin_bonus_support.php
if (!isset($_SESSION['admin_logged_in'])) { header("Location: admin_login.php"); exit; }
$support_id = (int) $_SESSION['admin_id'];

// 1. Create withdrawals table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS support_bonus_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (support_id) REFERENCES admins(id) ON DELETE CASCADE
)");

// Handle withdrawal request
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $req_amount = (float) $_POST['amount'];
    $max_amount = (float) $_POST['max_amount'];
    if ($req_amount <= 0) {
        $error_msg = 'المبلغ يجب أن يكون أكبر من الصفر.';
    } elseif ($req_amount > $max_amount) {
        $error_msg = 'عفواً، لا يمكنك سحب مبلغ أكبر من رصيدك القابل للسحب.';
    } else {
        $stmt = $conn->prepare("INSERT INTO support_bonus_withdrawals (support_id, amount, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("id", $support_id, $req_amount);
        if ($stmt->execute()) {
            $success_msg = 'تم إرسال طلب السحب بنجاح بانتظار موافقة الإدارة.';
        } else {
            $error_msg = 'حدث خطأ أثناء إرسال الطلب.';
        }
    }
}

// 2. Calculate Bonus Data
// Helper statuses
$delivered_statuses = "'delivered', 'received'";
$confirmed_statuses = "'confirmed', 'delivered', 'received', 'handed_to_rep', 'ready_to_ship', 'out_for_delivery'";

// Single Orders
$q_single = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $support_id AND bundle_type = 'single' AND order_status IN ($delivered_statuses)");
$single_delivered = $q_single ? (int)$q_single->fetch_assoc()['c'] : 0;
$single_bonus = $single_delivered * 10;

// Bundle Orders
$q_bundle = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $support_id AND bundle_type = 'bundle' AND order_status IN ($delivered_statuses)");
$bundle_delivered = $q_bundle ? (int)$q_bundle->fetch_assoc()['c'] : 0;

$q_bundle_assigned = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $support_id AND bundle_type = 'bundle'");
$bundle_assigned = $q_bundle_assigned ? (int)$q_bundle_assigned->fetch_assoc()['c'] : 0;

$bundle_bonus = $bundle_delivered * 15;

// Overall Confirmed vs Delivered
$q_confirmed = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $support_id AND order_status IN ($confirmed_statuses)");
$confirmed_total = $q_confirmed ? (int)$q_confirmed->fetch_assoc()['c'] : 0;

$q_delivered = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $support_id AND order_status IN ($delivered_statuses)");
$delivered_total = $q_delivered ? (int)$q_delivered->fetch_assoc()['c'] : 0;

$overall_delivery_rate = $confirmed_total > 0 ? ($delivered_total / $confirmed_total) : 0;

// Bundle Revenue & 1% extra
$extra_bonus = 0;
$total_bundle_revenue = 0;

$bundle_rate_from_confirmed = $confirmed_total > 0 ? ($bundle_delivered / $confirmed_total) : 0;

if ($bundle_rate_from_confirmed >= 0.5) {
    // Get total revenue of delivered bundle orders
    $q_rev = $conn->query("SELECT SUM(IFNULL(NULLIF(total_price,0), 0)) as total FROM support_orders WHERE support_id = $support_id AND bundle_type = 'bundle' AND order_status IN ($delivered_statuses)");
    if ($q_rev) {
        $total_bundle_revenue = (float)$q_rev->fetch_assoc()['total'];
        $extra_bonus = $total_bundle_revenue * 0.01;
    }
}

$base_bonus = $single_bonus + $bundle_bonus + $extra_bonus;
$final_bonus = $base_bonus;

$discount_msg = 'لم يتم خصم شيء (نسبة التسليم جيدة).';
$discount_color = 'text-green-600';

if ($delivered_total < 100) {
    $final_bonus = 0;
    $discount_msg = 'لا يوجد بونص (لم تحقق الحد الأدنى 100 طلب مسلم).';
    $discount_color = 'text-red-600';
} else {
    $rate_percent = round($overall_delivery_rate * 100);
    if ($rate_percent < 25) {
        $final_bonus = 0;
        $discount_msg = 'تم خصم البونص بالكامل (نسبة التسليم أقل من 25%).';
        $discount_color = 'text-red-600';
    } elseif ($rate_percent <= 30) {
        $final_bonus = $base_bonus * 0.5;
        $discount_msg = 'تم خصم 50% من البونص (نسبة التسليم بين 25% و 30%).';
        $discount_color = 'text-orange-600';
    } elseif ($rate_percent < 50) {
        $final_bonus = $base_bonus * 0.75;
        $discount_msg = 'تم خصم 25% من البونص (نسبة التسليم أقل من 50%).';
        $discount_color = 'text-yellow-600';
    }
}

// Check Withdrawals
$q_w = $conn->query("SELECT 
    SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending
    FROM support_bonus_withdrawals WHERE support_id = $support_id");
$w_data = $q_w->fetch_assoc();
$total_withdrawn = (float) $w_data['total_approved'];
$total_pending = (float) $w_data['total_pending'];

$available_bonus = max(0, $final_bonus - $total_withdrawn - $total_pending);

// Get Withdrawal History
$withdrawals = $conn->query("SELECT * FROM support_bonus_withdrawals WHERE support_id = $support_id ORDER BY id DESC LIMIT 20");
?>
<div class="p-6 md:p-8 space-y-6">

    <!-- سياسة البونص -->
    <div class="bg-indigo-50 border-r-4 border-indigo-600 rounded-lg p-6 mb-8 shadow-sm">
        <h3 class="text-xl font-bold text-indigo-800 mb-4 flex items-center">
            <i class='bx bx-info-circle text-2xl ml-2'></i>
            سياسة بونص الدعم الفني
        </h3>
        <ul class="list-disc list-inside text-indigo-900 space-y-3 font-medium text-sm md:text-base leading-relaxed">
            <li><strong>الطلبات الفردية (Single):</strong> يتم احتساب <span class="font-bold text-indigo-700">10 دل</span> عن كل طلب فردي يتم تسليمه بنجاح للعميل.</li>
            <li><strong>الطلبات المجمعة (Bundle):</strong> يتم احتساب <span class="font-bold text-indigo-700">15 دل</span> عن كل طلب مجمع (Bundle) يتم تسليمه للعميل.</li>
            <li><strong>البونص الإضافي (1%):</strong> في حال كانت نسبة التسليمات الخاصة بك من إجمالي الطلبات المؤكدة <span class="font-bold text-indigo-700">50% Bundle فأكثر</span>، ستحصل على بونص إضافي قدره <span class="font-bold text-indigo-700">1% من إجمالي مبيعات طلبات الـ Bundle المسلمة</span>.</li>
            <li><strong>شرط الحد الأدنى:</strong> لا يتم احتساب أي بونص إلا في حال تحقيق <span class="font-bold text-red-600">100 طلب مسلم</span> على الأقل كحد أدنى.</li>
            <li><strong>خصم الأداء:</strong> يتم حساب نسبة توصيلك العامة (الطلبات المسلمة / الطلبات المؤكدة). إذا انخفضت النسبة إلى ما دون <span class="font-bold text-red-600">50%</span> يتم خصم <span class="font-bold text-red-600">25%</span> من البونص الخاص بك، وإذا وصلت إلى <span class="font-bold text-red-600">30%</span> تفقد نصف البونص، وإذا كانت <span class="font-bold text-red-600">أقل من 25%</span> تفقد البونص بالكامل.</li>
        </ul>
    </div>

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-3xl font-bold text-gray-800"><i class='bx bx-gift text-indigo-500'></i> مكافآتك (البونص)</h2>
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <p class="text-sm text-gray-500 font-bold mb-1">إجمالي البونص المكتسب</p>
            <p class="text-2xl font-bold text-indigo-600"><?= number_format($final_bonus, 2) ?> دل</p>
            <p class="text-xs text-gray-400 mt-2">تراكمي مدى الحياة</p>
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <p class="text-sm text-gray-500 font-bold mb-1">رصيد قيد المراجعة</p>
            <p class="text-2xl font-bold text-orange-500"><?= number_format($total_pending, 2) ?> دل</p>
            <p class="text-xs text-gray-400 mt-2">طلبات سحب معلقة</p>
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <p class="text-sm text-gray-500 font-bold mb-1">تم سحبه مسبقاً</p>
            <p class="text-2xl font-bold text-green-600"><?= number_format($total_withdrawn, 2) ?> دل</p>
            <p class="text-xs text-gray-400 mt-2">طلبات سحب تمت الموافقة عليها</p>
        </div>
        <div class="bg-indigo-50 rounded-xl p-6 shadow-sm border border-indigo-100">
            <p class="text-sm text-indigo-800 font-bold mb-1">الرصيد القابل للسحب</p>
            <p class="text-3xl font-bold text-indigo-600"><?= number_format($available_bonus, 2) ?> دل</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Breakdown -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">تفاصيل احتساب البونص</h3>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center bg-gray-50 p-3 rounded">
                    <div>
                        <p class="font-bold text-gray-700">طلبات Single المسلمة</p>
                        <p class="text-xs text-gray-500"><?= $single_delivered ?> طلب × 10 دل</p>
                    </div>
                    <p class="font-bold text-blue-600"><?= number_format($single_bonus, 2) ?> دل</p>
                </div>
                
                <div class="flex justify-between items-center bg-gray-50 p-3 rounded">
                    <div>
                        <p class="font-bold text-gray-700">طلبات Bundle المسلمة</p>
                        <p class="text-xs text-gray-500"><?= $bundle_delivered ?> طلب × 15 دل</p>
                    </div>
                    <p class="font-bold text-blue-600"><?= number_format($bundle_bonus, 2) ?> دل</p>
                </div>

                <div class="flex justify-between items-center bg-gray-50 p-3 rounded">
                    <div>
                        <p class="font-bold text-gray-700">بونص 1% (طلبات Bundle)</p>
                        <p class="text-xs text-gray-500">
                            نسبة التسليم العامة: <?= round($overall_delivery_rate * 100, 1) ?>%
                            (مطلوب 50%)
                        </p>
                    </div>
                    <p class="font-bold <?= $extra_bonus > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                        <?= number_format($extra_bonus, 2) ?> دل
                    </p>
                </div>

                <div class="mt-4 border-t pt-4">
                    <p class="font-bold text-gray-700 mb-2">قاعدة خصم الأداء (نسبة تسليم المؤكد)</p>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">طلبات مؤكدة: <?= $confirmed_total ?></span>
                        <span class="text-gray-600">مسلّمة: <?= $delivered_total ?></span>
                        <span class="font-bold">النسبة: <?= round($overall_delivery_rate * 100, 1) ?>%</span>
                    </div>
                    <p class="text-sm font-bold <?= $discount_color ?>"><?= $discount_msg ?></p>
                </div>
            </div>
        </div>

        <!-- Withdrawal Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-xl font-bold text-gray-700 mb-4 border-b pb-2">طلب سحب رصيد البونص</h3>
            
            <form action="" method="POST" class="space-y-4" onsubmit="uiConfirmSubmit(event, this.querySelector('button[type=submit]'), 'تأكيد السحب', 'هل أنت متأكد من تقديم طلب سحب بونص؟', 'نعم، اطلب')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="max_amount" value="<?= $available_bonus ?>">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">المبلغ المطلوب (دل)</label>
                    <input type="number" name="amount" step="0.01" min="1" max="<?= $available_bonus ?>" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-xl font-bold" 
                           placeholder="أدخل المبلغ...">
                    <p class="text-xs text-gray-500 mt-2">الحد الأقصى المسموح: <?= number_format($available_bonus, 2) ?> دل</p>
                </div>
                
                <button type="submit" name="request_withdrawal" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition" <?= $available_bonus <= 0 ? 'disabled style="opacity: 0.5;"' : '' ?>>
                    <i class='bx bx-paper-plane mr-2'></i> إرسال طلب السحب للمدير
                </button>
            </form>
        </div>
    </div>

    <!-- History Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
            <h3 class="font-bold text-gray-700">سجل طلبات السحب الأخيرة</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-100 text-gray-600">
                    <tr>
                        <th class="p-3">رقم الطلب</th>
                        <th class="p-3">المبلغ</th>
                        <th class="p-3">التاريخ</th>
                        <th class="p-3">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($withdrawals && $withdrawals->num_rows > 0): ?>
                        <?php while ($w = $withdrawals->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-mono text-gray-500">#<?= $w['id'] ?></td>
                                <td class="p-3 font-bold text-indigo-600"><?= number_format($w['amount'], 2) ?> دل</td>
                                <td class="p-3 text-gray-500" dir="ltr"><?= date('Y-m-d H:i', strtotime($w['created_at'])) ?></td>
                                <td class="p-3">
                                    <?php if ($w['status'] == 'pending'): ?>
                                        <span class="bg-orange-100 text-orange-700 px-2 py-1 rounded text-xs font-bold">قيد الانتظار</span>
                                    <?php elseif ($w['status'] == 'approved'): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">تمت الموافقة</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">مرفوض</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-500">لا يوجد سجل طلبات سحب.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
