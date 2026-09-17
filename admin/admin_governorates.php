<?php
// admin_governorates.php

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$sessionRole = $_SESSION['admin_role'] ?? '';
$sessionAllowedRaw = $_SESSION['admin_allowed_pages'] ?? '';
if ($sessionRole !== 'super_admin' && !admin_can_access_page('governorates', $sessionRole, $sessionAllowedRaw)) {
    die("<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded m-4 font-bold'>عذراً، غير مصرح لك بالدخول إلى هذه الصفحة.</div>");
}

ensure_governorates_schema($conn);

$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    if (isset($_POST['add_governorate'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $flash_error = 'يرجى إدخال اسم المحافظة.';
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO governorates (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $flash_success = 'تم إضافة المحافظة بنجاح.';
                } else {
                    $flash_error = 'هذه المحافظة موجودة بالفعل.';
                }
            } else {
                $flash_error = 'حدث خطأ أثناء الإضافة.';
            }
        }
    } elseif (isset($_POST['delete_governorate'])) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM governorates WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $flash_success = 'تم حذف المحافظة بنجاح.';
        } else {
            $flash_error = 'حدث خطأ أثناء الحذف.';
        }
    }
}

$res = $conn->query("SELECT * FROM governorates ORDER BY name ASC");
$governorates = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $governorates[] = $row;
    }
}
?>

<div class="max-w-4xl mx-auto px-4 py-8 space-y-6 font-sans">
    <?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl font-bold flex items-center shadow-sm">
        <i class='bx bx-check-circle text-xl ml-2'></i><?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl font-bold flex items-center shadow-sm">
        <i class='bx bx-error-circle text-xl ml-2'></i><?= htmlspecialchars($flash_error) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl ml-3 shadow-lg shadow-indigo-100"><i class='bx bx-map-pin'></i></span>
                إدارة المحافظات والمدن
            </h1>
            <p class="text-slate-500 mt-2 font-medium">قم بإضافة أو إزالة المحافظات التي تظهر في شيتات الدعم الفني.</p>
        </div>
    </div>

    <!-- نموذج إضافة محافظة -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center"><i class='bx bx-plus-circle text-indigo-500 ml-2'></i> إضافة محافظة جديدة</h2>
        <form method="POST" class="flex flex-col sm:flex-row gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="flex-1">
                <input type="text" name="name" required placeholder="مثال: طرابلس، البريقة..." 
                       class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold text-slate-700">
            </div>
            <button type="submit" name="add_governorate" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 py-3 rounded-xl transition-colors shadow-md shadow-indigo-200 flex items-center justify-center whitespace-nowrap">
                <i class='bx bx-plus ml-2'></i> إضافة
            </button>
        </form>
    </div>

    <!-- جدول المحافظات -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">#</th>
                        <th class="px-6 py-4">اسم المحافظة</th>
                        <th class="px-6 py-4">تاريخ الإضافة</th>
                        <th class="px-6 py-4 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($governorates)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 font-bold">لا توجد محافظات مضافة حالياً.</td></tr>
                    <?php else: ?>
                        <?php foreach ($governorates as $index => $gov): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-400"><?= $index + 1 ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800 text-lg"><?= htmlspecialchars($gov['name']) ?></td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-500" dir="ltr"><?= $gov['created_at'] ?></td>
                                <td class="px-6 py-4 text-center">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="id" value="<?= $gov['id'] ?>">
                                        <button type="submit" name="delete_governorate" class="text-rose-500 bg-rose-50 hover:bg-rose-100 px-3 py-2 rounded-lg font-bold transition-colors inline-flex items-center" onclick="return confirm('هل أنت متأكد من حذف هذه المحافظة؟ لن تتمكن من التراجع.');">
                                            <i class='bx bx-trash ml-1'></i> حذف
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
