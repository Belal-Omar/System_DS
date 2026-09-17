<?php
// admin_your_rank_super.php
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    return;
}

if (!function_exists('format_speed')) {
    function format_speed($hours) {
        if ($hours < 24) return $hours . ' ساعة';
        $days = floor($hours / 24);
        $rem_hours = $hours % 24;
        return $days . ' يوم' . ($rem_hours > 0 ? ' و ' . $rem_hours . ' ساعة' : '');
    }
}

// 1. شركات الشحن
$query_shipping = "
SELECT 
    c.id, 
    c.name,
    COUNT(o.id) as total_orders,
    SUM(CASE WHEN o.order_status IN ('delivered', '?? ????????', '?? ???????', 'received') THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN o.order_status IN ('received', '?? ????????') THEN 1 ELSE 0 END) as received_orders,
    SUM(IFNULL(o.quantity, 0)) as total_products,
    AVG(TIMESTAMPDIFF(HOUR, o.handed_to_rep_at, o.received_at)) as avg_speed_hours
FROM shipping_accounts c
LEFT JOIN support_orders o ON c.id = o.shipping_company_id
GROUP BY c.id
";
$res_shipping = $conn->query($query_shipping);
$shipping_data = [];
if ($res_shipping) {
    while ($row = $res_shipping->fetch_assoc()) {
        $total_orders = (int)$row['total_orders'];
        $delivered_orders = (int)$row['delivered_orders'];
        
        $delivery_rate = $total_orders > 0 ? round(($delivered_orders / $total_orders) * 100, 1) : 0;
        $avg_speed = $row['avg_speed_hours'] !== null ? round((float)$row['avg_speed_hours'], 1) : 999999; 

        $shipping_data[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'total_orders' => $total_orders,
            'total_products' => (int)$row['total_products'],
            'delivery_rate' => $delivery_rate,
            'speed_hours' => $avg_speed,
            'speed_text' => $avg_speed == 999999 ? 'غير متاح' : format_speed($avg_speed)
        ];
    }
}

// 1b. مناديب الشحن
$query_shipping_reps = "
SELECT 
    r.id, 
    r.name, 
    c.name as company_name,
    COUNT(o.id) as total_orders,
    SUM(CASE WHEN o.order_status IN ('delivered', '?? ????????', '?? ???????', 'received') THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN o.order_status IN ('received', '?? ????????') THEN 1 ELSE 0 END) as received_orders,
    SUM(IFNULL(o.quantity, 0)) as total_products,
    AVG(TIMESTAMPDIFF(HOUR, o.handed_to_rep_at, o.received_at)) as avg_speed_hours
FROM shipping_company_reps r
LEFT JOIN support_orders o ON r.id = o.shipping_rep_id
LEFT JOIN shipping_accounts c ON r.company_id = c.id
GROUP BY r.id
";
$res_shipping_reps = $conn->query($query_shipping_reps);
$shipping_reps_data = [];
if ($res_shipping_reps) {
    while ($row = $res_shipping_reps->fetch_assoc()) {
        $total_orders = (int)$row['total_orders'];
        $delivered_orders = (int)$row['delivered_orders'];
        $received_orders = (int)$row['received_orders'];
        
        $delivery_rate = $total_orders > 0 ? round(($delivered_orders / $total_orders) * 100, 1) : 0;
        $receipt_rate = $total_orders > 0 ? round(($received_orders / $total_orders) * 100, 1) : 0;
        $avg_speed = $row['avg_speed_hours'] !== null ? round((float)$row['avg_speed_hours'], 1) : 999999; 

        $shipping_reps_data[] = [
            'id' => $row['id'],
            'name' => $row['name'] . ' (' . ($row['company_name'] ?? 'غير محدد') . ')',
            'total_orders' => $total_orders,
            'total_products' => (int)$row['total_products'],
            'delivery_rate' => $delivery_rate,
            'receipt_rate' => $receipt_rate,
            'speed_hours' => $avg_speed,
            'speed_text' => $avg_speed == 999999 ? 'غير متاح' : format_speed($avg_speed)
        ];
    }
}

