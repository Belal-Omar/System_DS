<?php
// admin_your_rank_support.php
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'support') {
    return;
}

// جلب إحصائيات موظفي الدعم الفني
$query = "
SELECT 
    support_id as id, 
    MAX(support_name) as name,
    COUNT(id) as total_orders,
    SUM(CASE WHEN order_status IN ('confirmed', 'order_confirmed', 'تم تأكيد الطلب', 'delivered', 'تم الاستلام', 'تم التسليم', 'received', 'handed_to_rep', 'تم تسليمه للمندوب', 'ready_to_ship', 'جاهز للشحن', 'out_for_delivery', 'خرج للتوصيل') THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN order_status IN ('delivered', 'تم الاستلام', 'تم التسليم', 'received') THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN order_status IN ('cancelled', 'order_cancelled', 'order_cancelled') THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(IFNULL(quantity, 0)) as total_products,
    SUM(IFNULL(total_price, 0)) as total_revenue
FROM support_orders
GROUP BY support_id
";

$result = $conn->query($query);
$reps = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $total_orders = (int)$row['total_orders'];
        $confirmed_orders = (int)$row['confirmed_orders'];
        $delivered_orders = (int)$row['delivered_orders'];
        
        $confirm_rate = $total_orders > 0 ? round(($confirmed_orders / $total_orders) * 100, 1) : 0;
        $delivery_rate = $confirmed_orders > 0 ? round(($delivered_orders / $confirmed_orders) * 100, 1) : 0;

        // Calculate bonus
        $sup_id = $row['id'];
        $bonus = 0;
        // single delivered
        $qs = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'single' AND order_status IN ('delivered', 'تم الاستلام', 'تم التسليم', 'received')");
        $single_del = $qs ? (int)$qs->fetch_assoc()['c'] : 0;
        
        // bundle delivered
        $qb = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'bundle' AND order_status IN ('delivered', 'تم الاستلام', 'تم التسليم', 'received')");
        $bundle_del = $qb ? (int)$qb->fetch_assoc()['c'] : 0;
        
        // bundle assigned
        $qba = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'bundle'");
        $bundle_assigned = $qba ? (int)$qba->fetch_assoc()['c'] : 0;
        
        // bundle rev
        $qbr = $conn->query("SELECT SUM(IFNULL(NULLIF(total_price,0), 0)) as total FROM support_orders WHERE support_id = $sup_id AND bundle_type = 'bundle' AND order_status IN ('delivered', 'تم الاستلام', 'تم التسليم', 'received')");
        $bundle_rev = $qbr ? (float)$qbr->fetch_assoc()['total'] : 0;
        
        // Confirmed (overall)
        $qc = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND order_status IN ('confirmed', 'order_confirmed', 'تم تأكيد الطلب', 'delivered', 'تم الاستلام', 'تم التسليم', 'received', 'handed_to_rep', 'تم تسليمه للمندوب', 'ready_to_ship', 'جاهز للشحن', 'out_for_delivery', 'خرج للتوصيل')");
        $conf_tot = $qc ? (int)$qc->fetch_assoc()['c'] : 0;
        
        // Delivered (overall)
        $qd = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE support_id = $sup_id AND order_status IN ('delivered', 'تم الاستلام', 'تم التسليم', 'received')");
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

        $reps[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'total_orders' => $total_orders,
            'confirmed_orders' => $confirmed_orders,
            'delivered_orders' => $delivered_orders,
            'total_products' => (int)$row['total_products'],
            'total_revenue' => (float)$row['total_revenue'],
            'confirm_rate' => $confirm_rate,
            'delivery_rate' => $delivery_rate,
            'bonus' => $bonus,
        ];
    }
}
?>

<div class="px-4 py-8 font-sans" dir="rtl">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8">
        <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
            <i class='bx bx-trophy text-yellow-500 mr-3 text-4xl'></i>
            ترتيب فريق الدعم (Your Rank)
        </h1>
        
        <div class="flex items-center gap-3 mt-4 md:mt-0 bg-white p-2 rounded-xl shadow-sm border border-slate-200">
            <span class="text-sm font-bold text-slate-600 px-3">ترتيب حسب:</span>
            <select id="rankFilter" class="bg-indigo-50 border-none text-indigo-800 font-bold text-sm rounded-lg focus:ring-0 p-2 cursor-pointer" onchange="renderRanking()">
                <option value="confirmed_orders">الطلبات المؤكدة</option>
                <option value="delivered_orders">الطلبات المُسلمة</option>
                <option value="confirm_rate">نسبة التأكيد %</option>
                <option value="delivery_rate">نسبة التوصيل %</option>
                <option value="total_revenue">إجمالي الإيرادات</option>
                <option value="total_orders">إجمالي الطلبات (العمليات)</option>
                <option value="bonus" selected>البونص (مكافآتك)</option>
            </select>
        </div>
    </div>

    <!-- Top 3 Medals Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12" id="top3-container">
        <!-- Renders via JS -->
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Table Section -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-700">ترتيب باقي الفريق</h3>
            </div>
            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-sm text-right h-full">
                    <thead class="text-xs text-slate-500 uppercase bg-white">
                        <tr>
                            <th class="px-6 py-4 border-b">المركز</th>
                            <th class="px-6 py-4 border-b">الموظف</th>
                            <th class="px-6 py-4 border-b text-center">القيمة</th>
                        </tr>
                    </thead>
                    <tbody id="ranking-table-body" class="divide-y divide-slate-100">
                        <!-- Renders via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="space-y-8">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-center justify-center">
                <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4" id="chart-title">أفضل 10 بالدعم</h3>
                <div class="w-full relative h-48">
                    <canvas id="rankingChart"></canvas>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-center justify-center">
                <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4">حصة الطلبات المؤكدة للفرق</h3>
                <div class="w-full relative h-48">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const repsData = <?= json_encode($reps) ?>;
