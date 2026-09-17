<?php
// admin_accounts.php — حسابات الأرباح والتكاليف
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

migrate_legacy_product_monthly($conn);

$is_super_admin = (($_SESSION['admin_role'] ?? '') === 'super_admin');
// غير المدير الرئيسي: يظهر له بوكس/قسم الليدات فقط
$accounts_full_access = $is_super_admin;

$section = $_GET['section'] ?? 'hub';
if (!in_array($section, ['hub', 'leads', 'accounts'], true)) {
    $section = 'hub';
}
if (!$accounts_full_access) {
    $section = 'leads';
}

$range = ($_GET['range'] ?? 'month') === 'year' ? 'year' : 'month';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_year = $_GET['year'] ?? date('Y');
$accounts_view = ($_GET['view'] ?? 'month') === 'product' ? 'product' : 'month';
$selected_product = $_GET['product'] ?? 'all';
$product_codes = get_support_product_codes_list($conn);
if ($accounts_view === 'product' && $selected_product === 'all' && !empty($product_codes)) {
    $selected_product = $product_codes[0];
}

$accounts_flash = '';
if (!empty($_SESSION['accounts_flash_success'])) {
    $accounts_flash = "<div class='bg-emerald-100 border border-emerald-400 text-emerald-800 px-4 py-3 rounded-2xl mb-4 flex items-center gap-2'><i class='bx bx-check-circle text-xl'></i>" . htmlspecialchars($_SESSION['accounts_flash_success'], ENT_QUOTES, 'UTF-8') . "</div>";
    unset($_SESSION['accounts_flash_success']);
} elseif (!empty($_SESSION['accounts_flash_error'])) {
    $accounts_flash = "<div class='bg-rose-100 border border-rose-400 text-rose-800 px-4 py-3 rounded-2xl mb-4 flex items-center gap-2'><i class='bx bx-error-circle text-xl'></i>" . htmlspecialchars($_SESSION['accounts_flash_error'], ENT_QUOTES, 'UTF-8') . "</div>";
    unset($_SESSION['accounts_flash_error']);
}

[$start_date, $end_date, $period_label, $range_type] = get_accounts_period_dates($range, $selected_month, $selected_year);
$product_filter = ($selected_product !== 'all') ? $selected_product : null;
$fin = calculate_support_accounts_financials($conn, $start_date, $end_date, $product_filter);
$inventory_month = $range === 'year' ? $selected_year . '-12' : $selected_month;
$inventory_product = $product_filter ?? ($accounts_view === 'product' ? $selected_product : null);
$inventory = calculate_inventory_standing_cost($conn, $inventory_month, $inventory_product, $start_date, $end_date);
$product_breakdown = ($accounts_view === 'product' || $selected_product === 'all')
    ? get_accounts_product_breakdown($conn, $start_date, $end_date)
    : [];
$monthly_breakdown = ($accounts_view === 'month' && $range === 'year')
    ? get_accounts_monthly_breakdown($conn, $selected_year, $product_filter)
    : [];
$reconcile = ($section === 'accounts' && $accounts_full_access)
    ? get_accounts_reconciliation_summary($conn, $start_date, $end_date, $product_filter)
    : null;

$settings_month = $range === 'year' ? date('Y-m') : $selected_month;
$settings_product = trim($_GET['edit_product'] ?? (($selected_product !== 'all') ? $selected_product : ($product_codes[0] ?? 'Etala001')));
if ($settings_product === '') {
    $settings_product = $product_codes[0] ?? 'Etala001';
}
$product_edit = get_product_monthly_row($conn, $settings_month, $settings_product) ?? [
    'product_code' => $settings_product,
    'product_name' => $settings_product,
    'stock_quantity' => 0,
    'product_sale_price' => 0,
    'bundle_sale_price' => 0,
    'product_cost' => 0,
    'bundle_cost' => 0,
    'intl_shipping' => 0,
    'dom_shipping' => 0,
    'ops_cost' => 0,
];
if ((float) ($product_edit['bundle_sale_price'] ?? 0) <= 0 && (float) ($product_edit['product_sale_price'] ?? 0) > 0) {
    $product_edit['bundle_sale_price'] = (float) $product_edit['product_sale_price'] * 3;
}
if ((float) ($product_edit['bundle_cost'] ?? 0) <= 0 && (float) ($product_edit['product_cost'] ?? 0) > 0) {
    $product_edit['bundle_cost'] = (float) $product_edit['product_cost'] * 3;
}

