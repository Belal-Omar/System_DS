<?php
// ملف: admin_reports_simple.php - نسخة مبسطة تعمل
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$merchant_id = $_GET['merchant_id'] ?? 0;

if ($merchant_id > 0) {
    // جلب بيانات التاجر
    $merchant_query = $conn->prepare("SELECT id, fullname, email, phone FROM users WHERE id = ?");
    $merchant_query->bind_param("i", $merchant_id);
    $merchant_query->execute();
    $merchant_data = $merchant_query->get_result()->fetch_assoc();
    
    if ($merchant_data) {
        // حساب الربح - نفس استعلام withdrawing1.php
        $profit_query = $conn->query("
            SELECT COALESCE(SUM((oi.price - oi.commission) * oi.quantity), 0) as total 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.user_id = $merchant_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')
        ");
        $profit_result = $profit_query->fetch_assoc();
        $total_profit = $profit_result['total'];
        
        // حساب المسحوب
        $withdrawn_query = $conn->query("
            SELECT COALESCE(SUM(amount), 0) as total_withdrawn
            FROM withdrawals 
            WHERE user_id = $merchant_id AND status = 'مكتمل'
        ");
        $withdrawn_result = $withdrawn_query->fetch_assoc();
        $total_withdrawn = $withdrawn_result['total_withdrawn'];
        
        $total_available = $total_profit - $total_withdrawn;
        
        // جلب عمليات السحب
        $withdrawals_query = $conn->query("
            SELECT w.*, DATE(w.created_at) as withdrawal_date
            FROM withdrawals w
            WHERE w.user_id = $merchant_id
            ORDER BY w.created_at DESC
        ");
        $withdrawals_data = $withdrawals_query->fetch_all(MYSQLI_ASSOC);
    }
}

// جلب كل التجار
$merchants_query = $conn->query("
    SELECT 
        u.id, u.fullname, u.email, u.phone,
        COUNT(DISTINCT p.id) as total_products,
        COALESCE(SUM(p.stock), 0) as total_stock
    FROM users u
    LEFT JOIN products p ON u.id = p.user_id
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
                                <th class="px-4 py-3 text-right text-gray-700 font-bold">طريقة الدفع</th>
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
                                    <td class="px-4 py-3"><?= $withdrawal['payment_method'] ?? '-' ?></td>
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
                        <p class="text-gray-500">لا توجد عمليات سحب</p>
                    </div>
                <?php endif; ?>
            </div>
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
                         onclick="window.location.href='?page=reports_simple&merchant_id=<?= $merchant['id'] ?>">
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
                        </div>
                        
                        <div class="mt-3 text-center">
                            <span class="text-blue-600 font-semibold text-sm">
                                <i class='bx bx-show ml-1'></i>عرض التقارير
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
