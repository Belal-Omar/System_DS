<?php
// admin_currency_exchange.php
// يتم تضمينها من admin_panel.php

// التحقق من الصلاحيات
if (!in_array($_SESSION['admin_role'], ['super_admin', 'admin', 'manager', 'accountant'])) {
    echo "<div class='text-center p-10'><h2 class='text-2xl text-red-600 font-bold'>غير مصرح لك بالدخول</h2></div>";
    return;
}

$admin_id = $_SESSION['admin_id'];

// 1. إنشاء الجداول إن لم تكن موجودة
$conn->query("CREATE TABLE IF NOT EXISTS currency_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate DECIMAL(10,4) NOT NULL,
    admin_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS currency_exchange_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount_lyd DECIMAL(12,2) NOT NULL,
    amount_egp DECIMAL(12,2) NOT NULL,
    rate_used DECIMAL(10,4) NOT NULL,
    note VARCHAR(255) NULL,
    admin_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. معالجة الطلبات (تحديث السعر أو حفظ التحويل)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_rate') {
            $new_rate = floatval($_POST['new_rate']);
            if ($new_rate > 0) {
                $stmt = $conn->prepare("INSERT INTO currency_rates (rate, admin_id) VALUES (?, ?)");
                $stmt->bind_param("di", $new_rate, $admin_id);
                if ($stmt->execute()) {
                    $_SESSION['currency_exchange_msg'] = "تم تحديث سعر الصرف بنجاح.";
                    
                    // Insert into logs as well
                    $note = "تحديث السعر اليومي";
                    $zero = 0;
                    $stmt_log = $conn->prepare("INSERT INTO currency_exchange_logs (amount_lyd, amount_egp, rate_used, note, admin_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt_log->bind_param("dddsi", $zero, $zero, $new_rate, $note, $admin_id);
                    $stmt_log->execute();
                    $stmt_log->close();

                } else {
                    $_SESSION['currency_exchange_err'] = "حدث خطأ أثناء تحديث السعر.";
                }
                $stmt->close();
            } else {
                $_SESSION['currency_exchange_err'] = "السعر غير صالح.";
            }
        } elseif ($_POST['action'] === 'save_log') {
            $amount_lyd = floatval($_POST['amount_lyd']);
            $amount_egp = floatval($_POST['amount_egp']);
            $rate_used = floatval($_POST['rate_used']);
            $note = isset($_POST['note']) ? trim($_POST['note']) : '';

            if ($amount_lyd > 0 && $amount_egp > 0 && $rate_used > 0) {
                $stmt = $conn->prepare("INSERT INTO currency_exchange_logs (amount_lyd, amount_egp, rate_used, note, admin_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("dddsi", $amount_lyd, $amount_egp, $rate_used, $note, $admin_id);
                if ($stmt->execute()) {
                    $_SESSION['currency_exchange_msg'] = "تم حفظ تقرير التحويل بنجاح.";
                } else {
                    $_SESSION['currency_exchange_err'] = "حدث خطأ أثناء حفظ التقرير.";
                }
                $stmt->close();
            } else {
                $_SESSION['currency_exchange_err'] = "يرجى إدخال مبالغ صالحة قبل الحفظ.";
            }
        }
        
        // منع إعادة إرسال النموذج عند تحديث الصفحة بواسطة جافا سكريبت بدلاً من الهيدر
        echo "<script>window.location.href='admin_panel.php?page=currency_exchange';</script>";
        exit;
    }
}

// جلب رسائل النجاح أو الخطأ
$message = $_SESSION['currency_exchange_msg'] ?? '';
$error = $_SESSION['currency_exchange_err'] ?? '';
unset($_SESSION['currency_exchange_msg'], $_SESSION['currency_exchange_err']);

// 3. جلب السعر الحالي (أحدث سعر)
$current_rate = 10; // افتراضي
$res_rate = $conn->query("SELECT rate FROM currency_rates ORDER BY id DESC LIMIT 1");
if ($res_rate && $res_rate->num_rows > 0) {
    $row = $res_rate->fetch_assoc();
    $current_rate = floatval($row['rate']);
}