// 2. الدعم ال�?ني
$query_support = "
SELECT 
    support_id as id, 
    MAX(support_name) as name,
    COUNT(id) as total_orders,
    SUM(CASE WHEN order_status IN ('confirmed', '?? ????? ?????', 'delivered', '?? ????????', '?? ???????', 'received', 'handed_to_rep', '?? ?????? ???????', 'ready_to_ship', '???? ?????', 'out_for_delivery', '??? ???????') THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN order_status IN ('delivered', '?? ????????', '?? ???????', 'received') THEN 1 ELSE 0 END) as delivered_orders,
    SUM(IFNULL(quantity, 0)) as total_products,
    SUM(IFNULL(total_price, 0)) as total_revenue
FROM support_orders
GROUP BY support_id
";
$res_support = $conn->query($query_support);
$support_data = [];
if ($res_support) {
    while ($row = $res_support->fetch_assoc()) {
        $total_orders = (int)$row['total_orders'];
        $confirmed_orders = (int)$row['confirmed_orders'];
        $delivered_orders = (int)$row['delivered_orders'];
        
        $confirm_rate = $total_orders > 0 ? round(($confirmed_orders / $total_orders) * 100, 1) : 0;
        
        // Calculate bonus
        $sup_id = $row['id'];
        $bonus = 0;
        // single delivered
        $qs = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'single' AND order_status IN ('delivered', '?? ????????', '?? ???????', 'received')");
        $single_del = $qs ? (int)$qs->fetch_assoc()['c'] : 0;
        
        // bundle delivered
        $qb = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'bundle' AND order_status IN ('delivered', '?? ????????', '?? ???????', 'received')");
        $bundle_del = $qb ? (int)$qb->fetch_assoc()['c'] : 0;
        
        // bundle assigned
        $qba = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'bundle'");
        $bundle_assigned = $qba ? (int)$qba->fetch_assoc()['c'] : 0;
        
        // bundle rev
        $qbr = $conn->query("SELECT SUM(IFNULL(NULLIF(total_price,0), 0)) as total FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'bundle' AND order_status IN ('delivered', '?? ????????', '?? ???????', 'received')");
        $bundle_rev = $qbr ? (float)$qbr->fetch_assoc()['total'] : 0;
        
        // Confirmed (overall)
        $qc = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND order_status IN ('confirmed', '?? ????? ?????', 'delivered', '?? ????????', '?? ???????', 'received', 'handed_to_rep', '?? ?????? ???????', 'ready_to_ship', '???? ?????', 'out_for_delivery', '??? ???????')");
        $conf_tot = $qc ? (int)$qc->fetch_assoc()['c'] : 0;
        
        // Delivered (overall)
        $qd = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND order_status IN ('delivered', '?? ????????', '?? ???????', 'received')");
        $del_tot = $qd ? (int)$qd->fetch_assoc()['c'] : 0;
        
        $over_rate = $conf_tot > 0 ? ($del_tot / $conf_tot) : 0;

        $bundle_rate_from_confirmed = $conf_tot > 0 ? ($bundle_del / $conf_tot) : 0;
        $extra_b = ($bundle_rate_from_confirmed >= 0.5) ? ($bundle_rev * 0.01) : 0;
        
        $base_b = ($single_del * 10) + ($bundle_del * 15) + $extra_b;
        
        if ($del_tot < 100) {
            $bonus = 0;
        } else {
            $rate_percent = round($over_rate * 100);
            if ($rate_percent < 25) {
                $bonus = 0;
            } elseif ($rate_percent <= 30) {
                $bonus = $base_b * 0.5;
            } elseif ($rate_percent < 50) {
                $bonus = $base_b * 0.75;
            } else {
                $bonus = $base_b;
            }
        }

        $support_data[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'total_orders' => $total_orders,
            'confirmed_orders' => $confirmed_orders,
            'total_products' => (int)$row['total_products'],
            'total_revenue' => (float)$row['total_revenue'],
            'confirm_rate' => $confirm_rate,
            'bonus' => $bonus,
        ];
    }
}

