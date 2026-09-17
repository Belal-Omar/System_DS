<?php
// ملف: admin_marketer_reports.php - تقارير المسوقين
// منع إعادة بدء الجلسة إذا كانت مفعلة بالفعل
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$marketer_id = $_GET['marketer_id'] ?? 0;
$report_type = $_GET['report_type'] ?? 'summary';
$period = $_GET['period'] ?? 'all';

// دالة شرط الفترة الزمنية
function getPeriodCondition($period, $prefix = 'o.') {
    switch($period) {
        case 'today': return "DATE({$prefix}created_at) = CURDATE()";
        case 'week': return "{$prefix}created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        case 'month': return "{$prefix}created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        case '3months': return "{$prefix}created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        case '6months': return "{$prefix}created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        case 'year': return "{$prefix}created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        default: return "1=1";
    }
}

// عرض تفاصيل مسوق معين
if ($marketer_id > 0) {
    // جلب بيانات المسوق
    $stmt = $conn->prepare("SELECT id, fullname, email, phone, user_type FROM users WHERE id = ?");
    $stmt->bind_param("i", $marketer_id);
    $stmt->execute();
    $marketer = $stmt->get_result()->fetch_assoc();
    
    if ($marketer) {
        $period_condition = getPeriodCondition($period, 'o.');
        
        // إحصائيات عامة
        $stats_query = "
            SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM((oi.commission - oi.special_commission) * oi.quantity), 0) as net_commission,
                COALESCE(SUM(oi.special_commission * oi.quantity), 0) as deducted_commission,
                COALESCE(SUM(oi.commission * oi.quantity), 0) as gross_commission
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ? AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل') AND $period_condition
        ";
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("i", $marketer_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // تفاصيل المنتجات
        $products_query = "
            SELECT 
                p.name as product_name,
                COALESCE(pi.image_path, CONCAT('imgs/', p.image)) as image,
                COALESCE(SUM(oi.quantity), 0) as qty_sold,
                COALESCE(SUM((oi.commission - oi.special_commission) * oi.quantity), 0) as net_earnings,
                COALESCE(SUM(oi.special_commission * oi.quantity), 0) as total_deduction,
                p.special_commission as unit_deduction
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
            WHERE o.user_id = ? AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل') AND $period_condition
            GROUP BY p.id
            ORDER BY net_earnings DESC
        ";
        $stmt = $conn->prepare($products_query);
        $stmt->bind_param("i", $marketer_id);
        $stmt->execute();
        $products_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// جلب قائمة المسوقين
// نستخدم نفس المنطق الموجود في admin_users.php للتأكد من تطابق user_type
$marketers_query_sql = "
    SELECT 
        u.id, u.fullname, u.email, u.phone,
        COUNT(DISTINCT o.id) as delivered_orders,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN (oi.commission - oi.special_commission) * oi.quantity ELSE 0 END), 0) as total_net_earnings,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN oi.special_commission * oi.quantity ELSE 0 END), 0) as total_deductions
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE u.user_type = 'مسوق'
    GROUP BY u.id
    ORDER BY total_net_earnings DESC
";

$marketers_result = $conn->query($marketers_query_sql);
$marketers = [];
if ($marketers_result) {
    $marketers = $marketers_result->fetch_all(MYSQLI_ASSOC);
} else {
    // Log error if query fails
    error_log("Marketer Query Failed: " . $conn->error);
}
?>

<div class="container mx-auto">
    <?php if ($marketer_id > 0 && $marketer): ?>
        <!-- واجهة تفاصيل المسوق -->
        <div class="mb-6 flex justify-between items-center">
            <button onclick="window.location.href='?page=marketer_reports'" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition">
                <i class='bx bx-arrow-back ml-2'></i> العودة للقائمة
            </button>
            
            <div class="bg-white rounded-lg shadow-sm p-2 flex gap-2">
                <?php foreach(['today'=>'اليوم', 'week'=>'أسبوع', 'month'=>'شهر', 'all'=>'الكل'] as $k => $label): ?>
                    <a href="?page=marketer_reports&marketer_id=<?= $marketer_id ?>&period=<?= $k ?>" 
                       class="px-3 py-1 rounded text-sm <?= $period == $k ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class='bx bx-user-circle ml-2 text-blue-600'></i>
                    <?= htmlspecialchars($marketer['fullname']) ?>
                </h2>
                <div class="text-sm text-gray-600">
                    <?= htmlspecialchars($marketer['email']) ?> | <?= htmlspecialchars($marketer['phone']) ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="text-sm text-blue-600 font-semibold">الطلبات المكتملة</h4>
                    <p class="text-2xl font-bold text-blue-800"><?= $stats['total_orders'] ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm text-gray-600 font-semibold">العمولة الكلية</h4>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($stats['gross_commission'], 2) ?> د.ل</p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="text-sm text-red-600 font-semibold">إجمالي الخصم</h4>
                    <p class="text-2xl font-bold text-red-800"><?= number_format($stats['deducted_commission'], 2) ?> د.ل</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="text-sm text-green-600 font-semibold">صافي الربح</h4>
                    <p class="text-2xl font-bold text-green-800"><?= number_format($stats['net_commission'], 2) ?> د.ل</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">تفاصيل المنتجات</h3>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">المنتج</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">عدد المباع</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">الخصم</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">الصافي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($products_stats)): ?>
                            <?php foreach($products_stats as $p): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 flex items-center">
                                    <?php if($p['image']): ?>
                                        <img src="<?= (strpos($p['image'], 'imgs/') === 0) ? $p['image'] : 'imgs/' . $p['image'] ?>" class="w-10 h-10 rounded ml-2 object-cover">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($p['product_name']) ?>
                                </td>
                                <td class="px-4 py-3"><?= $p['qty_sold'] ?></td>
                                <td class="px-4 py-3 text-red-600"><?= number_format($p['total_deduction'], 2) ?></td>
                                <td class="px-4 py-3 text-green-600 font-bold"><?= number_format($p['net_earnings'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">لا توجد بيانات</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- واجهة قائمة المسوقين -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class='bx bx-line-chart ml-2 text-blue-600'></i>
                تقارير المسوقين
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(!empty($marketers)): ?>
                    <?php foreach($marketers as $mk): ?>
                        <div class="bg-white rounded-lg p-6 border border-gray-200 hover:shadow-lg transition cursor-pointer"
                             onclick="window.location.href='?page=marketer_reports&marketer_id=<?= $mk['id'] ?>'">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                    <i class='bx bx-user text-xl'></i>
                                </div>
                                <div class="mr-3">
                                    <h3 class="font-bold text-gray-800"><?= htmlspecialchars($mk['fullname']) ?></h3>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($mk['email']) ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-2 text-sm border-t pt-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">طلبات مكتملة:</span>
                                    <span class="font-bold"><?= $mk['delivered_orders'] ?></span>
                                </div>
                                <div class="flex justify-between text-red-600">
                                    <span>الخصومات:</span>
                                    <span class="font-bold"><?= number_format($mk['total_deductions'], 2) ?> د.ل</span>
                                </div>
                                <div class="flex justify-between text-green-600">
                                    <span>الصافي:</span>
                                    <span class="font-bold text-lg"><?= number_format($mk['total_net_earnings'], 2) ?> د.ل</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12">
                        <i class='bx bx-user-x text-6xl text-gray-300 mb-4 block'></i>
                        <p class="text-gray-500">لا يوجد مسوقين</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
