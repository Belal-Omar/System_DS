<?php
// ملف: merchant_dashboard.php - لوحة تحكم التاجر
session_start();
include(__DIR__ . '/../core/config.php");
include(__DIR__ . '/../core/helpers.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// جلب بيانات التاجر
$user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

// التحقق من نوع المستخدم - يجب أن يكون تاجر
if (($user['user_type'] ?? '') != 'تاجر') {
    header("Location: Home1.html");
    exit;
}

// حساب إجمالي المبيعات (إجمالي سعر المنتجات الأصلية)
$total_sales_query = $conn->query("
    SELECT
        COALESCE(SUM(
            (oi.original_price * oi.quantity)
        ), 0) as total_sales
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')");
$total_sales = 0;
if ($total_sales_query) {
    $row = $total_sales_query->fetch_assoc();
    $total_sales = floatval($row['total_sales'] ?? 0);
}
$total_sales = max(0, $total_sales);

// حساب إجمالي العمولات والمصاريف
$total_costs_query = $conn->query("
    SELECT
        COALESCE(SUM(
            (oi.commission * oi.quantity) + 
            (COALESCE(oi.shipping_cost, 0))
        ), 0) as total_costs
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')");
$total_costs = 0;
if ($total_costs_query) {
    $row = $total_costs_query->fetch_assoc();
    $total_costs = floatval($row['total_costs'] ?? 0);
}

// صافي الربح = إجمالي المبيعات - إجمالي التكاليف
$total_profit = max(0, $total_sales - $total_costs);

// حساب المبلغ المسحوب
$withdrawn_query = $conn->query("
    SELECT COALESCE(SUM(amount), 0) as total_withdrawn 
    FROM withdrawals 
    WHERE user_id = $user_id AND status = 'مكتمل'");
$total_withdrawn = $withdrawn_query ? floatval($withdrawn_query->fetch_assoc()['total_withdrawn'] ?? 0) : 0;
$total_withdrawn = max(0, $total_withdrawn); // التأكد من عدم السلبية

// حساب إجمالي المبيعات المتوقعة (الطلبات قيد التنفيذ)
$pending_sales_query = $conn->query("
    SELECT
        COALESCE(SUM(
            (oi.original_price * oi.quantity)
        ), 0) as pending_sales
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('قيد الانتظار', 'تم التأكيد', 'قيد التنفيذ', 'تحت التحضير', 'في الشحن')");
$pending_sales = 0;
if ($pending_sales_query) {
    $row = $pending_sales_query->fetch_assoc();
    $pending_sales = floatval($row['pending_sales'] ?? 0);
}

// حساب التكاليف المتوقعة (العمولات والمصاريف)
$pending_costs_query = $conn->query("
    SELECT
        COALESCE(SUM(
            (oi.commission * oi.quantity) + 
            (COALESCE(oi.shipping_cost, 0))
        ), 0) as pending_costs
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.user_id = $user_id AND o.status IN ('قيد الانتظار', 'تم التأكيد', 'قيد التنفيذ', 'تحت التحضير', 'في الشحن')");
$pending_costs = 0;
if ($pending_costs_query) {
    $row = $pending_costs_query->fetch_assoc();
    $pending_costs = floatval($row['pending_costs'] ?? 0);
}

// الأرباح المتوقعة = إجمالي المبيعات المتوقعة - التكاليف المتوقعة
$pending_profit = max(0, $pending_sales - $pending_costs);

// حساب المبلغ المتاح للسحب (إجمالي الأرباح - المسحوب)
$available_for_withdrawal = max(0, $total_profit - $total_withdrawn);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم التاجر</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .stat-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- شريط التنقل -->
    <?php include(__DIR__ . '/../core/navbar.php'); ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">مرحباً بك، <?= htmlspecialchars($user['fullname'] ?? 'التاجر') ?></h1>
        
        <!-- بطاقات الإحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- إجمالي الأرباح -->
            <div class="stat-card p-6 border-r-4 border-blue-500">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg ml-4">
                        <i class='bx bx-money text-blue-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">إجمالي المبيعات</p>
                        <h3 class="text-2xl font-bold"><?= number_format($total_sales, 2) ?> د.ل</h3>
                    </div>
                </div>
            </div>
            
            <!-- متاح للسحب -->
            <div class="stat-card p-6 border-r-4 border-green-500">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg ml-4">
                        <i class='bx bx-wallet text-green-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">صافي الربح</p>
                        <h3 class="text-2xl font-bold"><?= number_format($total_profit, 2) ?> د.ل</h3>
                    </div>
                </div>
            </div>
            
            <!-- أرباح متوقعة -->
            <div class="stat-card p-6 border-r-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg ml-4">
                        <i class='bx bx-time-five text-yellow-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">أرباح متوقعة</p>
                        <h3 class="text-2xl font-bold"><?= number_format($pending_profit, 2) ?> د.ل</h3>
                        <p class="text-sm text-gray-500">(صافي الربح المتوقع بعد خصم التكاليف)</p>
                    </div>
                </div>
            </div>
            
            <!-- تم السحب -->
            <div class="stat-card p-6 border-r-4 border-purple-500">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg ml-4">
                        <i class='bx bx-credit-card text-purple-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600">تم السحب</p>
                        <h3 class="text-2xl font-bold"><?= number_format($total_withdrawn, 2) ?> د.ل</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- قسم الطلبات الحديثة -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">أحدث الطلبات</h2>
                <a href="merchant_orders.php" class="text-blue-600 hover:underline">عرض الكل</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">رقم الطلب</th>
                            <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المنتج</th>
                            <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الكمية</th>
                            <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">السعر الأصلي</th>
                            <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="recent-orders">
                        <!-- سيتم ملء هذا القسم عبر JavaScript -->
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">جاري التحميل...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // جلب أحدث الطلبات
        function loadRecentOrders() {
            $.ajax({
                url: 'get_merchant_orders.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.orders.length > 0) {
                        let html = '';
                        // عرض آخر 5 طلبات فقط
                        const recentOrders = response.orders.slice(0, 5);
                        
                        recentOrders.forEach(order => {
                            // استخدام سعر المنتج الأصلي
                            const price = parseFloat(order.original_price || order.price || 0);

                            html += `
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#${order.id}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${order.product_name || 'غير محدد'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${order.quantity}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${price.toFixed(2)} د.ل</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            ${getStatusClass(order.status)}">
                                            ${order.status || 'قيد الانتظار'}
                                        </span>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        $('#recent-orders').html(html);
                    } else {
                        $('#recent-orders').html(`
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">لا توجد طلبات بعد</td>
                            </tr>
                        `);
                    }
                },
                error: function() {
                    $('#recent-orders').html(`
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-red-500">حدث خطأ أثناء تحميل الطلبات</td>
                        </tr>
                    `);
                }
            });
        }
        
        // دالة للحصول على كلاس الحالة المناسب
        function getStatusClass(status) {
            switch(status) {
                case 'تم التوصيل':
                case 'مكتمل':
                    return 'bg-green-100 text-green-800';
                case 'ملغي':
                case 'مرفوض':
                    return 'bg-red-100 text-red-800';
                case 'في الطريق':
                case 'قيد التنفيذ':
                    return 'bg-yellow-100 text-yellow-800';
                default:
                    return 'bg-gray-100 text-gray-800';
            }
        }
        
        // تحميل الطلبات عند تحميل الصفحة
        $(document).ready(function() {
            loadRecentOrders();
        });
    </script>
</body>
</html>