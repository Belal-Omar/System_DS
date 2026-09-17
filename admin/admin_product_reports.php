<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'helpers.php';
/** @var mysqli $conn */
// Check permissions
$sessionRole = $_SESSION['admin_role'] ?? '';
$sessionAllowedRaw = $_SESSION['admin_allowed_pages'] ?? null;
if ($sessionRole !== 'super_admin' && !admin_can_access_page('product_reports', $sessionRole, $sessionAllowedRaw)) {
    echo "<div class='p-8'><div class='bg-red-100 text-red-700 p-4 rounded-xl font-bold'>ليس مصرح لك بالدخول</div></div>";
    exit;
}

// Fetch all product codes
$products = get_support_product_codes_list($conn);

// Date filter processing
$period = $_GET['period'] ?? 'all';
$date_filter_sql = "";
if ($period === 'day') {
    $date_filter_sql = " AND DATE(created_at) = CURDATE() ";
} elseif ($period === 'week') {
    $date_filter_sql = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
} elseif ($period === 'month') {
    $date_filter_sql = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) ";
} elseif ($period === '3months') {
    $date_filter_sql = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) ";
} elseif ($period === '6months') {
    $date_filter_sql = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) ";
} elseif ($period === 'year') {
    $date_filter_sql = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) ";
}

$city_product = $_GET['city_product'] ?? 'all';
$city_product_filter = "";
if ($city_product !== 'all') {
    $city_product_filter = " AND product_code = '" . $conn->real_escape_string($city_product) . "' ";
}

// Prepare data for each product
$product_data = [];

// Calculate overall metrics
$sql_metrics = "
    SELECT 
        product_code,
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status IN ('delivered', 'order_delivered', 'received', 'تم الاستلام', 'تم التسليم') THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN order_status IN ('cancelled', 'order_cancelled', 'returned') THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN order_status NOT IN ('delivered', 'order_delivered', 'received', 'تم الاستلام', 'تم التسليم', 'cancelled', 'order_cancelled', 'returned') THEN 1 ELSE 0 END) as pending
    FROM support_orders 
    WHERE product_code IS NOT NULL AND product_code != '' $date_filter_sql
    GROUP BY product_code
";
$res_metrics = $conn->query($sql_metrics);
if ($res_metrics) {
    while ($row = $res_metrics->fetch_assoc()) {
        $p = $row['product_code'];
        $product_data[$p] = [
            'total' => (int)$row['total_orders'],
            'delivered' => (int)$row['delivered'],
            'cancelled' => (int)$row['cancelled'],
            'pending' => (int)$row['pending'],
            'top_cities' => []
        ];
    }
}

// City statistics per product
$sql_cities = "
    SELECT product_code, governorate as shipping_city, COUNT(*) as city_count
    FROM support_orders
    WHERE product_code IS NOT NULL AND product_code != '' AND governorate IS NOT NULL AND governorate != '' $date_filter_sql
    GROUP BY product_code, governorate
";
$res_cities = $conn->query($sql_cities);
$city_counts = [];
if ($res_cities) {
    while ($row = $res_cities->fetch_assoc()) {
        $p = $row['product_code'];
        $city = $row['shipping_city'];
        $c = (int)$row['city_count'];
        if (!isset($city_counts[$p])) $city_counts[$p] = [];
        $city_counts[$p][$city] = $c;
    }
}

// Overall Top 5 Cities (for the main chart)
$sql_overall_cities = "
    SELECT governorate as shipping_city, COUNT(*) as city_count
    FROM support_orders
    WHERE product_code IS NOT NULL AND product_code != '' AND governorate IS NOT NULL AND governorate != '' $date_filter_sql $city_product_filter
    GROUP BY governorate
    ORDER BY city_count DESC
    LIMIT 5
";
$res_overall_cities = $conn->query($sql_overall_cities);
$overall_cities = [];
if ($res_overall_cities) {
    while ($row = $res_overall_cities->fetch_assoc()) {
        $overall_cities[$row['shipping_city']] = (int)$row['city_count'];
    }
}

foreach ($city_counts as $p => $cities) {
    arsort($cities); // Sort descending by count
    $top3 = array_slice($cities, 0, 3, true); // Get top 3
    if (isset($product_data[$p])) {
        $product_data[$p]['top_cities'] = $top3;
    }
}

// Prepare Chart Data
$chart_labels = [];
$chart_delivered = [];
$chart_cancelled = [];
$chart_pending = [];

foreach ($products as $p) {
    if (isset($product_data[$p])) {
        $chart_labels[] = $p;
        $chart_delivered[] = $product_data[$p]['delivered'];
        $chart_cancelled[] = $product_data[$p]['cancelled'];
        $chart_pending[] = $product_data[$p]['pending'];
    }
}

