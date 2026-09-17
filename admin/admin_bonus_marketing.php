<?php
// admin_bonus_marketing.php
// يتم تضمينها من admin_panel.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$selected_month = $_GET['month'] ?? date('Y-m');
$sort_by = $_GET['sort_by'] ?? 'bonus';

// 1. توليد بيانات تجريبية (Mock Data) للمسوقين بأسماء وحالات مختلفة لتوضيح طريقة الحساب
$mock_marketers = [
    ['name' => 'أحمد محمد', 'confirmed' => 1500, 'delivered' => 1050, 'bundle_delivered' => 600, 'cpl' => 45, 'cpo' => 213, 'avatar' => 'https://ui-avatars.com/api/?name=AM&background=8b5cf6&color=fff'], // ممتاز: حقق البونص والنسبة المضاعفة
    ['name' => 'سارة علي', 'confirmed' => 800, 'delivered' => 450, 'bundle_delivered' => 100, 'cpl' => 48, 'cpo' => 190, 'avatar' => 'https://ui-avatars.com/api/?name=SA&background=10b981&color=fff'], // جيد: حقق البونص لكن بدون مضاعفة الباندل
    ['name' => 'محمود خالد', 'confirmed' => 250, 'delivered' => 80, 'bundle_delivered' => 20, 'cpl' => 30, 'cpo' => 150, 'avatar' => 'https://ui-avatars.com/api/?name=MK&background=3b82f6&color=fff'], // مرفوض: لم يحقق الحد الأدنى للطلبات
    ['name' => 'نور حسين', 'confirmed' => 1000, 'delivered' => 400, 'bundle_delivered' => 150, 'cpl' => 40, 'cpo' => 180, 'avatar' => 'https://ui-avatars.com/api/?name=NH&background=f59e0b&color=fff'], // مرفوض: نسبة التسليم أقل من 50%
    ['name' => 'عمر طارق', 'confirmed' => 1200, 'delivered' => 700, 'bundle_delivered' => 400, 'cpl' => 60, 'cpo' => 250, 'avatar' => 'https://ui-avatars.com/api/?name=OT&background=ef4444&color=fff'], // مرفوض: CPL أعلى من 50 و CPO أعلى من 200
    ['name' => 'هبة حسن', 'confirmed' => 900, 'delivered' => 500, 'bundle_delivered' => 300, 'cpl' => 55, 'cpo' => 195, 'avatar' => 'https://ui-avatars.com/api/?name=HH&background=ec4899&color=fff'], // ناجح: CPL أعلى من 50 لكن CPO نجح (أقل من 200) ويحصل على مضاعفة الباندل
    ['name' => 'مصطفى كامل', 'confirmed' => 2000, 'delivered' => 1200, 'bundle_delivered' => 500, 'cpl' => 35, 'cpo' => 160, 'avatar' => 'https://ui-avatars.com/api/?name=MK&background=6366f1&color=fff'], // بطل: مبيعات ضخمة لكن الباندل أقل من 50%
];

// 2. تطبيق خوارزمية البونص على جميع المسوقين
$processed_marketers = [];
$min_delivered_required = 100;

foreach ($mock_marketers as $m) {
    $single_delivered = $m['delivered'] - $m['bundle_delivered'];
    $delivered_rate = ($m['confirmed'] > 0) ? round(($m['delivered'] / $m['confirmed']) * 100, 1) : 0;
    $bundle_rate = ($m['delivered'] > 0) ? round(($m['bundle_delivered'] / $m['delivered']) * 100, 1) : 0;
    
    $bonus = 0;
    $is_earned = false;
    $reject_reason = '';

    // التحقق من الشروط
    if ($m['delivered'] >= $min_delivered_required) {
        $cpd = ($m['delivered'] > 0) ? ($m['cpo'] * $m['confirmed']) / $m['delivered'] : 0; // Cost Per Delivered
        if ($delivered_rate >= 50 || $cpd <= 400) {
            if ($m['cpl'] <= 50 || ($m['cpl'] > 50 && $m['cpo'] <= 200)) {
                $is_earned = true;
                $single_bonus_rate = 10;
                $bundle_bonus_rate = ($bundle_rate >= 50) ? 25 : 15;
                
                $bonus = ($single_delivered * $single_bonus_rate) + ($m['bundle_delivered'] * $bundle_bonus_rate);
            } else {
                $reject_reason = "فشل شرط التكلفة (CPL / CPO)";
            }
        } else {
            $reject_reason = "نسبة التسليم أقل من 50% وتكلفة المسلم تخطت 400ج";
        }
    } else {
        $reject_reason = "لم يحقق الحد الأدنى للتسليمات";
    }

    $processed_marketers[] = array_merge($m, [
        'single_delivered' => $single_delivered,
        'delivered_rate' => $delivered_rate,
        'bundle_rate' => $bundle_rate,
        'bonus' => $bonus,
        'is_earned' => $is_earned,
        'reject_reason' => $reject_reason
    ]);
}

