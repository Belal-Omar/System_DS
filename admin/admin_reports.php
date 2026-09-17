<?php
// ملف: admin_reports.php - تقارير التجار والمنتجات
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$merchant_id = $_GET['merchant_id'] ?? 0;
$report_type = $_GET['report_type'] ?? 'products'; // products أو withdrawals
$period = $_GET['period'] ?? 'all'; // today, week, month, 3months, 6months, year, all
$export = $_GET['export'] ?? ''; // pdf, excel
$merchant_data = null;
$products_data = [];
$withdrawals_data = [];
$total_profit = 0;
$total_available = 0;
$total_withdrawn = 0;

// حساب الفترات الزمنية
function getPeriodCondition($period) {
    switch($period) {
        case 'today':
            return "DATE(created_at) = CURDATE()";
        case 'week':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        case 'month':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        case '3months':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        case '6months':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        case 'year':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        default:
            return "1=1"; // all
    }
}

if ($merchant_id > 0) {
    // جلب بيانات التاجر
    $merchant_query = $conn->prepare("SELECT id, fullname, email, phone, user_type FROM users WHERE id = ? AND user_type = 'تاجر'");
    $merchant_query->bind_param("i", $merchant_id);
    $merchant_query->execute();
    $merchant_data = $merchant_query->get_result()->fetch_assoc();
    
    if ($merchant_data) {
        // جلب بيانات السحب دائماً
        $period_condition = getPeriodCondition($period);
        
        $withdrawals_query = "
            SELECT 
                w.*,
                DATE(w.created_at) as withdrawal_date
            FROM withdrawals w
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ";
        
        $stmt = $conn->prepare($withdrawals_query);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $withdrawals_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // حساب الإجماليات - نفس استعلام withdrawing1.php
        $profit_query = "
            SELECT COALESCE(SUM((oi.price - p.commission) * oi.quantity), 0) as total 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.user_id = ? AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
        ";
        
        $stmt = $conn->prepare($profit_query);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $profit_result = $stmt->get_result()->fetch_assoc();
        $total_profit = $profit_result['total'];
        
        $withdrawn_query = "
            SELECT COALESCE(SUM(amount), 0) as total_withdrawn
            FROM withdrawals 
            WHERE user_id = ? AND status = 'مكتمل'
        ";
        
        $stmt = $conn->prepare($withdrawn_query);
        $stmt->bind_param("i", $merchant_id);
        $stmt->execute();
        $withdrawn_result = $stmt->get_result()->fetch_assoc();
        $total_withdrawn = $withdrawn_result['total_withdrawn'];
        
        $total_available = $total_profit - $total_withdrawn;
        
        if ($report_type == 'products') {
            // جلب منتجات التاجر مع إحصائيات كل منتج
            $products_query = "
                SELECT 
                    p.*,
                    COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN oi.quantity ELSE 0 END), 0) as sold_quantity,
                    COALESCE(SUM(CASE WHEN o.status IN ('في الشحن') THEN oi.quantity ELSE 0 END), 0) as shipping_quantity,
                    COALESCE(SUM(CASE WHEN o.status = 'مرتجع' THEN oi.quantity ELSE 0 END), 0) as returned_quantity,
                    COALESCE(SUM(CASE WHEN o.status = 'ملغي' THEN oi.quantity ELSE 0 END), 0) as cancelled_quantity,
                    COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN (oi.price - p.commission) * oi.quantity ELSE 0 END), 0) as net_profit,
                    COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN p.commission * oi.quantity ELSE 0 END), 0) as total_commission
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id
                WHERE p.user_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ";
            
            $stmt = $conn->prepare($products_query);
            $stmt->bind_param("i", $merchant_id);
            $stmt->execute();
            $products_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// جلب كل التجار
