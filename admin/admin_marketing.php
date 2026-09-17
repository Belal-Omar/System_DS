<?php
// admin_marketing.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$range = $_GET['range'] ?? 'month';
$selected_month = $_GET['month'] ?? date('Y-m');

$start_date = "";
$end_date = "";

switch ($range) {
    case 'today': $start_date = date('Y-m-d'); $end_date = date('Y-m-d'); break;
    case 'yesterday': $start_date = date('Y-m-d', strtotime('-1 day')); $end_date = date('Y-m-d', strtotime('-1 day')); break;
    case 'week': $start_date = date('Y-m-d', strtotime('-7 days')); $end_date = date('Y-m-d'); break;
    case '3months': $start_date = date('Y-m-d', strtotime('-3 months')); $end_date = date('Y-m-d'); break;
    case '6months': $start_date = date('Y-m-d', strtotime('-6 months')); $end_date = date('Y-m-d'); break;
    case 'year': $start_date = date('Y-m-d', strtotime('-1 year')); $end_date = date('Y-m-d'); break;
    case 'month':
    default:
        $start_date = date('Y-m-01', strtotime($selected_month . "-01"));
        $end_date = date('Y-m-t', strtotime($selected_month . "-01"));
        $range = 'month';
        break;
}

$m = calculate_support_marketing_metrics($conn, $start_date, $end_date);
$creative_perf = calculate_creative_performance($conn, $start_date, $end_date);

