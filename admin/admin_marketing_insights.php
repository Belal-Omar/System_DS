<?php
// admin_marketing_insights.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Access Denied");
}
?>
<div class="max-w-7xl mx-auto px-4 py-8 font-sans" dir="rtl">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl ml-4 shadow-lg shadow-indigo-100">
                    <i class='bx bx-bullseye'></i>
                </span>
                إحصائيات التسويق (Marketing Insights)
            </h1>
            <p class="text-slate-500 mt-2 font-medium">هذه الصفحة قيد الإنشاء...</p>
        </div>
    </div>
    
    <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-sm text-center">
        <i class='bx bx-chart text-6xl text-slate-300 mb-4'></i>
        <h2 class="text-2xl font-bold text-slate-700">قريباً</h2>
        <p class="text-slate-500 mt-2">سيتم إضافة إحصائيات التسويق هنا.</p>
    </div>
</div>