$merchants_query = $conn->query("
    SELECT 
        u.id, u.fullname, u.email, u.phone,
        (SELECT COUNT(*) FROM products WHERE user_id = u.id) as total_products,
        (SELECT COALESCE(SUM(stock), 0) FROM products WHERE user_id = u.id) as total_stock,
        COALESCE(
            SUM(CASE 
                WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') 
                THEN (oi.price - p.commission) * oi.quantity 
                ELSE 0 
            END), 0
        ) as total_profit,
        COALESCE(
            SUM(CASE 
                WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') 
                THEN p.commission * oi.quantity 
                ELSE 0 
            END), 0
        ) as total_commission
    FROM users u
    LEFT JOIN products p ON u.id = p.user_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE u.user_type = 'تاجر' AND u.is_active = 1
    GROUP BY u.id
    ORDER BY u.fullname
");
$merchants = $merchants_query->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mx-auto">
    <?php if ($merchant_id > 0 && $merchant_data): ?>
        <!-- عرض تفاصيل التاجر -->
        <div class="mb-6">
            <button onclick="history.back()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition">
                <i class='bx bx-arrow-back ml-2'></i> العودة للتجار
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class='bx bx-store ml-2 text-blue-600'></i>
                    <?= htmlspecialchars($merchant_data['fullname']) ?>
                </h2>
                <div class="text-sm text-gray-600">
                    <i class='bx bx-envelope ml-1'></i><?= htmlspecialchars($merchant_data['email']) ?>
                    <i class='bx bx-phone ml-3'></i><?= htmlspecialchars($merchant_data['phone']) ?>
                </div>
            </div>
            
            <!-- تبويب التقرير -->
            <div class="flex space-x-4 mb-6">
                <button onclick="window.location.href='?page=reports&merchant_id=<?= $merchant_id ?>&report_type=products'" 
                        class="px-4 py-2 rounded <?= $report_type == 'products' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                    <i class='bx bx-package ml-2'></i>المنتجات
                </button>
            </div>
            
            <?php if ($report_type == 'withdrawals'): ?>
                <!-- فلاتر الفترة الزمنية -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-semibold mb-3">فلترة حسب الفترة</h3>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="changePeriod('today')" 
                                class="px-3 py-1 rounded text-sm <?= $period == 'today' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            اليوم
                        </button>
                        <button onclick="changePeriod('week')" 
                                class="px-3 py-1 rounded text-sm <?= $period == 'week' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            أسبوع
                        </button>
                        <button onclick="changePeriod('month')" 
                                class="px-3 py-1 rounded text-sm <?= $period == 'month' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            شهر
                        </button>
                        <button onclick="changePeriod('3months')" 
                                class="px-3 py-1 rounded text-sm <?= $period == '3months' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            3 أشهر
                        </button>
                        <button onclick="changePeriod('6months')" 
                                class="px-3 py-1 rounded text-sm <?= $period == '6months' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            6 أشهر
                        </button>
                        <button onclick="changePeriod('year')" 
                                class="px-3 py-1 rounded text-sm <?= $period == 'year' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            سنة
                        </button>
                        <button onclick="changePeriod('all')" 
                                class="px-3 py-1 rounded text-sm <?= $period == 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                            الكل
                        </button>
                    </div>
                </div>
                
                <!-- إحصائيات السحب -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="text-sm text-green-600 font-semibold">إجمالي الربح</h4>
                        <p class="text-2xl font-bold text-green-800"><?= number_format($total_profit, 2) ?> د.ل</p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="text-sm text-blue-600 font-semibold">المتاح للسحب</h4>
                        <p class="text-2xl font-bold text-blue-800"><?= number_format($total_available, 2) ?> د.ل</p>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <h4 class="text-sm text-red-600 font-semibold">تم سحبه</h4>
                        <p class="text-2xl font-bold text-red-800"><?= number_format($total_withdrawn, 2) ?> د.ل</p>
                    </div>
                </div>
                
                <!-- زر التصدير -->
                <div class="mb-6 flex flex-wrap gap-3">
                    <button onclick="exportReport('pdf')" 
                            class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center gap-2">
                        <i class='bx bxs-file-pdf text-xl'></i>
                        <span>تصدير تقرير السحب PDF</span>
                    </button>
                    <button onclick="exportReport('excel')" 
                            class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center gap-2">
                        <i class='bx bxs-file-xls text-xl'></i>
                        <span>تصدير تقرير السحب Excel</span>
                    </button>
                </div>
                
                <!-- جدول عمليات السحب -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class='bx bx-history ml-2 text-blue-600'></i>
                        سجل عمليات السحب
                    </h3>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-right text-gray-700 font-bold">#</th>
                                    <th class="px-4 py-3 text-right text-gray-700 font-bold">المبلغ</th>
                                    <th class="px-4 py-3 text-right text-gray-700 font-bold">الحالة</th>
                                    <th class="px-4 py-3 text-right text-gray-700 font-bold">طريقة التحويل</th>
                                    <th class="px-4 py-3 text-right text-gray-700 font-bold">التاريخ</th>
                                    <th class="px-4 py-3 text-right text-gray-700 font-bold">ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; foreach($withdrawals_data as $withdrawal): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3"><?= $count ?></td>
                                        <td class="px-4 py-3 font-bold"><?= number_format($withdrawal['amount'], 2) ?> د.ل</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full 
                                                <?= $withdrawal['status'] == 'مكتمل' ? 'bg-green-100 text-green-800' : 
                                                   ($withdrawal['status'] == 'قيد المراجعة' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                <?= $withdrawal['status'] ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3"><?= $withdrawal['transfer_method'] ?? '-' ?></td>
                                        <td class="px-4 py-3"><?= date('Y-m-d H:i', strtotime($withdrawal['created_at'])) ?></td>
                                        <td class="px-4 py-3"><?= $withdrawal['notes'] ?? '-' ?></td>
                                    </tr>
                                <?php $count++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if(empty($withdrawals_data)): ?>
                        <div class="text-center py-8">
                            <i class='bx bx-money-withdraw text-4xl text-gray-400 mb-2 block'></i>
                            <p class="text-gray-500">لا توجد عمليات سحب في هذه الفترة</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- إحصائيات التاجر للمنتجات -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="text-sm text-blue-600 font-semibold">إجمالي المنتجات</h4>
                        <p class="text-2xl font-bold text-blue-800"><?= count($products_data) ?></p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="text-sm text-green-600 font-semibold">صافي الربح</h4>
                        <p class="text-2xl font-bold text-green-800"><?= number_format(array_sum(array_column($products_data, 'net_profit')), 2) ?> د.ل</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h4 class="text-sm text-purple-600 font-semibold">إجمالي العمولات</h4>
                        <p class="text-2xl font-bold text-purple-800"><?= number_format(array_sum(array_column($products_data, 'total_commission')), 2) ?> د.ل</p>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <h4 class="text-sm text-orange-600 font-semibold">المخزون المتاح</h4>
                        <p class="text-2xl font-bold text-orange-800"><?= array_sum(array_column($products_data, 'stock')) ?></p>
                    </div>
                </div>
                
                <!-- زر التصدير للمنتجات -->
                <div class="mb-6 flex flex-wrap gap-3">
                    <button onclick="exportAllProducts('pdf')" 
                            class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center gap-2">
                        <i class='bx bxs-file-pdf text-xl'></i>
                        <span>تصدير كل المنتجات PDF</span>
                    </button>
                    <button onclick="exportAllProducts('excel')" 
                            class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg flex items-center gap-2">
                        <i class='bx bxs-file-xls text-xl'></i>
                        <span>تصدير كل المنتجات Excel</span>
                    </button>
                </div>
                
                <!-- جدول المنتجات -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class='bx bx-package ml-2 text-blue-600'></i>
                        المنتجات والتفاصيل
                    </h3>
            
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">#</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">اسم المنتج</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">السعر</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">المخزون</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">المباع</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">في الشحن</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">مرتجع</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">ملغي</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">صافي الربح</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">العمولة</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">تصدير</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; foreach($products_data as $product): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?= $count ?></td>
                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($product['name']) ?></td>
                                <td class="px-4 py-3"><?= number_format($product['price'], 2) ?> د.ل</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $product['stock'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $product['stock'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?= $product['sold_quantity'] ?></td>
                                <td class="px-4 py-3"><?= $product['shipping_quantity'] ?></td>
                                <td class="px-4 py-3"><?= $product['returned_quantity'] ?></td>
                                <td class="px-4 py-3"><?= $product['cancelled_quantity'] ?></td>
                                <td class="px-4 py-3 font-bold text-green-600"><?= number_format($product['net_profit'], 2) ?> د.ل</td>
                                <td class="px-4 py-3 font-bold text-purple-600"><?= number_format($product['total_commission'], 2) ?> د.ل</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2 justify-center">
                                        <button onclick="exportProduct('<?= $product['id'] ?>', 'pdf')" 
                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 transform hover:scale-105 shadow-md flex items-center gap-1"
                                                title="تصدير PDF">
                                            <i class='bx bxs-file-pdf'></i>
                                            <span>PDF</span>
                                        </button>
                                        <button onclick="exportProduct('<?= $product['id'] ?>', 'excel')" 
                                                class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 transform hover:scale-105 shadow-md flex items-center gap-1"
                                                title="تصدير Excel">
                                            <i class='bx bxs-file-xls'></i>
                                            <span>Excel</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php $count++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- تقارير السحب - تظهر دائماً تحت المنتجات -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class='bx bx-money-withdraw ml-2 text-blue-600'></i>
                تقارير السحب
            </h3>
            
            <!-- فلاتر الفترة الزمنية -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="text-lg font-semibold mb-3">فلترة حسب الفترة</h4>
                <div class="flex flex-wrap gap-2">
                    <button onclick="changePeriod('today')" 
                            class="px-3 py-1 rounded text-sm <?= $period == 'today' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        اليوم
                    </button>
                    <button onclick="changePeriod('week')" 
                            class="px-3 py-1 rounded text-sm <?= $period == 'week' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        أسبوع
                    </button>
                    <button onclick="changePeriod('month')" 
                            class="px-3 py-1 rounded text-sm <?= $period == 'month' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        شهر
                    </button>
                    <button onclick="changePeriod('3months')" 
                            class="px-3 py-1 rounded text-sm <?= $period == '3months' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        3 أشهر
                    </button>
                    <button onclick="changePeriod('6months')" 
                            class="px-3 py-1 rounded text-sm <?= $period == '6months' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        6 أشهر
                    </button>
                    <button onclick="changePeriod('year')" 
                            class="px-3 py-1 rounded text-sm <?= $period == 'year' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        سنة
                    </button>
                    <button onclick="changePeriod('all')" 
                            class="px-3 py-1 rounded text-sm <?= $period == 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?> transition">
                        الكل
                    </button>
                </div>
            </div>
            
            <!-- إحصائيات السحب -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="text-sm text-green-600 font-semibold">إجمالي الربح</h4>
                    <p class="text-2xl font-bold text-green-800"><?= number_format($total_profit, 2) ?> د.ل</p>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="text-sm text-blue-600 font-semibold">المتاح للسحب</h4>
                    <p class="text-2xl font-bold text-blue-800"><?= number_format($total_available, 2) ?> د.ل</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="text-sm text-red-600 font-semibold">تم سحبه</h4>
                    <p class="text-2xl font-bold text-red-800"><?= number_format($total_withdrawn, 2) ?> د.ل</p>
                </div>
            </div>
            
            <!-- زر التصدير -->
            <div class="mb-4">
                <button onclick="exportReport('pdf')" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded ml-2 transition">
                    <i class='bx bxs-file-pdf ml-2'></i>تصدير PDF
                </button>
                <button onclick="exportReport('excel')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded transition">
                    <i class='bx bxs-file-xls ml-2'></i>تصدير Excel
                </button>
            </div>
            
            <!-- جدول عمليات السحب -->
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">#</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">المبلغ</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">الحالة</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">طريقة التحويل</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">التاريخ</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1; 
                        $filtered_withdrawals = [];
                        
                        // تطبيق الفلتر الزمني
                        foreach($withdrawals_data as $withdrawal) {
                            $withdrawal_date = date('Y-m-d', strtotime($withdrawal['created_at']));
                            $include = true;
                            
                            switch($period) {
                                case 'today':
                                    $include = ($withdrawal_date == date('Y-m-d'));
                                    break;
                                case 'week':
                                    $include = (strtotime($withdrawal['created_at']) >= strtotime('-7 days'));
                                    break;
                                case 'month':
                                    $include = (strtotime($withdrawal['created_at']) >= strtotime('-1 month'));
                                    break;
                                case '3months':
                                    $include = (strtotime($withdrawal['created_at']) >= strtotime('-3 months'));
                                    break;
                                case '6months':
                                    $include = (strtotime($withdrawal['created_at']) >= strtotime('-6 months'));
                                    break;
                                case 'year':
                                    $include = (strtotime($withdrawal['created_at']) >= strtotime('-1 year'));
                                    break;
                            }
                            
                            if ($include) {
                                $filtered_withdrawals[] = $withdrawal;
                            }
                        }
                        
                        foreach($filtered_withdrawals as $withdrawal): 
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?= $count ?></td>
                                <td class="px-4 py-3 font-bold"><?= number_format($withdrawal['amount'], 2) ?> د.ل</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?= $withdrawal['status'] == 'مكتمل' ? 'bg-green-100 text-green-800' : 
                                           ($withdrawal['status'] == 'قيد المراجعة' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= $withdrawal['status'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?= $withdrawal['transfer_method'] ?? '-' ?></td>
                                <td class="px-4 py-3"><?= date('Y-m-d H:i', strtotime($withdrawal['created_at'])) ?></td>
                                <td class="px-4 py-3"><?= $withdrawal['notes'] ?? '-' ?></td>
                            </tr>
                        <?php $count++; endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if(empty($filtered_withdrawals)): ?>
                <div class="text-center py-8">
                    <i class='bx bx-money-withdraw text-4xl text-gray-400 mb-2 block'></i>
                    <p class="text-gray-500">لا توجد عمليات سحب في هذه الفترة</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- عرض قائمة التجار -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class='bx bx-store ml-2 text-blue-600'></i>
                تقارير التجار
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($merchants as $merchant): ?>
                    <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-lg p-6 border border-blue-200 hover:shadow-lg transition cursor-pointer"
                         onclick="window.location.href='?page=reports&merchant_id=<?= $merchant['id'] ?>'">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white">
                                <i class='bx bx-store text-xl'></i>
                            </div>
                            <div class="mr-3">
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($merchant['fullname']) ?></h3>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($merchant['email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="bg-white rounded p-2">
                                <p class="text-gray-600">المنتجات</p>
                                <p class="font-bold text-blue-600"><?= $merchant['total_products'] ?></p>
                            </div>
                            <div class="bg-white rounded p-2">
                                <p class="text-gray-600">المخزون</p>
                                <p class="font-bold text-green-600"><?= $merchant['total_stock'] ?></p>
                            </div>
                            <div class="bg-white rounded p-2">
                                <p class="text-gray-600">صافي الربح</p>
                                <p class="font-bold text-green-600"><?= number_format($merchant['total_profit'], 2) ?> د.ل</p>
                            </div>
                            <div class="bg-white rounded p-2">
                                <p class="text-gray-600">العمولات</p>
                                <p class="font-bold text-purple-600"><?= number_format($merchant['total_commission'], 2) ?> د.ل</p>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <span class="text-blue-600 font-semibold text-sm">
                                <i class='bx bx-show ml-1'></i>عرض التفاصيل
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if(empty($merchants)): ?>
                <div class="text-center py-12">
                    <i class='bx bx-store text-6xl text-gray-400 mb-4 block'></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">لا يوجد تجار</h3>
                    <p class="text-gray-500">لم يتم تسجيل أي تجار في النظام بعد</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function changePeriod(period) {
    const url = new URL(window.location);
    url.searchParams.set('period', period);
    url.searchParams.delete('export');
    window.location.href = url.toString();
}

function exportProduct(productId, format) {
    const url = new URL('export_reports.php', window.location.origin);
    url.searchParams.set('merchant_id', <?= $merchant_id ?>);
    url.searchParams.set('product_id', productId);
    url.searchParams.set('export', format);
    url.searchParams.set('export_type', 'product');
    window.open(url.toString(), '_blank');
}

function exportAllProducts(format) {
    const url = new URL('export_reports.php', window.location.origin);
    url.searchParams.set('merchant_id', <?= $merchant_id ?>);
    url.searchParams.set('export', format);
    url.searchParams.set('export_type', 'all_products');
    window.open(url.toString(), '_blank');
}

function exportReport(format) {
    const url = new URL('export_reports.php', window.location.origin);
    url.searchParams.set('merchant_id', <?= $merchant_id ?>);
    url.searchParams.set('export', format);
    url.searchParams.set('export_type', 'withdrawals');
    window.open(url.toString(), '_blank');
}
</script>
