<?php
// admin_marketing_main.php - التسويق Marketing
// يتم تضمينها من admin_panel.php

$is_manager = in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'manager']);
$view_agent_code = $_GET['agent_code'] ?? '';
$is_manager_view = ($is_manager && empty($view_agent_code));

if ($is_manager_view) {
    include 'admin_marketing_manager.php';
    return;
}

// جلب البيانات الحقيقية من قاعدة البيانات
$my_tracking_code = $is_manager ? $view_agent_code : ($_SESSION['marketer_code'] ?? 'MK00');

$selected_month = $_GET['month'] ?? date('Y-m');
$selected_day = $_GET['day'] ?? date('Y-m-d');

$start_date = date('Y-m-01', strtotime($selected_month . "-01"));
$end_date = date('Y-m-t', strtotime($selected_month . "-01"));
$creative_perf = calculate_creative_performance($conn, $start_date, $end_date, $my_tracking_code);

$support_q = $conn->query("
    SELECT 
        COUNT(*) as total_clean,
        SUM(CASE WHEN order_status IN ('confirmed', 'out_for_delivery', 'delivered') THEN 1 ELSE 0 END) as total_confirmed,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as total_delivered,
        SUM(CASE WHEN order_status = 'delivered' AND pieces > 1 THEN 1 ELSE 0 END) as total_bundle_delivered
    FROM support_orders 
    WHERE marketing_agent = '$my_tracking_code' AND DATE_FORMAT(created_at, '%Y-%m') = '$selected_month'
");
$support_stats = ($support_q) ? $support_q->fetch_assoc() : [];

$confirmed = (int)($support_stats['total_confirmed'] ?? 0);
$delivered = (int)($support_stats['total_delivered'] ?? 0);
$bundle_delivered = (int)($support_stats['total_bundle_delivered'] ?? 0);
$single_delivered = $delivered - $bundle_delivered;

// حساب Rank
$rank_query = $conn->query("
    SELECT marketing_agent, COUNT(*) as d_count 
    FROM support_orders 
    WHERE order_status = 'delivered' AND marketing_agent != '' AND DATE_FORMAT(created_at, '%Y-%m') = '$selected_month'
    GROUP BY marketing_agent 
    ORDER BY d_count DESC
");
$current_rank = 1;
$my_numeric_rank = '-';
if ($rank_query) {
    while($r = $rank_query->fetch_assoc()) {
        if ($r['marketing_agent'] === $my_tracking_code) {
            $my_numeric_rank = $current_rank;
            break;
        }
        $current_rank++;
    }
}
$my_rank = $my_numeric_rank === '-' ? '-' : '#' . $my_numeric_rank;

$cpl = 0; // سيتم تحديده لاحقا بناء على الميزانية (Budget)
$cpo = 0; // سيتم تحديده لاحقا
$monthly_total_spend = array_sum(array_column($creative_perf, 'spend'));

// حساب ميزانية المسوق (Budget Data)
$budget_month_transfers = 0;
$budget_total_transfers = 0;
$budget_total_spent = 0;

$t_res1 = $conn->query("SELECT SUM(amount) as s FROM marketing_budgets WHERE marketer_code = '{$conn->real_escape_string($my_tracking_code)}' AND DATE_FORMAT(transfer_date, '%Y-%m') = '{$conn->real_escape_string($selected_month)}'");
if ($t_res1 && $row = $t_res1->fetch_assoc()) $budget_month_transfers = (float)$row['s'];

$t_res2 = $conn->query("SELECT SUM(amount) as s FROM marketing_budgets WHERE marketer_code = '{$conn->real_escape_string($my_tracking_code)}'");
if ($t_res2 && $row = $t_res2->fetch_assoc()) $budget_total_transfers = (float)$row['s'];

$t_res3 = $conn->query("SELECT SUM(lead_cost) as s FROM support_daily_leads WHERE marketer_code = '{$conn->real_escape_string($my_tracking_code)}'");
if ($t_res3 && $row = $t_res3->fetch_assoc()) $budget_total_spent = (float)$row['s'];

$budget_balance = $budget_total_transfers - $budget_total_spent;
$budget_carryover = $budget_balance - $budget_month_transfers + $monthly_total_spend;

$delivered_rate = ($confirmed > 0) ? round(($delivered / $confirmed) * 100, 1) : 0; 
$bundle_rate = ($delivered > 0) ? round(($bundle_delivered / $delivered) * 100, 1) : 0;

$min_delivered_required = 100; 

// حساب البونص بناءً على الشروط الجديدة
$bonus = 0;
$bonus_earned = false;
$bonus_reason = "";

if ($delivered >= $min_delivered_required) {
    $cpd = ($delivered > 0) ? ($cpo * $confirmed) / $delivered : 0;
    if ($delivered_rate >= 50 || $cpd <= 400) {
        // إذا لم يكن هناك إنفاق مسجل نفترض تحقق الشرط مؤقتاً
        if ($cpl <= 50 || ($cpl > 50 && $cpo <= 200) || $cpl == 0) {
            $bonus_earned = true;
            $single_bonus_rate = 10;
            $bundle_bonus_rate = ($bundle_rate >= 50) ? 25 : 15;
            
            $bonus = ($single_delivered * $single_bonus_rate) + ($bundle_delivered * $bundle_bonus_rate);
        } else {
            $bonus_reason = "لم يتم تحقيق شرط التكلفة (CPL/CPO).";
        }
    } else {
        $bonus_reason = "نسبة التسليم أقل من 50% وتكلفة المسلم تخطت 400ج.";
    }
} else {
    $bonus_reason = "لم يتم تحقيق الحد الأدنى للتسليمات ($min_delivered_required).";
}

// إعدادات الـ CPO
$cpo_green_cap = 200;
$cpo_yellow_cap = 280;

// تحديد لون الـ CPO
$cpo_color_class = 'text-green-500';
$cpo_bg_class = 'bg-green-500';
if ($cpo > $cpo_yellow_cap) {
    $cpo_color_class = 'text-red-500';
    $cpo_bg_class = 'bg-red-500';
} elseif ($cpo >= $cpo_green_cap && $cpo <= $cpo_yellow_cap) {
    $cpo_color_class = 'text-yellow-500';
    $cpo_bg_class = 'bg-yellow-500';
}

// Generate Marketer Tracking Code
$marketer_id = $_SESSION['admin_id'] ?? 0;
$marketer_fullname = trim($_SESSION['admin_fullname'] ?? 'Marketer');

// احسب رقم المسوق التسلسلي (للمسوقين فقط)
$marketer_seq = $marketer_id; // Default fallback
if (isset($conn) && $conn) {
    $seq_stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE (role = 'marketing' OR role = 'marketing_main') AND id <= ?");
    if ($seq_stmt) {
        $seq_stmt->bind_param("i", $marketer_id);
        $seq_stmt->execute();
        $seq_result = $seq_stmt->get_result();
        if ($seq_result && $row = $seq_result->fetch_row()) {
            $marketer_seq = $row[0];
        }
    }
}
// -- Automatic Schema Update for Marketing Dashboard --
try {
    $check_mc = $conn->query("SHOW COLUMNS FROM admins LIKE 'marketer_code'");
    if ($check_mc && $check_mc->num_rows == 0) {
        $conn->query("ALTER TABLE admins ADD COLUMN marketer_code VARCHAR(100) NULL AFTER role");
        $res = $conn->query("SELECT id, fullname, marketer_code FROM admins WHERE role IN ('marketing', 'marketing_main')");
        $seq = 1;
        while($row = $res->fetch_assoc()) {
            if (empty($row['marketer_code'])) {
                $fullname = trim($row['fullname']);
                $name_parts = explode(' ', $fullname);
                $first_initial = isset($name_parts[0]) ? mb_substr($name_parts[0], 0, 1, 'UTF-8') : 'M';
                $second_initial = isset($name_parts[1]) ? mb_substr($name_parts[1], 0, 1, 'UTF-8') : 'K';
                $code = strtoupper($first_initial . $second_initial . str_pad($seq, 2, '0', STR_PAD_LEFT));
                $stmt = $conn->prepare("UPDATE admins SET marketer_code = ? WHERE id = ?");
                $stmt->bind_param("si", $code, $row['id']);
                $stmt->execute();
            }
            $seq++;
        }
    }
    
    // Update support_daily_leads to include marketer_code
    $check_sdl_mc = $conn->query("SHOW COLUMNS FROM support_daily_leads LIKE 'marketer_code'");
    if ($check_sdl_mc && $check_sdl_mc->num_rows == 0) {
        $conn->query("ALTER TABLE support_daily_leads ADD COLUMN marketer_code VARCHAR(100) NULL AFTER product_code");
        // Update unique key to include marketer_code
        try {
            $conn->query("ALTER TABLE support_daily_leads DROP INDEX uq_date_product");
        } catch (Exception $e) {}
        $conn->query("ALTER TABLE support_daily_leads ADD UNIQUE KEY uq_date_product_marketer (date, product_code, marketer_code)");
    }
    
    // Create marketing_budgets table
    $conn->query("CREATE TABLE IF NOT EXISTS marketing_budgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        marketer_code VARCHAR(100) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        transfer_date DATE NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $check_cat = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");
    if ($check_cat && $check_cat->num_rows == 0) {
        $conn->query("ALTER TABLE products ADD COLUMN category VARCHAR(100) NULL AFTER name");
    }
} catch (Exception $e) {
    error_log("Marketing schema update error: " . $e->getMessage());
}
// ----------------------------------------------------

// Ensure marketer_code exists in session if missing
if (!isset($_SESSION['marketer_code'])) {
    $admin_id = $_SESSION['admin_id'];
    $mc_res = $conn->query("SELECT marketer_code FROM admins WHERE id = $admin_id");
    if ($mc_res && $mc_row = $mc_res->fetch_assoc()) {
        $_SESSION['marketer_code'] = $mc_row['marketer_code'];
    }
}

// Get tracking code
$my_tracking_code = $_SESSION['marketer_code'] ?? 'MK00';
$my_tracking_link = "https://digital-soldiers.com/miske.php/" . $my_tracking_code;
?>

<div class="container mx-auto p-6 pb-20">
    <!-- Tracking Link Banner -->
    <div class="bg-indigo-600 text-white p-6 rounded-2xl shadow-md mb-8 flex flex-col md:flex-row items-center justify-between">
        <div>
            <h2 class="text-xl font-bold flex items-center mb-2">
                <i class='bx bx-link text-3xl mr-2 ml-2'></i> الرابط الخاص بك لـ Landing Page (Miske)
            </h2>
            <p class="text-indigo-200 text-sm">استخدم هذا الرابط في حملاتك الإعلانية. سيتم تسجيل أي طلبات تأتي عبره باسمك تلقائياً كود المسوق: <strong><?= $my_tracking_code ?></strong>.</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-col items-center w-full md:w-auto">
            <div class="bg-indigo-800 px-4 py-3 rounded-lg text-indigo-100 font-mono text-sm mb-2 flex items-center justify-between w-full md:w-auto min-w-[300px]" dir="ltr">
                <span id="trackingLinkText"><?= $my_tracking_link ?></span>
                <button type="button" onclick="copyTrackingLink()" class="ml-4 bg-indigo-500 hover:bg-indigo-400 text-white p-1.5 rounded-md transition" title="نسخ الرابط">
                    <i class='bx bx-copy'></i>
                </button>
            </div>
            <span id="copySuccessMsg" class="text-xs text-emerald-300 font-bold opacity-0 transition-opacity">تم نسخ الرابط بنجاح!</span>
        </div>
    </div>
    <script>
    function copyTrackingLink() {
        const linkText = document.getElementById('trackingLinkText').innerText;
        navigator.clipboard.writeText(linkText).then(() => {
            const msg = document.getElementById('copySuccessMsg');
            msg.classList.remove('opacity-0');
            setTimeout(() => msg.classList.add('opacity-0'), 2000);
        });
    }
    </script>

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">
            <i class='bx bx-pie-chart-alt-2 mr-2 text-indigo-500'></i>
            <?= ($is_manager && !empty($view_agent_code)) ? "Marketer Dashboard ($view_agent_code)" : "Marketing Dashboard" ?>
        </h1>
        <div class="flex items-center gap-4">
            <?php if ($is_manager && !empty($view_agent_code)): ?>
            <a href="?page=marketing_main" class="inline-flex items-center text-sm font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-2 rounded-lg transition">
                <i class='bx bx-arrow-back mr-2'></i> Back to Overview
            </a>
            <?php endif; ?>
            <form method="GET" action="" class="flex items-center bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-100">
                <input type="hidden" name="page" value="marketing_main">
                <?php if(!empty($view_agent_code)): ?><input type="hidden" name="agent_code" value="<?= htmlspecialchars($view_agent_code) ?>"><?php endif; ?>
                <?php if(isset($_GET['day'])): ?><input type="hidden" name="day" value="<?= htmlspecialchars($_GET['day']) ?>"><?php endif; ?>
                <label for="monthFilter" class="text-sm text-slate-500 font-bold mr-2 ml-2">شهر:</label>
                <input type="month" id="monthFilter" name="month" value="<?= $selected_month ?>" onchange="this.form.submit()" class="border-none text-sm font-bold text-slate-700 bg-transparent focus:ring-0 cursor-pointer">
            </form>
        </div>
    </div>


    <!-- القسم العلوي: الإحصائيات (المربعات) -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        
        <!-- My Rank -->
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-4 shadow-lg text-white flex flex-col justify-center items-center relative overflow-hidden transform hover:-translate-y-1 transition duration-300">
            <div class="absolute -right-4 -top-4 opacity-20">
                <i class='bx bxs-award text-7xl'></i>
            </div>
            <p class="text-indigo-100 font-bold text-sm z-10 mb-1">My Rank</p>
            <p class="text-4xl font-black z-10"><?= htmlspecialchars($my_rank) ?></p>
        </div>

        <!-- Confirmed -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-col justify-center items-center hover:shadow-md transition duration-300">
            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center mb-2">
                <i class='bx bx-check-shield text-xl'></i>
            </div>
            <p class="text-slate-400 font-bold text-xs mb-1">Confirmed</p>
            <p class="text-2xl font-black text-slate-700"><?= number_format($confirmed) ?></p>
        </div>

        <!-- Delivered -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-col justify-center items-center hover:shadow-md transition duration-300">
            <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mb-2">
                <i class='bx bx-package text-xl'></i>
            </div>
            <p class="text-slate-400 font-bold text-xs mb-1">Delivered</p>
            <p class="text-2xl font-black text-slate-700"><?= number_format($delivered) ?></p>
        </div>

        <!-- Delivered Rate -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-col justify-center items-center hover:shadow-md transition duration-300">
            <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center mb-2">
                <i class='bx bx-line-chart text-xl'></i>
            </div>
            <p class="text-slate-400 font-bold text-xs mb-1">Delivered Rate</p>
            <p class="text-2xl font-black text-slate-700"><?= $delivered_rate ?>%</p>
        </div>

        <!-- Bonus -->
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-col justify-center items-center hover:shadow-md transition duration-300 relative overflow-hidden">
            <div class="w-10 h-10 rounded-full <?= $bonus_earned ? 'bg-rose-50 text-rose-500' : 'bg-slate-50 text-slate-400' ?> flex items-center justify-center mb-2">
                <i class='bx bx-wallet text-xl'></i>
            </div>
            <p class="text-slate-400 font-bold text-xs mb-1">Bonus</p>
            <p class="text-2xl font-black <?= $bonus_earned ? 'text-rose-600' : 'text-slate-400' ?>"><?= number_format($bonus, 2) ?> <span class="text-sm font-bold <?= $bonus_earned ? 'text-rose-400' : 'text-slate-300' ?>">ج.م</span></p>
            <?php if (!$bonus_earned): ?>
                <p class="text-[9px] text-red-500 mt-1 font-bold text-center leading-tight"><?= $bonus_reason ?></p>
            <?php endif; ?>
        </div>

    </div>

    <!-- القسم الأوسط: الرسوم البيانية -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Delivered VS Min -->
        <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-sm font-bold text-slate-600 mb-4 flex items-center">
                <i class='bx bx-bar-chart-alt-2 mr-2 text-indigo-500'></i> Delivered VS Min Target
            </h3>
            <div class="relative h-48 w-full">
                <canvas id="deliveredVsMinChart"></canvas>
            </div>
        </div>

        <!-- CPO vs Caps -->
        <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col">
            <h3 class="text-sm font-bold text-slate-600 mb-4 flex items-center">
                <i class='bx bx-money mr-2 <?= $cpo_color_class ?>'></i> CPO vs Caps
            </h3>
            
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="text-center mb-4">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Current CPO</p>
                    <p class="text-5xl font-black <?= $cpo_color_class ?>"><?= $cpo ?></p>
                </div>
                
                <!-- CPO Progress Bar -->
                <div class="w-full max-w-xs relative pt-6" dir="ltr">
                    <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden flex">
                        <!-- Green Zone -->
                        <div class="h-full bg-green-400" style="width: <?= min(100, ($cpo_green_cap / 350) * 100) ?>%;"></div>
                        <!-- Yellow Zone -->
                        <div class="h-full bg-yellow-400" style="width: <?= min(100, (($cpo_yellow_cap - $cpo_green_cap) / 350) * 100) ?>%;"></div>
                        <!-- Red Zone -->
                        <div class="h-full bg-red-400" style="flex: 1;"></div>
                    </div>
                    
                    <!-- Indicator Pin -->
                    <?php 
                        // حساب نسبة تواجد المؤشر (بحد أقصى 350 للرسم)
                        $pin_percent = min(100, ($cpo / 350) * 100); 
                    ?>
                    <div class="absolute flex flex-col items-center transition-all duration-1000 transform -translate-x-1/2" style="left: <?= $pin_percent ?>%; top: 0px;">
                        <div class="<?= $cpo_bg_class ?> text-white text-[10px] font-bold px-2 py-0.5 rounded shadow">
                            <?= $cpo ?>
                        </div>
                        <div class="w-0 h-0 border-l-[5px] border-r-[5px] border-t-[6px] border-l-transparent border-r-transparent border-t-<?= explode('-', $cpo_bg_class)[1] ?>-500"></div>
                    </div>
                    
                    <!-- Labels -->
                    <div class="relative h-4 mt-2">
                        <span class="absolute text-[10px] font-bold text-slate-400 transform -translate-x-1/2" style="left: 0%;">0</span>
                        <span class="absolute text-[10px] font-bold text-green-500 transform -translate-x-1/2" style="left: <?= min(100, ($cpo_green_cap / 350) * 100) ?>%;"><?= $cpo_green_cap ?></span>
                        <span class="absolute text-[10px] font-bold text-yellow-500 transform -translate-x-1/2" style="left: <?= min(100, ($cpo_yellow_cap / 350) * 100) ?>%;"><?= $cpo_yellow_cap ?></span>
                        <span class="absolute text-[10px] font-bold text-red-500 transform -translate-x-1/2" style="left: 100%;">350+</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivered Rate Gauge -->
        <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-sm font-bold text-slate-600 mb-4 flex items-center">
                <i class='bx bx-target-lock mr-2 text-emerald-500'></i> Delivered Rate
            </h3>
            <div class="relative h-48 w-full flex items-center justify-center">
                <canvas id="deliveredRateChart"></canvas>
                <!-- النسبة المئوية في المنتصف -->
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-4">
                    <span class="text-3xl font-black text-slate-700"><?= $delivered_rate ?>%</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Rate</span>
                </div>
            </div>
        </div>

    </div>
    
    <!-- مساحة لمحتويات الصفحة الإضافية لاحقاً -->
    <div class="mt-8 border-t border-slate-200 pt-8">
        
        <!-- قسم الميزانية (Budget) -->
        <div class="bg-white text-slate-800 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-6 relative overflow-hidden" dir="ltr">
            <h2 class="text-lg font-bold mb-6 flex items-center relative z-10 text-slate-800">
                <i class='bx bx-wallet text-indigo-500 mr-2'></i> Budget
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 relative z-10">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">Balance</p>
                    <p class="text-2xl font-black <?= $budget_balance < 0 ? 'text-red-500' : 'text-slate-800' ?>"><?= number_format($budget_balance) ?> <span class="text-xs font-bold text-slate-400 ml-1">ج.م</span></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">Spent</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format($monthly_total_spend) ?> <span class="text-xs font-bold text-slate-400 ml-1">ج.م</span></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">Transfers In</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format($budget_month_transfers) ?> <span class="text-xs font-bold text-slate-400 ml-1">ج.م</span></p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">Carryover</p>
                    <p class="text-2xl font-black <?= $budget_carryover < 0 ? 'text-red-500' : 'text-slate-800' ?>"><?= number_format($budget_carryover) ?> <span class="text-xs font-bold text-slate-400 ml-1">ج.م</span></p>
                </div>
            </div>
        </div>

        <!-- قسم الإنفاق الإعلاني اليومي (My Daily Ad Spend) -->
        <div class="bg-white text-slate-800 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-8 relative" dir="ltr">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h2 class="text-xl font-bold flex items-center text-slate-800">
                    <i class='bx bx-line-chart text-indigo-500 mr-2'></i> My Daily Ad Spend 
                </h2>
                <form method="GET" action="" class="bg-slate-50 rounded-lg px-4 py-2 border border-slate-200 text-sm font-bold text-slate-600 flex items-center">
                    <input type="hidden" name="page" value="marketing_main">
                    <?php if(!empty($view_agent_code)): ?><input type="hidden" name="agent_code" value="<?= htmlspecialchars($view_agent_code) ?>"><?php endif; ?>
                    <?php if(isset($_GET['month'])): ?><input type="hidden" name="month" value="<?= htmlspecialchars($_GET['month']) ?>"><?php endif; ?>
                    <i class='bx bx-calendar mr-2'></i>
                    <input type="date" name="day" value="<?= $selected_day ?>" onchange="this.form.submit()" class="bg-transparent border-none text-sm font-bold text-slate-700 focus:ring-0 cursor-pointer p-0">
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 mb-6 bg-white">
                <table class="w-full text-left border-collapse" id="adSpendTable">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="py-4 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider w-1/4">Source Code</th>
                            <th class="py-4 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider w-1/4">Product</th>
                            <th class="py-4 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider w-1/4">Category</th>
                            <th class="py-4 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider text-center w-32">Leads</th>
                            <th class="py-4 px-5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right w-40">Spend (ج.م)</th>
                        </tr>
                    </thead>
                    <tbody id="adSpendBody">
                        <?php
                        // استرجاع كل السورسات (products) التي لها مبيعات أو إنفاق في هذا اليوم للمسوق
                        $daily_spend_data = [];
                        $ds_q = $conn->query("SELECT product_code, lead_cost FROM support_daily_leads WHERE date = '{$conn->real_escape_string($selected_day)}' AND marketer_code = '{$conn->real_escape_string($my_tracking_code)}'");
                        if ($ds_q) {
                            while($drow = $ds_q->fetch_assoc()) {
                                $daily_spend_data[$drow['product_code']] = (float)$drow['lead_cost'];
                            }
                        }
                        
                        $daily_leads_q = $conn->query("
                            SELECT COALESCE(NULLIF(product_code, ''), 'Etala001') as pcode, COUNT(*) as lead_count
                            FROM support_orders 
                            WHERE marketing_agent = '{$conn->real_escape_string($my_tracking_code)}' 
                            AND DATE(created_at) = '{$conn->real_escape_string($selected_day)}'
                            GROUP BY pcode
                        ");
                        $daily_leads_data = [];
                        if ($daily_leads_q) {
                            while($lrow = $daily_leads_q->fetch_assoc()) {
                                $daily_leads_data[$lrow['pcode']] = (int)$lrow['lead_count'];
                            }
                        }
                        
                        $all_daily_codes = array_unique(array_merge(array_keys($daily_spend_data), array_keys($daily_leads_data)));
                        $total_daily_leads = 0;
                        $total_daily_spend = 0;
                        
                        if (empty($all_daily_codes)) {
                            echo '<tr id="noDataRow" class="border-b border-slate-100"><td colspan="5" class="py-4 px-5 text-center text-slate-400">لا توجد بيانات لهذا اليوم</td></tr>';
                        } else {
                            foreach ($all_daily_codes as $code) {
                                $c_leads = $daily_leads_data[$code] ?? 0;
                                $c_spend = $daily_spend_data[$code] ?? 0;
                                $total_daily_leads += $c_leads;
                                $total_daily_spend += $c_spend;
                                
                                $c_prod = $code;
                                $c_cat = 'General';
                                $cat_res2 = $conn->query("SELECT category FROM products WHERE name = '" . $conn->real_escape_string($code) . "' LIMIT 1");
                                if ($cat_res2 && $cat_row2 = $cat_res2->fetch_assoc()) {
                                    if (!empty($cat_row2['category'])) $c_cat = htmlspecialchars($cat_row2['category']);
                                }
                                ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition group" data-code="<?= htmlspecialchars($code) ?>">
                                    <td class="py-4 px-5 text-sm font-bold text-slate-800 flex items-center justify-between group">
                                        <?= htmlspecialchars($code) ?>
                                        <button type="button" onclick="removeRow(this)" class="text-slate-400 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                    <td class="py-4 px-5 text-sm text-slate-600"><?= htmlspecialchars($c_prod) ?></td>
                                    <td class="py-4 px-5 text-sm text-slate-600"><?= htmlspecialchars($c_cat) ?></td>
                                    <td class="py-4 px-5 text-sm font-bold text-slate-800 text-center"><?= $c_leads ?></td>
                                    <td class="py-4 px-5 text-right">
                                        <input type="number" min="0" value="<?= $c_spend ?>" class="spend-input bg-white border border-slate-200 text-slate-800 text-sm font-bold rounded-lg px-3 py-1.5 w-24 text-center focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition shadow-sm" oninput="updateTotals()" onkeydown="if(event.key === 'Enter') saveAdSpend()">
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 bg-slate-50">
                            <td colspan="3" class="py-4 px-5 text-sm font-bold text-slate-600">Total</td>
                            <td class="py-4 px-5 text-sm font-bold text-slate-800 text-center" id="totalLeads"><?= $total_daily_leads ?></td>
                            <td class="py-4 px-5 text-sm font-bold text-slate-800 text-right" id="totalSpendCol"><?= $total_daily_spend ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Add Code Section -->
            <div class="flex flex-col md:flex-row items-center gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <input type="text" id="newSourceCode" placeholder="e.g. Digital-Soldiers01" class="bg-white border border-slate-200 text-slate-800 text-sm rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 w-full md:w-64 placeholder-slate-400 transition shadow-sm">
                
                <select id="newProduct" class="bg-white border border-slate-200 text-slate-800 text-sm rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 w-full md:w-56 transition shadow-sm">
                    <option value="" disabled selected>Select product</option>
                    <?php
                    // جلب أسماء المنتجات (product codes) من شيتات الدعم كما تظهر في باقي التقارير
                    $prod_codes = [];
                    if (function_exists('get_support_product_codes_list')) {
                        $prod_codes = get_support_product_codes_list($conn);
                    } else {
                        $prod_codes_res = $conn->query("SELECT DISTINCT product_code FROM support_orders WHERE product_code IS NOT NULL AND product_code != '' ORDER BY product_code ASC");
                        if ($prod_codes_res) {
                            while($prow = $prod_codes_res->fetch_assoc()) {
                                $prod_codes[] = $prow['product_code'];
                            }
                        }
                    }
                    
                    foreach ($prod_codes as $pcode) {
                        $pname = htmlspecialchars($pcode);
                        $pcat = 'General';
                        
                        // محاولة جلب تصنيف المنتج.من جدول products إن وُجد
                        $cat_res = $conn->query("SELECT category FROM products WHERE name = '" . $conn->real_escape_string($pcode) . "' LIMIT 1");
                        if ($cat_res && $cat_row = $cat_res->fetch_assoc()) {
                            if (!empty($cat_row['category'])) {
                                $pcat = htmlspecialchars($cat_row['category']);
                            }
                        }
                        
                        echo "<option value=\"{$pname}|{$pcat}\">{$pname}</option>";
                    }
                    ?>
                </select>

                <button type="button" onclick="addSpendRow()" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-sm font-bold py-2.5 px-5 rounded-lg transition shadow-sm w-full md:w-auto flex items-center justify-center">
                    <i class='bx bx-plus mr-2'></i> Add Code
                </button>
            </div>

            <!-- Footer Save -->
            <div class="mt-8 flex flex-col md:flex-row justify-between items-center border-t border-slate-200 pt-6 gap-4">
                <div class="text-sm font-bold text-slate-600">
                    Total: <span id="totalSpendLabel" class="text-slate-800 text-base"><?= $total_daily_spend ?? 0 ?></span> <span class="text-slate-400 ml-1">ج.م</span>
                </div>
                <button type="button" onclick="saveAdSpend()" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2.5 px-8 rounded-lg transition shadow-lg shadow-emerald-500/20 w-full md:w-auto">
                    Save Spend
                </button>
            </div>
        </div>

        <!-- قسم الأداء الإبداعي (Creative Performance) -->
        <div class="bg-white text-slate-800 rounded-3xl p-6 md:p-8 shadow-sm border border-slate-100 mb-8 relative" dir="ltr">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h2 class="text-xl font-bold flex items-center text-slate-800">
                    <i class='bx bx-table text-indigo-500 mr-2'></i> Creative Performance
                </h2>
                <div class="flex gap-2">
                    <div class="bg-slate-50 rounded-lg px-3 py-1.5 border border-slate-200 text-sm font-bold text-slate-600 flex items-center">
                        <i class='bx bx-calendar mr-2'></i> <?= date('m/01/Y') ?> to <?= date('m/d/Y') ?>
                    </div>
                    <select id="signalFilter" onchange="filterSignals()" class="bg-slate-50 border border-slate-200 text-slate-600 text-sm font-bold rounded-lg px-3 py-1.5 focus:outline-none cursor-pointer">
                        <option value="all">All Signals</option>
                        <option value="Scale">Green (Scale)</option>
                        <option value="Review">Yellow (Review)</option>
                        <option value="Stop">Red (Stop)</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Source Code</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Raw</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Confirmed</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Conf%</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Delivered</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Bundle%</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Spend</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">CPO</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">CPD</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Signal</th>
                            <th class="py-3 px-4 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Creative Link</th>
                        </tr>
                    </thead>
                    <tbody id="creativePerformanceBody">
                        <?php if (empty($creative_perf)): ?>
                        <tr>
                            <td colspan="11" class="px-6 py-8 text-center text-slate-400">لا توجد بيانات لهذه الفترة</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($creative_perf as $cp): 
                            $sig_col = 'bg-slate-100 text-slate-600 border border-slate-200';
                            if ($cp['signal'] === 'green') { $sig_col = 'bg-emerald-100 text-emerald-700 border border-emerald-200'; $cp['signal'] = 'Scale'; }
                            elseif ($cp['signal'] === 'yellow') { $sig_col = 'bg-yellow-100 text-yellow-700 border border-yellow-200'; $cp['signal'] = 'Review'; }
                            elseif ($cp['signal'] === 'red') { $sig_col = 'bg-red-100 text-red-700 border border-red-200'; $cp['signal'] = 'Stop'; }
                            else { $cp['signal'] = 'Low Data'; }
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition creative-row" data-signal="<?= $cp['signal'] ?>">
                            <td class="py-3 px-4 text-sm font-bold text-slate-800"><?= htmlspecialchars($cp['source_code']) ?></td>
                            <td class="py-3 px-4 text-sm font-medium text-slate-600 text-right"><?= $cp['raw'] ?></td>
                            <td class="py-3 px-4 text-sm font-bold text-indigo-600 text-right"><?= $cp['confirmed'] ?></td>
                            <td class="py-3 px-4 text-sm font-medium text-slate-600 text-right"><?= $cp['conf_percent'] ?>%</td>
                            <td class="py-3 px-4 text-sm font-bold text-emerald-600 text-right"><?= $cp['delivered'] ?></td>
                            <td class="py-3 px-4 text-sm font-medium text-slate-600 text-right"><?= $cp['bundle_percent'] ?>%</td>
                            <td class="py-3 px-4 text-sm font-bold text-slate-800 text-right"><?= number_format($cp['spend']) ?></td>
                            <td class="py-3 px-4 text-sm font-medium text-slate-600 text-right"><?= $cp['cpo'] ?></td>
                            <td class="py-3 px-4 text-sm font-bold text-rose-600 text-right"><?= $cp['cpd'] ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md <?= $sig_col ?>"><?= $cp['signal'] ?></span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <input type="text" class="creative-link-input px-2 py-1 border border-slate-200 rounded text-xs w-32 focus:outline-none focus:border-indigo-500" 
                                        placeholder="Paste link..." 
                                        data-code="<?= htmlspecialchars($cp['source_code']) ?>"
                                        value="<?= htmlspecialchars($cp['creative_link']) ?>">
                                    <a href="<?= htmlspecialchars($cp['creative_link']) ?>" target="_blank" class="text-indigo-500 hover:text-indigo-700 <?= empty($cp['creative_link']) ? 'hidden' : '' ?>" title="Open Link">
                                        <i class='bx bx-link-external'></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Delivered VS Min Chart (Bar Chart)
    const ctxMin = document.getElementById('deliveredVsMinChart').getContext('2d');
    new Chart(ctxMin, {
        type: 'bar',
        data: {
            labels: ['Minimum Required', 'Actual Delivered'],
            datasets: [{
                label: 'Orders',
                data: [<?= $min_delivered_required ?>, <?= $delivered ?>],
                backgroundColor: [
                    'rgba(203, 213, 225, 0.5)', // Slate for Min
                    'rgba(16, 185, 129, 0.8)'   // Emerald for Delivered
                ],
                borderColor: [
                    'rgb(148, 163, 184)',
                    'rgb(5, 150, 105)'
                ],
                borderWidth: 2,
                borderRadius: 8,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' Orders';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [4, 4], color: '#f1f5f9' },
                    border: { display: false }
                },
                x: {
                    grid: { display: false },
                    border: { display: false }
                }
            }
        }
    });

    // 2. Delivered Rate Chart (Doughnut/Gauge Chart)
    const ctxRate = document.getElementById('deliveredRateChart').getContext('2d');
    const deliveredRate = <?= $delivered_rate ?>;
    const remainingRate = 100 - deliveredRate;
    
    // Determine color based on rate (e.g. >60% is good)
    let rateColor = '#10b981'; // emerald
    if(deliveredRate < 40) rateColor = '#ef4444'; // red
    else if (deliveredRate < 60) rateColor = '#f59e0b'; // amber

    new Chart(ctxRate, {
        type: 'doughnut',
        data: {
            labels: ['Delivered', 'Other'],
            datasets: [{
                data: [deliveredRate, remainingRate],
                backgroundColor: [rateColor, '#f1f5f9'],
                borderWidth: 0,
                cutout: '75%',
                borderRadius: [10, 0] // Rounded edges for the filled part
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            rotation: -90,
            circumference: 180, // Half circle gauge
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            },
            layout: {
                padding: {
                    bottom: 10
                }
            }
        }
    });
    // 3. Ad Spend Table Functionality
    window.updateTotals = function() {
        let totalSpend = 0;
        document.querySelectorAll('.spend-input').forEach(input => {
            totalSpend += parseFloat(input.value) || 0;
        });
        document.getElementById('totalSpendCol').innerText = totalSpend;
        document.getElementById('totalSpendLabel').innerText = totalSpend;
    };

    window.removeRow = function(btn) {
        btn.closest('tr').remove();
        updateTotals();
    };

    window.addSpendRow = function() {
        const codeInput = document.getElementById('newSourceCode');
        const prodSelect = document.getElementById('newProduct');
        
        const code = codeInput.value.trim();
        const prodVal = prodSelect.value;
        
        if (!code || !prodVal) {
            alert("Please enter a source code and select a product.");
            return;
        }
        
        const [product, category] = prodVal.split('|');
        const tbody = document.getElementById('adSpendBody');
        
        const noDataRow = document.getElementById('noDataRow');
        if (noDataRow) {
            noDataRow.remove();
        }

        const tr = document.createElement('tr');
        tr.className = "border-b border-slate-100 hover:bg-slate-50/50 transition group";
        tr.setAttribute('data-code', code);
        
        tr.innerHTML = `
            <td class="py-4 px-5 text-sm font-bold text-slate-800 flex items-center justify-between group">
                ${code} 
                <button type="button" onclick="removeRow(this)" class="text-slate-400 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                    <i class='bx bx-trash'></i>
                </button>
            </td>
            <td class="py-4 px-5 text-sm text-slate-600">${product}</td>
            <td class="py-4 px-5 text-sm text-slate-600">${category}</td>
            <td class="py-4 px-5 text-sm font-bold text-slate-800 text-center">0</td>
            <td class="py-4 px-5 text-right">
                <input type="number" min="0" value="0" class="spend-input bg-white border border-slate-200 text-slate-800 text-sm font-bold rounded-lg px-3 py-1.5 w-24 text-center focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition shadow-sm" oninput="updateTotals()" onkeydown="if(event.key === 'Enter') saveAdSpend()">
            </td>
        `;
        
        tbody.appendChild(tr);
        codeInput.value = '';
        prodSelect.selectedIndex = 0;
        updateTotals();
    };

    window.saveAdSpend = function() {
        const rows = document.querySelectorAll('#adSpendBody tr[data-code]');
        const spends = [];
        rows.forEach(row => {
            const code = row.getAttribute('data-code');
            const spendInput = row.querySelector('.spend-input');
            if (code && spendInput) {
                spends.push({
                    product_code: code,
                    marketer_code: '<?= $my_tracking_code ?>',
                    cost: spendInput.value || 0
                });
            }
        });
        
        fetch('ajax_save_ad_spend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'date': '<?= $selected_day ?>',
                'spends': JSON.stringify(spends)
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('تم حفظ المصروفات بنجاح!');
                window.location.reload();
            } else {
                alert('خطأ أثناء الحفظ: ' + (data.error || 'غير معروف'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('حدث خطأ في الاتصال بالخادم');
        });
    };

    // Save Creative Link AJAX
    document.querySelectorAll('.creative-link-input').forEach(input => {
        input.addEventListener('change', function() {
            const pcode = this.getAttribute('data-code');
            const link = this.value.trim();
            const aTag = this.nextElementSibling;
            
            fetch('ajax_save_creative_link.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    'product_code': pcode,
                    'link': link
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    this.classList.add('border-emerald-500', 'bg-emerald-50');
                    setTimeout(() => this.classList.remove('border-emerald-500', 'bg-emerald-50'), 1500);
                    if (link) {
                        aTag.href = link;
                        aTag.classList.remove('hidden');
                    } else {
                        aTag.href = '#';
                        aTag.classList.add('hidden');
                    }
                } else {
                    alert('Error saving link: ' + (data.error || 'Unknown'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection error');
            });
        });
    });
});

    // Filter Signals
    function filterSignals() {
        const selected = document.getElementById('signalFilter').value;
        const rows = document.querySelectorAll('.creative-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const signal = row.getAttribute('data-signal');
            if (selected === 'all' || signal === selected) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Check if there are no visible rows
        const tbody = document.getElementById('creativePerformanceBody');
        let noDataRow = document.getElementById('noDataRow');
        
        if (visibleCount === 0 && rows.length > 0) {
            if (!noDataRow) {
                noDataRow = document.createElement('tr');
                noDataRow.id = 'noDataRow';
                noDataRow.innerHTML = '<td colspan="11" class="px-6 py-8 text-center text-slate-400 font-medium">لا توجد بيانات لهذه الإشارة</td>';
                tbody.appendChild(noDataRow);
            } else {
                noDataRow.style.display = '';
            }
        } else if (noDataRow) {
            noDataRow.style.display = 'none';
        }
    }
</script>
