<?php
// admin_marketing_manager.php
// Included by admin_marketing_main.php when the user is a manager and no agent_code is provided.

$selected_month = $_GET['month'] ?? date('Y-m');
$start_date = date('Y-m-01', strtotime($selected_month . "-01"));
$end_date = date('Y-m-t', strtotime($selected_month . "-01"));

// 1. Get all marketing agents from admins table
$agents = [];
$res = $conn->query("SELECT id, fullname, marketer_code FROM admins WHERE role IN ('marketing', 'marketing_main') AND marketer_code != ''");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $agents[$r['marketer_code']] = [
            'id' => $r['id'],
            'fullname' => $r['fullname'],
            'marketer_code' => $r['marketer_code'],
            'total_spend' => 0,
            'total_confirmed' => 0,
            'total_delivered' => 0,
            'cpo' => 0,
            'cpd' => 0
        ];
    }
}

// 2. Fetch Performance for each agent
$total_dept_spend = 0;
$total_dept_confirmed = 0;
$total_dept_delivered = 0;

foreach ($agents as $code => &$agent_data) {
    $creative_perf = calculate_creative_performance($conn, $start_date, $end_date, $code);
    
    $agent_confirmed = 0;
    $agent_delivered = 0;
    $agent_spend = 0;
    
    foreach ($creative_perf as $item) {
        $agent_confirmed += $item['confirmed'];
        $agent_delivered += $item['delivered'];
        $agent_spend += $item['spend'];
    }
    
    $agent_data['total_confirmed'] = $agent_confirmed;
    $agent_data['total_delivered'] = $agent_delivered;
    $agent_data['total_spend'] = $agent_spend;
    
    if ($agent_confirmed > 0) {
        $agent_data['cpo'] = round($agent_spend / $agent_confirmed, 2);
    }
    if ($agent_delivered > 0) {
        $agent_data['cpd'] = round($agent_spend / $agent_delivered, 2);
    }
    
    $total_dept_spend += $agent_spend;
    $total_dept_confirmed += $agent_confirmed;
    $total_dept_delivered += $agent_delivered;
}
unset($agent_data);

$dept_cpo = ($total_dept_confirmed > 0) ? round($total_dept_spend / $total_dept_confirmed, 2) : 0;
$dept_cpd = ($total_dept_delivered > 0) ? round($total_dept_spend / $total_dept_delivered, 2) : 0;
$dept_del_rate = ($total_dept_confirmed > 0) ? round(($total_dept_delivered / $total_dept_confirmed) * 100, 1) : 0;

// Prepare chart data
$chart_labels = [];
$chart_confirmed = [];
$chart_cpo = [];
foreach ($agents as $agent) {
    if ($agent['total_confirmed'] > 0 || $agent['total_spend'] > 0) {
        $chart_labels[] = $agent['fullname'];
        $chart_confirmed[] = $agent['total_confirmed'];
        $chart_cpo[] = $agent['cpo'];
    }
}
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">
        <i class='bx bx-briefcase mr-2 text-indigo-500'></i>
        Marketing Department Overview
    </h1>
    <div class="flex gap-2">
        <form method="GET" action="" class="flex items-center bg-white px-4 py-2 rounded-lg shadow-sm border border-slate-100">
            <input type="hidden" name="page" value="marketing_main">
            <label for="monthFilter" class="text-sm text-slate-500 font-bold mr-2 ml-2">شهر:</label>
            <input type="month" id="monthFilter" name="month" value="<?= $selected_month ?>" onchange="this.form.submit()" class="border-none text-sm font-bold text-slate-700 bg-transparent focus:ring-0 cursor-pointer">
        </form>
        <button onclick="document.getElementById('transferBudgetModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition flex items-center">
            <i class='bx bx-transfer mr-2'></i> تحويل رصيد
        </button>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-slate-500 mb-1">Total Spend</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($total_dept_spend) ?> <span class="text-sm text-slate-400 font-bold">ج.م</span></h3>
        </div>
        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-2xl">
            <i class='bx bx-money'></i>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-slate-500 mb-1">Total Confirmed</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($total_dept_confirmed) ?></h3>
        </div>
        <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-2xl">
            <i class='bx bx-check-circle'></i>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-slate-500 mb-1">Total Delivered</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($total_dept_delivered) ?></h3>
        </div>
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-2xl">
            <i class='bx bx-package'></i>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-slate-500 mb-1">Avg CPO</p>
            <h3 class="text-2xl font-black text-slate-800"><?= number_format($dept_cpo, 1) ?> <span class="text-sm text-slate-400 font-bold">ج.م</span></h3>
        </div>
        <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-2xl">
            <i class='bx bx-line-chart'></i>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Confirmed Orders per Agent</h3>
        <div class="h-64 relative">
            <canvas id="confirmedChart"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Avg CPO per Agent (ج.م)</h3>
        <div class="h-64 relative">
            <canvas id="cpoChart"></canvas>
        </div>
    </div>