// 3. المسوقين
$query_marketing = "
SELECT 
    u.id, 
    MAX(u.fullname) as name,
    COUNT(DISTINCT o.id) as total_orders,
    COUNT(DISTINCT CASE WHEN o.status IN ('تم التوصيل', 'مكتمل', 'محصل') THEN o.id ELSE NULL END) as successful_orders,
    SUM(CASE WHEN o.status IN ('تم التوصيل', 'مكتمل', 'محصل') THEN (oi.commission * oi.quantity) ELSE 0 END) as total_commission,
    SUM(CASE WHEN o.status IN ('تم التوصيل', 'مكتمل', 'محصل') THEN (oi.price * oi.quantity) ELSE 0 END) as total_revenue
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
LEFT JOIN order_items oi ON o.id = oi.order_id
WHERE u.user_type = 'marketer'
GROUP BY u.id
";
$res_marketing = $conn->query($query_marketing);
$marketing_data = [];
if ($res_marketing) {
    while ($row = $res_marketing->fetch_assoc()) {
        $total_orders = (int)$row['total_orders'];
        $successful_orders = (int)$row['successful_orders'];
        $success_rate = $total_orders > 0 ? round(($successful_orders / $total_orders) * 100, 1) : 0;

        $marketing_data[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'total_orders' => $total_orders,
            'successful_orders' => $successful_orders,
            'total_commission' => (float)$row['total_commission'],
            'total_revenue' => (float)$row['total_revenue'],
            'success_rate' => $success_rate,
        ];
    }
}
?>

<div class="px-4 py-8 font-sans" dir="rtl">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8">
        <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
            <i class='bx bx-trophy text-yellow-500 mr-3 text-4xl'></i>
            الترتيب العام (لوحة المدير)
        </h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-4 mb-8 overflow-x-auto pb-2 border-b border-gray-200">
        <button onclick="switchTab('shipping')" id="tab-shipping" class="px-6 py-3 font-bold text-sm rounded-t-lg bg-indigo-50 text-indigo-700 border-b-2 border-indigo-600 transition-colors">
            <i class='bx bx-building-house mr-1'></i> شركات الشحن
        </button>
        <button onclick="switchTab('shipping_reps')" id="tab-shipping_reps" class="px-6 py-3 font-bold text-sm text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded-t-lg transition-colors">
            <i class='bx bx-truck mr-1'></i> مناديب الشحن
        </button>
        <button onclick="switchTab('support')" id="tab-support" class="px-6 py-3 font-bold text-sm text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded-t-lg transition-colors">
            <i class='bx bx-support mr-1'></i> الدعم ال�?ني
        </button>
        <button onclick="switchTab('marketing')" id="tab-marketing" class="px-6 py-3 font-bold text-sm text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded-t-lg transition-colors">
            <i class='bx bx-line-chart mr-1'></i> المسوقين
        </button>
    </div>

    <!-- Dynamic Filter Area -->
    <div class="flex justify-end mb-6 bg-white p-3 rounded-xl shadow-sm border border-slate-100">
        <div class="flex items-center gap-3">
            <span class="text-sm font-bold text-slate-600">ترتيب حسب:</span>
            <select id="rankFilter" class="bg-indigo-50 border-none text-indigo-800 font-bold text-sm rounded-lg focus:ring-0 p-2 cursor-pointer" onchange="renderRanking()">
                <!-- Populated via JS based on tab -->
            </select>
        </div>
    </div>

    <!-- Top 3 Medals Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12" id="top3-container"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Table Section -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-700" id="table-title">الترتيب</h3>
            </div>
            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-sm text-right h-full">
                    <thead class="text-xs text-slate-500 uppercase bg-white">
                        <tr>
                            <th class="px-6 py-4 border-b">المركز</th>
                            <th class="px-6 py-4 border-b">الاسم</th>
                            <th class="px-6 py-4 border-b text-center">القيمة</th>
                        </tr>
                    </thead>
                    <tbody id="ranking-table-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-center justify-center">
                <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4" id="chart-title">أ�?ضل 10</h3>
                <div class="w-full relative h-64">
                    <canvas id="rankingChart"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Doughnut Chart: Share -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4" id="share-chart-title">نسبة الاستحواذ (أعلى 5)</h3>
                    <div class="w-full relative h-64">
                        <canvas id="shareChart"></canvas>
                    </div>
                </div>

                <!-- Radar Chart: Multi-metric Comparison -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4">مقارنة شاملة للأداء (أ�?ضل 3)</h3>
                    <div class="w-full relative h-64">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const allData = {
    shipping: <?= json_encode($shipping_data) ?>,
    shipping_reps: <?= json_encode($shipping_reps_data) ?>,
    support: <?= json_encode($support_data) ?>,
    marketing: <?= json_encode($marketing_data) ?>
};

