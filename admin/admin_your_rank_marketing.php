<?php
// admin_your_rank_marketing.php
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'marketing') {
    return;
}

// جلب إحصائيات المسوقين
$query = "
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

$result = $conn->query($query);
$reps = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $total_orders = (int)$row['total_orders'];
        $successful_orders = (int)$row['successful_orders'];
        
        $success_rate = $total_orders > 0 ? round(($successful_orders / $total_orders) * 100, 1) : 0;

        $reps[] = [
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
            ترتيب المسوقين (Your Rank)
        </h1>
        
        <div class="flex items-center gap-3 mt-4 md:mt-0 bg-white p-2 rounded-xl shadow-sm border border-slate-200">
            <span class="text-sm font-bold text-slate-600 px-3">ترتيب حسب:</span>
            <select id="rankFilter" class="bg-indigo-50 border-none text-indigo-800 font-bold text-sm rounded-lg focus:ring-0 p-2 cursor-pointer" onchange="renderRanking()">
                <option value="successful_orders">الطلبات الناجحة</option>
                <option value="total_commission">إجمالي العمولات</option>
                <option value="total_revenue">المبيعات (الإيرادات)</option>
                <option value="success_rate">نسبة النجاح %</option>
                <option value="total_orders">إجمالي الطلبات المُدخلة</option>
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
                <h3 class="text-lg font-bold text-slate-700">ترتيب باقي المسوقين</h3>
            </div>
            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-sm text-right h-full">
                    <thead class="text-xs text-slate-500 uppercase bg-white">
                        <tr>
                            <th class="px-6 py-4 border-b">المركز</th>
                            <th class="px-6 py-4 border-b">المسوق</th>
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
                <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4" id="chart-title">أفضل 10 مسوقين</h3>
                <div class="w-full relative h-48">
                    <canvas id="rankingChart"></canvas>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-center justify-center">
                <h3 class="text-lg font-bold text-slate-700 w-full text-right mb-4">حصة الطلبات الناجحة</h3>
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
    if (filter === 'success_rate') return rep[filter] + '%';
    if (filter === 'total_revenue' || filter === 'total_commission') return new Intl.NumberFormat('en-US').format(rep[filter]) + ' ج.م';
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
                backgroundColor: 'rgba(16, 185, 129, 0.8)', // emerald-500
                borderColor: 'rgba(5, 150, 105, 1)', // emerald-600
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
    const sortedByOrders = [...repsData].sort((a, b) => b.successful_orders - a.successful_orders);
    
    const top5 = sortedByOrders.slice(0, 5);
    const others = sortedByOrders.slice(5);
    
    let labels = top5.map(r => r.name.split(' ')[0]);
    let data = top5.map(r => r.successful_orders);
    
    if (others.length > 0) {
        labels.push('آخرون');
        data.push(others.reduce((sum, r) => sum + r.successful_orders, 0));
    }

    const colors = [
        'rgba(16, 185, 129, 0.8)', // emerald
        'rgba(245, 158, 11, 0.8)', // amber
        'rgba(59, 130, 246, 0.8)', // blue
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