// ترتيب المسوقين بناءً على الفلتر المختار
usort($processed_marketers, function($a, $b) use ($sort_by) {
    if ($sort_by === 'delivery_rate') {
        return $b['delivered_rate'] <=> $a['delivered_rate'];
    } elseif ($sort_by === 'single_rate') {
        // نسبة السنجل من إجمالي التسليمات
        $a_single = ($a['delivered'] > 0) ? ($a['single_delivered'] / $a['delivered']) * 100 : 0;
        $b_single = ($b['delivered'] > 0) ? ($b['single_delivered'] / $b['delivered']) * 100 : 0;
        return $b_single <=> $a_single;
    } elseif ($sort_by === 'bundle_rate') {
        return $b['bundle_rate'] <=> $a['bundle_rate'];
    } else {
        return $b['bonus'] <=> $a['bonus'];
    }
});

// تجهيز أول 3 مسوقين وباقي القائمة
$top3 = array_slice($processed_marketers, 0, 3);
$rest_marketers = array_slice($processed_marketers, 3);
?>

<div class="container mx-auto p-6 pb-20">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-slate-800">
            <i class='bx bx-gift mr-2 text-rose-500'></i>
            إحصائيات بونص التسويق
        </h1>
        <form id="filterForm" method="GET" action="admin_panel.php" class="flex gap-2">
            <input type="hidden" name="page" value="bonus_marketing">
            <select name="sort_by" class="border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-rose-500 focus:border-rose-500 bg-white text-slate-600 font-bold cursor-pointer">
                <option value="bonus" <?= ($sort_by == 'bonus') ? 'selected' : '' ?>>الأعلى بونص</option>
                <option value="delivery_rate" <?= ($sort_by == 'delivery_rate') ? 'selected' : '' ?>>أعلى نسبة تسليم كلية</option>
                <option value="single_rate" <?= ($sort_by == 'single_rate') ? 'selected' : '' ?>>أعلى نسبة تسليم سنجل</option>
                <option value="bundle_rate" <?= ($sort_by == 'bundle_rate') ? 'selected' : '' ?>>أعلى نسبة تسليم باندل</option>
            </select>
            <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" class="border border-slate-300 rounded-lg px-4 py-2 text-sm focus:ring-rose-500 focus:border-rose-500 bg-white cursor-pointer">
        </form>
    </div>

    <!-- قواعد حساب البونص -->
    <div class="bg-slate-800 text-white rounded-3xl p-8 mb-10 shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 opacity-10">
            <i class='bx bx-brain text-9xl'></i>
        </div>
        <h2 class="text-xl font-bold mb-6 flex items-center text-rose-300 z-10 relative">
            <i class='bx bx-list-check mr-2'></i> قواعد وشروط بونص الماركتنج
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 z-10 relative">
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="bg-slate-700 p-2 rounded-lg mr-3 text-emerald-400"><i class='bx bx-target-lock text-xl'></i></div>
                    <div>
                        <h3 class="font-bold text-slate-100">1. شرط الحد الأدنى</h3>
                        <p class="text-sm text-slate-400 mt-1">يجب أن يصل إجمالي الطلبات المسلمة للمسوق إلى 100 طلب على الأقل كحد أدنى للدخول في حساب البونص.</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="bg-slate-700 p-2 rounded-lg mr-3 text-blue-400"><i class='bx bx-pie-chart-alt text-xl'></i></div>
                    <div>
                        <h3 class="font-bold text-slate-100">2. شرط جودة التسليم</h3>
                        <p class="text-sm text-slate-400 mt-1">يجب أن تكون نسبة التسليم لا تقل عن 50%. <br><span class="text-amber-300">استثناء:</span> في حال كانت النسبة أقل من 50%، يُقبل البونص بشرط ألا تتخطى تكلفة الأوردر المسلم (400 جنيه).</p>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="bg-slate-700 p-2 rounded-lg mr-3 text-orange-400"><i class='bx bx-money text-xl'></i></div>
                    <div>
                        <h3 class="font-bold text-slate-100">3. شرط تكلفة الليد (CPL & CPO)</h3>
                        <p class="text-sm text-slate-400 mt-1">يجب ألا تتخطى تكلفة الليد (CPL) 50 جنيه. وفي حال تخطت 50 جنيه، يجب أن تكون تكلفة الطلب (CPO) كحد أقصى 200 جنيه ليتم قبول البونص.</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="bg-slate-700 p-2 rounded-lg mr-3 text-rose-400"><i class='bx bx-wallet text-xl'></i></div>
                    <div>
                        <h3 class="font-bold text-slate-100">4. حساب القيمة</h3>
                        <p class="text-sm text-slate-400 mt-1">
                            عند استيفاء الشروط: يُحسب (10 جنيه) لكل طلب Single و (15 جنيه) للـ Bundle. <br>
                            <span class="text-emerald-300 font-bold">*استثناء مضاعف:</span> إذا كانت تسليمات الـ Bundle تمثل 50% فأكثر من إجمالي التسليمات، يرتفع بونص الـ Bundle إلى (25 جنيه) للطلب!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- حاوية المحتوى المتغير (سيتم تحديثها بالـ AJAX) -->
    <div id="marketing-bonus-content" class="transition-opacity duration-300">
        <!-- منصة تتويج أول 3 مسوقين (Top 3 Podium) -->
        <div class="mb-12 mt-12 pt-8">
            <h2 class="text-2xl font-bold text-center text-slate-700 mb-12">أبطال التسويق هذا الشهر 🏆</h2>
        <div class="flex flex-col md:flex-row justify-center items-end gap-6 h-auto md:h-64 px-4">
            
            <!-- المركز الثاني -->
            <?php if(isset($top3[1])): ?>
            <div class="w-full md:w-64 bg-slate-100 rounded-t-3xl border-t-4 border-slate-300 shadow-md relative flex flex-col items-center justify-end pb-6 pt-16 transform transition hover:-translate-y-2 order-2 md:order-1 h-48">
                <div class="absolute -top-12 flex flex-col items-center">
                    <div class="w-20 h-20 rounded-full border-4 border-slate-300 shadow-lg overflow-hidden bg-white">
                        <img src="<?= $top3[1]['avatar'] ?>" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <div class="bg-slate-300 text-slate-700 font-black rounded-full w-8 h-8 flex items-center justify-center -mt-4 shadow border-2 border-white z-10">2</div>
                </div>
                <h3 class="font-bold text-slate-700 text-lg text-center px-2 line-clamp-1"><?= htmlspecialchars($top3[1]['name']) ?></h3>
                <p class="text-rose-500 font-black text-xl mt-1"><?= number_format($top3[1]['bonus']) ?> <span class="text-xs">دل</span></p>
                <div class="text-[10px] text-slate-400 mt-2 bg-white px-2 py-1 rounded-full border border-slate-200">
                    نسبة التسليم: <?= $top3[1]['delivered_rate'] ?>%
                </div>
            </div>
            <?php endif; ?>

            <!-- المركز الأول -->
            <?php if(isset($top3[0])): ?>
            <div class="w-full md:w-72 bg-gradient-to-b from-amber-100 to-amber-50 rounded-t-3xl border-t-4 border-amber-400 shadow-xl relative flex flex-col items-center justify-end pb-8 pt-16 transform transition hover:-translate-y-2 order-1 md:order-2 h-64">
                <div class="absolute -top-16 flex flex-col items-center">
                    <i class='bx bxs-crown text-amber-500 text-4xl -mb-2 z-20 drop-shadow-md relative animate-bounce'></i>
                    <div class="w-24 h-24 rounded-full border-4 border-amber-400 shadow-lg overflow-hidden bg-white relative z-10">
                        <img src="<?= $top3[0]['avatar'] ?>" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <div class="bg-amber-400 text-white font-black rounded-full w-10 h-10 flex items-center justify-center -mt-5 shadow-lg border-2 border-white z-20 text-lg">1</div>
                </div>
                <h3 class="font-black text-amber-700 text-xl text-center px-2 line-clamp-1"><?= htmlspecialchars($top3[0]['name']) ?></h3>
                <p class="text-rose-600 font-black text-3xl mt-1"><?= number_format($top3[0]['bonus']) ?> <span class="text-sm">دل</span></p>
                <div class="text-[11px] text-amber-600 font-bold mt-3 bg-amber-200 px-3 py-1 rounded-full border border-amber-300">
                    نجم التسويق
                </div>
            </div>
            <?php endif; ?>

            <!-- المركز الثالث -->
            <?php if(isset($top3[2])): ?>
            <div class="w-full md:w-64 bg-orange-50 rounded-t-3xl border-t-4 border-orange-300 shadow-md relative flex flex-col items-center justify-end pb-4 pt-16 transform transition hover:-translate-y-2 order-3 md:order-3 h-40">
                <div class="absolute -top-12 flex flex-col items-center">
                    <div class="w-16 h-16 rounded-full border-4 border-orange-300 shadow-lg overflow-hidden bg-white">
                        <img src="<?= $top3[2]['avatar'] ?>" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <div class="bg-orange-300 text-white font-black rounded-full w-8 h-8 flex items-center justify-center -mt-4 shadow border-2 border-white z-10">3</div>
                </div>
                <h3 class="font-bold text-slate-700 text-lg text-center px-2 line-clamp-1"><?= htmlspecialchars($top3[2]['name']) ?></h3>
                <p class="text-rose-500 font-black text-xl mt-1"><?= number_format($top3[2]['bonus']) ?> <span class="text-xs">دل</span></p>
                <div class="text-[10px] text-slate-400 mt-2 bg-white px-2 py-1 rounded-full border border-slate-200">
                    CPO: <?= $top3[2]['cpo'] ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <div class="h-2 w-full max-w-4xl mx-auto bg-gradient-to-r from-transparent via-slate-300 to-transparent rounded-full mt-2 hidden md:block"></div>
    </div>

    <!-- جدول المسوقين -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-10">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-700">ترتيب جميع المسوقين</h3>
            <span class="text-xs font-bold text-slate-400 bg-slate-200 px-2 py-1 rounded-lg"><?= count($processed_marketers) ?> مسوق</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-4 font-bold">الترتيب</th>
                        <th class="px-6 py-4 font-bold">المسوق</th>
                        <th class="px-6 py-4 font-bold text-center">مؤكد</th>
                        <th class="px-6 py-4 font-bold text-center">تسليم</th>
                        <th class="px-6 py-4 font-bold text-center">نسبة التسليم</th>
                        <th class="px-6 py-4 font-bold text-center">CPL / CPO</th>
                        <th class="px-6 py-4 font-bold text-center">باندل %</th>
                        <th class="px-6 py-4 font-bold text-center">حالة البونص</th>
                        <th class="px-6 py-4 font-bold text-center">البونص المستحق</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($processed_marketers as $index => $m): ?>
                    <tr class="hover:bg-slate-50 transition <?= ($m['is_earned']) ? 'bg-white' : 'bg-slate-50/50 opacity-80' ?>">
                        <td class="px-6 py-4 font-bold text-slate-400">#<?= $index + 1 ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <img src="<?= $m['avatar'] ?>" class="w-8 h-8 rounded-full shadow-sm mr-3">
                                <div>
                                    <p class="font-bold text-slate-700"><?= htmlspecialchars($m['name']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-slate-600"><?= number_format($m['confirmed']) ?></td>
                        <td class="px-6 py-4 text-center font-bold text-slate-600"><?= number_format($m['delivered']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-bold <?= ($m['delivered_rate'] >= 50) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $m['delivered_rate'] ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="text-xs">
                                <span class="font-bold <?= ($m['cpl'] <= 50) ? 'text-emerald-500' : 'text-red-500' ?>">L: <?= $m['cpl'] ?></span> | 
                                <span class="font-bold <?= ($m['cpo'] <= 200) ? 'text-emerald-500' : 'text-orange-500' ?>">O: <?= $m['cpo'] ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center font-bold <?= ($m['bundle_rate'] >= 50) ? 'text-purple-600' : 'text-slate-500' ?>">
                            <?= $m['bundle_rate'] ?>%
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($m['is_earned']): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded-full border border-green-200">
                                    <i class='bx bx-check-circle mr-1'></i> مستحق
                                </span>
                            <?php else: ?>
                                <span title="<?= htmlspecialchars($m['reject_reason']) ?>" class="px-2 py-1 bg-red-100 text-red-700 text-[10px] font-bold rounded-full border border-red-200 cursor-help">
                                    <i class='bx bx-x-circle mr-1'></i> مرفوض
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($m['is_earned']): ?>
                                <span class="font-black text-rose-600 text-lg"><?= number_format($m['bonus']) ?></span>
                            <?php else: ?>
                                <span class="font-bold text-slate-300">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
    <!-- نهاية حاوية المحتوى -->

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const contentDiv = document.getElementById('marketing-bonus-content');
    
    if (form && contentDiv) {
        form.addEventListener('change', function() {
            // إضافة تأثير التعتيم للإيحاء بالتحميل
            contentDiv.style.opacity = '0.4';
            
            // جلب الرابط مع المتغيرات
            const url = new URL(form.action, window.location.href);
            const formData = new FormData(form);
            formData.forEach((value, key) => {
                url.searchParams.append(key, value);
            });
            
            // جلب الصفحة الجديدة
            fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(response => response.text())
                .then(html => {
                    // استخراج الحاوية من الـ HTML الجديد
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('marketing-bonus-content');
                    
                    if (newContent) {
                        contentDiv.innerHTML = newContent.innerHTML;
                    }
                    
                    // إزالة تأثير التعتيم
                    contentDiv.style.opacity = '1';
                    
                    // تحديث رابط المتصفح ليتناسب مع الفلاتر الجديدة (اختياري)
                    window.history.pushState({}, '', url.toString());
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    contentDiv.style.opacity = '1';
                });
        });
    }
});
</script>