const filters = {
    shipping: [
        { value: 'delivery_rate', text: 'نسبة التوصيل' },
        { value: 'speed', text: 'الأسرع توصيلاً' },
        { value: 'total_orders', text: 'إجمالي الطلبات' },
        { value: 'total_products', text: 'إجمالي المنتجات' }
    ],
    shipping_reps: [
        { value: 'delivery_rate', text: 'نسبة التوصيل' },
        { value: 'speed', text: 'الأسرع توصيلاً' },
        { value: 'total_orders', text: 'إجمالي الطلبات' },
        { value: 'total_products', text: 'إجمالي المنتجات' }
    ],
    support: [
        { value: 'confirm_rate', text: 'نسبة التأكيد' },
        { value: 'confirmed_orders', text: 'الطلبات المؤكدة' },
        { value: 'total_revenue', text: 'الإيرادات' },
        { value: 'total_orders', text: 'إجمالي العمليات' },
        { value: 'bonus', text: 'البونص' }
    ],
    marketing: [
        { value: 'success_rate', text: 'نسبة النجاح' },
        { value: 'successful_orders', text: 'الطلبات الناجحة' },
        { value: 'total_revenue', text: 'المبيعات' },
        { value: 'total_commission', text: 'العمولات' }
    ]
};

let currentTab = 'shipping';
let chartInstance = null;
let shareChartInstance = null;
let radarChartInstance = null;

function switchTab(tab) {
    currentTab = tab;
    
    // Update tabs UI
    ['shipping', 'shipping_reps', 'support', 'marketing'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (t === tab) {
            el.className = "px-6 py-3 font-bold text-sm rounded-t-lg bg-indigo-50 text-indigo-700 border-b-2 border-indigo-600 transition-colors";
        } else {
            el.className = "px-6 py-3 font-bold text-sm text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded-t-lg transition-colors";
        }
    });

    // Populate filters
    const filterSelect = document.getElementById('rankFilter');
    filterSelect.innerHTML = '';
    filters[tab].forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.value;
        opt.textContent = f.text;
        filterSelect.appendChild(opt);
    });

    renderRanking();
}