let chartInstance = null;
let pieChartInstance = null;

function renderRanking() {
    const filter = document.getElementById('rankFilter').value;
    
    let sortedReps = [...repsData];
    sortedReps.sort((a, b) => b[filter] - a[filter]);

    const top3Container = document.getElementById('top3-container');
    const tableBody = document.getElementById('ranking-table-body');
    
    top3Container.innerHTML = '';
    tableBody.innerHTML = '';

    if (sortedReps.length === 0) {
        top3Container.innerHTML = '<div class="col-span-3 text-center text-gray-500 py-10 font-bold">لا توجد بيانات متاحة حالياً.</div>';
        return;
    }

    const medals = [
        { color: 'text-yellow-400', bg: 'bg-gradient-to-br from-yellow-50 to-yellow-100', border: 'border-yellow-200', icon: 'bx-medal' },
        { color: 'text-slate-400', bg: 'bg-gradient-to-br from-slate-50 to-slate-100', border: 'border-slate-200', icon: 'bx-medal' },
        { color: 'text-orange-400', bg: 'bg-gradient-to-br from-orange-50 to-orange-100', border: 'border-orange-200', icon: 'bx-medal' }
    ];

    const podiumIndices = [1, 0, 2];
    
    podiumIndices.forEach((rankIndex, i) => {
        const rep = sortedReps[rankIndex];
        if (!rep) {
            top3Container.innerHTML += '<div></div>'; 
            return;
        }
        
        const m = medals[rankIndex];
        const isFirst = rankIndex === 0;
        
        let valText = getValText(rep, filter);

        const card = `
            <div class="relative ${m.bg} rounded-3xl p-6 border ${m.border} flex flex-col items-center justify-center text-center transform transition duration-500 hover:scale-105 shadow-sm ${isFirst ? 'md:-translate-y-4 shadow-md' : ''}">
                <div class="absolute -top-5 bg-white w-10 h-10 rounded-full flex items-center justify-center shadow-sm border border-gray-200 font-bold text-indigo-900">
                    <span class="text-lg">${rankIndex + 1}</span>
                </div>
                <i class='bx ${m.icon} text-6xl ${m.color} drop-shadow-sm mb-4'></i>
                <h3 class="text-xl font-black text-slate-800 mb-2">${rep.name}</h3>
                <div class="bg-white/80 backdrop-blur-sm px-4 py-2 rounded-xl shadow-sm border border-white font-bold text-lg text-indigo-700">
                    ${valText}
                </div>
            </div>
        `;
        top3Container.innerHTML += card;
    });

    for (let i = 3; i < sortedReps.length; i++) {
        const rep = sortedReps[i];
        let valText = getValText(rep, filter);
        
        const row = `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-500">#${i + 1}</td>
                <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800">${rep.name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center font-bold text-indigo-600">${valText}</td>
            </tr>
        `;
        tableBody.innerHTML += row;
    }

    renderBarChart(sortedReps, filter);
    renderPieChart();
}

function getValText(rep, filter) {
    if (filter === 'confirm_rate' || filter === 'delivery_rate') return rep[filter] + '%';
    if (filter === 'total_revenue') return new Intl.NumberFormat('en-US').format(rep[filter]) + ' دل';
    if (filter === 'bonus') return new Intl.NumberFormat('en-US').format(rep[filter]) + ' دل';
    return rep[filter] + ' طلب';
}

function renderBarChart(sortedReps, filter) {
    const ctx = document.getElementById('rankingChart').getContext('2d');
    
    const chartData = sortedReps.slice(0, 10);
    const labels = chartData.map(r => r.name.split(' ')[0]);
    const data = chartData.map(r => r[filter]);

    let labelName = document.getElementById('rankFilter').options[document.getElementById('rankFilter').selectedIndex].text;
    document.getElementById('chart-title').innerText = 'أفضل 10 - ' + labelName;

    if (chartInstance) chartInstance.destroy();

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: labelName,
                data: data,
                backgroundColor: 'rgba(59, 130, 246, 0.8)', // blue-500
                borderColor: 'rgba(37, 99, 235, 1)', // blue-600
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
}

function renderPieChart() {
    if (pieChartInstance) pieChartInstance.destroy();
    if (repsData.length === 0) return;

    const ctx = document.getElementById('pieChart').getContext('2d');
    const sortedByOrders = [...repsData].sort((a, b) => b.confirmed_orders - a.confirmed_orders);
    
    const top5 = sortedByOrders.slice(0, 5);
    const others = sortedByOrders.slice(5);
    
    let labels = top5.map(r => r.name.split(' ')[0]);
    let data = top5.map(r => r.confirmed_orders);
    
    if (others.length > 0) {
        labels.push('آخرون');
        data.push(others.reduce((sum, r) => sum + r.confirmed_orders, 0));
    }

    const colors = [
        'rgba(59, 130, 246, 0.8)', // blue
        'rgba(245, 158, 11, 0.8)', // amber
        'rgba(16, 185, 129, 0.8)', // emerald
        'rgba(236, 72, 153, 0.8)', // pink
        'rgba(139, 92, 246, 0.8)', // violet
        'rgba(156, 163, 175, 0.8)' // gray
    ];

    pieChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'left', labels: { font: { family: 'sans-serif', size: 10 } } }
            },
            cutout: '65%'
        }
    });
}

document.addEventListener('DOMContentLoaded', renderRanking);
</script>
