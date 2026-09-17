<?php
// ملف: admin_dashboard.php
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

// إحصائيات سريعة
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
$total_orders = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'];
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$total_revenue = $conn->query("SELECT SUM(total) as total FROM orders WHERE status = 'تم التوصيل'")->fetch_assoc()['total'] ?? 0;
?>
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">لوحة التحكم</h1>
</div>

<!-- بطاقات الإحصائيات -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                <i class='bx bx-package text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي المنتجات</p>
                <h3 class="text-2xl font-bold"><?= $total_products ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg mr-4">
                <i class='bx bx-cart text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الطلبات</p>
                <h3 class="text-2xl font-bold"><?= $total_orders ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg mr-4">
                <i class='bx bx-user text-purple-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي المستخدمين</p>
                <h3 class="text-2xl font-bold"><?= $total_users ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                <i class='bx bx-money text-yellow-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الإيرادات</p>
                <h3 class="text-2xl font-bold"><?= number_format($total_revenue, 2) ?> د.ل</h3>
            </div>
        </div>
    </div>
</div>

<!-- الطلبات الحديثة -->
<div class="stat-card p-6">
    <h3 class="text-xl font-bold mb-4">أحدث الطلبات</h3>
    <?php
    $recent_orders = $conn->query("
        SELECT o.*, u.fullname as marketer_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="p-3 text-right">رقم الطلب</th>
                    <th class="p-3 text-right">العميل</th>
                    <th class="p-3 text-right">المسوق</th>
                    <th class="p-3 text-right">المجموع</th>
                    <th class="p-3 text-right">الحالة</th>
                    <th class="p-3 text-right">التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($order = $recent_orders->fetch_assoc()): ?>
                <tr class="border-b">
                    <td class="p-3">#<?= $order['id'] ?></td>
                    <td class="p-3"><?= $order['customer_name'] ?></td>
                    <td class="p-3"><?= $order['marketer_name'] ?? 'غير معين' ?></td>
                    <td class="p-3"><?= number_format($order['total'], 2) ?> د.ل</td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-white 
                            <?= $order['status'] == 'تم التوصيل' ? 'bg-green-500' : 
                               ($order['status'] == 'مرفوض' ? 'bg-red-500' : 'bg-yellow-500') ?>">
                            <?= $order['status'] ?>
                        </span>
                    </td>
                    <td class="p-3"><?= date('Y-m-d', strtotime($order['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>