function renderRanking() {
    const filter = document.getElementById('rankFilter').value;
    const data = allData[currentTab];
    
    let sortedData = [...data];
    if (filter === 'speed') {
        sortedData.sort((a, b) => a.speed_hours - b.speed_hours);
    } else {
        sortedData.sort((a, b) => b[filter] - a[filter]);
    }

    const top3Container = document.getElementById('top3-container');
    const tableBody = document.getElementById('ranking-table-body');
    
    top3Container.innerHTML = '';
    tableBody.innerHTML = '';

    if (sortedData.length === 0) {
        top3Container.innerHTML = '<div class="col-span-3 text-center text-gray-500 py-10 font-bold">لا توجد بيانات متاحة �?ي هذا القسم.</div>';
        if (chartInstance) chartInstance.destroy();
        return;
    }

    const medals = [
        { color: 'text-yellow-400', bg: 'bg-gradient-to-br from-yellow-50 to-yellow-100', border: 'border-yellow-200', icon: 'bx-medal' },
        { color: 'text-slate-400', bg: 'bg-gradient-to-br from-slate-50 to-slate-100', border: 'border-slate-200', icon: 'bx-medal' },
        { color: 'text-orange-400', bg: 'bg-gradient-to-br from-orange-50 to-orange-100', border: 'border-orange-200', icon: 'bx-medal' }
    ];

    const podiumIndices = [1, 0, 2];
    
    podiumIndices.forEach((rankIndex, i) => {
        const item = sortedData[rankIndex];
        if (!item) {
            top3Container.innerHTML += '<div></div>'; 
            return;
        }
        
        const m = medals[rankIndex];
        const isFirst = rankIndex === 0;
        let valText = getValText(item, filter, currentTab);

        const card = `
            <div class="relative ${m.bg} rounded-3xl p-6 border ${m.border} flex flex-col items-center justify-center text-center transform transition duration-500 hover:scale-105 shadow-sm ${isFirst ? 'md:-translate-y-4 shadow-md' : ''}">
                <div class="absolute -top-5 bg-white w-10 h-10 rounded-full flex items-center justify-center shadow-sm border border-gray-200 font-bold text-indigo-900">
                    <span class="text-lg">${rankIndex + 1}</span>
                </div>
                <i class='bx ${m.icon} text-6xl ${m.color} drop-shadow-sm mb-4'></i>
                <h3 class="text-xl font-black text-slate-800 mb-2">${item.name}</h3>
                <div class="bg-white/80 backdrop-blur-sm px-4 py-2 rounded-xl shadow-sm border border-white font-bold text-lg text-indigo-700">
                    ${valText}
                </div>
            </div>
        `;
        top3Container.innerHTML += card;
    });

    for (let i = 3; i < sortedData.length; i++) {
        const item = sortedData[i];
        let valText = getValText(item, filter, currentTab);
        
        const row = `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-500">#${i + 1}</td>
                <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800">${item.name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center font-bold text-indigo-600">${valText}</td>
            </tr>
        `;
        tableBody.innerHTML += row;
    }

    renderCharts(sortedData, filter);
}

function getValText(item, filter, tab) {
    if (filter === 'speed') return item.speed_text;
    if (filter === 'delivery_rate' || filter === 'confirm_rate' || filter === 'success_rate') return item[filter] + '%';
    if (filter === 'total_revenue' || filter === 'total_commission') return new Intl.NumberFormat('en-US').format(item[filter]) + ' دل';
    if (filter === 'bonus') return new Intl.NumberFormat('en-US').format(item[filter]) + ' دل';
    return item[filter];
}

