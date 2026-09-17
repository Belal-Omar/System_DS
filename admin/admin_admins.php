<?php
if (isset($conn) && is_object($conn)) {
    @$conn->query("ALTER TABLE admins MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin'");
    @$conn->query("UPDATE admins SET role = 'shipping_company' WHERE (role = '' OR role = 'admin') AND shipping_company_id IS NOT NULL AND shipping_company_id > 0");
}
// ملف: admin_admins.php
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include(__DIR__ . '/core/config.php");
/** @var mysqli $conn */
include(__DIR__ . '/core/helpers.php");

ensure_admin_permissions_schema($conn);

// التحقق من صلاحيات المدير الرئيسي
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin') {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
            <i class='bx bx-error-alt mr-2'></i>
            ليس لديك صلاحية للوصول إلى هذه الصفحة
          </div>";
    return;
}

$manageable_pages = get_admin_manageable_pages();
$roles_with_tabs = ['admin', 'marketing'];

$shipping_companies = [];
$sc_res = $conn->query("SELECT id, name FROM shipping_accounts ORDER BY name ASC");
if ($sc_res) {
    $shipping_companies = $sc_res->fetch_all(MYSQLI_ASSOC);
}

if (!function_exists('admin_collect_posted_pages')) {
    function admin_collect_posted_pages(array $manageable_pages): array {
        $posted = $_POST['allowed_pages'] ?? [];
        if (!is_array($posted)) {
            return [];
        }
        return array_values(array_intersect($posted, array_keys($manageable_pages)));
    }
}

// معالجة إضافة مدير جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $username = clean_input($_POST['username']);
    $password = password_hash(clean_input($_POST['password']), PASSWORD_DEFAULT);
    $fullname = clean_input($_POST['fullname']);
    $email = clean_input($_POST['email']);
    $role = clean_input($_POST['role']);
    $allowed_roles = ['admin', 'super_admin', 'support', 'marketing', 'shipping_company', 'shipping_company'];
    if (!in_array($role, $allowed_roles, true)) {
        $role = 'admin';
    }

    $allowed_pages_json = null;
    if (in_array($role, $roles_with_tabs, true)) {
        $allowed_pages_json = encode_admin_allowed_pages(admin_collect_posted_pages($manageable_pages));
    }

    $shipping_company_id = null;
    if ($role === 'shipping_company' && isset($_POST['shipping_company_id'])) {
        $shipping_company_id = (int) $_POST['shipping_company_id'];
    }

    $stmt = $conn->prepare("INSERT INTO admins (username, password, fullname, email, role, allowed_pages, shipping_company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $username, $password, $fullname, $email, $role, $allowed_pages_json, $shipping_company_id);

    if ($stmt->execute()) {
        $new_admin_id = $stmt->insert_id;

        if ($role == 'support') {
            $conn->query("INSERT INTO support_stats (support_id, confirmation_rate, delivery_rate, cancellation_rate, total_orders, completed_orders, cancelled_orders)
                         VALUES ($new_admin_id, 0, 0, 0, 0, 0, 0)");
        }

        echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                <i class='bx bx-check-circle mr-2'></i>
                تم إضافة المدير بنجاح
              </div>";
    } else {
        echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                <i class='bx bx-error-alt mr-2'></i>
                فشل في إضافة المدير: " . htmlspecialchars($stmt->error) . "
              </div>";
    }
    $stmt->close();
}

// معالجة تعديل مدير
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_admin'])) {
    $admin_id = intval($_POST['admin_id'] ?? 0);
    $fullname = clean_input($_POST['fullname'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $role = clean_input($_POST['role'] ?? '');
    $allowed_roles = ['admin', 'super_admin', 'support', 'marketing', 'shipping_company', 'shipping_company'];
    if (!in_array($role, $allowed_roles, true)) {
        $role = 'admin';
    }

    if ($admin_id > 0) {
        $allowed_pages_json = null;
        if (in_array($role, $roles_with_tabs, true)) {
            $allowed_pages_json = encode_admin_allowed_pages(admin_collect_posted_pages($manageable_pages));
        }

        $shipping_company_id = null;
        if ($role === 'shipping_company' && isset($_POST['shipping_company_id'])) {
            $shipping_company_id = (int) $_POST['shipping_company_id'];
        }

        $new_password = trim($_POST['password'] ?? '');
        if ($new_password !== '') {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET fullname = ?, email = ?, role = ?, allowed_pages = ?, password = ?, shipping_company_id = ? WHERE id = ?");
            $stmt->bind_param("sssssii", $fullname, $email, $role, $allowed_pages_json, $hashed, $shipping_company_id, $admin_id);
        } else {
            $stmt = $conn->prepare("UPDATE admins SET fullname = ?, email = ?, role = ?, allowed_pages = ?, shipping_company_id = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $fullname, $email, $role, $allowed_pages_json, $shipping_company_id, $admin_id);
        }

        if ($stmt->execute()) {
            if ($role == 'support') {
                $exists = $conn->query("SELECT id FROM support_stats WHERE support_id = $admin_id LIMIT 1");
                if ($exists && $exists->num_rows === 0) {
                    $conn->query("INSERT INTO support_stats (support_id, confirmation_rate, delivery_rate, cancellation_rate, total_orders, completed_orders, cancelled_orders)
                                 VALUES ($admin_id, 0, 0, 0, 0, 0, 0)");
                }
            }
            // تحديث جلسة المستخدم لو كان يعدّل نفسه
            if ($admin_id == ($_SESSION['admin_id'] ?? 0)) {
                $_SESSION['admin_role'] = $role;
                $_SESSION['admin_fullname'] = $fullname;
                $_SESSION['admin_allowed_pages'] = $allowed_pages_json;
                $_SESSION['admin_allowed_pages_loaded'] = true;
            }
            echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>
                    <i class='bx bx-check-circle mr-2'></i>
                    تم تحديث بيانات المدير بنجاح
                  </div>";
        } else {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>
                    <i class='bx bx-error-alt mr-2'></i>
                    فشل في تحديث المدير: " . htmlspecialchars($stmt->error) . "
                  </div>";
        }
        $stmt->close();
    }
}

// معالجة حذف مدير
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = intval($_POST['delete_admin']);
    if ($admin_id != $_SESSION['admin_id'] && $admin_id > 0) {
        $conn->query("DELETE FROM admins WHERE id = $admin_id");
    }
}

if (!function_exists('admin_role_badge')) {
    function admin_role_badge($role) {
        switch ($role) {
            case 'super_admin':
                return ['bg-purple-500', 'مدير رئيسي'];
            case 'support':
                return ['bg-orange-500', 'دعم فني'];
            case 'marketing':
                return ['bg-pink-500', 'Marketing'];
            case 'shipping_company':
                return ['bg-teal-500', 'شركة شحن'];
            default:
                return ['bg-blue-500', 'مدير'];
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">إدارة المديرين</h1>
    <button onclick="openAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
        <i class='bx bx-plus mr-2'></i>
        إضافة مدير جديد
    </button>
</div>

<!-- بطاقات إحصائيات المديرين -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-8">
    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                <i class='bx bx-user-circle text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي المديرين</p>
                <h3 class="text-2xl font-bold">
                    <?= $conn->query("SELECT COUNT(*) as total FROM admins")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg mr-4">
                <i class='bx bx-check-shield text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">المديرين النشطين</p>
                <h3 class="text-2xl font-bold">
                    <?= $conn->query("SELECT COUNT(*) as total FROM admins WHERE is_active = 1")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg mr-4">
                <i class='bx bx-star text-purple-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">المديرين الرئيسيين</p>
                <h3 class="text-2xl font-bold">
                    <?= $conn->query("SELECT COUNT(*) as total FROM admins WHERE role = 'super_admin'")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-orange-100 rounded-lg mr-4">
                <i class='bx bx-headphone text-orange-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">الدعم الفني</p>
                <h3 class="text-2xl font-bold">
                    <?= $conn->query("SELECT COUNT(*) as total FROM admins WHERE role = 'support'")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-pink-100 rounded-lg mr-4">
                <i class='bx bx-bullseye text-pink-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">Marketing</p>
                <h3 class="text-2xl font-bold">
                    <?= $conn->query("SELECT COUNT(*) as total FROM admins WHERE role = 'marketing'")->fetch_assoc()['total'] ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<!-- جدول المديرين -->
<div class="stat-card p-6">
    <h3 class="text-xl font-bold mb-4">قائمة المديرين</h3>
    <div class="overflow-x-auto">
        <table class="w-full" style="table-layout: auto !important; max-width: none !important;">
            <thead>
                <tr class="bg-gray-50">
                    <th class="p-3 text-right whitespace-nowrap">#</th>
                    <th class="p-3 text-right whitespace-nowrap">اسم المستخدم</th>
                    <th class="p-3 text-right whitespace-nowrap">الاسم الكامل</th>
                    <th class="p-3 text-right whitespace-nowrap">البريد الإلكتروني</th>
                    <th class="p-3 text-right whitespace-nowrap">الدور</th>
                    <th class="p-3 text-right whitespace-nowrap">التبويبات</th>
                    <th class="p-3 text-right whitespace-nowrap">الحالة</th>
                    <th class="p-3 text-right whitespace-nowrap">آخر دخول</th>
                    <th class="p-3 text-right whitespace-nowrap">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $admins = $conn->query("SELECT a.*, s.name as company_name FROM admins a LEFT JOIN shipping_accounts s ON a.shipping_company_id = s.id ORDER BY a.created_at DESC");
                while ($admin = $admins->fetch_assoc()):
                    list($roleClass, $roleName) = admin_role_badge($admin['role']);
                    $effective = get_admin_effective_pages($admin['role'], $admin['allowed_pages'] ?? null);
                    $page_labels = [];
                    foreach ($effective as $pk) {
                        if (isset($manageable_pages[$pk])) {
                            $page_labels[] = $manageable_pages[$pk];
                        }
                    }
                    if ($admin['role'] === 'super_admin') {
                        $tabs_summary = 'الكل';
                    } elseif ($admin['role'] === 'support') {
                        $tabs_summary = 'الدعم فقط';
                    } elseif (empty($page_labels)) {
                        $tabs_summary = 'لا يوجد';
                    } else {
                        $tabs_summary = count($page_labels) . ' تبويب';
                    }
                ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3 whitespace-nowrap"><?= $admin['id'] ?></td>
                    <td class="p-3 font-medium whitespace-nowrap"><?= htmlspecialchars($admin['username']) ?></td>
                    <td class="p-3 whitespace-nowrap"><?= htmlspecialchars($admin['fullname']) ?></td>
                    <td class="p-3 whitespace-nowrap"><?= htmlspecialchars($admin['email']) ?></td>
                    <td class="p-3 whitespace-nowrap">
                        <span class="px-2 py-1 rounded text-white <?= $roleClass ?>">
                            <?= $roleName ?>
                        </span>
                        <?php if (!empty($admin['company_name'])): ?>
                            <br><span class="text-xs text-gray-500 mt-1 font-bold inline-block"><i class='bx bx-buildings'></i> <?= htmlspecialchars($admin['company_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 whitespace-nowrap" title="<?= htmlspecialchars(implode('، ', $page_labels)) ?>">
                        <span class="text-sm text-gray-600"><?= htmlspecialchars($tabs_summary) ?></span>
                    </td>
                    <td class="p-3 whitespace-nowrap">
                        <span class="px-2 py-1 rounded text-white <?= $admin['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>">
                            <?= $admin['is_active'] ? 'نشط' : 'غير نشط' ?>
                        </span>
                    </td>
                    <td class="p-3 whitespace-nowrap"><?= $admin['last_login'] ? date('Y-m-d H:i', strtotime($admin['last_login'])) : 'لم يسجل دخول' ?></td>
                    <td class="p-3 whitespace-nowrap">
                        <div class="flex space-x-2 space-x-reverse">
                            <button type="button"
                                class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm flex items-center transition"
                                onclick='openEditModal(<?= json_encode([
                                    "id" => (int)$admin["id"],
                                    "username" => $admin["username"],
                                    "fullname" => $admin["fullname"],
                                    "email" => $admin["email"],
                                    "role" => $admin["role"],
                                    "shipping_company_id" => $admin["shipping_company_id"] ?? null,
                                    "pages" => decode_admin_allowed_pages($admin["allowed_pages"] ?? null) ?? (
                                        $admin["role"] === "marketing" ? ["marketing"] : array_keys($manageable_pages)
                                    )
                                ], JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class='bx bx-edit mr-1'></i>
                                تعديل
                            </button>
                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المدير؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_admin" value="<?= $admin['id'] ?>">
                                <button type="submit"
                               class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm flex items-center transition">
                                <i class='bx bx-trash mr-1'></i>
                                حذف
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
if (!function_exists('render_pages_checkboxes')) {
    function render_pages_checkboxes($prefix, $manageable_pages) {
        ?>
        <div class="mb-4" id="<?= $prefix ?>_pages_wrap" style="display:none;">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-gray-700 font-medium">التبويبات المسموح بها</label>
                <div class="flex gap-2 text-sm">
                    <button type="button" onclick="toggleAllPages('<?= $prefix ?>', true)" class="text-blue-600 hover:underline">تحديد الكل</button>
                    <span class="text-gray-300">|</span>
                    <button type="button" onclick="toggleAllPages('<?= $prefix ?>', false)" class="text-blue-600 hover:underline">إلغاء الكل</button>
                </div>
            </div>
            <p class="text-xs text-gray-500 mb-3">اختر التبويبات الظاهرة لهذا الحساب. أي تبويب غير محدد لن يظهر له ولن يستطيع فتحه.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50">
                <?php foreach ($manageable_pages as $key => $label): ?>
                <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-white p-1 rounded">
                    <input type="checkbox" name="allowed_pages[]" value="<?= htmlspecialchars($key) ?>"
                           class="<?= $prefix ?>-page-cb rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span><?= htmlspecialchars($label) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
?>

<!-- Modal إضافة مدير جديد -->
<div id="adminModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-bold mb-4">إضافة مدير جديد</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="add_admin" value="1">

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">اسم المستخدم</label>
                <input type="text" name="username" required
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">كلمة المرور</label>
                <input type="password" name="password" required
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">الاسم الكامل</label>
                <input type="text" name="fullname" required
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">البريد الإلكتروني</label>
                <input type="email" name="email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">الدور</label>
                <select name="role" id="add_role" onchange="syncPagesVisibility('add')"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="admin">مدير</option>
                    <option value="marketing">Marketing</option>
                    <option value="support">دعم فني</option>
                    <option value="super_admin">مدير رئيسي</option>
                    <option value="shipping_company">شركة شحن</option>
                </select>
            </div>

            <div class="mb-4" id="add_shipping_company_wrap" style="display: none;">
                <label class="block text-gray-700 mb-2">اختر شركة الشحن</label>
                <select name="shipping_company_id" id="add_shipping_company_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="">-- اختر الشركة --</option>
                    <?php foreach ($shipping_companies as $sc): ?>
                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php render_pages_checkboxes('add', $manageable_pages); ?>

            <div class="flex justify-end space-x-2 space-x-reverse">
                <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    إضافة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل مدير -->
<div id="editAdminModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-bold mb-4">تعديل مدير</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="edit_admin" value="1">
            <input type="hidden" name="admin_id" id="edit_admin_id" value="">

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">اسم المستخدم</label>
                <input type="text" id="edit_username" disabled
                       class="w-full px-3 py-2 border border-gray-200 rounded bg-gray-100 text-gray-600">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">كلمة المرور الجديدة <span class="text-gray-400 text-xs">(اتركها فارغة للإبقاء على الحالية)</span></label>
                <input type="password" name="password"
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">الاسم الكامل</label>
                <input type="text" name="fullname" id="edit_fullname" required
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">البريد الإلكتروني</label>
                <input type="email" name="email" id="edit_email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 mb-2">الدور</label>
                <select name="role" id="edit_role" onchange="syncPagesVisibility('edit')"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="admin">مدير</option>
                    <option value="marketing">Marketing</option>
                    <option value="support">دعم فني</option>
                    <option value="super_admin">مدير رئيسي</option>
                    <option value="shipping_company">شركة شحن</option>
                </select>
            </div>

            <div class="mb-4" id="edit_shipping_company_wrap" style="display: none;">
                <label class="block text-gray-700 mb-2">اختر شركة الشحن</label>
                <select name="shipping_company_id" id="edit_shipping_company_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    <option value="">-- اختر الشركة --</option>
                    <?php foreach ($shipping_companies as $sc): ?>
                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php render_pages_checkboxes('edit', $manageable_pages); ?>

            <div class="flex justify-end space-x-2 space-x-reverse">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function syncPagesVisibility(prefix) {
    var roleEl = document.getElementById(prefix + '_role');
    var wrap = document.getElementById(prefix + '_pages_wrap');
    if (!roleEl || !wrap) return;
    var role = roleEl.value;
    var show = (role === 'admin' || role === 'marketing');
    wrap.style.display = show ? 'block' : 'none';

    var scWrap = document.getElementById(prefix + '_shipping_company_wrap');
    if (scWrap) {
        scWrap.style.display = (role === 'shipping_company') ? 'block' : 'none';
        if (role !== 'shipping_company') {
            document.getElementById(prefix + '_shipping_company_id').value = '';
        }
    }

    if (show && prefix === 'add') {
        // افتراضي: كل التبويبات للمدير، والتسويق فقط لـ marketing
        var boxes = document.querySelectorAll('.' + prefix + '-page-cb');
        boxes.forEach(function(cb) {
            if (role === 'marketing') {
                cb.checked = (cb.value === 'marketing');
            } else if (!cb.dataset.touched) {
                cb.checked = true;
            }
        });
    }
}

function toggleAllPages(prefix, checked) {
    document.querySelectorAll('.' + prefix + '-page-cb').forEach(function(cb) {
        cb.checked = checked;
        cb.dataset.touched = '1';
    });
}

function openAddModal() {
    document.getElementById('adminModal').classList.remove('hidden');
    document.getElementById('adminModal').classList.add('flex');
    document.querySelectorAll('.add-page-cb').forEach(function(cb) {
        cb.checked = true;
        delete cb.dataset.touched;
    });
    document.getElementById('add_role').value = 'admin';
    syncPagesVisibility('add');
}

function closeAddModal() {
    document.getElementById('adminModal').classList.add('hidden');
    document.getElementById('adminModal').classList.remove('flex');
}

function openEditModal(data) {
    document.getElementById('edit_admin_id').value = data.id;
    document.getElementById('edit_username').value = data.username || '';
    document.getElementById('edit_fullname').value = data.fullname || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_role').value = data.role || 'admin';
    if (document.getElementById('edit_shipping_company_id') && data.shipping_company_id) {
        document.getElementById('edit_shipping_company_id').value = data.shipping_company_id;
    }

    var pages = data.pages || [];
    document.querySelectorAll('.edit-page-cb').forEach(function(cb) {
        cb.checked = pages.indexOf(cb.value) !== -1;
        cb.dataset.touched = '1';
    });

    syncPagesVisibility('edit');
    document.getElementById('editAdminModal').classList.remove('hidden');
    document.getElementById('editAdminModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editAdminModal').classList.add('hidden');
    document.getElementById('editAdminModal').classList.remove('flex');
}
</script>


