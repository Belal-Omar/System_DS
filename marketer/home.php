<?php
// ملف: home.php
session_start();
include(__DIR__ . '/core/config.php");
include_once("helpers.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

// حساب الإحصائيات الخاصة بالمستخدم
// Get user-specific statistics using new helper functions
$user_type = $user['user_type'] ?? 'all';
$product_stats = get_user_product_stats($conn, $user_id, $user_type);
$cities_stats = get_user_cities_stats($conn, $user_id);
$orders_stats = get_user_orders_stats($conn, $user_id);

// Extract values from orders_stats
$total_orders = $orders_stats['total_orders'] ?? 0;
$completed_orders = $orders_stats['completed_orders'] ?? 0;
$pending_orders = $orders_stats['pending_orders'] ?? 0;

// Calculate commission statistics
$total_commissions = $conn->query("SELECT SUM(commission_total) as total FROM orders WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل', 'مكتمل')")->fetch_assoc()['total'] ?? 0;
$total_commissions = floatval($total_commissions);

// المبلغ المسحوب
$withdrawn = $conn->query("SELECT SUM(amount) as total FROM withdrawals WHERE user_id = $user_id AND status = 'مكتمل'")->fetch_assoc()['total'] ?? 0;
$withdrawn = floatval($withdrawn);

// المبلغ المتاح للسحب
$available_withdrawal = $total_commissions - $withdrawn;
if ($available_withdrawal < 0) $available_withdrawal = 0;

// الطلبات المكتملة
//$completed_orders = $conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل', 'مكتمل')")->fetch_assoc()['total'] ?? 0;

// الطلبات قيد الانتظار
//$pending_orders = $conn->query("SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id AND status NOT IN ('تم التوصيل', 'محصل', 'مكتمل', 'ملغي', 'مرفوض')")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرئيسية - لوحة المسوق</title>
    <?php include __DIR__ . '/favicon_tags.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        html, body {
          margin: 0;
          padding: 0;
          width: 100%;
          max-width: 100vw;
          overflow-x: hidden;
          box-sizing: border-box;
        }
        * {
          box-sizing: border-box;
        }
        body {
          background: #f8fafc;
          font-family: 'Cairo', sans-serif;
          color: #1e293b;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center space-x-4 space-x-reverse">
                <a href="home.php" class="text-xl font-bold text-green-600">لوحة المسوق</a>
                <a href="products.php" class="text-gray-700 hover:text-green-600">المنتجات</a>
                <a href="orders.php" class="text-gray-700 hover:text-green-600">الطلبات</a>
                <a href="shipping_cities_statistics.html" class="text-gray-700 hover:text-green-600">إحصائيات المدن</a>
                <a href="withdrawals.php" class="text-gray-700 hover:text-green-600">السحب</a>
            </div>
            <div class="flex items-center space-x-4 space-x-reverse">
                <span class="text-gray-700">مرحباً، <?php echo $user['fullname']; ?></span>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">تسجيل الخروج</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-8">مرحباً بك في لوحة المسوق</h1>
        
        <!-- إحصائيات خاصة بالمستخدم -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-l-4 border-l-blue-500">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg mr-4">
                        <i class='bx bx-cart text-blue-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">إجمالي الطلبات</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_orders; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border border-l-4 border-l-green-500">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg mr-4">
                        <i class='bx bx-money text-green-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">إجمالي العمولات</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($total_commissions, 2); ?> د.ل</h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border border-l-4 border-l-purple-500">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg mr-4">
                        <i class='bx bx-wallet text-purple-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">المتاح للسحب</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($available_withdrawal, 2); ?> د.ل</h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border border-l-4 border-l-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                        <i class='bx bx-check-circle text-yellow-600 text-2xl'></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">الطلبات المكتملة</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $completed_orders; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Statistics -->
        <div class="bg-white p-6 rounded-lg shadow-sm border mb-8">
            <h2 class="text-xl font-bold mb-4">Product Statistics</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded">
                    <h3 class="text-lg font-semibold text-blue-600"><?php echo $product_stats['products']['total_products'] ?? 0; ?></h3>
                    <p class="text-gray-600">Total Products</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded">
                    <h3 class="text-lg font-semibold text-green-600"><?php echo $product_stats['products']['available'] ?? 0; ?></h3>
                    <p class="text-gray-600">Available</p>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded">
                    <h3 class="text-lg font-semibold text-yellow-600"><?php echo $product_stats['products']['low_stock'] ?? 0; ?></h3>
                    <p class="text-gray-600">Low Stock</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded">
                    <h3 class="text-lg font-semibold text-red-600"><?php echo $product_stats['products']['out_of_stock'] ?? 0; ?></h3>
                    <p class="text-gray-600">Out of Stock</p>
                </div>
            </div>
        </div>

        <!-- City Statistics -->
        <div class="bg-white p-6 rounded-lg shadow-sm border mb-8">
            <h2 class="text-xl font-bold mb-4">City Statistics</h2>
            <div class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center p-4 bg-blue-50 rounded">
                        <h3 class="text-lg font-semibold text-blue-600"><?php echo $cities_stats['summary']['cities_with_orders'] ?? 0; ?></h3>
                        <p class="text-gray-600">Cities with Orders</p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded">
                        <h3 class="text-lg font-semibold text-green-600"><?php echo $cities_stats['summary']['total_orders'] ?? 0; ?></h3>
                        <p class="text-gray-600">Total Orders</p>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded">
                        <h3 class="text-lg font-semibold text-purple-600"><?php echo number_format($cities_stats['summary']['total_revenue'] ?? 0, 2); ?> LYD</h3>
                        <p class="text-gray-600">Total Revenue</p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($cities_stats['cities'])): ?>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="p-3 text-right">City Name</th>
                            <th class="p-3 text-right">Shipping Cost</th>
                            <th class="p-3 text-right">Orders</th>
                            <th class="p-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cities_stats['cities'] as $city): ?>
                        <tr class="border-b">
                            <td class="p-3"><?php echo $city['city_name']; ?></td>
                            <td class="p-3"><?php echo $city['shipping_cost']; ?> LYD</td>
                            <td class="p-3"><?php echo $city['total_orders']; ?></td>
                            <td class="p-3"><?php echo number_format($city['total_revenue'], 2); ?> LYD</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-center">No orders yet</p>
            <?php endif; ?>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="text-center">
                    <i class='bx bx-package text-4xl text-blue-500 mb-4'></i>
                    <h3 class="text-xl font-bold mb-2">المنتجات</h3>
                    <p class="text-gray-600 mb-4">تصفح وعرض جميع المنتجات المتاحة</p>
                    <a href="products.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">عرض المنتجات</a>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="text-center">
                    <i class='bx bx-cart text-4xl text-green-500 mb-4'></i>
                    <h3 class="text-xl font-bold mb-2">الطلبات</h3>
                    <p class="text-gray-600 mb-4">إدارة ومتابعة طلبات العملاء</p>
                    <a href="orders.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">عرض الطلبات</a>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-sm border">
                <div class="text-center">
                    <i class='bx bx-wallet text-4xl text-purple-500 mb-4'></i>
                    <h3 class="text-xl font-bold mb-2">السحب</h3>
                    <p class="text-gray-600 mb-4">إدارة طلبات سحب الأرباح</p>
                    <a href="withdrawals.php" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">عرض السحب</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>