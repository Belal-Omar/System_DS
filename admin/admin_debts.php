<?php
// admin_debts.php — كشف حساب شركات الشحن (مربوط بحسابات الأرباح)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($conn) || !is_object($conn)) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'>تعذر الاتصال بقاعدة البيانات. تحقق من الإعدادات.</div>";
    return;
}

// تعريف احتياطي لو helpers.php قديم أو الـ opcache ما حدّثش
if (!function_exists('ensure_debts_schema')) {
    function ensure_debts_schema($conn) {
        if (!is_object($conn)) {
            return false;
        }
        static $done = false;
        static $has_period = null;
        if ($done) {
            return $has_period === true;
        }
        $done = true;
        @$conn->query("CREATE TABLE IF NOT EXISTS support_financial_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            type ENUM('collected', 'creditor', 'debtor') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            note TEXT,
            period_month VARCHAR(7) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $col = @$conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'period_month'");
        $has_period = ($col && $col->num_rows > 0);
        if (!$has_period) {
            @$conn->query("ALTER TABLE support_financial_records ADD COLUMN period_month VARCHAR(7) NULL AFTER note");
            $col2 = @$conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'period_month'");
            $has_period = ($col2 && $col2->num_rows > 0);
        }
        if ($has_period) {
            @$conn->query("UPDATE support_financial_records
                SET period_month = DATE_FORMAT(entry_date, '%Y-%m')
                WHERE period_month IS NULL OR period_month = ''");
        }
        return $has_period === true;
    }
}

if (!function_exists('debts_period_month_sql')) {
    function debts_period_month_sql($alias = '', $has_period_month = true) {
        $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        if (!$has_period_month) {
            return "DATE_FORMAT({$p}entry_date, '%Y-%m')";
        }
        return "COALESCE(NULLIF({$p}period_month, ''), DATE_FORMAT({$p}entry_date, '%Y-%m'))";
    }
}

try {
migrate_legacy_product_monthly($conn);
$has_period_month = ensure_debts_schema($conn);

// POST يُعالَج في admin_debts_post_early.php قبل أي HTML

$scope = ($_GET['scope'] ?? 'period') === 'lifetime' ? 'lifetime' : 'period';
$range = ($_GET['range'] ?? 'month') === 'year' ? 'year' : 'month';
$selected_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', (string) $selected_month)) {
    $selected_month = date('Y-m');
}
$selected_year = $_GET['year'] ?? date('Y');
$selected_product = $_GET['product'] ?? 'all';
$product_codes = get_support_product_codes_list($conn);
$product_filter = ($selected_product !== 'all') ? $selected_product : null;

[$start_date, $end_date, $period_label] = get_accounts_period_dates($range, $selected_month, $selected_year);
$period_title = $range === 'year' ? ('سنة ' . $selected_year) : ('شهر ' . $selected_month);

$shipping_companies_list = [];
if ($_SESSION['admin_role'] !== 'shipping_company') {
    $sc_res = $conn->query("SELECT id, name FROM shipping_accounts ORDER BY name ASC");
    if ($sc_res) {
        while($r = $sc_res->fetch_assoc()){
            $shipping_companies_list[] = $r;
        }
    }
}

if ($_SESSION['admin_role'] === 'shipping_company') {
    $sc_id_filter = (int)($_SESSION['shipping_company_id'] ?? 0);
} else {
    $sc_id_filter = isset($_GET['sc_id']) && $_GET['sc_id'] !== 'all' ? (int)$_GET['sc_id'] : null;
}
if ($scope === 'lifetime') {
    $ship = calculate_shipping_collection_balance($conn, null, null, $product_filter, $sc_id_filter);
    $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_filter);
    $scope_label = 'تراكمي — كل الفترات';
} else {
    $ship = calculate_shipping_collection_balance($conn, $start_date, $end_date, $product_filter, $sc_id_filter);
    $fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_filter);
    $scope_label = $period_title;
}

