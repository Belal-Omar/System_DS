<?php
/** @var string $sessionRole */
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if ($sessionRole !== 'super_admin') {
    echo "<div class='p-8'><div class='bg-red-100 text-red-700 p-4 rounded-xl'>لا تملك صلاحية للوصول لهذه الصفحة.</div></div>";
    return;
}

// معالجة الإضافة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    require_csrf();
    $name = clean_input($_POST['name'] ?? '');
    $code = clean_input($_POST['code'] ?? '');
    
    if ($name && $code) {
        $stmt = $conn->prepare("INSERT INTO shipping_accounts (name, code) VALUES (?, ?)");
        if (!$stmt) {
            $_SESSION['flash_error'] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $name, $code);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "تمت إضافة شركة الشحن بنجاح!";
            } else {
                $_SESSION['flash_error'] = "حدث خطأ أثناء الإضافة. قد يكون كود الشركة مكرراً.";
            }
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=shipping_accounts';</script>";
    exit;
}

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_company'])) {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $name = clean_input($_POST['name'] ?? '');
    $code = clean_input($_POST['code'] ?? '');
    
    if ($id && $name && $code) {
        $stmt = $conn->prepare("UPDATE shipping_accounts SET name = ?, code = ? WHERE id = ?");
        if (!$stmt) {
            $_SESSION['flash_error'] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ssi", $name, $code, $id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "تم تعديل شركة الشحن بنجاح!";
            } else {
                $_SESSION['flash_error'] = "حدث خطأ أثناء التعديل. قد يكون كود الشركة مكرراً.";
            }
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=shipping_accounts';</script>";
    exit;
}

// معالجة الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        // يمكننا إضافة فحص هنا لعدم حذف شركة مسند إليها طلبات أو مديرين، لكن للتبسيط سنقوم بالحذف.
        $stmt = $conn->prepare("DELETE FROM shipping_accounts WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash_success'] = "تم حذف شركة الشحن بنجاح.";
    }
    echo "<script>window.location.href='admin_panel.php?page=shipping_accounts';</script>";
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// جلب الشركات
$companies = $conn->query("SELECT * FROM shipping_accounts ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$reps_data = $conn->query("
    SELECT r.*, 
           (SELECT COUNT(*) FROM support_orders WHERE shipping_rep_id = r.id) as orders_count
    FROM shipping_company_reps r
")->fetch_all(MYSQLI_ASSOC);
$reps_by_company = [];
foreach ($reps_data as $r) {
    $reps_by_company[$r['company_id']][] = $r;
}
?>

<div class="max-w-6xl mx-auto px-4 py-8 space-y-8 font-sans">
    
    <?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl font-medium shadow-sm flex items-center">
        <i class='bx bx-check-circle mr-2 text-xl'></i><?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl font-medium shadow-sm flex items-center">
        <i class='bx bx-error-circle mr-2 text-xl'></i><?= htmlspecialchars($flash_error) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 flex items-center">
                <span class="bg-indigo-600 text-white p-2 rounded-xl mr-4 shadow-lg shadow-indigo-100">
                    <i class='bx bx-bus-school'></i>
                </span>
                حسابات شركات الشحن
            </h1>
            <p class="text-slate-500 mt-2 font-medium">إدارة أسماء وأكواد شركات الشحن لربطها بالطلبات والمديرين.</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-indigo-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-indigo-700 transition shadow-md whitespace-nowrap">
            <i class='bx bx-plus mr-1'></i> إضافة شركة شحن
        </button>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="p-4">اسم الشركة</th>
                        <th class="p-4">الكود (Code)</th>
                        <th class="p-4">تاريخ الإضافة</th>
                        <th class="p-4">المناديب (الطلبات)</th>
                        <th class="p-4 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <?php if(empty($companies)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-400">لا توجد شركات شحن مضافة حتى الآن.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($companies as $c): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($c['name']) ?></td>
                            <td class="p-4">
                                <span class="bg-indigo-50 text-indigo-700 font-mono font-bold px-3 py-1 rounded-lg border border-indigo-100">
                                    <?= htmlspecialchars($c['code']) ?>
                                </span>
                            </td>
                            <td class="p-4 text-slate-500"><?= date('Y-m-d h:i A', strtotime($c['created_at'])) ?></td>
                            <td class="p-4">
                                <?php if (!empty($reps_by_company[$c['id']])): ?>
                                    <select onchange="if(this.value) window.open('admin_panel.php?page=shipping_rep_stats&id=' + this.value, '_blank'); this.value='';" class="w-full text-xs bg-slate-50 border border-slate-200 rounded p-2 focus:ring-2 focus:ring-indigo-200 cursor-pointer">
                                        <option value="">-- اختر المندوب لعرض بياناته --</option>
                                        <?php foreach ($reps_by_company[$c['id']] as $rep): ?>
                                            <option value="<?= $rep['id'] ?>">
                                                <?= htmlspecialchars($rep['name']) ?> - <?= htmlspecialchars($rep['phone']) ?> (<?= $rep['orders_count'] ?> طلب)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">لا يوجد مناديب</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center space-x-2 space-x-reverse">
                                <a href="admin_panel.php?page=shipping_company_report&id=<?= $c['id'] ?>" class="bg-slate-100 text-slate-600 hover:bg-emerald-100 hover:text-emerald-700 px-3 py-1.5 rounded-lg transition font-medium text-xs">
                                    <i class='bx bx-bar-chart-alt-2 mr-1'></i>تقرير
                                </a>
                                <button onclick="openEditModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>', '<?= htmlspecialchars(addslashes($c['code'])) ?>')" class="bg-slate-100 text-slate-600 hover:bg-indigo-100 hover:text-indigo-700 px-3 py-1.5 rounded-lg transition font-medium text-xs">
                                    <i class='bx bx-edit mr-1'></i>تعديل
                                </button>
                                <form method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من حذف شركة الشحن هذه؟');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="delete_company" class="bg-slate-100 text-slate-600 hover:bg-rose-100 hover:text-rose-700 px-3 py-1.5 rounded-lg transition font-medium text-xs">
                                        <i class='bx bx-trash mr-1'></i>حذف
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

<!-- Modal إضافة -->
<div id="addModal" class="hidden fixed inset-0 bg-slate-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform transition-all">
        <div class="bg-slate-50 border-b border-slate-100 px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-slate-800">إضافة شركة شحن</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition"><i class='bx bx-x text-2xl'></i></button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">اسم الشركة</label>
                <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="مثال: Aramex">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">كود الشركة (Code)</label>
                <input type="text" name="code" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 font-mono rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition" placeholder="مثال: AR-01">
                <p class="text-xs text-slate-500 mt-2">يجب أن يكون الكود فريداً وألا يتكرر.</p>
            </div>
            
            <div class="pt-2">
                <button type="submit" name="add_company" class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-indigo-700 transition shadow-md">حفظ وإضافة</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل -->
<div id="editModal" class="hidden fixed inset-0 bg-slate-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform transition-all">
        <div class="bg-slate-50 border-b border-slate-100 px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-slate-800">تعديل شركة الشحن</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition"><i class='bx bx-x text-2xl'></i></button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="edit_id">
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">اسم الشركة</label>
                <input type="text" name="name" id="edit_name" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">كود الشركة (Code)</label>
                <input type="text" name="code" id="edit_code" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 font-mono rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
            </div>
            
            <div class="pt-2">
                <button type="submit" name="edit_company" class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-xl hover:bg-indigo-700 transition shadow-md">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, code) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