// Overall Stats
$overall_delivered = array_sum($chart_delivered);
$overall_cancelled = array_sum($chart_cancelled);
$overall_pending = array_sum($chart_pending);

$cities_labels = array_keys($overall_cities);
$cities_data = array_values($overall_cities);

?>

<div class="px-4 py-8 font-sans" dir="rtl">
    <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
        <div class="flex items-center">
            <i class='bx bx-bar-chart-alt-2 text-indigo-600 mr-3 text-4xl'></i>
            <div>
                <h2 class="text-3xl font-extrabold text-slate-800">تقارير المنتجات</h2>
                <p class="text-sm text-slate-500 mt-1">تحليل شامل لنسب التسليمات والإلغاءات وأكثر المدن طلباً لكل منتج.</p>
            </div>
        </div>
        
        <form method="GET" action="admin_panel.php" class="flex items-center gap-3 bg-white p-3 rounded-lg shadow-sm border border-slate-200">
            <input type="hidden" name="page" value="product_reports">
            
            <div class="flex flex-col">
                <label class="text-xs text-slate-500 mb-1">المنتج (للمدن)</label>
                <select name="city_product" class="border rounded px-3 py-1.5 text-sm focus:ring-indigo-500" onchange="this.form.submit()">
                    <option value="all" <?= $city_product === 'all' ? 'selected' : '' ?>>إجمالي جميع المنتجات</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $city_product === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex flex-col">
                <label class="text-xs text-slate-500 mb-1">المدة الزمنية</label>
                <select name="period" class="border rounded px-3 py-1.5 text-sm focus:ring-indigo-500" onchange="this.form.submit()">
                    <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>كل الأوقات</option>
                    <option value="day" <?= $period === 'day' ? 'selected' : '' ?>>اليوم</option>
                    <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>آخر 7 أيام</option>
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>آخر شهر</option>
                    <option value="3months" <?= $period === '3months' ? 'selected' : '' ?>>آخر 3 شهور</option>
                    <option value="6months" <?= $period === '6months' ? 'selected' : '' ?>>آخر 6 شهور</option>
                    <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>آخر سنة</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Chart Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 lg:col-span-2">
            <h3 class="text-lg font-bold text-slate-700 mb-4"><i class='bx bx-bar-chart text-indigo-500 mr-2'></i> أداء المنتجات بالتفصيل</h3>
            <div class="relative h-80 w-full">
                <canvas id="productsChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-lg font-bold text-slate-700 mb-4"><i class='bx bx-pie-chart-alt-2 text-indigo-500 mr-2'></i> إجمالي حالات الطلبات</h3>
            <div class="relative h-80 w-full flex items-center justify-center">
                <canvas id="overallStatusChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
        <h3 class="text-lg font-bold text-slate-700 mb-4"><i class='bx bxs-city text-indigo-500 mr-2'></i> أكثر المدن طلباً (إجمالي جميع المنتجات)</h3>
        <div class="relative h-72 w-full">
            <canvas id="topCitiesChart"></canvas>
        </div>
    </div>

    <!-- Product Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($products as $code): ?>
            <?php 
            $data = $product_data[$code] ?? ['total' => 0, 'delivered' => 0, 'cancelled' => 0, 'pending' => 0, 'top_cities' => []];
            $t = max(1, $data['total']); // prevent division by zero
            $p_del = round(($data['delivered'] / $t) * 100, 1);
            $p_can = round(($data['cancelled'] / $t) * 100, 1);
            $p_pen = round(($data['pending'] / $t) * 100, 1);
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="bg-gradient-to-r from-indigo-50 to-white p-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-lg font-extrabold text-indigo-700">🛒 <?= htmlspecialchars($code) ?></h3>
                    <span class="bg-indigo-100 text-indigo-800 text-xs font-bold px-2 py-1 rounded-full"><?= $data['total'] ?> طلب</span>
                </div>
                
                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-center">
                            <p class="text-xs text-slate-500 font-bold mb-1">تسليم</p>
                            <p class="text-emerald-600 font-extrabold text-lg" dir="ltr"><?= $p_del ?>%</p>
                            <p class="text-xs text-slate-400">(<?= $data['delivered'] ?>)</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-slate-500 font-bold mb-1">ملغي/مرتجع</p>
                            <p class="text-red-500 font-extrabold text-lg" dir="ltr"><?= $p_can ?>%</p>
                            <p class="text-xs text-slate-400">(<?= $data['cancelled'] ?>)</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-slate-500 font-bold mb-1">قيد التنفيذ</p>
                            <p class="text-amber-500 font-extrabold text-lg" dir="ltr"><?= $p_pen ?>%</p>
                            <p class="text-xs text-slate-400">(<?= $data['pending'] ?>)</p>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 rounded-full h-2.5 mb-6 flex overflow-hidden">
                        <div class="bg-emerald-500 h-2.5" style="width: <?= $p_del ?>%"></div>
                        <div class="bg-amber-400 h-2.5" style="width: <?= $p_pen ?>%"></div>
                        <div class="bg-red-500 h-2.5" style="width: <?= $p_can ?>%"></div>
                    </div>

                    <!-- Top Cities -->
                    <div>
                        <p class="text-sm font-bold text-slate-700 mb-3 flex items-center"><i class='bx bx-map text-slate-400 mr-1'></i> أكثر المدن طلباً:</p>
                        <?php if (empty($data['top_cities'])): ?>
                            <p class="text-xs text-slate-400">لا توجد بيانات للمدن</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($data['top_cities'] as $city => $count): ?>
                                    <li class="flex justify-between items-center text-sm bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                                        <span class="text-slate-600 font-semibold"><?= htmlspecialchars($city) ?></span>
                                        <span class="text-xs bg-white border border-slate-200 text-slate-500 px-2 py-0.5 rounded-full"><?= $count ?> طلب</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.register(ChartDataLabels);
    // 1. Products Detail Bar Chart
    const ctx = document.getElementById('productsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'تم التسليم',
                    data: <?= json_encode($chart_delivered) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'قيد التنفيذ',
                    data: <?= json_encode($chart_pending) ?>,
                    backgroundColor: 'rgba(251, 191, 36, 0.85)',
                    borderColor: '#fbbf24',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'ملغي / مرتجع',
                    data: <?= json_encode($chart_cancelled) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.85)',
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { family: "'Cairo', sans-serif", size: 13 }, usePointStyle: true, padding: 20 } },
                tooltip: { 
                    titleFont: { family: "'Cairo', sans-serif" }, 
                    bodyFont: { family: "'Cairo', sans-serif" },
                    padding: 10,
                    backgroundColor: 'rgba(15, 23, 42, 0.9)'
                },
                datalabels: {
                    color: '#fff',
                    font: { family: "'Cairo', sans-serif", weight: 'bold', size: 12 },
                    formatter: function(value, context) {
                        return value > 0 ? value : '';
                    }
                }
            },
            scales: {
                x: { 
                    stacked: false, 
                    ticks: { font: { family: "'Cairo', sans-serif" } },
                    grid: { display: false }
                },
                y: { 
                    stacked: false, 
                    ticks: { font: { family: "'Cairo', sans-serif", size: 13, weight: 'bold' } },
                    grid: { color: 'rgba(226, 232, 240, 0.5)' }
                }
            }
        }
    });

    // 2. Overall Status Doughnut Chart
    const ctxOverall = document.getElementById('overallStatusChart').getContext('2d');
    new Chart(ctxOverall, {
        type: 'doughnut',
        data: {
            labels: ['تم التسليم', 'قيد التنفيذ', 'ملغي / مرتجع'],
            datasets: [{
                data: [<?= $overall_delivered ?>, <?= $overall_pending ?>, <?= $overall_cancelled ?>],
                backgroundColor: ['#10b981', '#fbbf24', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: "'Cairo', sans-serif", size: 12 }, padding: 20 } },
                tooltip: { titleFont: { family: "'Cairo', sans-serif" }, bodyFont: { family: "'Cairo', sans-serif" } },
                datalabels: {
                    color: '#fff',
                    font: { family: "'Cairo', sans-serif", weight: 'bold', size: 14 },
                    formatter: function(value, context) {
                        let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                        if (sum === 0 || value === 0) return '';
                        let percentage = (value * 100 / sum).toFixed(1) + "%";
                        return percentage + "\n(" + value + ")";
                    },
                    textAlign: 'center'
                }
            }
        }
    });

    // 3. Top Cities Bar Chart
    const ctxCities = document.getElementById('topCitiesChart').getContext('2d');
    new Chart(ctxCities, {
        type: 'bar',
        data: {
            labels: <?= json_encode($cities_labels) ?>,
            datasets: [{
                label: 'عدد الطلبات',
                data: <?= json_encode($cities_data) ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.85)',
                borderColor: '#6366f1',
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'y', // Horizontal bar chart
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { titleFont: { family: "'Cairo', sans-serif" }, bodyFont: { family: "'Cairo', sans-serif" } },
                datalabels: {
                    color: '#fff',
                    font: { family: "'Cairo', sans-serif", weight: 'bold', size: 12 },
                    formatter: function(value, context) {
                        return value > 0 ? value : '';
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, ticks: { font: { family: "'Cairo', sans-serif" }, stepSize: 1 } },
                y: { ticks: { font: { family: "'Cairo', sans-serif", size: 14 } } }
            }
        }
    });
});
</script>