</div>

<!-- Agents Table -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <h2 class="text-xl font-bold text-slate-800 flex items-center">
            <i class='bx bx-group text-indigo-500 mr-2'></i> Marketing Agents Performance
        </h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" dir="ltr">
            <thead>
                <tr class="bg-slate-50/50">
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider">Agent</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">Spend</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">Confirmed</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">Delivered</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">Del Rate</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">CPO</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">CPD</th>
                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach($agents as $code => $agent): ?>
                <?php 
                    $del_rate = ($agent['total_confirmed'] > 0) ? round(($agent['total_delivered'] / $agent['total_confirmed']) * 100, 1) : 0;
                ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="py-4 px-6">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm mr-3">
                                <?= mb_substr($agent['fullname'], 0, 2, 'UTF-8') ?>
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($agent['fullname']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($code) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-6 text-center text-sm font-bold text-slate-700"><?= number_format($agent['total_spend']) ?></td>
                    <td class="py-4 px-6 text-center text-sm font-bold text-slate-700"><?= number_format($agent['total_confirmed']) ?></td>
                    <td class="py-4 px-6 text-center text-sm font-bold text-slate-700"><?= number_format($agent['total_delivered']) ?></td>
                    <td class="py-4 px-6 text-center text-sm font-bold text-slate-700"><?= $del_rate ?>%</td>
                    <td class="py-4 px-6 text-center text-sm font-bold <?= $agent['cpo'] > 350 ? 'text-red-500' : 'text-emerald-500' ?>"><?= number_format($agent['cpo'], 1) ?></td>
                    <td class="py-4 px-6 text-center text-sm font-bold text-slate-700"><?= number_format($agent['cpd'], 1) ?></td>
                    <td class="py-4 px-6 text-right">
                        <a href="?page=marketing_main&agent_code=<?= urlencode($code) ?>&month=<?= urlencode($selected_month) ?>" class="inline-flex items-center justify-center bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-1.5 rounded-lg text-sm font-bold transition">
                            View Dashboard <i class='bx bx-right-arrow-alt ml-1'></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($agents)): ?>
                <tr>
                    <td colspan="8" class="py-8 text-center text-slate-500 font-bold">No marketing agents found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transfer Budget Modal -->
<div id="transferBudgetModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden" dir="rtl">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 text-lg">تحويل رصيد لمسوق</h3>
            <button type="button" onclick="document.getElementById('transferBudgetModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">المسوق</label>
                    <select id="transferMarketer" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                        <?php foreach($agents as $code => $ag): ?>
                            <option value="<?= $code ?>"><?= $ag['fullname'] ?> (<?= $code ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">المبلغ (ج.م)</label>
                    <input type="number" id="transferAmount" min="1" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">تاريخ التحويل</label>
                    <input type="date" id="transferDate" value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">ملاحظات</label>
                    <input type="text" id="transferNotes" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('transferBudgetModal').classList.add('hidden')" class="px-4 py-2 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">إلغاء</button>
                <button type="button" onclick="submitTransfer()" class="px-4 py-2 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">تأكيد التحويل</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function submitTransfer() {
    const marketer = document.getElementById('transferMarketer').value;
    const amount = document.getElementById('transferAmount').value;
    const date = document.getElementById('transferDate').value;
    const notes = document.getElementById('transferNotes').value;

    if (!marketer || amount <= 0) {
        alert("يرجى إدخال مبلغ صحيح");
        return;
    }

    fetch('ajax_marketing_transfer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ marketer_code: marketer, amount: amount, date: date, notes: notes })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('تم التحويل بنجاح!');
            window.location.reload();
        } else {
            alert('خطأ: ' + (data.error || 'غير معروف'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('حدث خطأ في الاتصال');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const labels = <?= json_encode($chart_labels) ?>;
    const confirmedData = <?= json_encode($chart_confirmed) ?>;
    const cpoData = <?= json_encode($chart_cpo) ?>;
    
    if (labels.length > 0) {
        new Chart(document.getElementById('confirmedChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Confirmed Orders',
                    data: confirmedData,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        
        new Chart(document.getElementById('cpoChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'CPO (ج.م)',
                    data: cpoData,
                    backgroundColor: 'rgba(139, 92, 246, 0.8)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>
