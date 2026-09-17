<?php
// ملف: admin_withdrawals.php
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include(__DIR__ . '/../core/config.php");
/** @var mysqli $conn */
include(__DIR__ . '/../core/helpers.php");

// معالجة تحديث حالة السحب
if (isset($_POST['update_withdrawal_status'])) {
    $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
    $new_status = clean_input($_POST['status'] ?? '');
    $transfer_method = clean_input($_POST['transfer_method'] ?? '');
    
    if ($withdrawal_id == 0 || empty($new_status)) {
        $error = "بيانات غير مكتملة: withdrawal_id=$withdrawal_id, status=$new_status";
    } else {
        if ($transfer_method) {
            if ($new_status !== 'قيد المراجعة') {
                $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, processed_at = NOW(), transfer_method = ? WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, processed_at = NULL, transfer_method = ? WHERE id = ?");
            }
            $stmt->bind_param("ssi", $new_status, $transfer_method, $withdrawal_id);
        } else {
            if ($new_status !== 'قيد المراجعة') {
                $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, processed_at = NOW() WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, processed_at = NULL WHERE id = ?");
            }
            $stmt->bind_param("si", $new_status, $withdrawal_id);
        }
        
        if ($stmt->execute()) {
            $success = "تم تحديث حالة طلب السحب بنجاح!";
            if ($transfer_method) {
                $success .= " طريقة التحويل: " . $transfer_method;
            }
            
            // إشعار للمستخدم بتغيير حالة السحب
            if (function_exists('send_notification')) {
                $u_res = $conn->query("SELECT user_id, amount FROM withdrawals WHERE id = $withdrawal_id");
                if ($u_res && $u_row = $u_res->fetch_assoc()) {
                    $uid = (int)$u_row['user_id'];
                    $amount = $u_row['amount'];
                    if ($uid > 0) {
                        $title = "تحديث طلب السحب";
                        $msg = "تم تحديث حالة طلب السحب الخاص بك بمبلغ $amount إلى: $new_status";
                        $link = "withdrawals.php";
                        send_notification($conn, 'user', $uid, $title, $msg, $link);
                    }
                }
            }
        } else {
            $error = "فشل في تحديث حالة طلب السحب: " . $conn->error;
        }
    }
}

// جلب طلبات السحب مع معلومات المستخدمين ونوع الحساب
$withdrawals_query = $conn->query("
    SELECT w.*, u.fullname, u.email, u.user_type
    FROM withdrawals w 
    LEFT JOIN users u ON w.user_id = u.id 
    ORDER BY w.created_at DESC
");

?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">طلبات السحب</h1>
</div>

<!-- رسائل النجاح/الخطأ -->
<?php if(isset($success)): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-check-circle mr-2'></i>
    <?= $success ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-error-alt mr-2'></i>
    <?= $error ?>
</div>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 border-l-4 border-l-yellow-500">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                <i class='bx bx-time text-yellow-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">قيد المراجعة</p>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?= $conn->query("SELECT COUNT(*) as total FROM withdrawals WHERE status = 'قيد المراجعة'")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-green-500">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg mr-4">
                <i class='bx bx-check-circle text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">مكتملة</p>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?= $conn->query("SELECT COUNT(*) as total FROM withdrawals WHERE status = 'مكتمل'")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-red-500">
        <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg mr-4">
                <i class='bx bx-x-circle text-red-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">مرفوضة</p>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?= $conn->query("SELECT COUNT(*) as total FROM withdrawals WHERE status = 'مرفوض'")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                <i class='bx bx-money text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي المسحوب</p>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?php 
                    $total_withdrawn = $conn->query("SELECT SUM(amount) as total FROM withdrawals WHERE status = 'مكتمل'")->fetch_assoc()['total'];
                    echo number_format($total_withdrawn ?: 0, 2) . ' د.ل';
                    ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<!-- جدول طلبات السحب -->
<div class="stat-card p-6">
    <h3 class="text-xl font-bold mb-4 text-gray-800">قائمة طلبات السحب</h3>
    <div class="overflow-x-auto">
        <table class="w-full" style="table-layout: auto !important; max-width: none !important;">
            <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">#</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">المستخدم</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">نوع الحساب</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">البريد الإلكتروني</th>
                    <th class="p-8 text-right text-gray-700 font-bold whitespace-nowrap">المبلغ</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">رقم الهاتف</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">الحالة</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">تاريخ الطلب</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">تاريخ المعالجة</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">الإجراءات</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">طريقة التحويل</th>
                </tr>
            </thead>
            <tbody>
                <?php if($withdrawals_query && $withdrawals_query->num_rows > 0): ?>
                    <?php while($withdrawal = $withdrawals_query->fetch_assoc()): ?>
                        <?php
                        $status_color = [
                            'قيد المراجعة' => 'bg-yellow-500',
                            'مكتمل' => 'bg-green-500',
                            'مرفوض' => 'bg-red-500'
                        ][$withdrawal['status']] ?? 'bg-gray-500';
                        ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="p-4 text-gray-600 whitespace-nowrap">#<?= $withdrawal['id'] ?></td>
                            <td class="p-4 whitespace-nowrap">
                                <div class="text-gray-800 font-medium"><?= $withdrawal['fullname'] ?></div>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <?php
                                $user_type = $withdrawal['user_type'] ?? 'مستخدم';
                                $type_colors = [
                                    'admin' => 'bg-red-500',
                                    'مدير' => 'bg-red-500',
                                    'merchant' => 'bg-blue-500',
                                    'تاجر' => 'bg-blue-500',
                                    'marketer' => 'bg-green-500',
                                    'مسوق' => 'bg-green-500',
                                    'user' => 'bg-gray-500',
                                    'مستخدم' => 'bg-gray-500'
                                ];
                                $color_class = $type_colors[$user_type] ?? 'bg-gray-500';
                                ?>
                                <span class="px-3 py-1 rounded-full text-white <?= $color_class ?> text-sm font-medium">
                                    <?= $user_type ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-600 whitespace-nowrap"><?= $withdrawal['email'] ?></td>
                            <td class="p-8 font-bold text-green-600 whitespace-nowrap"><?= number_format($withdrawal['amount'], 2) ?> د.ل</td>
                            <td class="p-4 text-gray-600 whitespace-nowrap"><?= $withdrawal['phone'] ?></td>
                            <td class="p-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-white <?= $status_color ?> text-sm font-medium">
                                    <?= $withdrawal['status'] ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-500 text-sm whitespace-nowrap"><?= date('Y-m-d H:i', strtotime($withdrawal['created_at'])) ?></td>
                            <td class="p-4 text-gray-500 text-sm whitespace-nowrap">
                                <?= $withdrawal['processed_at'] ? date('Y-m-d H:i', strtotime($withdrawal['processed_at'])) : '---' ?>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <form method="POST" class="flex flex-col space-y-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>">
                                    <input type="hidden" name="update_withdrawal_status" value="1">
                                    <div class="flex items-center space-x-2 space-x-reverse">
                                        <select name="status" onchange="handleStatusChange(this)" 
                                                class="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-[#4b6b2f]">
                                            <option value="قيد المراجعة" <?= $withdrawal['status'] == 'قيد المراجعة' ? 'selected' : '' ?>>قيد المراجعة</option>
                                            <option value="مكتمل" <?= $withdrawal['status'] == 'مكتمل' ? 'selected' : '' ?>>مكتمل</option>
                                            <option value="مرفوض" <?= $withdrawal['status'] == 'مرفوض' ? 'selected' : '' ?>>مرفوض</option>
                                        </select>
                                    </div>
                                    <div id="transfer-method-<?= $withdrawal['id'] ?>" class="transfer-method-container" style="display: <?= $withdrawal['status'] == 'مكتمل' ? 'block' : 'none' ?>;">
                                        <select name="transfer_method" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-[#4b6b2f] w-full">
                                            <option value="">اختر طريقة التحويل</option>
                                            <option value="تحويل بنكي" <?= ($withdrawal['transfer_method'] ?? '') == 'تحويل بنكي' ? 'selected' : '' ?>>تحويل بنكي</option>
                                            <option value="محفظة إلكترونية" <?= ($withdrawal['transfer_method'] ?? '') == 'محفظة إلكترونية' ? 'selected' : '' ?>>محفظة إلكترونية</option>
                                            <option value="شيك بنكي" <?= ($withdrawal['transfer_method'] ?? '') == 'شيك بنكي' ? 'selected' : '' ?>>شيك بنكي</option>
                                            <option value="تحويل نقدي" <?= ($withdrawal['transfer_method'] ?? '') == 'تحويل نقدي' ? 'selected' : '' ?>>تحويل نقدي</option>
                                            <option value="أخرى" <?= ($withdrawal['transfer_method'] ?? '') == 'أخرى' ? 'selected' : '' ?>>أخرى</option>
                                        </select>
                                    </div>
                                </form>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <?php if ($withdrawal['transfer_method']): ?>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm font-medium">
                                        <?= $withdrawal['transfer_method'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">---</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="p-4 text-center text-gray-500">
                            <i class='bx bx-wallet text-4xl mb-2 block'></i>
                            لا توجد طلبات سحب 
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function handleStatusChange(select) {
    const form = select.closest('form');
    const withdrawalId = form.querySelector('input[name="withdrawal_id"]').value;
    const transferMethodContainer = document.getElementById('transfer-method-' + withdrawalId);
    
    if (select.value === 'مكتمل') {
        transferMethodContainer.style.display = 'block';
    } else {
        transferMethodContainer.style.display = 'none';
        form.querySelector('select[name="transfer_method"]').value = '';
        form.submit();
    }
}
</script>