$creditor_total = (float) ($ship['creditor_due'] ?? 0);
$debtor_total = (float) ($ship['debtor_received'] ?? 0);
$remaining_total = (float) ($ship['remaining'] ?? 0);
$other_costs = (float) ($fin['total_costs'] ?? 0) - (float) ($fin['sum_dom_shipping'] ?? 0);

$payable_remaining = $remaining_total;
$can_collect = $payable_remaining > 0;
$default_entry_date = date('Y-m-d');

$flash_success = $_SESSION['debts_flash_success'] ?? null;
$flash_error = $_SESSION['debts_flash_error'] ?? null;
unset($_SESSION['debts_flash_success'], $_SESSION['debts_flash_error']);

$pm_expr = debts_period_month_sql('r', $has_period_month);
$records_sql = "SELECT r.*, $pm_expr AS apply_month, s.name as company_name 
                FROM support_financial_records r 
                LEFT JOIN shipping_accounts s ON r.shipping_company_id = s.id 
                WHERE r.type = 'debtor'";
if ($sc_id_filter !== null) {
    $records_sql .= " AND r.shipping_company_id = " . (int)$sc_id_filter;
}
if ($scope === 'period') {
    $start_m = $conn->real_escape_string(substr($start_date, 0, 7));
    $end_m = $conn->real_escape_string(substr($end_date, 0, 7));
    $records_sql .= " AND $pm_expr BETWEEN '$start_m' AND '$end_m'";
}
$records_sql .= ' ORDER BY r.entry_date DESC, r.id DESC';
$records_query = @$conn->query($records_sql);

$debts_url = function (array $extra = []) use ($scope, $range, $selected_month, $selected_year, $selected_product) {
    return 'admin_panel.php?' . http_build_query(array_merge([
        'page' => 'debts',
        'scope' => $scope,
        'range' => $range,
        'month' => $selected_month,
        'year' => $selected_year,
        'product' => $selected_product,
    ], $extra));
};
} catch (Throwable $e) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>";
    echo "<strong>خطأ في صفحة المديونيات:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    return;
}
?>

