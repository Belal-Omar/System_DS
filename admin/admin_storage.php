<?php
// admin_storage.php
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['super_admin', 'admin'])) {
    return;
}

// معالجة إضافة رصيد للمخزن الرئيسي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_main_stock') {
    $code = $conn->real_escape_string(trim($_POST['product_code']));
    $quantity = (int)$_POST['quantity'];
    
    if ($code !== '' && $quantity > 0) {
        $stmt = $conn->prepare("UPDATE shipping_inventory_products SET stock_quantity = stock_quantity + ? WHERE product_code = ?");
        $stmt->bind_param('is', $quantity, $code);
        if ($stmt->execute()) {
            $_SESSION['storage_flash_success'] = 'تم إضافة الرصيد للمخزن الرئيسي بنجاح.';
        } else {
            $_SESSION['storage_flash_error'] = 'حدث خطأ أثناء إضافة الرصيد.';
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// معالجة إضافة منتج جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $code = $conn->real_escape_string(trim($_POST['product_code']));
    $name = $conn->real_escape_string(trim($_POST['product_name']));
    
    if ($code !== '' && $name !== '') {
        $stmt = $conn->prepare("INSERT INTO shipping_inventory_products (product_code, product_name) VALUES (?, ?)");
        $stmt->bind_param('ss', $code, $name);
        if ($stmt->execute()) {
            $_SESSION['storage_flash_success'] = 'تم إضافة المنتج للمخزن بنجاح.';
        } else {
            $_SESSION['storage_flash_error'] = 'خطأ في إضافة المنتج، ربما الكود مكرر.';
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// معالجة توزيع أو تحديث رصيد شركة الشحن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $company_id = (int)$_POST['shipping_company_id'];
    $product_code = $conn->real_escape_string($_POST['product_code']);
    $quantity = (int)$_POST['quantity']; // الكمية الجديدة
    
    if ($company_id > 0 && $product_code !== '' && $quantity > 0) {
        $stmt = $conn->prepare("
            INSERT INTO shipping_inventory (shipping_company_id, product_code, quantity) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + ?
        ");
        $stmt->bind_param('isii', $company_id, $product_code, $quantity, $quantity);
        if ($stmt->execute()) {
            if (function_exists('main_inventory_deduct')) {
                main_inventory_deduct($conn, $product_code, $quantity);
            }
            $_SESSION['storage_flash_success'] = 'تم إضافة الرصيد لشركة الشحن بنجاح.';
        } else {
            $_SESSION['storage_flash_error'] = 'خطأ في الإضافة.';
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// معالجة تفعيل/تعطيل منتج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_product') {
    $product_id = (int)$_POST['product_id'];
    if ($product_id > 0) {
        $conn->query("UPDATE shipping_inventory_products SET status = IF(status='active', 'disabled', 'active') WHERE id = $product_id");
        $_SESSION['storage_flash_success'] = 'تم تحديث حالة المنتج بنجاح.';
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// معالجة حذف منتج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $product_id = (int)$_POST['product_id'];
    if ($product_id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM shipping_inventory_products WHERE id = ?");
            $stmt->bind_param('i', $product_id);
            if ($stmt->execute()) {
                $_SESSION['storage_flash_success'] = 'تم حذف المنتج بنجاح.';
            } else {
                $_SESSION['storage_flash_error'] = 'لا يمكن حذف المنتج لارتباطه ببيانات أخرى، قم بتعطيله بدلاً من ذلك.';
            }
        } catch (Exception $e) {
            $_SESSION['storage_flash_error'] = 'لا يمكن حذف المنتج لارتباطه ببيانات أخرى، قم بتعطيله بدلاً من ذلك.';
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// معالجة تفعيل/تعطيل رصيد شركة الشحن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_company_stock') {
    $inv_id = (int)$_POST['inv_id'];
    if ($inv_id > 0) {
        @$conn->query("ALTER TABLE shipping_inventory ADD COLUMN IF NOT EXISTS status ENUM('active', 'disabled') DEFAULT 'active'");
        $conn->query("UPDATE shipping_inventory SET status = IF(status='active', 'disabled', 'active') WHERE id = $inv_id");
        $_SESSION['storage_flash_success'] = 'تم تحديث حالة رصيد الشركة بنجاح.';
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// معالجة حذف رصيد شركة الشحن وإرجاع الرصيد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_company_stock') {
    $inv_id = (int)$_POST['inv_id'];
    if ($inv_id > 0) {
        $res = $conn->query("SELECT product_code, quantity FROM shipping_inventory WHERE id = $inv_id");
        if ($res && $row = $res->fetch_assoc()) {
            $qty = (int)$row['quantity'];
            $code = $conn->real_escape_string($row['product_code']);
            if ($qty > 0) {
                $conn->query("UPDATE shipping_inventory_products SET stock_quantity = stock_quantity + $qty WHERE product_code = '$code'");
            }
            $conn->query("DELETE FROM shipping_inventory WHERE id = $inv_id");
            $_SESSION['storage_flash_success'] = 'تم حذف الرصيد بنجاح ' . ($qty > 0 ? "وإرجاع $qty قطعة إلى المخزون الرئيسي." : '');
        }
    }
    echo "<script>window.location.href='admin_panel.php?page=storage';</script>";
    exit;
}

// جلب المنتجات
$products = [];
$search_product = clean_input($_GET['search_product'] ?? '');
$search_query_p = "";
if (!empty($search_product)) {
    $search_query_p = " WHERE product_name LIKE '%$search_product%' OR product_code LIKE '%$search_product%' ";
}
$res_prod = $conn->query("SELECT * FROM shipping_inventory_products $search_query_p ORDER BY product_name ASC");
if ($res_prod) {
    while ($r = $res_prod->fetch_assoc()) {
        $products[] = $r;
    }
}

// Sales Statistics Logic
$sales_period = $_GET['sales_period'] ?? 'all';
$sales_date_filter = "";
if ($sales_period === 'day') {
    $sales_date_filter = " AND DATE(created_at) = CURDATE() ";
} elseif ($sales_period === 'week') {
    $sales_date_filter = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
} elseif ($sales_period === 'month') {
    $sales_date_filter = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) ";
} elseif ($sales_period === '3months') {
    $sales_date_filter = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) ";
} elseif ($sales_period === '6months') {
    $sales_date_filter = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) ";
} elseif ($sales_period === 'year') {
    $sales_date_filter = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) ";
}

$sales_stats = [];
$res_sales = $conn->query("
    SELECT product_code, COUNT(*) as total_orders, SUM(pieces) as total_pieces
    FROM support_orders
    WHERE product_code IS NOT NULL AND product_code != '' 
    AND order_status NOT IN ('cancelled', 'order_cancelled', 'returned')
    $sales_date_filter
    GROUP BY product_code
");
if ($res_sales) {
    while ($row = $res_sales->fetch_assoc()) {
        $sales_stats[$row['product_code']] = [
            'orders' => (int)$row['total_orders'],
            'pieces' => (int)$row['total_pieces']
        ];
    }
}

// جلب شركات الشحن
$companies = [];
$res_comp = $conn->query("SELECT id, name FROM shipping_accounts ORDER BY name ASC");
if ($res_comp) {
    while ($r = $res_comp->fetch_assoc()) {
        $companies[] = $r;
    }
}

// جلب الأرصدة الحالية للشركات
@$conn->query("ALTER TABLE shipping_inventory ADD COLUMN IF NOT EXISTS status ENUM('active', 'disabled') DEFAULT 'active'");

$inv_query = "
    SELECT i.*, c.name as company_name, p.product_name 
    FROM shipping_inventory i
    JOIN shipping_accounts c ON i.shipping_company_id = c.id
    LEFT JOIN shipping_inventory_products p ON i.product_code = p.product_code
    ORDER BY c.name ASC, p.product_name ASC
";
$res_inv = $conn->query($inv_query);
$inventory = [];
if ($res_inv) {
    while ($r = $res_inv->fetch_assoc()) {
        $inventory[] = $r;
    }
}

$low_stock_msg = [];
foreach ($inventory as $inv) {
    if ($inv['quantity'] <= 10) {
        $low_stock_msg[] = "- شركة ({$inv['company_name']}) - منتج ({$inv['product_name']}) رصيد ({$inv['quantity']})";
    }
}
?>

<?php if (!empty($low_stock_msg)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire('تنبيه نواقص المخزون!', <?= json_encode("يوجد نقص بالمخزون لدى شركات الشحن التالية:\n" . implode("\n", $low_stock_msg)) ?>, 'warning');
});
</script>
<?php endif; ?>

<div class="px-4 py-8 font-sans" dir="rtl">
    <div class="flex items-center mb-8">
        <i class='bx bx-box text-blue-600 mr-3 text-4xl'></i>
        <h1 class="text-3xl font-extrabold text-slate-800">إدارة المخزون والتوزيع</h1>
    </div>

    <?php if (isset($_SESSION['storage_flash_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
            <strong class="font-bold">نجاح!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($_SESSION['storage_flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['storage_flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['storage_flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
            <strong class="font-bold">خطأ!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($_SESSION['storage_flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['storage_flash_error']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
        <!-- Add New Product -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-xl font-bold text-slate-700 mb-4 flex items-center"><i class='bx bx-plus-circle text-indigo-500 mr-2'></i> إضافة منتج للمخزن</h3>
            <form method="POST" action="admin_panel.php?page=storage">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="add_product">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">اسم المنتج</label>
                    <input type="text" name="product_name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">كود المنتج</label>
                    <input type="text" name="product_code" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline text-left" dir="ltr">
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full transition-colors">
                    إضافة المنتج
                </button>
            </form>
        </div>

        <!-- Distribute to Shipping Company -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-xl font-bold text-slate-700 mb-4 flex items-center"><i class='bx bx-transfer text-emerald-500 mr-2'></i> توزيع رصيد لشركة شحن</h3>
            <form method="POST" action="admin_panel.php?page=storage">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="update_stock">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">شركة الشحن</label>
                    <select name="shipping_company_id" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                        <option value="">-- اختر الشركة --</option>
                        <?php foreach($companies as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">المنتج</label>
                    <select name="product_code" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                        <option value="">-- اختر المنتج --</option>
                        <?php foreach($products as $p): ?>
                            <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                <option value="<?= htmlspecialchars($p['product_code']) ?>"><?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">الكمية المضافة (سيتم إضافتها على الرصيد الحالي)</label>
                    <input type="number" min="0" name="quantity" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full transition-colors">
                    تحديث الرصيد
                </button>
            </form>
        </div>

        <!-- Add Main Stock -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-xl font-bold text-slate-700 mb-4 flex items-center"><i class='bx bx-archive-in text-blue-500 mr-2'></i> تغذية المخزن الرئيسي</h3>
            <form method="POST" action="admin_panel.php?page=storage">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="add_main_stock">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">المنتج</label>
                    <select name="product_code" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                        <option value="">-- اختر المنتج --</option>
                        <?php foreach($products as $p): ?>
                            <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                <option value="<?= htmlspecialchars($p['product_code']) ?>"><?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">الكمية الواردة للمخزن</label>
                    <input type="number" min="1" name="quantity" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full transition-colors">
                    إضافة الرصيد
                </button>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <!-- إحصائيات المبيعات -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mt-8 mb-8">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <h3 class="text-lg font-bold text-slate-700"><i class='bx bx-trending-up text-indigo-500 mr-2'></i>إحصائيات مبيعات المنتجات</h3>
            <form method="GET" action="admin_panel.php" class="w-full md:w-1/3 flex">
                <input type="hidden" name="page" value="storage">
                <select name="sales_period" class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 text-sm bg-white" onchange="this.form.submit()">
                    <option value="all" <?= $sales_period === 'all' ? 'selected' : '' ?>>كل الأوقات</option>
                    <option value="day" <?= $sales_period === 'day' ? 'selected' : '' ?>>اليوم</option>
                    <option value="week" <?= $sales_period === 'week' ? 'selected' : '' ?>>آخر 7 أيام</option>
                    <option value="month" <?= $sales_period === 'month' ? 'selected' : '' ?>>آخر شهر</option>
                    <option value="3months" <?= $sales_period === '3months' ? 'selected' : '' ?>>آخر 3 شهور</option>
                    <option value="6months" <?= $sales_period === '6months' ? 'selected' : '' ?>>آخر 6 شهور</option>
                    <option value="year" <?= $sales_period === 'year' ? 'selected' : '' ?>>آخر سنة</option>
                </select>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 font-medium">اسم المنتج</th>
                        <th class="px-6 py-3 font-medium">الكود</th>
                        <th class="px-6 py-3 font-medium">عدد الطلبات المسجلة</th>
                        <th class="px-6 py-3 font-medium text-emerald-600">إجمالي القطع المباعة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($products)): ?>
                    <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">لا توجد منتجات</td></tr>
                    <?php else: ?>
                        <?php foreach($products as $p): 
                            $stats = $sales_stats[$p['product_code']] ?? ['orders' => 0, 'pieces' => 0];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?= htmlspecialchars($p['product_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500" dir="ltr"><?= htmlspecialchars($p['product_code']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-700"><?= $stats['orders'] ?> طلب</td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-emerald-600"><?= $stats['pieces'] ?> قطعة</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mt-8 mb-8">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4">
            <h3 class="text-lg font-bold text-slate-700">المنتجات المسجلة في المخزن</h3>
            <form method="GET" action="admin_panel.php" class="w-full md:w-1/3 flex">
                <input type="hidden" name="page" value="storage">
                <input type="text" id="storageSearchInput" name="search_product" value="<?= htmlspecialchars($search_product) ?>" placeholder="بحث باسم أو كود المنتج..." class="w-full p-2 border border-gray-300 rounded-r-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm">
                <button type="submit" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-4 rounded-l-lg border border-r-0 border-gray-300 transition-colors"><i class='bx bx-search text-xl'></i></button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="text-xs text-slate-500 uppercase bg-white">
                    <tr>
                        <th class="px-6 py-4 border-b">اسم المنتج</th>
                        <th class="px-6 py-4 border-b">الكود</th>
                        <th class="px-6 py-4 border-b">رصيد المخزن</th>
                        <th class="px-6 py-4 border-b">الحالة</th>
                        <th class="px-6 py-4 border-b text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500 font-bold">لا توجد منتجات مضافة بعد.</td></tr>
                    <?php else: ?>
                        <?php foreach($products as $p): ?>
                        <tr class="hover:bg-slate-50 transition-colors product-row">
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800 product-name"><?= htmlspecialchars($p['product_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 product-code" dir="ltr"><?= htmlspecialchars($p['product_code']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold <?= ($p['stock_quantity'] ?? 0) < 0 ? 'text-red-600' : 'text-slate-700' ?>"><?= (int)($p['stock_quantity'] ?? 0) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">نشط</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-gray-200 text-gray-600 rounded-full text-xs font-bold">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center flex justify-center gap-2">
                                <form method="POST" action="admin_panel.php?page=storage" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="toggle_product">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <?php if (($p['status'] ?? 'active') === 'active'): ?>
                                        <button type="submit" class="text-orange-500 hover:text-orange-700 bg-orange-50 px-3 py-1 rounded" title="تعطيل"><i class='bx bx-block'></i> تعطيل</button>
                                    <?php else: ?>
                                        <button type="submit" class="text-green-500 hover:text-green-700 bg-green-50 px-3 py-1 rounded" title="تنشيط"><i class='bx bx-check-circle'></i> تنشيط</button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" action="admin_panel.php?page=storage" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 bg-red-50 px-3 py-1 rounded" title="حذف"><i class='bx bx-trash'></i> حذف</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mt-8">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-700">الأرصدة الحالية لدى شركات الشحن</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="text-xs text-slate-500 uppercase bg-white">
                    <tr>
                        <th class="px-6 py-4 border-b">شركة الشحن</th>
                        <th class="px-6 py-4 border-b">المنتج</th>
                        <th class="px-6 py-4 border-b">الكود</th>
                        <th class="px-6 py-4 border-b text-center">الكمية المتوفرة</th>
                        <th class="px-6 py-4 border-b text-center">الحالة</th>
                        <th class="px-6 py-4 border-b">آخر تحديث</th>
                        <th class="px-6 py-4 border-b text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($inventory)): ?>
                        <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 font-bold">لا توجد أي أرصدة موزعة بعد.</td></tr>
                    <?php else: ?>
                        <?php foreach($inventory as $inv): ?>
                        <tr class="hover:bg-slate-50 transition-colors product-row <?= ($inv['status'] ?? 'active') === 'disabled' ? 'opacity-60 bg-gray-50' : '' ?>">
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-800"><?= htmlspecialchars($inv['company_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-600 product-name"><?= htmlspecialchars($inv['product_name'] ?? 'غير معروف') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 product-code" dir="ltr"><?= htmlspecialchars($inv['product_code']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-3 py-1 rounded-full text-sm font-bold <?= $inv['quantity'] <= 10 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                                    <?= (int)$inv['quantity'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if (($inv['status'] ?? 'active') === 'active'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">نشط</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500 text-xs"><?= htmlspecialchars($inv['updated_at']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <div class="flex justify-center space-x-2 space-x-reverse">
                                    <form method="POST" action="admin_panel.php?page=storage" class="inline">
                                        <input type="hidden" name="action" value="toggle_company_stock">
                                        <input type="hidden" name="inv_id" value="<?= $inv['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="<?= ($inv['status'] ?? 'active') === 'active' ? 'text-orange-600 hover:text-orange-900 bg-orange-50 hover:bg-orange-100' : 'text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100' ?> px-3 py-1 rounded transition-colors tooltip" data-tip="<?= ($inv['status'] ?? 'active') === 'active' ? 'تعطيل' : 'تفعيل' ?>">
                                            <i class='bx <?= ($inv['status'] ?? 'active') === 'active' ? 'bx-block' : 'bx-check-circle' ?> text-lg'></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="admin_panel.php?page=storage" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الرصيد؟ سيتم إرجاع الكمية الموجبة للمخزون الرئيسي.');">
                                        <input type="hidden" name="action" value="delete_company_stock">
                                        <input type="hidden" name="inv_id" value="<?= $inv['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded transition-colors tooltip" data-tip="حذف وإرجاع">
                                            <i class='bx bx-trash text-lg'></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('storageSearchInput');
    const rows = document.querySelectorAll('.product-row');

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim().toLowerCase();

            rows.forEach(row => {
                const nameEl = row.querySelector('.product-name');
                const codeEl = row.querySelector('.product-code');
                
                const name = nameEl ? nameEl.textContent.toLowerCase() : '';
                const code = codeEl ? codeEl.textContent.toLowerCase() : '';

                if (name.includes(query) || code.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
