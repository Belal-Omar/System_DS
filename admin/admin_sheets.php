<?php
// admin_sheets.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'helpers.php';

// Only Admin or Manager
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'manager'])) {
    echo "<div class='text-center p-10'><h2 class='text-2xl text-red-600 font-bold'>غير مصرح لك بالدخول</h2></div>";
    exit;
}



// Fetch grouped landing page leads
$sheets = [];
if (isset($conn) && $conn) {
    $sql = "SELECT 
                DATE(created_at) as sheet_date, 
                marketer_code, 
                COUNT(*) as total_leads 
            FROM landing_page_leads 
            GROUP BY DATE(created_at), marketer_code 
            ORDER BY sheet_date DESC, marketer_code ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sheets[] = $row;
        }
    }
}
?>

<div class="container mx-auto px-4 py-8" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 flex items-center">
                <i class='bx bx-table text-indigo-500 mr-3 ml-3 text-4xl'></i>
                شيتات Landing Pages اليومية
            </h1>
            <p class="text-slate-500 mt-2 font-medium">عرض الشيتات المجمعة يومياً (كل 24 ساعة) مع إمكانية التحميل.</p>
        </div>
        <div class="bg-indigo-50 text-indigo-700 px-6 py-3 rounded-xl border border-indigo-100 font-bold shadow-sm flex items-center">
            <i class='bx bx-file mr-2 ml-2 text-xl'></i>
            إجمالي الشيتات: <span class="text-2xl ml-2 mr-2 font-black"><?= count($sheets) ?></span>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="py-4 px-6 text-sm font-bold text-slate-600 whitespace-nowrap">تاريخ الشيت</th>
                        <th class="py-4 px-6 text-sm font-bold text-slate-600 whitespace-nowrap">كود المسوق</th>
                        <th class="py-4 px-6 text-sm font-bold text-slate-600 whitespace-nowrap text-center">إجمالي الطلبات المسجلة</th>
                        <th class="py-4 px-6 text-sm font-bold text-slate-600 whitespace-nowrap text-left">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($sheets)): ?>
                        <tr>
                            <td colspan="4" class="py-12 text-center text-slate-500 font-medium">
                                <i class='bx bx-info-circle text-4xl mb-3 text-slate-300 block'></i>
                                لا توجد شيتات يومية مسجلة حتى الآن.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sheets as $sheet): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-4 px-6 text-sm font-bold text-slate-800">
                                    <i class='bx bx-calendar text-slate-400 ml-1'></i> <?= $sheet['sheet_date'] ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="bg-purple-50 text-purple-700 px-3 py-1 rounded-full text-sm font-bold border border-purple-100">
                                        <i class='bx bx-user mr-1'></i> <?= htmlspecialchars($sheet['marketer_code']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-bold">
                                        <?= $sheet['total_leads'] ?> طلب
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-left">
                                    <a href="admin_download_sheets.php?action=download&date=<?= urlencode($sheet['sheet_date']) ?>&marketer=<?= urlencode($sheet['marketer_code']) ?>" 
                                       class="inline-flex items-center justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-xl shadow-sm transition transform hover:-translate-y-0.5 text-sm">
                                        <i class='bx bxs-file-export ml-2 text-lg'></i>
                                        تنزيل Excel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