$period_title = $range === 'year' ? ('سنة ' . $selected_year) : ('شهر ' . $selected_month);
$hub_url = fn($s) => 'admin_panel.php?page=accounts&section=' . $s
    . '&view=' . urlencode($accounts_view)
    . '&range=' . urlencode($range)
    . '&month=' . urlencode($selected_month)
    . '&year=' . urlencode($selected_year)
    . '&product=' . urlencode($selected_product);
?>

<style>
.accounts-hub-card { transition: transform .2s, box-shadow .2s; cursor: pointer; }
.accounts-hub-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(15,23,42,.12); }
.accounts-section-hidden { display: none; }
</style>

<div class="max-w-7xl mx-auto px-4 py-8 space-y-6 font-sans">

    <?= $accounts_flash ?>

    <!-- شريط الفترة المشترك -->
    <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800 flex items-center gap-3">
                <span class="bg-indigo-600 text-white p-2 rounded-xl"><i class='bx bx-wallet'></i></span>
                <?= $accounts_full_access ? 'حسابات الأرباح والتكاليف' : 'الليدات' ?>
            </h1>
            <p class="text-slate-500 text-sm mt-1">
                <?= $period_title ?> — من <?= $start_date ?> إلى <?= $end_date ?>
                <?php if ($selected_product !== 'all'): ?>
                    — منتج: <strong class="text-indigo-600"><?= htmlspecialchars($selected_product) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-2 bg-slate-100 p-2 rounded-2xl">
            <input type="hidden" name="page" value="accounts">
            <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
            <?php if ($accounts_full_access): ?>
            <div class="flex bg-white rounded-xl p-1 gap-1">
                <a href="admin_panel.php?page=accounts&section=<?= urlencode($section) ?>&view=month&range=<?= urlencode($range) ?>&month=<?= urlencode($selected_month) ?>&year=<?= urlencode($selected_year) ?>&product=<?= urlencode($selected_product) ?>"
                   class="px-3 py-1 rounded-lg text-sm font-bold <?= $accounts_view === 'month' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' ?>">بالشهر</a>
                <a href="admin_panel.php?page=accounts&section=<?= urlencode($section) ?>&view=product&range=<?= urlencode($range) ?>&month=<?= urlencode($selected_month) ?>&year=<?= urlencode($selected_year) ?>&product=<?= urlencode($selected_product === 'all' ? ($product_codes[0] ?? 'all') : $selected_product) ?>"
                   class="px-3 py-1 rounded-lg text-sm font-bold <?= $accounts_view === 'product' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50' ?>">بالمنتج</a>
            </div>
            <?php endif; ?>
            <input type="hidden" name="view" value="<?= htmlspecialchars($accounts_full_access ? $accounts_view : 'month') ?>">
            <select name="range" onchange="this.form.submit()" class="bg-transparent font-bold text-slate-700 px-2 py-1 rounded-lg">
                <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>شهر</option>
                <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>سنة كاملة</option>
            </select>
            <?php if ($range === 'month'): ?>
                <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()" class="bg-white rounded-xl px-2 py-1 font-bold text-indigo-600 border-0">
            <?php else: ?>
                <input type="number" name="year" value="<?= htmlspecialchars($selected_year) ?>" min="2020" max="2099" onchange="this.form.submit()" class="bg-white rounded-xl px-3 py-1 font-bold text-indigo-600 w-24 border-0">
            <?php endif; ?>
            <?php if ($accounts_full_access): ?>
            <select name="product" onchange="this.form.submit()" class="bg-white rounded-xl px-2 py-1 font-bold text-slate-700 border-0">
                <?php if ($accounts_view === 'month'): ?>
                <option value="all" <?= $selected_product === 'all' ? 'selected' : '' ?>>كل المنتجات</option>
                <?php endif; ?>
                <?php foreach ($product_codes as $code): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $selected_product === $code ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="product" value="all">
            <?php endif; ?>
        </form>
    </div>

    <?php if ($accounts_full_access): ?>
    <!-- ملخص مرتبط (للمدير الرئيسي فقط) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 text-center">
            <p class="text-xs text-slate-400 font-bold">تكلفة الليدات</p>
            <p class="text-xl font-black text-rose-600"><?= number_format($fin['total_lead_cost'], 2) ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 text-center">
            <p class="text-xs text-slate-400 font-bold">إجمالي التكاليف</p>
            <p class="text-xl font-black text-slate-800"><?= number_format($fin['total_costs'], 2) ?></p>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 text-center">
            <p class="text-xs text-slate-400 font-bold">الإيرادات (مسلّم)</p>
            <p class="text-xl font-black text-emerald-600"><?= number_format($fin['sum_revenue'], 2) ?></p>
        </div>
        <div class="<?= $fin['net_profit'] < 0 ? 'bg-rose-600' : 'bg-emerald-600' ?> p-4 rounded-2xl text-white text-center">
            <p class="text-xs opacity-80 font-bold"><?= $fin['net_profit'] < 0 ? 'خسارة صافية' : 'ربح صافي' ?></p>
            <p class="text-xl font-black"><?= number_format($fin['net_profit'], 2) ?> دل</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($section === 'hub' && $accounts_full_access): ?>
    <!-- ========== الصفحة الرئيسية: بوكسين ========== -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
        <a href="<?= $hub_url('leads') ?>" class="accounts-hub-card block bg-gradient-to-br from-amber-50 to-orange-100 border-2 border-orange-200 rounded-3xl p-8 no-underline">
            <div class="flex items-center justify-between mb-6">
                <div class="w-16 h-16 bg-orange-500 rounded-2xl flex items-center justify-center text-white text-3xl shadow-lg">
                    <i class='bx bx-bullseye'></i>
                </div>
                <span class="text-orange-600 font-bold text-sm">اضغط للدخول ←</span>
            </div>
            <h2 class="text-2xl font-black text-slate-800 mb-2">الليدات</h2>
            <p class="text-slate-600 mb-4">إدخال سعر الليد اليومي وحساب تكلفة الليدات المرتبطة بعدد الطلبات</p>
            <div class="bg-white/70 rounded-2xl p-4">
                <p class="text-sm text-slate-500">إجمالي تكلفة الليدات للفترة</p>
                <p class="text-3xl font-black text-orange-600"><?= number_format($fin['total_lead_cost'], 2) ?> <span class="text-sm">دل</span></p>
            </div>
        </a>

        <a href="<?= $hub_url('accounts') ?>" class="accounts-hub-card block bg-gradient-to-br from-indigo-50 to-violet-100 border-2 border-indigo-200 rounded-3xl p-8 no-underline">
            <div class="flex items-center justify-between mb-6">
                <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center text-white text-3xl shadow-lg">
                    <i class='bx bx-calculator'></i>
                </div>
                <span class="text-indigo-600 font-bold text-sm">اضغط للدخول ←</span>
            </div>
            <h2 class="text-2xl font-black text-slate-800 mb-2">الحسابات والأرباح</h2>
            <p class="text-slate-600 mb-4">تكاليف المنتجات، المخزون الواقف، والربح/الخسارة للشهر أو السنة</p>
            <div class="bg-white/70 rounded-2xl p-4">
                <p class="text-sm text-slate-500">صافي الربح / الخسارة</p>
                <p class="text-3xl font-black <?= $fin['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>">
                    <?= number_format($fin['net_profit'], 2) ?> <span class="text-sm">دل</span>
                </p>
            </div>
        </a>
    </div>
    <p class="text-center text-slate-400 text-sm"><i class='bx bx-link'></i> القسمين مرتبطين — تكلفة الليدات تدخل تلقائياً في حساب الربح الصافي</p>

    <?php elseif ($section === 'leads'): ?>
    <!-- ========== قسم الليدات ========== -->
    <div class="flex items-center gap-3">
        <?php if ($accounts_full_access): ?>
        <a href="<?= $hub_url('hub') ?>" class="inline-flex items-center gap-2 bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl font-bold text-sm transition">
            <i class='bx bx-arrow-back'></i> رجوع
        </a>
        <?php endif; ?>
        <h2 class="text-xl font-bold text-slate-800"><i class='bx bx-bullseye text-orange-500'></i> سجل الليدات اليومي</h2>
    </div>

    <div class="bg-white rounded-3xl shadow-md border border-slate-100 overflow-hidden">
        <div class="bg-orange-500 text-white p-4 flex justify-between items-center">
            <span class="font-bold">إدخال سعر الليد لكل يوم <?= $selected_product !== 'all' ? '(' . htmlspecialchars($selected_product) . ')' : '' ?></span>
            <span class="text-sm bg-white/20 px-3 py-1 rounded-full">المجموع: <?= number_format($fin['total_lead_cost'], 2) ?> دل</span>
        </div>
        
        <?php if ($selected_product === 'all'): ?>
        <div class="bg-amber-100 border-l-4 border-amber-500 text-amber-700 p-4 m-4 rounded-r-lg">
            <p class="font-bold flex items-center gap-2"><i class='bx bx-error-circle text-xl'></i> تنبيه: تخصيص الليدات</p>
            <p class="text-sm mt-1">حسابات الليدات أصبحت منفصلة لكل منتج. يرجى اختيار منتدلحدد من القائمة العلوية لتتمكن من إدخال أو تعديل تكلفة الليدات الخاصة به.</p>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto max-h-[60vh]">
            <table class="w-full text-right">
                <thead class="bg-slate-50 text-slate-500 text-xs font-bold sticky top-0">
                    <tr>
                        <th class="py-3 px-4">التاريخ</th>
                        <th class="py-3 px-4 text-center">عدد الطلبات</th>
                        <th class="py-3 px-4">سعر الليد (دل)</th>
                        <th class="py-3 px-4">تكلفة اليوم</th>
                        <th class="py-3 px-4 text-center">حالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($fin['daily_table'] as $day): ?>
                    <tr class="hover:bg-orange-50/50">
                        <td class="py-2 px-4 font-bold text-slate-700"><?= $day['date'] ?></td>
                        <td class="py-2 px-4 text-center"><span class="bg-slate-100 px-2 py-1 rounded text-xs font-bold"><?= $day['count'] ?></span></td>
                        <td class="py-2 px-4">
                            <form method="POST" action="admin_panel.php?page=accounts" class="flex items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="date" value="<?= $day['date'] ?>">
                                <input type="hidden" name="redirect_section" value="leads">
                                <input type="hidden" name="current_month" value="<?= htmlspecialchars($selected_month) ?>">
                                <input type="hidden" name="current_year" value="<?= htmlspecialchars($selected_year) ?>">
                                <input type="hidden" name="current_range" value="<?= htmlspecialchars($range) ?>">
                                <input type="hidden" name="current_view" value="<?= htmlspecialchars($accounts_view) ?>">
                                <input type="hidden" name="current_product" value="<?= htmlspecialchars($selected_product) ?>">
                                
                                <?php if ($selected_product === 'all'): ?>
                                <input type="number" value="<?= $day['price'] ?>" disabled class="w-24 border rounded-lg p-2 text-sm font-bold bg-gray-100 text-gray-400 cursor-not-allowed" title="اختر منتجاً لإدخال السعر">
                                <button type="button" disabled class="bg-gray-300 text-gray-500 p-2 rounded-lg cursor-not-allowed" title="اختر منتجاً أولاً"><i class='bx bx-check'></i></button>
                                <?php else: ?>
                                <input type="number" name="lead_cost" value="<?= $day['price'] ?>" step="0.01" class="w-24 border rounded-lg p-2 text-sm font-bold">
                                <button type="submit" name="save_daily_lead" class="bg-orange-500 text-white p-2 rounded-lg hover:bg-orange-600"><i class='bx bx-check'></i></button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="py-2 px-4 font-bold"><?= number_format($day['cost'], 2) ?> دل</td>
                        <td class="py-2 px-4 text-center">
                            <?= $day['price'] > 0 ? "<i class='bx bxs-check-circle text-emerald-500 text-xl'></i>" : "<i class='bx bx-error-circle text-amber-500 text-xl'></i>" ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($section === 'accounts' && $accounts_full_access): ?>
    <!-- ========== قسم الحسابات (المدير الرئيسي فقط) ========== -->
    <div class="flex items-center gap-3">
        <a href="<?= $hub_url('hub') ?>" class="inline-flex items-center gap-2 bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl font-bold text-sm transition">
            <i class='bx bx-arrow-back'></i> رجوع
        </a>
        <h2 class="text-xl font-bold text-slate-800"><i class='bx bx-calculator text-indigo-600'></i> الحسابات والأرباح</h2>
    </div>

    <!-- المخزون الواقف (بدون سعر البيع) + تكلفة الليدات -->
    <div class="bg-slate-900 text-white rounded-3xl p-6">
        <h3 class="text-lg font-bold mb-1 flex items-center gap-2">
            <i class='bx bx-package text-amber-400'></i>
            المنتج واقف عليك بكام؟
            <span class="text-sm font-normal text-slate-400">(مخزون + شحن دولي + تكلفة الليدات للمنتج — بدون سعر البيع)</span>
        </h3>
        <p class="text-3xl font-black text-amber-400 mt-3"><?= number_format($inventory['total'], 2) ?> دل</p>
        <?php if (($inventory['total_lead_alloc'] ?? 0) > 0 || ($inventory['total_base'] ?? 0) > 0): ?>
        <div class="flex flex-wrap gap-4 mt-2 text-sm text-slate-300">
            <span>مخزون: <strong class="text-white"><?= number_format($inventory['total_base'] ?? 0, 2) ?></strong> دل</span>
            <span>+ ليدات مخصصة: <strong class="text-orange-300"><?= number_format($inventory['total_lead_alloc'] ?? 0, 2) ?></strong> دل</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($inventory['items'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
            <?php foreach ($inventory['items'] as $item): ?>
            <div class="bg-slate-800 rounded-xl p-4 text-sm">
                <p class="font-bold text-white"><?= htmlspecialchars($item['product_name']) ?> <span class="text-slate-400">(<?= htmlspecialchars($item['product_code']) ?>)</span></p>
                <p class="text-slate-400 mt-1"><?= $item['stock_quantity'] ?> قطعة × <?= number_format($item['unit_cost'], 2) ?> دل/قطعة = <?= number_format($item['inventory_base'] ?? ($item['stock_quantity'] * $item['unit_cost']), 2) ?> دل</p>
                <?php if (($item['lead_cost_allocated'] ?? 0) > 0): ?>
                <p class="text-orange-300 mt-1">
                    + <?= number_format($item['leads_count_allocated'] ?? 0, 0) ?> ليد × <?= number_format($item['cost_per_lead'] ?? 0, 2) ?> دل
                    = <?= number_format($item['lead_cost_allocated'], 2) ?> دل
                </p>
                <?php endif; ?>
                <p class="text-amber-300 font-bold mt-1"><?= number_format($item['standing_cost'], 2) ?> دل</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- تفصيل التكاليف والربح -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <h3 class="font-bold text-slate-800 mb-4 border-b pb-3">تفصيل الحساب — <?= $period_title ?></h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">إيرادات (طلبات مسلّمة)</span><span class="font-bold text-emerald-600">+ <?= number_format($fin['sum_revenue'], 2) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">تكلفة الليدات</span><span class="font-bold text-rose-500">- <?= number_format($fin['total_lead_cost'], 2) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">تكلفة المنتجات (مسلّم)</span><span class="font-bold text-rose-500">- <?= number_format($fin['sum_product_cost'], 2) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">شحن دولي (تناسبي)</span><span class="font-bold text-rose-500">- <?= number_format($fin['total_proportional_intl'], 2) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">شحن داخلي (مؤكد)</span><span class="font-bold text-rose-500">- <?= number_format($fin['sum_dom_shipping'], 2) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">مصاريف تشغيل</span><span class="font-bold text-rose-500">- <?= number_format($fin['total_proportional_ops'], 2) ?></span></div>
                
                <!-- تفاصيل البونص -->
                <?php $t_bonus = $fin['bonuses']['total'] ?? 0; if ($t_bonus > 0): ?>
                <div class="mt-2 mb-2 p-2 bg-rose-50 rounded-lg border border-rose-100 text-xs">
                    <p class="font-bold text-rose-700 mb-1 border-b border-rose-200 pb-1">إجمالي البونص المخصوم لجميع الأقسام: <?= number_format($t_bonus, 2) ?> دل</p>
                    <div class="flex justify-between"><span class="text-rose-600">بونص الدعم الفني</span><span class="font-bold text-rose-700"><?= number_format($fin['bonuses']['support'] ?? 0, 2) ?></span></div>
                    <div class="flex justify-between"><span class="text-rose-600">بونص التسويق</span><span class="font-bold text-rose-700"><?= number_format($fin['bonuses']['marketing'] ?? 0, 2) ?></span></div>
                    <div class="flex justify-between"><span class="text-rose-600">بونص شركات الشحن</span><span class="font-bold text-rose-700"><?= number_format($fin['bonuses']['shipping'] ?? 0, 2) ?></span></div>
                </div>
                <?php else: ?>
                <div class="flex justify-between"><span class="text-slate-500">إجمالي بونص الأقسام</span><span class="font-bold text-rose-500">- 0.00</span></div>
                <?php endif; ?>

                <div class="border-t pt-3 flex justify-between text-base">
                    <span class="font-bold">الصافي</span>
                    <span class="font-black <?= $fin['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= number_format($fin['net_profit'], 2) ?> دل</span>
                </div>
            </div>
            <?php if ($reconcile): ?>
            <div class="mt-4 p-3 bg-indigo-50 rounded-xl text-xs border border-indigo-100">
                <p class="font-bold text-indigo-800 mb-2"><i class='bx bx-link'></i> مقارنة مع كشف الشحن (نفس الفترة)</p>
                <div class="flex justify-between text-slate-600"><span>مستحق من شركة الشحن</span><span class="font-bold text-indigo-700"><?= number_format($reconcile['creditor_due'], 2) ?> دل</span></div>
                <div class="flex justify-between text-slate-500 mt-1"><span>بيع مسلّم − شحن داخلي مسلّم</span><span><?= number_format($reconcile['total_sale'], 2) ?> − <?= number_format($reconcile['total_dom_shipping'], 2) ?></span></div>
                <div class="flex justify-between text-slate-600 mt-2 border-t border-indigo-100 pt-2"><span>ربح صافي (هنا)</span><span class="font-bold <?= $reconcile['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= number_format($reconcile['net_profit'], 2) ?> دل</span></div>
                <p class="text-slate-500 mt-2">
                    الفرق <?= number_format($reconcile['shipping_vs_profit_gap'], 2) ?> دل = تكاليف ليدات ومنتج وشحن دولي وتشغيل.
                    <a href="admin_panel.php?page=debts&scope=period&range=<?= urlencode($range) ?>&month=<?= urlencode($selected_month) ?>&year=<?= urlencode($selected_year) ?>&product=<?= urlencode($selected_product) ?>" class="text-indigo-600 font-bold hover:underline">كشف الشحن ←</a>
                </p>
            </div>
            <?php endif; ?>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                <div class="bg-slate-50 p-2 rounded-xl"><span class="text-slate-400 block">ليدات (شيت)</span><strong><?= $fin['total_leads_pool'] ?></strong></div>
                <div class="bg-slate-50 p-2 rounded-xl"><span class="text-slate-400 block">مؤكد</span><strong><?= $fin['confirmed_count'] ?></strong></div>
                <div class="bg-slate-50 p-2 rounded-xl"><span class="text-slate-400 block">مسلّم</span><strong><?= $fin['delivered_count'] ?></strong></div>
            </div>
        </div>

        <!-- إعدادات المنتج -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <h3 class="font-bold text-slate-800 mb-4 border-b pb-3">
                <i class='bx bx-cog text-slate-400'></i>
                إعدادات المنتج — <?= htmlspecialchars($settings_month) ?>
            </h3>
            <form method="POST" action="admin_panel.php?page=accounts" class="grid grid-cols-2 gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect_section" value="accounts">
                <input type="hidden" name="current_month" value="<?= htmlspecialchars($selected_month) ?>">
                <input type="hidden" name="current_year" value="<?= htmlspecialchars($selected_year) ?>">
                <input type="hidden" name="current_range" value="<?= htmlspecialchars($range) ?>">
                <input type="hidden" name="current_view" value="<?= htmlspecialchars($accounts_view) ?>">
                <input type="hidden" name="current_product" value="<?= htmlspecialchars($selected_product) ?>">
                <input type="hidden" name="month" value="<?= htmlspecialchars($settings_month) ?>">

                <div class="col-span-2">
                    <label class="text-xs font-bold text-slate-400">كود المنتج (اكتب كود جديد أو اختر موجود)</label>
                    <input type="text" name="product_code" list="product-codes-list" value="<?= htmlspecialchars($settings_product) ?>" required
                        class="w-full bg-slate-50 p-2 rounded-xl border font-mono" placeholder="مثال: Etala001">
                    <datalist id="product-codes-list">
                        <?php foreach ($product_codes as $code): ?>
                            <option value="<?= htmlspecialchars($code) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <p class="text-[10px] text-slate-400 mt-1">عند حفظ كود جديد سيتم إنشاء إعدادات له لهذا الشهر تلقائياً</p>
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-bold text-slate-400">اسم المنتج</label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($product_edit['product_name'] ?? '') ?>" class="w-full bg-slate-50 p-2 rounded-xl border">
                </div>
                <div><label class="text-xs font-bold text-slate-400">المخزون (قطع)</label><input type="number" name="stock_quantity" value="<?= (int) ($product_edit['stock_quantity'] ?? 0) ?>" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">سعر بيع Single (1 قطعة)</label><input type="number" name="product_sale_price" value="<?= (float) ($product_edit['product_sale_price'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">سعر بيع Bundle (3 قطع)</label><input type="number" name="bundle_sale_price" value="<?= (float) ($product_edit['bundle_sale_price'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">تكلفة Single</label><input type="number" name="product_cost" value="<?= (float) ($product_edit['product_cost'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">تكلفة Bundle</label><input type="number" name="bundle_cost" value="<?= (float) ($product_edit['bundle_cost'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">شحن دولي/قطعة</label><input type="number" name="intl_shipping" value="<?= (float) ($product_edit['intl_shipping'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">شحن داخلي/طلب</label><input type="number" name="dom_shipping" value="<?= (float) ($product_edit['dom_shipping'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <div><label class="text-xs font-bold text-slate-400">تشغيل (شهري)</label><input type="number" name="ops_cost" value="<?= (float) ($product_edit['ops_cost'] ?? 0) ?>" step="0.01" class="w-full bg-slate-50 p-2 rounded-xl border"></div>
                <button type="submit" name="save_product_monthly" class="col-span-2 bg-indigo-600 text-white p-3 rounded-2xl font-bold hover:bg-indigo-700">حفظ إعدادات المنتج</button>
            </form>
            <p class="text-xs text-slate-400 mt-3">المخزون × (تكلفة + شحن دولي) + تكلفة الليدات المخصصة للمنتج = رأس المال الواقف</p>
        </div>
    </div>

    <?php if ($accounts_view === 'month' && $range === 'year' && !empty($monthly_breakdown)): ?>
    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="bg-emerald-600 text-white p-4 font-bold">
            <i class='bx bx-calendar'></i> تقرير الأرباح والخسائر شهرياً — سنة <?= htmlspecialchars($selected_year) ?>
            <?php if ($selected_product !== 'all'): ?> — <?= htmlspecialchars($selected_product) ?><?php endif; ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold">
                    <tr>
                        <th class="p-3">الشهر</th>
                        <th class="p-3">ليدات</th>
                        <th class="p-3">إيرادات</th>
                        <th class="p-3">تكاليف</th>
                        <th class="p-3">ليدات (تكلفة)</th>
                        <th class="p-3 text-rose-500">دعم</th>
                        <th class="p-3 text-rose-500">تسويق</th>
                        <th class="p-3 text-rose-500">شحن</th>
                        <th class="p-3">مخزون واقف</th>
                        <th class="p-3">صافي</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($monthly_breakdown as $row): ?>
                    <tr class="hover:bg-emerald-50/30">
                        <td class="p-3 font-bold"><?= htmlspecialchars($row['month']) ?></td>
                        <td class="p-3"><?= $row['total_leads_pool'] ?></td>
                        <td class="p-3 text-emerald-600 font-bold"><?= number_format($row['sum_revenue'], 2) ?></td>
                        <td class="p-3 text-rose-500"><?= number_format($row['total_costs'] - $row['total_lead_cost'] - ($row['bonuses']['total'] ?? 0), 2) ?></td>
                        <td class="p-3 text-orange-500"><?= number_format($row['total_lead_cost'], 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['support'] ?? 0, 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['marketing'] ?? 0, 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['shipping'] ?? 0, 2) ?></td>
                        <td class="p-3 text-amber-600"><?= number_format($row['inventory_standing'], 2) ?></td>
                        <td class="p-3 font-black <?= $row['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= number_format($row['net_profit'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($accounts_view === 'product' && !empty($product_breakdown)): ?>
    <!-- جدول كل منتج على حدة -->
    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="bg-indigo-600 text-white p-4 font-bold">
            <i class='bx bx-grid-alt'></i> تقرير الأرباح والخسائر لكل منتج — <?= $period_title ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold">
                    <tr>
                        <th class="p-3">المنتج</th>
                        <th class="p-3">ليدات</th>
                        <th class="p-3">طلبات</th>
                        <th class="p-3">مسلّم</th>
                        <th class="p-3">إيرادات</th>
                        <th class="p-3">تكاليف</th>
                        <th class="p-3">ليدات (تكلفة)</th>
                        <th class="p-3 text-rose-500">دعم</th>
                        <th class="p-3 text-rose-500">تسويق</th>
                        <th class="p-3 text-rose-500">شحن</th>
                        <th class="p-3">مخزون واقف</th>
                        <th class="p-3">صافي</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($product_breakdown as $row): ?>
                    <tr class="hover:bg-indigo-50/30 <?= $selected_product === $row['product_code'] ? 'bg-indigo-50/50' : '' ?>">
                        <td class="p-3 font-bold">
                            <a href="admin_panel.php?page=accounts&section=accounts&view=product&range=<?= urlencode($range) ?>&month=<?= urlencode($selected_month) ?>&year=<?= urlencode($selected_year) ?>&product=<?= urlencode($row['product_code']) ?>" class="text-indigo-600 hover:underline">
                                <?= htmlspecialchars($row['product_code']) ?>
                            </a>
                        </td>
                        <td class="p-3"><?= $row['total_leads_pool'] ?></td>
                        <td class="p-3"><?= $row['total_orders'] ?></td>
                        <td class="p-3"><?= $row['delivered_count'] ?></td>
                        <td class="p-3 text-emerald-600 font-bold"><?= number_format($row['sum_revenue'], 2) ?></td>
                        <td class="p-3 text-rose-500"><?= number_format($row['total_costs'] - $row['total_lead_cost'] - ($row['bonuses']['total'] ?? 0), 2) ?></td>
                        <td class="p-3 text-orange-500"><?= number_format($row['total_lead_cost'], 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['support'] ?? 0, 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['marketing'] ?? 0, 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['shipping'] ?? 0, 2) ?></td>
                        <td class="p-3 text-amber-600"><?= number_format($row['inventory_standing'], 2) ?></td>
                        <td class="p-3 font-black <?= $row['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= number_format($row['net_profit'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($accounts_view === 'month' && $selected_product === 'all' && !empty($product_breakdown)): ?>
    <!-- جدول كل منتج على حدة -->
    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="bg-indigo-600 text-white p-4 font-bold">
            <i class='bx bx-grid-alt'></i> تقرير كل منتج على حدة — <?= $period_title ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold">
                    <tr>
                        <th class="p-3">المنتج</th>
                        <th class="p-3">طلبات</th>
                        <th class="p-3">مسلّم</th>
                        <th class="p-3">إيرادات</th>
                        <th class="p-3">تكاليف</th>
                        <th class="p-3">ليدات</th>
                        <th class="p-3">بونص دعم</th>
                        <th class="p-3">بونص تسويق</th>
                        <th class="p-3">بونص شحن</th>
                        <th class="p-3">مخزون واقف</th>
                        <th class="p-3">صافي</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($product_breakdown as $row): ?>
                    <tr class="hover:bg-indigo-50/30">
                        <td class="p-3 font-bold"><?= htmlspecialchars($row['product_code']) ?></td>
                        <td class="p-3"><?= $row['total_orders'] ?></td>
                        <td class="p-3"><?= $row['delivered_count'] ?></td>
                        <td class="p-3 text-emerald-600 font-bold"><?= number_format($row['sum_revenue'], 2) ?></td>
                        <td class="p-3 text-rose-500"><?= number_format($row['total_costs'] - $row['total_lead_cost'] - ($row['bonuses']['total'] ?? 0), 2) ?></td>
                        <td class="p-3 text-orange-500"><?= number_format($row['total_lead_cost'], 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['support'] ?? 0, 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['marketing'] ?? 0, 2) ?></td>
                        <td class="p-3 text-rose-600"><?= number_format($row['bonuses']['shipping'] ?? 0, 2) ?></td>
                        <td class="p-3 text-amber-600"><?= number_format($row['inventory_standing'], 2) ?></td>
                        <td class="p-3 font-black <?= $row['net_profit'] < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= number_format($row['net_profit'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