<div class="max-w-7xl mx-auto px-4 py-8 space-y-8 font-sans" id="debtsPage">
    <?php
    $isSC = ($_SESSION['admin_role'] === 'shipping_company');
    $text_creditor_label = $isSC ? 'المـديـن (المطلوب منك)' : 'الـدائـن (المستحق لك)';
    $text_debtor_label = $isSC ? 'الـدائـن (ما قمت بتسديده)' : 'المـديـن (ما استلمته)';
    $text_total_label = $isSC ? 'المطلوب منك (الكلي)' : 'المجموع الكلي (المستحق)';
    $text_total_collections = $isSC ? 'مجموع ما تم سداده' : 'مجموع التحصيلات';
    $text_remaining = $isSC ? 'المتبقي بعد السداد' : 'المتبقي بعد التحصيلات';
    $text_after_collection = $isSC ? 'بعد السداد' : 'بعد التحصيل';
    $text_total_due = $isSC ? 'المطلوب الكلي' : 'المستحق الكلي';
    $text_minus_collections = $isSC ? '− مجموع ما تم سداده' : '− مجموع التحصيلات';
    $text_actual_collection = $isSC ? 'السداد الفعلي' : 'التحصيل الفعلي';
    $text_receive_date = $isSC ? 'تاريخ السداد' : 'تاريخ الاستلام';
    $text_amount_received = $isSC ? 'المبلغ المسدّد' : 'المبلغ المستلم';
    $text_btn_add = $isSC ? '+ تسجيل سداد' : '+ تسجيل تحصيل';
    $text_history_title = $isSC ? 'سجل عمليات السداد' : 'سجل التحصيلات';
    $text_no_records = $isSC ? 'لا توجد مبالغ مسدّدة مسجلة' : 'لا توجد تحصيلات مسجلة';
    ?>

    <?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl font-medium">
        <i class='bx bx-check-circle mr-1'></i><?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl font-medium">
        <i class='bx bx-error-circle mr-1'></i><?= htmlspecialchars($flash_error) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl mr-4 shadow-lg shadow-indigo-100"><i class='bx bx-wallet-alt'></i></span>
                كشف حساب شركات الشحن
            </h1>
            <p class="text-slate-500 mt-2 font-medium"><?= htmlspecialchars($scope_label) ?>
                <?php if ($selected_product !== 'all'): ?> — منتج <?= htmlspecialchars($selected_product) ?><?php endif; ?>
            </p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2 bg-slate-100 p-2 rounded-2xl">
            <input type="hidden" name="page" value="debts">
            <div class="flex bg-white rounded-xl p-1 gap-1">
                <a href="<?= $debts_url(['scope' => 'period']) ?>" class="px-3 py-1 rounded-lg text-sm font-bold <?= $scope === 'period' ? 'bg-indigo-600 text-white' : 'text-slate-600' ?>">الفترة</a>
                <a href="<?= $debts_url(['scope' => 'lifetime']) ?>" class="px-3 py-1 rounded-lg text-sm font-bold <?= $scope === 'lifetime' ? 'bg-indigo-600 text-white' : 'text-slate-600' ?>">تراكمي</a>
            </div>
            <?php if ($scope === 'period'): ?>
            <input type="hidden" name="scope" value="period">
            <select name="range" onchange="this.form.submit()" class="bg-transparent font-bold text-slate-700 px-2 py-1 rounded-lg">
                <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>شهر</option>
                <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>سنة</option>
            </select>
            <?php if ($range === 'month'): ?>
                <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()" class="bg-white rounded-xl px-2 py-1 font-bold text-indigo-600 border-0">
            <?php else: ?>
                <input type="number" name="year" value="<?= htmlspecialchars($selected_year) ?>" min="2020" max="2099" onchange="this.form.submit()" class="bg-white rounded-xl px-3 py-1 font-bold text-indigo-600 w-24 border-0">
            <?php endif; ?>
            <?php else: ?>
            <input type="hidden" name="scope" value="lifetime">
            <?php endif; ?>
            <select name="product" onchange="this.form.submit()" class="bg-white rounded-xl px-2 py-1 font-bold text-slate-700 border-0">
                <option value="all" <?= $selected_product === 'all' ? 'selected' : '' ?>>كل المنتجات</option>
                <?php foreach ($product_codes as $code): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $selected_product === $code ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($_SESSION['admin_role'] !== 'shipping_company'): ?>
            <select name="sc_id" onchange="this.form.submit()" class="bg-white rounded-xl px-2 py-1 font-bold text-slate-700 border-0">
                <option value="all" <?= $sc_id_filter === null ? 'selected' : '' ?>>كل شركات الشحن</option>
                <?php foreach ($shipping_companies_list as $sc): ?>
                    <option value="<?= $sc['id'] ?>" <?= $sc_id_filter === (int)$sc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- ملخص: الكلي / المحصّل / المتبقي -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase mb-1"><?= $text_total_label ?></p>
            <h2 class="text-2xl font-black text-slate-800"><?= number_format($creditor_total, 2) ?> <span class="text-sm font-normal text-slate-400">دل</span></h2>
            <p class="text-xs text-slate-500 mt-1">بيع مسلّم − شحن داخلي</p>
        </div>
        <div class="bg-white rounded-2xl border border-emerald-100 p-5 shadow-sm">
            <p class="text-xs font-bold text-emerald-600 uppercase mb-1"><?= $text_total_collections ?></p>
            <h2 class="text-2xl font-black text-emerald-700"><?= number_format($debtor_total, 2) ?> <span class="text-sm font-normal text-emerald-400">دل</span></h2>
            <p class="text-xs text-slate-500 mt-1"><?= $isSC ? 'كل المبالغ التي سددتها لهذه الفترة' : 'كل المبالغ المستلمة لهذه الفترة' ?></p>
        </div>
        <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-5 shadow-sm">
            <p class="text-xs font-bold text-indigo-500 uppercase mb-1"><?= $text_remaining ?></p>
            <h2 class="text-2xl font-black text-indigo-700"><?= number_format($remaining_total, 2) ?> <span class="text-sm font-normal text-indigo-400">دل</span></h2>
            <p class="text-xs text-slate-500 mt-1"><?= number_format($creditor_total, 2) ?> − <?= number_format($debtor_total, 2) ?></p>
        </div>
    </div>

    <?php if ($scope === 'period' && !$isSC): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-950">
        <p class="font-bold mb-2 flex items-center gap-2"><i class='bx bx-info-circle'></i> لماذا الرقم هنا يختلف عن «ربح صافي» في حسابات الأرباح؟</p>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white/70 rounded-xl p-3">
                <p class="text-xs text-slate-500 font-bold mb-1">مستحق من الشحن (هذه الصفحة)</p>
                <p class="text-lg font-black text-indigo-700"><?= number_format($creditor_total, 2) ?> دل</p>
                <p class="text-xs mt-1">بيع مسلّم (<?= number_format($ship['total_sale'], 2) ?>) − شحن داخلي مسلّم (<?= number_format($ship['total_dom_shipping'], 2) ?>)</p>
                <p class="text-xs text-slate-500 mt-1">مسلّم: <?= $ship['delivered_count'] ?> طلب</p>
            </div>
            <div class="bg-white/70 rounded-xl p-3">
                <p class="text-xs text-slate-500 font-bold mb-1">ربح صافي (حسابات الأرباح — نفس الفترة)</p>
                <p class="text-lg font-black <?= $fin['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= number_format($fin['net_profit'], 2) ?> دل</p>
                <p class="text-xs mt-1">بعد خصم: ليدات + منتج + شحن دولي + شحن داخلي + تشغيل</p>
                <p class="text-xs text-slate-500 mt-1">تكاليف أخرى (بدون شحن داخلي): <?= number_format($other_costs, 2) ?> دل</p>
            </div>
        </div>
        <p class="text-xs mt-3 text-slate-600">
            الفرق الطبيعي = <?= number_format($creditor_total - $fin['net_profit'], 2) ?> دل
            (تكاليف التشغيل والتسويق والمنتج التي لا تخص شركة الشحن مباشرة).
            <a href="admin_panel.php?page=accounts&section=accounts&view=month&range=<?= urlencode($range) ?>&month=<?= urlencode($selected_month) ?>&year=<?= urlencode($selected_year) ?>&product=<?= urlencode($selected_product) ?>" class="text-indigo-600 font-bold hover:underline">افتح حسابات الأرباح ←</a>
        </p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-slate-900 p-8 rounded-3xl text-white shadow-xl relative overflow-hidden">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <p class="text-indigo-400 text-xs font-bold uppercase tracking-widest mb-1"><?= $text_creditor_label ?></p>
                    <h3 class="text-4xl font-black"><?= number_format($remaining_total, 2) ?> <span class="text-lg font-normal opacity-50">دل</span></h3>
                    <p class="text-sm text-slate-400 mt-2"><?= $text_remaining ?></p>
                </div>
                <span class="bg-white bg-opacity-10 px-3 py-1 rounded-full text-[10px] font-bold uppercase"><?= $text_after_collection ?></span>
            </div>
            <div class="space-y-2 text-sm text-slate-300">
                <div class="flex justify-between"><span><?= $text_total_due ?></span><span><?= number_format($creditor_total, 2) ?></span></div>
                <div class="flex justify-between text-emerald-400"><span><?= $text_minus_collections ?></span><span><?= number_format($debtor_total, 2) ?></span></div>
                <div class="flex justify-between border-t border-slate-700 pt-2 font-bold text-white"><span>= المتبقي</span><span><?= number_format($remaining_total, 2) ?></span></div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-200 border-t-4 border-t-emerald-500">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <p class="text-emerald-600 text-xs font-bold uppercase tracking-widest mb-1"><?= $text_debtor_label ?></p>
                    <h3 class="text-4xl font-black text-slate-800"><?= number_format($debtor_total, 2) ?> <span class="text-lg font-normal text-slate-400">دل</span></h3>
                </div>
                <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-[10px] font-bold uppercase"><?= $text_actual_collection ?></span>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="text-slate-500 font-medium">
                    يخصم من استحقاق: <strong class="text-indigo-700"><?= htmlspecialchars($period_title) ?></strong>
                </span>
                <span class="font-bold <?= $can_collect ? 'text-emerald-700' : 'text-rose-600' ?>">
                    متبقي: <?= number_format($payable_remaining, 2) ?> دل
                </span>
            </div>

            <?php if ($_SESSION['admin_role'] !== 'shipping_company' && $sc_id_filter === null): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-2xl p-4">
                <i class='bx bx-info-circle mr-1'></i>
                الرجاء اختيار شركة شحن من الأعلى لتسجيل تحصيل جديد.
            </div>
            <?php elseif (!$can_collect): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-2xl p-4">
                <i class='bx bx-info-circle mr-1'></i>
                لا يوجد مبلغ مستحق متبقي في <?= htmlspecialchars($period_title) ?>. <?= $isSC ? 'لن يتم تسجيل سداد جديد.' : 'لن يتم تسجيل تحصيل جديد.' ?>
            </div>
            <?php else: ?>
            <form method="POST" class="mt-2 flex flex-wrap items-end gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100" id="addPaymentForm">
                <?= csrf_field() ?>
                <input type="hidden" name="sc_id" value="<?= (int)$sc_id_filter ?>">
                <input type="hidden" name="current_scope" value="<?= htmlspecialchars($scope === 'lifetime' ? 'period' : $scope) ?>">
                <input type="hidden" name="current_range" value="<?= htmlspecialchars($range) ?>">
                <input type="hidden" name="current_product" value="<?= htmlspecialchars($selected_product) ?>">
                <?php if ($range === 'year'): ?>
                    <input type="hidden" name="current_month" value="<?= htmlspecialchars($selected_month) ?>">
                    <div class="flex-1 min-w-[120px]">
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">السنة التي يُخصم منها</label>
                        <input type="number" name="current_year" value="<?= htmlspecialchars($selected_year) ?>" min="2020" max="2099"
                               class="w-full bg-white border-none p-2 rounded-lg text-sm font-bold text-indigo-700 shadow-sm" required>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="current_year" value="<?= htmlspecialchars($selected_year) ?>">
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">شهر الاستحقاق (يُخصم منه)</label>
                        <input type="month" name="current_month" id="collectMonthInput" value="<?= htmlspecialchars($selected_month) ?>"
                               class="w-full bg-white border-none p-2 rounded-lg text-sm font-bold text-indigo-700 shadow-sm" required>
                    </div>
                <?php endif; ?>
                <div class="flex-1 min-w-[120px]">
                    <label class="block text-[10px] font-bold text-slate-400 mb-1"><?= $text_receive_date ?> الفعلي</label>
                    <input type="date" name="entry_date" value="<?= htmlspecialchars($default_entry_date) ?>"
                           class="w-full bg-white border-none p-2 rounded-lg text-sm font-bold text-slate-700 shadow-sm" required>
                </div>
                <div class="flex-1 min-w-[120px]">
                    <label class="block text-[10px] font-bold text-slate-400 mb-1"><?= $text_amount_received ?> (حد أقصى <?= number_format($payable_remaining, 2) ?>)</label>
                    <input type="number" name="amount" placeholder="0.00" step="0.01" min="0.01"
                           max="<?= htmlspecialchars((string) round($payable_remaining, 2)) ?>"
                           class="w-full bg-white border-none p-2 rounded-lg text-sm font-bold text-slate-700 shadow-sm" required>
                </div>
                <div class="w-full md:w-auto">
                    <button type="submit" name="add_payment" value="1"
                            class="w-full bg-emerald-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-emerald-700 transition shadow-md text-sm">
                        <?= $text_btn_add ?>
                    </button>
                </div>
                <input type="hidden" name="note" value="<?= $isSC ? 'سداد من شركة الشحن (مسجل ذاتياً)' : 'تحصيل من شركة شحن' ?>">
            </form>

            <script>
            (function () {
                var monthInput = document.getElementById('collectMonthInput');
                if (!monthInput) return;
                monthInput.addEventListener('change', function () {
                    var m = monthInput.value;
                    if (!/^\d{4}-\d{2}$/.test(m)) return;
                    var url = new URL(window.location.href);
                    url.searchParams.set('page', 'debts');
                    url.searchParams.set('scope', 'period');
                    url.searchParams.set('range', 'month');
                    url.searchParams.set('month', m);
                    window.location.href = url.toString();
                });
            })();
            </script>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50">
            <h3 class="text-xl font-bold text-slate-700 flex items-center"><i class='bx bx-history mr-2 text-indigo-500'></i> <?= $text_history_title ?> — <?= htmlspecialchars($scope_label) ?></h3>
            <div class="flex gap-2">
                <?php
                $export_qs = http_build_query([
                    'scope' => $scope,
                    'range' => $range,
                    'month' => $selected_month,
                    'year' => $selected_year,
                    'product' => $selected_product,
                ]);
                ?>
                <a href="debts_export.php?format=csv&<?= htmlspecialchars($export_qs) ?>"
                   class="flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 rounded-xl text-xs font-bold hover:bg-emerald-100 transition border border-emerald-100">
                    <i class='bx bx-spreadsheet'></i> Excel
                </a>
                <a href="debts_export.php?format=pdf&<?= htmlspecialchars($export_qs) ?>"
                   class="flex items-center gap-2 px-4 py-2 bg-rose-50 text-rose-700 rounded-xl text-xs font-bold hover:bg-rose-100 transition border border-rose-100">
                    <i class='bx bxs-file-pdf'></i> PDF
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right" id="recordsTable">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 font-bold text-xs uppercase border-b">
                        <th class="py-4 px-6"><?= $text_receive_date ?></th>
                        <th class="py-4 px-6">شهر الاستحقاق</th>
                        <th class="py-4 px-6">البيان</th>
                        <th class="py-4 px-6 text-right"><?= $text_amount_received ?></th>
                        <th class="py-4 px-6 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($records_query && $records_query->num_rows > 0): ?>
                        <?php while ($row = $records_query->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-4 px-6 text-slate-600 font-medium"><?= htmlspecialchars($row['entry_date']) ?></td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold"><?= htmlspecialchars($row['apply_month'] ?? $row['period_month'] ?? '-') ?></span>
                            </td>
                            <td class="py-4 px-6 text-slate-500 text-xs italic">
                                <?php if (!$isSC && !empty($row['company_name'])): ?>
                                    <span class="block font-bold text-slate-700"><?= htmlspecialchars($row['company_name']) ?></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($row['note'] ?? ($isSC ? 'سداد مسجل' : 'تحصيل من شركة الشحن')) ?>
                            </td>
                            <td class="py-4 px-6 font-black text-emerald-600"><?= number_format((float) $row['amount'], 2) ?> دل</td>
                            <td class="py-4 px-6 text-center">
                                <?php if ($_SESSION['admin_role'] !== 'shipping_company'): ?>
                                <form method="POST" onsubmit="return confirm('هل تريد حذف هذا التحصيل؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="current_scope" value="<?= htmlspecialchars($scope) ?>">
                                    <input type="hidden" name="current_range" value="<?= htmlspecialchars($range) ?>">
                                    <input type="hidden" name="current_month" value="<?= htmlspecialchars($selected_month) ?>">
                                    <input type="hidden" name="current_year" value="<?= htmlspecialchars($selected_year) ?>">
                                    <input type="hidden" name="current_product" value="<?= htmlspecialchars($selected_product) ?>">
                                    <button type="submit" name="delete_record" class="text-rose-300 hover:text-rose-600 transition"><i class='bx bx-trash text-xl'></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="py-12 text-center text-slate-400 italic"><?= $text_no_records ?><?= $scope === 'period' ? ' في هذه الفترة' : '' ?> حتى الآن</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($debtor_total > 0): ?>
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex flex-wrap justify-between gap-2 text-sm font-bold">
            <span class="text-slate-600"><?= $isSC ? 'مجموع المبالغ المعروضة' : 'مجموع التحصيلات المعروضة' ?></span>
            <span class="text-emerald-700"><?= number_format($debtor_total, 2) ?> دل</span>
        </div>
        <?php endif; ?>
    </div>
</div>