function renderCharts(sortedData, filter) {
    const ctxBar = document.getElementById('rankingChart').getContext('2d');
    const ctxShare = document.getElementById('shareChart').getContext('2d');
    const ctxRadar = document.getElementById('radarChart').getContext('2d');
    
    // 1. Bar Chart (Top 10)
    const barData = sortedData.slice(0, 10).filter(r => {
        if(filter === 'speed' && r.speed_hours === 999999) return false;
        return true;
    });

    const barLabels = barData.map(r => r.name.split(' ')[0]);
    const barValues = barData.map(r => filter === 'speed' ? r.speed_hours : r[filter]);

    let labelName = document.getElementById('rankFilter').options[document.getElementById('rankFilter').selectedIndex].text;
    document.getElementById('chart-title').innerText = 'أ�?ضل 10 - ' + labelName;

    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                label: labelName,
                data: barValues,
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 2. Doughnut Chart (Share of Total Orders / Volume)
    // We sort by total_orders generally to show market share
    let shareMetric = 'total_orders';
    let shareLabel = 'إجمالي الطلبات';
    if (currentTab === 'marketing') {
        shareMetric = 'successful_orders';
        shareLabel = 'الطلبات الناجحة';
    } else if (currentTab === 'support') {
        shareMetric = 'confirmed_orders';
        shareLabel = 'الطلبات المؤكدة';
    }

    document.getElementById('share-chart-title').innerText = 'حصة ' + shareLabel + ' (أعلى 5)';

    const shareSorted = [...allData[currentTab]].sort((a, b) => b[shareMetric] - a[shareMetric]);
    const shareData = shareSorted.slice(0, 5);
    const shareLabels = shareData.map(r => r.name.split(' ')[0]);
    const shareValues = shareData.map(r => r[shareMetric]);
    
    // Background colors for doughnut
    const bgColors = [
        'rgba(99, 102, 241, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(139, 92, 246, 0.8)'
    ];

    if (shareChartInstance) shareChartInstance.destroy();
    shareChartInstance = new Chart(ctxShare, {
        type: 'doughnut',
        data: {
            labels: shareLabels,
            datasets: [{
                data: shareValues,
                backgroundColor: bgColors,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });

    // 3. Radar Chart (Top 3 Performance Comparison)
    // We pick the top 3 from the currently sorted data
    const top3 = sortedData.slice(0, 3);
    
    let radarLabels = [];
    let radarMetrics = [];
    
    if (currentTab === 'shipping' || currentTab === 'shipping_reps') {
        radarLabels = ['التوصيل (%)', 'إجمالي الطلبات', 'المنتجات'];
        radarMetrics = ['delivery_rate', 'total_orders', 'total_products'];
    } else if (currentTab === 'support') {
        radarLabels = ['التأكيد (%)', 'مؤكدة', 'عمليات'];
        radarMetrics = ['confirm_rate', 'confirmed_orders', 'total_orders'];
    } else if (currentTab === 'marketing') {
        radarLabels = ['النجاح (%)', 'ناجحة', 'العمولات'];
        radarMetrics = ['success_rate', 'successful_orders', 'total_commission'];
    }

    // Normalize data for radar chart so they fit on the same scale (0-100)
    // We find max value for each metric across all data to normalize
    const maxVals = radarMetrics.map(metric => {
        let max = Math.max(...allData[currentTab].map(r => r[metric] || 0));
        return max === 0 ? 1 : max;
    });

    const radarDatasets = top3.map((item, index) => {
        const normalizedData = radarMetrics.map((metric, mIndex) => {
            return ((item[metric] || 0) / maxVals[mIndex]) * 100;
        });

        // Use distinct colors
        const colorBase = index === 0 ? '99, 102, 241' : (index === 1 ? '16, 185, 129' : '245, 158, 11');

        return {
            label: item.name.split(' ')[0],
            data: normalizedData,
            backgroundColor: `rgba(${colorBase}, 0.2)`,
            borderColor: `rgba(${colorBase}, 1)`,
            pointBackgroundColor: `rgba(${colorBase}, 1)`,
            borderWidth: 2
        };
    });

    if (radarChartInstance) radarChartInstance.destroy();
    radarChartInstance = new Chart(ctxRadar, {
        type: 'radar',
        data: {
            labels: radarLabels,
            datasets: radarDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { display: false } // Hide ticks because they are normalized percentages
                }
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            // Show original value in tooltip instead of normalized
                            const originalVal = top3[context.datasetIndex][radarMetrics[context.dataIndex]];
                            let valStr = originalVal;
                            if(radarMetrics[context.dataIndex].includes('rate')) valStr += '%';
                            else if (radarMetrics[context.dataIndex].includes('commission') || radarMetrics[context.dataIndex].includes('revenue')) valStr += ' دل';
                            
                            return context.dataset.label + ': ' + valStr;
                        }
                    }
                }
            }
        }
    });
}

// Initial init
document.addEventListener('DOMContentLoaded', () => {
    switchTab('shipping');
});
</script>
