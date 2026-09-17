<?php
// admin_shipping_reps.php - إدارة مناديب الشحن
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'shipping_company') {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>ليس مصرح لك بالدخول</div>";
    return;
}

$sc_id = (int)($_SESSION['shipping_company_id'] ?? 0);
if ($sc_id <= 0) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>خطأ: لا يوجد شركة شحن مرتبطة بحسابك.</div>";
    return;
}

// معالجة الإضافة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rep'])) {
    $name = clean_input($_POST['name'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    
    if (!empty($name) && !empty($phone)) {
        $stmt = $conn->prepare("INSERT INTO shipping_company_reps (company_id, name, phone) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $sc_id, $name, $phone);
        if ($stmt->execute()) {
            echo "<div class='bg-green-100 text-green-700 p-4 rounded mb-4'>✅ تم إضافة المندوب بنجاح!</div>";
        } else {
            echo "<div class='bg-red-100 text-red-700 p-4 rounded mb-4'>❌ حدث خطأ أثناء الإضافة.</div>";
        }
    } else {
        echo "<div class='bg-yellow-100 text-yellow-700 p-4 rounded mb-4'>⚠️ يرجى تعبئة جميع الحقول.</div>";
    }
}

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_rep'])) {
    $rep_id = (int)$_POST['rep_id'];
    $name = clean_input($_POST['name'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    
    if (!empty($name) && !empty($phone) && $rep_id > 0) {
        $stmt = $conn->prepare("UPDATE shipping_company_reps SET name = ?, phone = ? WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ssii", $name, $phone, $rep_id, $sc_id);
        if ($stmt->execute()) {
            echo "<div class='bg-blue-100 text-blue-700 p-4 rounded mb-4'>ℹ️ تم تعديل بيانات المندوب بنجاح.</div>";
        } else {
            echo "<div class='bg-red-100 text-red-700 p-4 rounded mb-4'>❌ حدث خطأ أثناء التعديل.</div>";
        }
    }
}

// معالجة الحذف
if (isset($_GET['delete_rep'])) {
    $rep_id = (int)$_GET['delete_rep'];
    if ($rep_id > 0) {
        $stmt = $conn->prepare("DELETE FROM shipping_company_reps WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $rep_id, $sc_id);
        if ($stmt->execute()) {
            echo "<div class='bg-gray-100 text-gray-700 p-4 rounded mb-4'>🗑️ تم حذف المندوب.</div>";
        }
    }
}

// جلب المندوبين
$reps_q = $conn->query("SELECT * FROM shipping_company_reps WHERE company_id = $sc_id ORDER BY id DESC");
$reps = [];
if ($reps_q) {
    while ($r = $reps_q->fetch_assoc()) {
        $reps[] = $r;
    }
}
?>

<div class="bg-white rounded-lg shadow-lg p-6 mb-6">
    <h2 class="text-2xl font-bold mb-6 flex items-center text-indigo-800">
        <i class='bx bx-user-plus mr-2 text-indigo-500'></i> إضافة مندوب جديد
    </h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <?= csrf_field() ?>
        <div>
            <label for="add_name" class="block text-sm font-bold text-gray-700 mb-1">اسم المندوب</label>
            <input type="text" name="name" id="add_name" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-200">
        </div>
        <div>
            <label for="add_phone" class="block text-sm font-bold text-gray-700 mb-1">رقم الهاتف</label>
            <input type="text" name="phone" id="add_phone" required dir="ltr" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-200 text-left">
        </div>
        <div>
            <button type="submit" name="add_rep" class="w-full bg-indigo-600 text-white font-bold py-2 px-4 rounded hover:bg-indigo-700 transition">
                إضافة المندوب
            </button>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow-lg p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold flex items-center text-gray-800">
            <i class='bx bx-group mr-2 text-gray-500'></i> مناديب الشركة
        </h2>
        <div class="w-full md:w-1/3 flex">
            <div class="relative w-full">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <i class='bx bx-search text-gray-400 text-xl'></i>
                </div>
                <label for="repSearchInput" class="sr-only">بحث بالاسم أو الهاتف...</label>
                <input type="text" id="repSearchInput" placeholder="بحث بالاسم أو الهاتف..." class="w-full p-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
                <tr>
                    <th class="py-3 px-4 text-right font-bold text-gray-700">الاسم</th>
                    <th class="py-3 px-4 text-right font-bold text-gray-700">الهاتف</th>
                    <th class="py-3 px-4 text-center font-bold text-gray-700">تاريخ الإضافة</th>
                    <th class="py-3 px-4 text-center font-bold text-gray-700">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reps as $rep): ?>
                <tr class="border-b hover:bg-gray-50 rep-row">
                    <td class="py-3 px-4 font-bold text-gray-800 rep-name"><?= htmlspecialchars($rep['name']) ?></td>
                    <td class="py-3 px-4 font-semibold text-gray-600 text-right rep-phone"><span dir="ltr"><?= htmlspecialchars($rep['phone']) ?></span></td>
                    <td class="py-3 px-4 text-center text-gray-500"><?= date('Y-m-d', strtotime($rep['created_at'])) ?></td>
                    <td class="py-3 px-4 text-center space-x-2 space-x-reverse">
                        <a href="?page=shipping_rep_stats&id=<?= $rep['id'] ?>" class="text-indigo-600 hover:text-indigo-800" title="الإحصائيات"><i class='bx bx-bar-chart-alt-2 text-xl'></i></a>
                        <button onclick="editRep(<?= $rep['id'] ?>, '<?= htmlspecialchars(addslashes($rep['name'])) ?>', '<?= htmlspecialchars(addslashes($rep['phone'])) ?>')" class="text-blue-500 hover:text-blue-700" title="تعديل"><i class='bx bx-edit text-xl'></i></button>
                        <a href="?page=shipping_reps&delete_rep=<?= $rep['id'] ?>" onclick="return confirm('هل أنت متأكد من حذف هذا المندوب؟')" class="text-red-500 hover:text-red-700" title="حذف"><i class='bx bx-trash text-xl'></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reps)): ?>
                <tr>
                    <td colspan="4" class="py-6 text-center text-gray-500 font-bold bg-gray-50">لا يوجد مناديب مضافين حتى الآن</td>
                </tr>
                <?php endif; ?>
                <tr id="noResultsRow" style="display: none;">
                    <td colspan="4" class="py-6 text-center text-gray-500 font-bold bg-gray-50">لم يتم العثور على نتائدلطابقة للبحث</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- مودال التعديل -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-xl font-bold mb-4">تعديل بيانات المندوب</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="rep_id" id="edit_rep_id">
            <div class="mb-4">
                <label for="edit_name" class="block text-sm font-bold text-gray-700 mb-1">اسم المندوب</label>
                <input type="text" name="name" id="edit_name" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-200">
            </div>
            <div class="mb-4">
                <label for="edit_phone" class="block text-sm font-bold text-gray-700 mb-1">رقم الهاتف</label>
                <input type="text" name="phone" id="edit_phone" required dir="ltr" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-200 text-left">
            </div>
            <div class="flex justify-end space-x-3 space-x-reverse">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">إلغاء</button>
                <button type="submit" name="edit_rep" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 font-bold">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRep(id, name, phone) {
    document.getElementById('edit_rep_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('repSearchInput');
    const rows = document.querySelectorAll('.rep-row');
    const noResultsRow = document.getElementById('noResultsRow');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.rep-name').textContent.toLowerCase();
                const phone = row.querySelector('.rep-phone').textContent.toLowerCase();

                if (name.includes(query) || phone.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount === 0 && rows.length > 0) {
                noResultsRow.style.display = '';
            } else {
                noResultsRow.style.display = 'none';
            }
        });
    }
});
</script>
