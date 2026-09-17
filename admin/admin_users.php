<?php
// ملف: admin_users.php
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// التحقق من تسجيل الدخول لمنع الدخول المباشر للرابط
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
include(__DIR__ . '/../core/config.php");
include(__DIR__ . '/../core/helpers.php");

// معالجة حذف مستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    require_csrf();
    $user_id = intval($_POST['delete_user']);
    if ($user_id > 0) {
        $conn->query("DELETE FROM users WHERE id = $user_id");
        $success = "تم حذف المستخدم بنجاح!";
    }
}

// معالجة تعديل نسبة العمولة الافتراضية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_commission'])) {
    require_csrf();
    $user_id = intval($_POST['user_id']);
    $default_merchant_commission = floatval($_POST['default_merchant_commission']);
    
    // التحقق من أن المستخدم تاجر
    $check = $conn->query("SELECT user_type FROM users WHERE id = $user_id")->fetch_assoc();
    if ($check && $check['user_type'] == 'تاجر') {
        $stmt = $conn->prepare("UPDATE users SET default_merchant_commission = ? WHERE id = ?");
        $stmt->bind_param("di", $default_merchant_commission, $user_id);
        if ($stmt->execute()) {
            $success = "تم تحديث عمولة التاجر الافتراضية بنجاح!";
        } else {
            $error = "حدث خطأ أثناء تحديث العمولة: " . $stmt->error;
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">إدارة المستخدمين</h1>
</div>

<!-- رسائل النجاح/الخطأ -->
<?php if(isset($success)): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-check-circle mr-2'></i>
    <?= $success ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-error-circle mr-2'></i>
    <?= $error ?>
</div>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stat-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                <i class='bx bx-user text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي المستخدمين</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-green-500">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg mr-4">
                <i class='bx bx-user-plus text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">مسوقين</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'مسوق'")->fetch_assoc()['total'] ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-purple-500">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg mr-4">
                <i class='bx bx-store text-purple-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">تجار</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'تاجر'")->fetch_assoc()['total'] ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- جدول المستخدمين -->
<div class="stat-card p-6">
    <h3 class="text-xl font-bold mb-4 text-gray-800">قائمة المستخدمين</h3>
    <div class="overflow-x-auto">
        <table class="w-full" style="table-layout: auto !important; max-width: none !important;">
            <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">#</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">الاسم الكامل</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">البريد الإلكتروني</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">الهاتف</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">نوع الحساب</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">تاريخ التسجيل</th>
                    <th class="p-4 text-right text-gray-700 font-bold whitespace-nowrap">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
                while($user = $users->fetch_assoc()):
                ?>
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="p-4 text-gray-600 whitespace-nowrap"><?= $user['id'] ?></td>
                    <td class="p-4 whitespace-nowrap">
                        <div class="text-gray-800 font-medium"><?= htmlspecialchars($user['fullname'] ?? '') ?></div>
                    </td>
                    <td class="p-4 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($user['email'] ?? '') ?></td>
                    <td class="p-4 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($user['phone'] ?? '') ?></td>
                    <td class="p-4 whitespace-nowrap">
                        <span class="px-3 py-1 rounded-full text-white <?= $user['user_type'] == 'مسوق' ? 'bg-green-500' : 'bg-purple-500' ?> text-sm font-medium">
                            <?= htmlspecialchars($user['user_type'] ?? '') ?>
                        </span>
                    </td>
                    <td class="p-4 text-gray-500 text-sm whitespace-nowrap"><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                    <td class="p-4 whitespace-nowrap">
                        <div class="flex space-x-2 space-x-reverse">
                            <?php if($user['user_type'] == 'تاجر'): ?>
                            <button onclick="openEditUserModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['fullname']) ?>', <?= $user['default_merchant_commission'] ?? 0 ?>)" 
                               class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm flex items-center transition">
                                <i class='bx bx-edit mr-1'></i>
                                تعديل
                            </button>
                            <?php endif; ?>
                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_user" value="<?= $user['id'] ?>">
                                <button type="submit"
                               class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 text-sm flex items-center transition">
                                <i class='bx bx-trash mr-1'></i>
                                حذف
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal تعديل التاجر -->
<div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
            <h3 class="text-2xl font-bold text-gray-800">إعدادات التاجر</h3>
            <button onclick="closeEditUserModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="update_commission" value="1">
            <input type="hidden" name="user_id" id="edit-user-id">
            
            <div class="mb-4">
                <p class="text-gray-600 mb-2">اسم التاجر: <strong id="edit-user-name" class="text-gray-800"></strong></p>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2 font-medium">العمولة الافتراضية المستقطعة (د.ل) *</label>
                <div class="relative">
                    <input type="number" name="default_merchant_commission" id="edit-user-commission" step="0.01" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                    <div class="absolute left-3 top-3 text-gray-400">د.ل</div>
                </div>
                <p class="text-xs text-gray-500 mt-1">يتم تطبيق هذه القيمة المعينة مسبقاً على هذا التاجر كعمولة للشركة.</p>
            </div>
            
            <div class="flex justify-end space-x-3 space-x-reverse mt-8 pt-6 border-t border-gray-200">
                <button type="button" onclick="closeEditUserModal()" 
                        class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-[#4b6b2f] text-white rounded-lg hover:bg-[#3a5524] transition font-medium flex items-center">
                    <i class='bx bx-save mr-2'></i>
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(id, name, commission) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-user-name').innerText = name;
    document.getElementById('edit-user-commission').value = commission || 0;
    
    document.getElementById('editUserModal').classList.remove('hidden');
    document.getElementById('editUserModal').classList.add('flex');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
    document.getElementById('editUserModal').classList.remove('flex');
}
</script>