$total_leads = $m['total_leads'];
$confirmed_count = $m['confirmed_count'];
$delivered_count = $m['delivered_count'];
$cancelled_count = $m['cancelled_count'];
$returned_count = $m['returned_count'];
$delivery_from_confirmed = $m['delivery_from_confirmed'];
$confirmation_rate = $m['confirmation_rate'];
$cost_per_lead = $m['cost_per_lead'];
$cost_per_order = $m['cost_per_order'];
$cancellation_rate = $m['cancellation_rate'];
$return_rate = $m['return_rate'];
$status_data = $m['status_data'];
$status_chart_denominator = max($total_leads, array_sum(array_column($status_data, 'count')));
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-7xl mx-auto px-4 py-8 space-y-8 font-sans">
    
    <div class="flex flex-col md:flex-row justify-between items-center gap-6 bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl mr-4 shadow-lg shadow-indigo-100"><i class='bx bx-bullseye'></i></span>
                تحليلات التسويق (Marketing Insights)
            </h1>
            <p class="text-slate-500 mt-1 font-medium italic"><i class='bx bx-calendar-check mr-1'></i> تحليل الفترة: <span class="text-indigo-600"><?= $start_date ?></span> ⮕ <span class="text-indigo-600"><?= $end_date ?></span></p>
            <?php if ($m['total_sheet_leads'] > 0): ?>
                <p class="text-xs text-slate-400 mt-1">إجمالي الأسماء من شيتات الدعم: <strong class="text-indigo-500"><?= $m['total_sheet_leads'] ?></strong> — محفوظ في النظام: <strong><?= $m['total_orders_saved'] ?></strong></p>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="flex items-center gap-2 bg-slate-100 p-2 rounded-2xl border border-slate-200">
            <input type="hidden" name="page" value="marketing">
            <select name="range" onchange="this.form.submit()" class="bg-transparent font-bold text-slate-700 px-3 focus:outline-none cursor-pointer">
                <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>الشهر المختار</option>
                <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>اليوم</option>
                <option value="yesterday" <?= $range == 'yesterday' ? 'selected' : '' ?>>أمس</option>
                <option value="week" <?= $range == 'week' ? 'selected' : '' ?>>آخر 7 أيام</option>
                <option value="3months" <?= $range == '3months' ? 'selected' : '' ?>>آخر 3 شهور</option>
                <option value="6months" <?= $range == '6months' ? 'selected' : '' ?>>آخر 6 شهور</option>
                <option value="year" <?= $range == 'year' ? 'selected' : '' ?>>آخر سنة</option>
            </select>
            <?php if($range == 'month'): ?>
                <input type="month" name="month" value="<?= $selected_month ?>" onchange="this.form.submit()" class="bg-white border-none rounded-xl px-2 py-1 font-bold text-indigo-600">
            <?php endif; ?>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <p class="text-slate-400 text-xs font-bold uppercase mb-2">نسبة التسليم من المؤكد</p>
            <h3 class="text-3xl font-black text-indigo-600"><?= $delivery_from_confirmed ?>%</h3>
            <p class="text-xs text-slate-500 mt-2 font-medium">مُسلّم: <?= $delivered_count ?> / مؤكد: <?= $confirmed_count ?></p>
        </div>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <p class="text-slate-400 text-xs font-bold uppercase mb-2">تكلفة الأوردر المؤكد (CPO)</p>
            <h3 class="text-3xl font-black text-rose-600"><?= number_format($cost_per_order, 2) ?> <span class="text-sm font-normal">دل</span></h3>
            <p class="text-xs text-slate-500 mt-2 font-medium">إجمالي الصرف ÷ التاكيدات</p>
        </div>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <p class="text-slate-400 text-xs font-bold uppercase mb-2">نسبة التأكيد العامة</p>
            <h3 class="text-3xl font-black text-emerald-600"><?= $confirmation_rate ?>%</h3>
            <p class="text-xs text-slate-500 mt-2 font-medium">مؤكد: <?= $confirmed_count ?> / إجمالي: <?= $total_leads ?></p>
        </div>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <p class="text-slate-400 text-xs font-bold uppercase mb-2">متوسط سعر الليد (CPL)</p>
            <h3 class="text-3xl font-black text-slate-800"><?= number_format($cost_per_lead, 2) ?> <span class="text-sm font-normal">دل</span></h3>
            <p class="text-xs text-slate-500 mt-2 font-medium">إجمالي الصرف ÷ إجمالي الليدات</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-xl font-bold mb-6 text-slate-700 flex items-center">
                <i class='bx bx-chart mr-2 text-indigo-500'></i> توزيع الحالات بيانيًا
            </h3>
            <div class="relative" style="height: 300px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="bg-slate-900 text-white p-8 rounded-3xl shadow-xl border border-slate-800">
            <h3 class="text-xl font-bold mb-6 text-indigo-400 flex items-center">
                <i class='bx bx-list-check mr-2'></i> تحليل حالات الشيت (بالعربي)
            </h3>
            <div class="space-y-4 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                <?php 
                usort($status_data, function($a, $b) { return $b['count'] - $a['count']; });
                foreach ($status_data as $item): 
                    $percent = ($status_chart_denominator > 0) ? ($item['count'] / $status_chart_denominator) * 100 : 0;
                ?>
                <div class="flex items-center justify-between group">
                    <div class="flex items-center gap-3 w-1/2">
                        <div class="w-3 h-3 rounded-full" style="background-color: <?= $item['color'] ?>"></div>
                        <span class="text-sm font-bold text-slate-200"><?= htmlspecialchars($item['ar']) ?></span>
                    </div>
                    <div class="flex items-center gap-4 w-1/2 justify-end">
                        <span class="text-xs text-slate-500"><?= $item['count'] ?> أوردر</span>
                        <div class="w-20 bg-slate-800 h-1.5 rounded-full overflow-hidden">
                            <div class="h-full" style="width: <?= $percent ?>%; background-color: <?= $item['color'] ?>"></div>
                        </div>
                        <span class="text-sm font-black w-10 text-right"><?= round($percent, 1) ?>%</span>
                    </div>
                </div>
                <div class="h-px bg-slate-800"></div>
                <?php endforeach; ?>
                <?php if (empty($status_data)): ?>
                    <p class="text-slate-500 text-sm text-center py-8">لا توجد حالات مسجّلة في هذه الفترة</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-rose-50 p-6 rounded-3xl border border-rose-100 flex items-center justify-between">
            <div><p class="text-rose-600 text-sm font-bold">نسبة الإلغاء</p><h4 class="text-2xl font-black text-rose-800"><?= $cancellation_rate ?>%</h4></div>
            <div class="text-rose-200 text-5xl opacity-50"><i class='bx bx-x-circle'></i></div>
        </div>
        <div class="bg-amber-50 p-6 rounded-3xl border border-amber-100 flex items-center justify-between">
            <div><p class="text-amber-600 text-sm font-bold">نسبة المرتجع</p><h4 class="text-2xl font-black text-amber-800"><?= $return_rate ?>%</h4></div>
            <div class="text-amber-200 text-5xl opacity-50"><i class='bx bx-undo'></i></div>
        </div>
    </div>

    <!-- Creative Performance Section -->
    <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 overflow-x-auto">
        <h3 class="text-xl font-bold mb-6 text-slate-700 flex items-center">
            <i class='bx bx-rocket mr-2 text-indigo-500'></i> أداء المنتجات (Creative Performance)
        </h3>
        <table class="min-w-full text-sm text-left rtl:text-right text-slate-500 whitespace-nowrap">
            <thead class="text-xs text-slate-700 uppercase bg-slate-50">
                <tr>
                    <th class="px-6 py-3 rounded-tr-lg">Source Code</th>
                    <th class="px-6 py-3">Raw</th>
                    <th class="px-6 py-3">Confirmed</th>
                    <th class="px-6 py-3">Conf%</th>
                    <th class="px-6 py-3">Delivered</th>
                    <th class="px-6 py-3">Bundle%</th>
                    <th class="px-6 py-3">Spend</th>
                    <th class="px-6 py-3">CPO</th>
                    <th class="px-6 py-3">CPD</th>
                    <th class="px-6 py-3 text-center">Signal</th>
                    <th class="px-6 py-3 rounded-tl-lg">Creative Link</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($creative_perf)): ?>
                <tr>
                    <td colspan="11" class="px-6 py-8 text-center text-slate-400">لا توجد بيانات لهذه الفترة</td>
                </tr>
                <?php else: ?>
                <?php foreach ($creative_perf as $row): 
                    $signal_bg = '#e2e8f0'; // gray
                    $signal_icon = 'bx-minus';
                    if ($row['signal'] === 'green') { $signal_bg = '#10b981'; $signal_icon = 'bx-check'; }
                    elseif ($row['signal'] === 'yellow') { $signal_bg = '#f59e0b'; $signal_icon = 'bx-error-circle'; }
                    elseif ($row['signal'] === 'red') { $signal_bg = '#ef4444'; $signal_icon = 'bx-x'; }
                ?>
                <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($row['source_code']) ?></td>
                    <td class="px-6 py-4"><?= $row['raw'] ?></td>
                    <td class="px-6 py-4 text-indigo-600 font-semibold"><?= $row['confirmed'] ?></td>
                    <td class="px-6 py-4"><?= $row['conf_percent'] ?>%</td>
                    <td class="px-6 py-4 text-emerald-600 font-semibold"><?= $row['delivered'] ?></td>
                    <td class="px-6 py-4"><?= $row['bundle_percent'] ?>%</td>
                    <td class="px-6 py-4 font-mono font-bold">$<?= number_format($row['spend'], 2) ?></td>
                    <td class="px-6 py-4 font-mono">$<?= number_format($row['cpo'], 2) ?></td>
                    <td class="px-6 py-4 font-mono">$<?= number_format($row['cpd'], 2) ?></td>
                    <td class="px-6 py-4 text-center">
                        <div class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white" style="background-color: <?= $signal_bg ?>;">
                            <i class='bx <?= $signal_icon ?> text-xl'></i>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <input type="text" class="creative-link-input px-3 py-1.5 border border-slate-200 rounded-lg text-xs w-48 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" 
                                placeholder="Paste link..." 
                                data-code="<?= htmlspecialchars($row['source_code']) ?>"
                                value="<?= htmlspecialchars($row['creative_link']) ?>">
                            <a href="<?= htmlspecialchars($row['creative_link']) ?>" target="_blank" class="text-indigo-500 hover:text-indigo-700 p-1 <?= empty($row['creative_link']) ? 'hidden' : '' ?>" title="Open Link">
                                <i class='bx bx-link-external text-lg'></i>
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

<style>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    const statusData = <?= json_encode($status_data) ?>;
    
    if (statusData.length === 0) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.ar),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: statusData.map(item => item.color),
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#475569',
                        font: { family: 'Cairo', size: 10 },
                        usePointStyle: true,
                        padding: 15
                    }
                }
            },
            cutout: '75%'
        }
    });

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
</script>
<?php include 'footer.php'; ?>