// 4. جلب التقارير السابقة
$logs = [];
$res_logs = $conn->query("
    SELECT l.*, a.username as admin_name 
    FROM currency_exchange_logs l 
    LEFT JOIN admins a ON l.admin_id = a.id 
    ORDER BY l.id DESC 
    LIMIT 100
");
if ($res_logs && $res_logs->num_rows > 0) {
    while ($row = $res_logs->fetch_assoc()) {
        $logs[] = $row;
    }
}
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">
        <i class='bx bx-transfer-alt mr-2 text-indigo-500'></i>
        أداة تحويل العملات والتقارير
    </h1>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 flex items-center">
    <i class='bx bx-check-circle text-xl mr-2'></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-center">
    <i class='bx bx-error-circle text-xl mr-2'></i>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- القسم الأول: إعداد سعر الصرف -->
    <div class="lg:col-span-1 bg-white rounded-3xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
                <i class='bx bx-line-chart mr-2 text-indigo-500'></i>
                السعر اليومي للعملة
            </h2>
            <p class="text-sm text-slate-500 mb-6">أدخل سعر الـ "دل" مقابل الجنيه المصري لليوم. هذا السعر سيتم حفظه واستخدامه في الحاسبة كافتراضي.</p>
            
            <form method="POST" action="?page=currency_exchange" class="space-y-4">
                <input type="hidden" name="action" value="update_rate">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensure_csrf_token()) ?>">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-1">1 دينار ليبي (دل) يساوي =</label>
                    <div class="relative">
                        <input type="number" name="new_rate" id="currentDbRate" value="<?= htmlspecialchars($current_rate) ?>" step="0.01" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 font-bold text-slate-800 text-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                        <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400 font-bold">ج.م</span>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all flex items-center justify-center">
                    <i class='bx bx-save mr-2'></i> حفظ السعر اليومي
                </button>
            </form>
        </div>
    </div>

    <!-- القسم الثاني: الحاسبة وحفظ التقرير -->
    <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
        <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
            <i class='bx bx-calculator mr-2 text-indigo-500'></i>
            حاسبة التحويل السريع
        </h2>
        
        <div class="flex flex-col md:flex-row items-center gap-4 relative mb-6">
            <!-- إدخال بالدينار الليبي -->
            <div class="w-full">
                <label class="block text-sm font-bold text-slate-600 mb-2">المبلغ (دل)</label>
                <div class="relative">
                    <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400">
                        <i class='bx bx-money text-xl'></i>
                    </span>
                    <input type="number" id="calc_amountLYD" placeholder="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl pr-12 pl-12 py-4 font-black text-slate-800 text-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-center" oninput="calculateFromLYD()">
                    <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500 font-bold">دل</span>
                </div>
            </div>

            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 text-slate-400 my-2 md:my-0 mt-6 z-10 shrink-0">
                <i class='bx bx-transfer md:bx-rotate-0 bx-rotate-90 text-2xl'></i>
            </div>

            <!-- إدخال بالجنيه المصري -->
            <div class="w-full">
                <label class="block text-sm font-bold text-slate-600 mb-2">المبلغ (ج.م)</label>
                <div class="relative">
                    <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400">
                        <i class='bx bx-wallet text-xl'></i>
                    </span>
                    <input type="number" id="calc_amountEGP" placeholder="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl pr-12 pl-12 py-4 font-black text-slate-800 text-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-center" oninput="calculateFromEGP()">
                    <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500 font-bold">ج.م</span>
                </div>
            </div>
        </div>

        <hr class="border-slate-100 mb-6">
        
        <form method="POST" action="?page=currency_exchange" id="saveLogForm" class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
            <h3 class="text-sm font-bold text-slate-700 mb-3"><i class='bx bx-book-content mr-1'></i> حفظ هذا التحويل في التقارير</h3>
            <input type="hidden" name="action" value="save_log">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ensure_csrf_token()) ?>">
            
            <!-- Hidden fields populated by JS -->
            <input type="hidden" name="amount_lyd" id="form_lyd" value="0">
            <input type="hidden" name="amount_egp" id="form_egp" value="0">
            <input type="hidden" name="rate_used" id="form_rate" value="<?= htmlspecialchars($current_rate) ?>">

            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="note" placeholder="ملاحظات (مثال: تحويل لحساب المسوق فلان...)" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="button" onclick="submitLogForm()" class="md:w-auto w-full py-3 px-6 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-all flex items-center justify-center whitespace-nowrap">
                    <i class='bx bx-plus-circle mr-2'></i> إضافة للتقرير
                </button>
            </div>
        </form>
    </div>
</div>

<!-- القسم الثالث: تقارير التحويلات -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-lg font-bold text-slate-800 flex items-center">
            <i class='bx bx-history mr-2 text-indigo-500'></i>
            سجل تقارير التحويلات
        </h2>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-y border-slate-100">
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">#</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">التاريخ والوقت</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">المبلغ (دل)</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">المبلغ (ج.م)</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">سعر الصرف</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">ملاحظات</th>
                    <th class="py-3 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">المسؤول</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="7" class="py-8 text-center text-slate-400 font-bold bg-slate-50/50">لا توجد تقارير تحويل مسجلة بعد.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3 px-4 text-sm text-slate-500 text-right">#<?= $log['id'] ?></td>
                        <td class="py-3 px-4 text-sm font-bold text-slate-700 text-right" dir="ltr">
                            <?= date('Y-m-d h:i A', strtotime($log['created_at'])) ?>
                        </td>
                        <?php if ($log['amount_lyd'] == 0 && $log['amount_egp'] == 0): ?>
                        <td colspan="2" class="py-3 px-4 text-sm font-black text-slate-500 text-center bg-slate-50">
                            <i class='bx bx-line-chart text-indigo-400 mr-1'></i> تم تحديث السعر فقط
                        </td>
                        <?php else: ?>
                        <td class="py-3 px-4 text-sm font-black text-indigo-600 text-right">
                            <?= number_format($log['amount_lyd'], 2) ?> <span class="text-xs font-normal">دل</span>
                        </td>
                        <td class="py-3 px-4 text-sm font-black text-emerald-600 text-right">
                            <?= number_format($log['amount_egp'], 2) ?> <span class="text-xs font-normal">ج.م</span>
                        </td>
                        <?php endif; ?>
                        <td class="py-3 px-4 text-sm text-slate-600 font-bold text-right">
                            <?= number_format($log['rate_used'], 2) ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-slate-600 text-right max-w-xs truncate" title="<?= htmlspecialchars($log['note']) ?>">
                            <?= $log['note'] ? htmlspecialchars($log['note']) : '<span class="text-slate-300">-</span>' ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-slate-500 text-right">
                            <?= htmlspecialchars($log['admin_name']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function getRate() {
        let rate = parseFloat(document.getElementById('currentDbRate').value);
        return isNaN(rate) || rate <= 0 ? 1 : rate;
    }

    function calculateFromLYD() {
        let lydInput = document.getElementById('calc_amountLYD');
        let egpInput = document.getElementById('calc_amountEGP');
        
        let lydValue = parseFloat(lydInput.value);
        if (isNaN(lydValue)) {
            egpInput.value = '';
            return;
        }

        let rate = getRate();
        let egpValue = lydValue * rate;
        
        egpInput.value = parseFloat(egpValue.toFixed(2));
    }

    function calculateFromEGP() {
        let lydInput = document.getElementById('calc_amountLYD');
        let egpInput = document.getElementById('calc_amountEGP');
        
        let egpValue = parseFloat(egpInput.value);
        if (isNaN(egpValue)) {
            lydInput.value = '';
            return;
        }

        let rate = getRate();
        let lydValue = egpValue / rate;
        
        lydInput.value = parseFloat(lydValue.toFixed(2));
    }

    function submitLogForm() {
        let lyd = parseFloat(document.getElementById('calc_amountLYD').value);
        let egp = parseFloat(document.getElementById('calc_amountEGP').value);
        
        if (isNaN(lyd) || lyd <= 0 || isNaN(egp) || egp <= 0) {
            alert('يرجى إدخال مبلغ صحيح في الحاسبة أولاً.');
            return;
        }
        
        document.getElementById('form_lyd').value = lyd;
        document.getElementById('form_egp').value = egp;
        document.getElementById('form_rate').value = getRate();
        
        document.getElementById('saveLogForm').submit();
    }
</script>
