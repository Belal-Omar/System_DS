<?php
// ملف: admin_shipping_companies.php
session_start();
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Handle form submission for adding new company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    $name = $_POST['company_name'] ?? '';
    $phone = $_POST['company_phone'] ?? '';
    $email = $_POST['company_email'] ?? '';
    $address = $_POST['company_address'] ?? '';
    
    if (!empty($name)) {
        if (add_shipping_company($conn, $name, $phone, $email, $address)) {
            $success_message = "تم إضافة شركة الشحن بنجاح";
        } else {
            $error_message = "حدث خطأ أثناء إضافة شركة الشحن";
        }
    } else {
        $error_message = "اسم شركة الشحن مطلوب";
    }
}

// Handle edit company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_company'])) {
    $id = $_POST['company_id'] ?? '';
    $name = $_POST['company_name'] ?? '';
    $phone = $_POST['company_phone'] ?? '';
    $email = $_POST['company_email'] ?? '';
    $address = $_POST['company_address'] ?? '';
    
    if (!empty($name) && !empty($id)) {
        if (update_shipping_company($conn, $id, $name, $phone, $email, $address)) {
            $success_message = "تم تحديث شركة الشحن بنجاح";
        } else {
            $error_message = "حدث خطأ أثناء تحديث شركة الشحن";
        }
    } else {
        $error_message = "اسم شركة الشحن مطلوب";
    }
}

// Handle delete company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) {
    $id = $_POST['company_id'] ?? '';
    
    if (!empty($id)) {
        if (delete_shipping_company($conn, $id)) {
            $success_message = "تم حذف شركة الشحن بنجاح";
            // Clear selected company if it was deleted
            if ($selected_company_id == $id) {
                $selected_company_id = null;
                $company_stats = null;
                $company_orders = [];
                $selected_company = null;
            }
        } else {
            $error_message = "لا يمكن حذف شركة الشحن لأن لديها طلبات مرتبطة بها";
        }
    }
}

// Handle assign cities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_cities'])) {
    $id = $_POST['company_id'] ?? '';
    $cities = $_POST['cities'] ?? [];
    
    if (!empty($id)) {
        // Delete existing associations
        $conn->query("DELETE FROM shipping_company_cities WHERE shipping_company_id = " . (int)$id);
        
        // Insert new associations
        if (!empty($cities) && is_array($cities)) {
            $stmt = $conn->prepare("INSERT INTO shipping_company_cities (shipping_company_id, city_id) VALUES (?, ?)");
            foreach ($cities as $city_id) {
                $stmt->bind_param("ii", $id, $city_id);
                $stmt->execute();
            }
        }
        $success_message = "تم تحديث المدن المدعومة بنجاح";
    }
}

// Get all shipping companies
$companies = get_shipping_companies($conn);

// Get all cities for the assignment UI
$all_cities = [];
$cities_result = $conn->query("SELECT id, city_name FROM shipping_cities ORDER BY city_name ASC");
if ($cities_result) {
    while ($row = $cities_result->fetch_assoc()) {
        $all_cities[] = $row;
    }
}

// Get selected company details
$selected_company_id = $_GET['company_id'] ?? null;
$company_stats = null;
$company_orders = [];
$selected_company = null;

if ($selected_company_id) {
    $company_stats = get_shipping_company_stats($conn, $selected_company_id);
    $company_products_stats = get_shipping_company_products_stats($conn, $selected_company_id);
    $all_company_orders = get_shipping_company_orders($conn, $selected_company_id, 1000); // Get all orders
    $recent_company_orders = get_shipping_company_orders($conn, $selected_company_id, 5); // Get last 5 orders
    $company_products_with_orders = get_shipping_company_products_with_orders($conn, $selected_company_id);
    
    // DEBUG: Show what we got
    echo "<!-- DEBUG: Selected company ID: $selected_company_id -->";
    echo "<!-- DEBUG: Company products with orders count: " . count($company_products_with_orders) . " -->";
    echo "<!-- DEBUG: Company orders count: " . count($all_company_orders) . " -->";
    echo "<!-- DEBUG: Company stats: " . ($company_stats ? 'FOUND' : 'NULL') . " -->";
    
    // Get selected company details
    foreach ($companies as $company) {
        if ($company['id'] == $selected_company_id) {
            $selected_company = $company;
            break;
        }
    }
    // Get assigned cities for the selected company
    $assigned_cities = [];
    $ac_result = $conn->query("SELECT city_id FROM shipping_company_cities WHERE shipping_company_id = " . (int)$selected_company_id);
    if ($ac_result) {
        while ($r = $ac_result->fetch_assoc()) {
            $assigned_cities[] = $r['city_id'];
        }
    }
} else {
    echo "<!-- DEBUG: No company selected -->";
}
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class='bx bxs-truck text-3xl ml-3 text-blue-600'></i>
                    <?php if ($selected_company): ?>
                        تفاصيل شركة: <?php echo htmlspecialchars($selected_company['name']); ?>
                    <?php else: ?>
                        شركات الشحن
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 mt-1">
                    <?php if ($selected_company): ?>
                        عرض الإحصائيات والطلبات الخاصة بشركة <?php echo htmlspecialchars($selected_company['name']); ?>
                    <?php else: ?>
                        متابعة شركات الشحن والإحصائيات الخاصة بها
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($selected_company): ?>
                <button onclick="deselectCompany()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition flex items-center">
                    <i class='bx bx-arrow-back ml-2'></i> العودة للقائمة
                </button>
            <?php else: ?>
                <button onclick="showAddCompanyModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition flex items-center">
                    <i class='bx bx-plus ml-2'></i> إضافة شركة شحن
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Shipping Companies Grid - Visible only if no company is selected -->
    <?php if (!$selected_company): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="companiesGrid">
        <?php foreach ($companies as $company): ?>
            <?php
            $stats = get_shipping_company_stats($conn, $company['id']);
            $total_orders = $stats['orders']['total_orders'] ?? 0;
            $delivered_orders = $stats['orders']['delivered'] ?? 0;
            $shipping_orders = $stats['orders']['shipping'] ?? 0;
            $cancelled_orders = $stats['orders']['cancelled'] ?? 0;
            $net_revenue = $stats['financial']['revenue_after_deductions'] ?? 0;
            $total_shipping_cost = $stats['financial']['total_shipping_cost'] ?? 0;
            $total_revenue = $stats['financial']['total_revenue'] ?? 0;
            $total_commission = $stats['financial']['total_commission'] ?? 0;
            $delivery_rate = $total_orders > 0 ? ($delivered_orders / $total_orders) * 100 : 0;
            ?>
            
            <div class="company-box bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-all cursor-pointer <?= ($selected_company_id == $company['id']) ? 'ring-2 ring-green-500' : '' ?>" 
                 onclick="selectCompany(<?php echo $company['id']; ?>)">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class='bx bx-truck text-green-600 text-xl'></i>
                    </div>
                    <span class="text-xs text-gray-500">#<?php echo $company['id']; ?></span>
                </div>
                
                <h3 class="font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($company['name']); ?></h3>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">إجمالي الطلبات:</span>
                        <span class="font-semibold"><?php echo $total_orders; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">قيد التوصيل:</span>
                        <span class="font-semibold text-blue-600"><?php echo $shipping_orders; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تم التوصيل:</span>
                        <span class="font-semibold text-green-600"><?php echo $delivered_orders; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">تم الإلغاء:</span>
                        <span class="font-semibold text-red-600"><?php echo $cancelled_orders; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">اجمالي الإيرادات:</span>
                        <span class="font-semibold text-green-700"><?php echo number_format($total_revenue, 2); ?> دل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">اجمالي العمولة:</span>
                        <span class="font-semibold text-red-700"><?php echo number_format($total_commission, 2); ?> دل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">اجمالي مصاريف الشحن:</span>
                        <span class="font-semibold text-blue-700"><?php echo number_format($total_shipping_cost, 2); ?> دل</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">الإيرادات بعد الخصم:</span>
                        <span class="font-semibold text-purple-700"><?php echo number_format($net_revenue, 2); ?> دل</span>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $delivery_rate; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">معدل التوصيل: <?php echo round($delivery_rate, 1); ?>%</p>
                </div>
                
                <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                    <button onclick="event.stopPropagation(); showEditCompanyModal(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['name']); ?>', '<?php echo htmlspecialchars($company['phone'] ?? ''); ?>', '<?php echo htmlspecialchars($company['email'] ?? ''); ?>', '<?php echo htmlspecialchars($company['address'] ?? ''); ?>')" 
                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-sm transition">
                        <i class='bx bx-edit'></i> تعديل
                    </button>
                    <button onclick="event.stopPropagation(); confirmDeleteCompany(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['name']); ?>')" 
                            class="flex-1 bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm transition">
                        <i class='bx bx-trash'></i> حذف
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Add New Company Box -->
        <div class="company-box bg-gray-50 rounded-lg shadow-sm border-2 border-dashed border-gray-300 p-4 hover:border-green-400 hover:bg-green-50 transition-all cursor-pointer flex items-center justify-center min-h-[200px]" 
             onclick="showAddCompanyModal()">
            <div class="text-center">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class='bx bx-plus text-green-600 text-xl'></i>
                </div>
                <p class="text-gray-600 font-medium">إضافة شركة جديدة</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Company Details Section -->
    <?php if ($selected_company): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">إحصائيات الأداء</h2>
                    <p class="text-gray-600">نظرة عامة على أداء الشركة المالي والتشغيلي</p>
                </div>
            </div>

            <!-- Company Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">هاتف الشركة</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($selected_company['phone'] ?? 'غير محدد'); ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">البريد الإلكتروني</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($selected_company['email'] ?? 'غير محدد'); ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">العنوان</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($selected_company['address'] ?? 'غير محدد'); ?></p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <div class="stat-card bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-blue-600 mb-1">قيد الانتظار</p>
                            <p class="text-2xl font-bold text-blue-700"><?php echo $company_stats['orders']['pending'] ?? 0; ?></p>
                            <p class="text-xs text-blue-600"><?php echo $company_stats['orders']['pending_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-time text-blue-500 text-2xl'></i>
                    </div>
                </div>

                <div class="stat-card bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-orange-600 mb-1">قيد التوصيل</p>
                            <p class="text-2xl font-bold text-orange-700"><?php echo $company_stats['orders']['shipping'] ?? 0; ?></p>
                            <p class="text-xs text-orange-600"><?php echo $company_stats['orders']['shipping_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-truck text-orange-500 text-2xl'></i>
                    </div>
                </div>

                <div class="stat-card bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-green-600 mb-1">تم التوصيل</p>
                            <p class="text-2xl font-bold text-green-700"><?php echo $company_stats['orders']['delivered'] ?? 0; ?></p>
                            <p class="text-xs text-green-600"><?php echo $company_stats['orders']['delivered_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-check-circle text-green-500 text-2xl'></i>
                    </div>
                </div>

                <div class="stat-card bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-red-600 mb-1">تم الإلغاء</p>
                            <p class="text-2xl font-bold text-red-700"><?php echo $company_stats['orders']['cancelled'] ?? 0; ?></p>
                            <p class="text-xs text-red-600"><?php echo $company_stats['orders']['cancelled_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-x-circle text-red-500 text-2xl'></i>
                    </div>
                </div>

                <div class="stat-card bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-purple-600 mb-1">مرتجع</p>
                            <p class="text-2xl font-bold text-purple-700"><?php echo $company_stats['orders']['returned'] ?? 0; ?></p>
                            <p class="text-xs text-purple-600"><?php echo $company_stats['orders']['returned_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-arrow-back text-purple-500 text-2xl'></i>
                    </div>
                </div>
            </div>

            <!-- Financial Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-sm text-green-600 mb-1">اجمالي الإيرادات</p>
                    <p class="text-xl font-bold text-green-700"><?php echo number_format($company_stats['financial']['total_revenue'] ?? 0, 2); ?> دل</p>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-sm text-red-600 mb-1">اجمالي العمولة</p>
                    <p class="text-xl font-bold text-red-700"><?php echo number_format($company_stats['financial']['total_commission'] ?? 0, 2); ?> دل</p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-600 mb-1">اجمالي مصاريف الشحن</p>
                    <p class="text-xl font-bold text-blue-700"><?php echo number_format($company_stats['financial']['total_shipping_cost'] ?? 0, 2); ?> دل</p>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <p class="text-sm text-purple-600 mb-1">الإيرادات بعد الخصم</p>
                    <p class="text-xl font-bold text-purple-700"><?php echo number_format($company_stats['financial']['revenue_after_deductions'] ?? 0, 2); ?> دل</p>
                </div>
            </div>

            <!-- City Assignment Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">المدن المدعومة</h3>
                    <button onclick="showAssignCitiesModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition flex items-center text-sm">
                        <i class='bx bx-map-pin ml-2'></i> إدارة المدن
                    </button>
                </div>
                
                <?php if (!empty($assigned_cities)): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($all_cities as $city): ?>
                            <?php if (in_array($city['id'], $assigned_cities)): ?>
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium border border-green-200">
                                    <i class='bx bx-map mr-1'></i> <?php echo htmlspecialchars($city['city_name']); ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                        <i class='bx bx-map text-gray-400 text-3xl mb-2'></i>
                        <p class="text-gray-500">لا توجد مدن مرتبطة بهذه الشركة حالياً</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Comprehensive Statistics -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">إحصائيات شاملة</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Orders Statistics -->
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-3">إحصائيات الطلبات</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">إجمالي الطلبات:</span>
                                <span class="font-bold text-gray-800"><?php echo $company_stats['orders']['total_orders'] ?? 0; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">قيد الانتظار:</span>
                                <span class="font-semibold text-blue-600"><?php echo $company_stats['orders']['pending'] ?? 0; ?> (<?php echo $company_stats['orders']['pending_percent'] ?? 0; ?>%)</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">قيد التوصيل:</span>
                                <span class="font-semibold text-orange-600"><?php echo $company_stats['orders']['shipping'] ?? 0; ?> (<?php echo $company_stats['orders']['shipping_percent'] ?? 0; ?>%)</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">تم التوصيل:</span>
                                <span class="font-semibold text-green-600"><?php echo $company_stats['orders']['delivered'] ?? 0; ?> (<?php echo $company_stats['orders']['delivered_percent'] ?? 0; ?>%)</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">ملغي:</span>
                                <span class="font-semibold text-red-600"><?php echo $company_stats['orders']['cancelled'] ?? 0; ?> (<?php echo $company_stats['orders']['cancelled_percent'] ?? 0; ?>%)</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">مرتجع:</span>
                                <span class="font-semibold text-purple-600"><?php echo $company_stats['orders']['returned'] ?? 0; ?> (<?php echo $company_stats['orders']['returned_percent'] ?? 0; ?>%)</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Statistics -->
                    <div>
                        <h4 class="font-semibold text-gray-700 mb-3">إحصائيات مالية</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">صافي الإيرادات:</span>
                                <span class="font-bold text-green-700"><?php echo number_format($company_stats['financial']['net_revenue'] ?? 0, 2); ?> دل</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">مصاريف الشحن:</span>
                                <span class="font-bold text-blue-700"><?php echo number_format($company_stats['financial']['total_shipping_cost'] ?? 0, 2); ?> دل</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">الربح (بدون عمولة):</span>
                                <span class="font-bold text-purple-700"><?php echo number_format($company_stats['financial']['profit_without_commission'] ?? 0, 2); ?> دل</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">إجمالي الإيرادات:</span>
                                <span class="font-bold text-orange-700"><?php echo number_format($company_stats['financial']['gross_revenue'] ?? 0, 2); ?> دل</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">الطلبات المكتملة:</span>
                                <span class="font-bold text-green-600"><?php echo $company_stats['financial']['delivered_count'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Visual Charts -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="font-semibold text-gray-700 mb-4">رسوم بيانية</h4>
                    <div class="space-y-4">
                        <?php 
                        $total_orders = $company_stats['orders']['total_orders'] ?? 0;
                        if ($total_orders > 0): 
                            $statuses = [
                                ['name' => 'قيد الانتظار', 'count' => $company_stats['orders']['pending'] ?? 0, 'color' => 'blue', 'icon' => 'bx-time'],
                                ['name' => 'قيد التوصيل', 'count' => $company_stats['orders']['shipping'] ?? 0, 'color' => 'orange', 'icon' => 'bx-truck'],
                                ['name' => 'تم التوصيل', 'count' => $company_stats['orders']['delivered'] ?? 0, 'color' => 'green', 'icon' => 'bx-check-circle'],
                                ['name' => 'ملغي', 'count' => $company_stats['orders']['cancelled'] ?? 0, 'color' => 'red', 'icon' => 'bx-x-circle'],
                                ['name' => 'مرتجع', 'count' => $company_stats['orders']['returned'] ?? 0, 'color' => 'purple', 'icon' => 'bx-arrow-back']
                            ];
                            
                            foreach ($statuses as $status): 
                                $percentage = $total_orders > 0 ? ($status['count'] / $total_orders) * 100 : 0;
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <div class="w-10 h-10 bg-<?php echo $status['color']; ?>-100 rounded-full flex items-center justify-center">
                                        <i class='bx <?php echo $status['icon']; ?> text-<?php echo $status['color']; ?>-600'></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo $status['name']; ?></p>
                                        <p class="text-sm text-gray-500"><?php echo $status['count']; ?> طلب</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3 space-x-reverse">
                                    <div class="w-32 bg-gray-200 rounded-full h-2">
                                        <div class="bg-<?php echo $status['color']; ?>-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-<?php echo $status['color']; ?>-700 w-12 text-left"><?php echo round($percentage, 1); ?>%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-center text-gray-500 py-8">لا توجد طلبات لهذه الشركة بعد</p>
                            <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($total_orders > 0): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-bold text-gray-800">إجمالي الطلبات</span>
                        <span class="text-lg font-bold text-gray-800"><?php echo $total_orders; ?></span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600">
                        <i class='bx bx-info-circle'></i>
                        نسبة التوصيل الناجح: <?php echo $company_stats['orders']['delivered_percent'] ?? 0; ?>%
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">الطلبات المرتبطة بالشركة</h3>
                
                <?php if (!empty($all_company_orders)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border border-gray-200 px-4 py-2 text-right">#</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">العميل</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">المنتجات</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">المنطقة</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">المجموع</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">الشحن</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">الحالة</th>
                                    <th class="border border-gray-200 px-4 py-2 text-right">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_company_orders as $order): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 px-4 py-2 text-sm">#<?php echo $order['id']; ?></td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm">
                                            <div class="max-w-xs">
                                                <span class="text-xs text-gray-600"><?php echo $order['items_count']; ?> منتج</span><br>
                                                <span class="text-xs"><?php echo htmlspecialchars($order['product_names'] ?? 'غير محدد'); ?></span>
                                            </div>
                                        </td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo htmlspecialchars($order['region']); ?></td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo number_format($order['total'], 2); ?> دل</td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo number_format($order['shipping_cost'], 2); ?> دل</td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?php 
                                                $status_class = '';
                                                switch($order['status']) {
                                                    case 'قيد الانتظار': $status_class = 'bg-blue-100 text-blue-700'; break;
                                                    case 'قيد التنفيذ': 
                                                    case 'تحت التحضير': 
                                                    case 'في الشحن': $status_class = 'bg-orange-100 text-orange-700'; break;
                                                    case 'تم التوصيل': $status_class = 'bg-green-100 text-green-700'; break;
                                                    case 'ملغي': 
                                                    case 'مرفوض': $status_class = 'bg-red-100 text-red-700'; break;
                                                    case 'مرتجع': 
                                                    case 'محصل': $status_class = 'bg-purple-100 text-purple-700'; break;
                                                    default: $status_class = 'bg-gray-100 text-gray-700';
                                                }
                                                echo $status_class;
                                                ?>">
                                                <?php echo htmlspecialchars($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class='bx bx-package text-gray-400 text-2xl'></i>
                        </div>
                        <p class="text-gray-500">لا توجد طلبات مرتبطة بهذه الشركة حالياً</p>
                        <p class="text-sm text-gray-400 mt-2">سيتم عرض الطلبات هنا بمجرد ربط المنتجات بشركة الشحن</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Company Products with Orders -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">منتجات الشركة وتفاصيل الطلبات</h3>
                
                <?php if (!empty($company_products_with_orders)): ?>
                    <!-- DEBUG: Show all products data -->
                    <?php 
                    echo "<!-- DEBUG: Total products found: " . count($company_products_with_orders) . " -->";
                    echo "<!-- DEBUG: Selected company ID: " . $selected_company_id . " -->";
                    echo "<!-- DEBUG: Company name: " . ($selected_company['name'] ?? 'NULL') . " -->";
                    
                    foreach ($company_products_with_orders as $index => $product) {
                        echo "<!-- PRODUCT $index: " . htmlspecialchars($product['name']) . " -->";
                        echo "<!--   - Image: " . ($product['image'] ?? 'NULL') . " -->";
                        echo "<!--   - ID: " . $product['id'] . " -->";
                        echo "<!--   - Orders: " . $product['total_orders'] . " -->";
                        echo "<!--   - Stock: " . $product['stock'] . " -->";
                        echo "<!--   - Commission: " . $product['commission'] . " -->";
                        
                        // Check if this might be hair cream
                        if (strpos(strtolower($product['name']), 'كريم') !== false || 
                            strpos(strtolower($product['name']), 'شعر') !== false ||
                            strpos(strtolower($product['name']), 'cream') !== false ||
                            strpos(strtolower($product['name']), 'hair') !== false) {
                            echo "<!--   *** POTENTIAL HAIR CREAM PRODUCT *** -->";
                        }
                        
                        // Check if image file exists
                        if (!empty($product['image'])) {
                            // Fix double imgs/ path
                            $image_path = $product['image'];
                            if (str_starts_with($image_path, 'imgs/imgs/')) {
                                $image_path = str_replace('imgs/imgs/', 'imgs/', $image_path);
                            } elseif (!str_starts_with($image_path, 'imgs/')) {
                                $image_path = 'imgs/' . $image_path;
                            }
                            
                            if (file_exists($image_path)) {
                                echo "<!--   - Image file EXISTS: $image_path -->";
                            } else {
                                echo "<!--   - Image file MISSING: $image_path -->";
                            }
                        }
                    }
                    
                    // Also show what files exist in imgs folder
                    $imgs_files = ['1763340271_0_shopping.webp', 'shopping.webp', 'zity.webp', '68fc8f3b4dec8.webp'];
                    echo "<!-- DEBUG: Available files in imgs folder: " . implode(', ', $imgs_files) . " -->";
                    
                    // Show if any products have NULL images
                    $null_images = array_filter($company_products_with_orders, function($p) { 
                        return empty($p['image']); 
                    });
                    if (!empty($null_images)) {
                        echo "<!-- DEBUG: Products with NULL images: " . count($null_images) . " -->";
                        foreach ($null_images as $null_product) {
                            echo "<!--   - " . htmlspecialchars($null_product['name']) . " (ID: " . $null_product['id'] . ") -->";
                        }
                    }
                    ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($company_products_with_orders as $product): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <!-- Product Image and Basic Info -->
                                <div class="flex items-start space-x-3 space-x-reverse mb-4">
                                    <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <?php 
                                        $show_image = false;
                                        $image_path = '';
                                        $debug_info = '';
                                        if (!empty($product['image'])) {
                                            $image_path = $product['image'];
                                            // Fix double imgs/ path
                                            if (str_starts_with($image_path, 'imgs/imgs/')) {
                                                $image_path = str_replace('imgs/imgs/', 'imgs/', $image_path);
                                            } elseif (!str_starts_with($image_path, 'imgs/') && !str_starts_with($image_path, '/imgs/')) {
                                                $image_path = 'imgs/' . $image_path;
                                            }
                                            $debug_info = 'File: ' . $image_path;
                                            $show_image = true;
                                        } else {
                                            // Use a default image for products without images
                                            $image_path = 'imgs/68e0357a3cbd4.webp';
                                            $debug_info = 'Using default image (no image in database)';
                                            $show_image = true;
                                        }
                                        ?>
                                        <?php if ($show_image): ?>
                                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover rounded-lg" onerror="this.style.display='none'; this.parentElement.nextElementSibling.style.display='flex';" style="object-fit: cover;" title="<?php echo htmlspecialchars($debug_info); ?>" loading="lazy">
                                            <div class="w-full h-full flex items-center justify-center" style="display:none;" title="<?php echo htmlspecialchars($debug_info); ?>">
                                                <i class='bx bx-image text-gray-400 text-2xl'></i>
                                            </div>
                                        <?php else: ?>
                                            <i class='bx bx-image text-gray-400 text-2xl' title="<?php echo htmlspecialchars($debug_info); ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($product['name']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo number_format($product['price'], 2); ?> دل</p>
                                        <p class="text-xs text-gray-500 font-bold text-blue-600">المخزون المخصص: <?php echo $product['stock']; ?> قطعة</p>
                                    </div>
                                </div>
                                
                                <!-- Order Statistics -->
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">إجمالي الطلبات:</span>
                                        <span class="font-semibold text-blue-600"><?php echo $product['total_orders']; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">طلبات تم التوصيل:</span>
                                        <span class="font-semibold text-green-600"><?php echo $product['delivered_orders']; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">إجمالي الكمية:</span>
                                        <span class="font-semibold text-purple-600"><?php echo $product['total_quantity']; ?> قطعة</span>
                                    </div>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">الكمية المسلمة:</span>
                                        <span class="font-semibold text-green-600"><?php echo $product['delivered_quantity']; ?> قطعة</span>
                                    </div>
                                    
                                    <!-- Order Status Breakdown -->
                                    <?php if (!empty($product['status_counts'])): ?>
                                        <div class="pt-2 border-t border-gray-100">
                                            <p class="text-xs text-gray-500 mb-1">حالات الطلبات:</p>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($product['status_counts'] as $status => $count): ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                                        <?php 
                                                        $status_class = '';
                                                        switch($status) {
                                                            case 'قيد الانتظار': $status_class = 'bg-blue-100 text-blue-700'; break;
                                                            case 'قيد التنفيذ': 
                                                            case 'تحت التحضير': 
                                                            case 'في الشحن': $status_class = 'bg-orange-100 text-orange-700'; break;
                                                            case 'تم التوصيل': $status_class = 'bg-green-100 text-green-700'; break;
                                                            case 'ملغي': 
                                                            case 'مرفوض': $status_class = 'bg-red-100 text-red-700'; break;
                                                            case 'مرتجع': 
                                                            case 'محصل': $status_class = 'bg-purple-100 text-purple-700'; break;
                                                            default: $status_class = 'bg-gray-100 text-gray-700';
                                                        }
                                                        echo $status_class;
                                                        ?>">
                                                        <?php echo htmlspecialchars($status); ?>: <?php echo $count; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class='bx bx-package text-gray-400 text-2xl'></i>
                        </div>
                        <p class="text-gray-500">لا توجد منتجات مرتبطة بهذه الشركة حالياً</p>
                        <p class="text-sm text-gray-400 mt-2">سيتم عرض المنتجات هنا بمجرد ربطها بشركة الشحن</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Products Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-indigo-600 mb-1">إجمالي المنتجات</p>
                            <p class="text-2xl font-bold text-indigo-700"><?php echo $company_products_stats['products']['total_products'] ?? 0; ?></p>
                        </div>
                        <i class='bx bx-package text-indigo-500 text-2xl'></i>
                    </div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-green-600 mb-1">متوفرة</p>
                            <p class="text-2xl font-bold text-green-700"><?php echo $company_products_stats['products']['available_products'] ?? 0; ?></p>
                            <p class="text-xs text-green-600"><?php echo $company_products_stats['products']['available_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-check-circle text-green-500 text-2xl'></i>
                    </div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-yellow-600 mb-1">مخزون منخفض</p>
                            <p class="text-2xl font-bold text-yellow-700"><?php echo $company_products_stats['products']['low_stock_products'] ?? 0; ?></p>
                            <p class="text-xs text-yellow-600"><?php echo $company_products_stats['products']['low_stock_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-error-circle text-yellow-500 text-2xl'></i>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-red-600 mb-1">نفذت</p>
                            <p class="text-2xl font-bold text-red-700"><?php echo $company_products_stats['products']['out_of_stock_products'] ?? 0; ?></p>
                            <p class="text-xs text-red-600"><?php echo $company_products_stats['products']['out_of_stock_percent'] ?? 0; ?>%</p>
                        </div>
                        <i class='bx bx-x-circle text-red-500 text-2xl'></i>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">الطلبات الأخيرة</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-700">رقم الطلب</th>
                                <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-700">العميل</th>
                                <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-700">الإجمالي</th>
                                <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-700">تكلفة الشحن</th>
                                <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-700">الحالة</th>
                                <th class="border border-gray-200 px-4 py-2 text-right text-sm font-medium text-gray-700">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_company_orders as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 px-4 py-2 text-sm">#<?php echo $order['id']; ?></td>
                                    <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo number_format($order['total'], 2); ?> دل</td>
                                    <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo number_format($order['shipping_cost'], 2); ?> دل</td>
                                    <td class="border border-gray-200 px-4 py-2 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?php 
                                            $status_class = '';
                                            switch($order['status']) {
                                                case 'قيد الانتظار': $status_class = 'bg-blue-100 text-blue-700'; break;
                                                case 'قيد التنفيذ': 
                                                case 'تحت التحضير': 
                                                case 'في الشحن': $status_class = 'bg-orange-100 text-orange-700'; break;
                                                case 'تم التوصيل': $status_class = 'bg-green-100 text-green-700'; break;
                                                case 'ملغي': 
                                                case 'مرفوض': $status_class = 'bg-red-100 text-red-700'; break;
                                                case 'مرتجع': 
                                                case 'محصل': $status_class = 'bg-purple-100 text-purple-700'; break;
                                                default: $status_class = 'bg-gray-100 text-gray-700';
                                            }
                                            echo $status_class;
                                            ?>">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-200 px-4 py-2 text-sm"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($recent_company_orders)): ?>
                        <p class="text-center text-gray-500 py-4">لا توجد طلبات حديثة لهذه الشركة</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Company Modal -->
<div id="addCompanyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">إضافة شركة شحن جديدة</h3>
            <button onclick="hideAddCompanyModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">اسم الشركة *</label>
                <input type="text" name="company_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هاتف الشركة</label>
                <input type="text" name="company_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                <input type="email" name="company_email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                <textarea name="company_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" name="add_company" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                    إضافة الشركة
                </button>
                <button type="button" onclick="hideAddCompanyModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Company Modal -->
<div id="editCompanyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">تعديل شركة الشحن</h3>
            <button onclick="hideEditCompanyModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" id="edit_company_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">اسم الشركة *</label>
                <input type="text" name="company_name" id="edit_company_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هاتف الشركة</label>
                <input type="text" name="company_phone" id="edit_company_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                <input type="email" name="company_email" id="edit_company_email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                <textarea name="company_address" id="edit_company_address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" name="edit_company" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    تحديث الشركة
                </button>
                <button type="button" onclick="hideEditCompanyModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteCompanyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">تأكيد الحذف</h3>
            <button onclick="hideDeleteCompanyModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        
        <div class="mb-6">
            <p class="text-gray-600">هل أنت متأكد من حذف شركة الشحن "<span id="delete_company_name" class="font-bold"></span>"؟</p>
            <p class="text-sm text-red-600 mt-2">لا يمكن حذف الشركة إذا كانت لديها طلبات مرتبطة بها.</p>
        </div>
        
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" id="delete_company_id">
            
            <div class="flex gap-3">
                <button type="submit" name="delete_company" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">
                    حذف الشركة
                </button>
                <button type="button" onclick="hideDeleteCompanyModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Cities Modal -->
<div id="assignCitiesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">إدارة المدن المدعومة - <span id="assign_company_name_title"><?php echo htmlspecialchars($selected_company['name'] ?? ''); ?></span></h3>
            <button onclick="hideAssignCitiesModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        
        <form method="POST" class="flex flex-col flex-1 min-h-0">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?php echo $selected_company_id ?? ''; ?>">
            
            <div class="mb-4">
                <input type="text" id="citySearch" placeholder="ابحث عن مدينة..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" onkeyup="filterCitiesList()">
            </div>
            
            <div class="flex justify-between mb-2 px-2">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="selectAllCities" class="form-checkbox h-5 w-5 text-green-600 rounded border-gray-300" onchange="toggleAllCities(this)">
                    <span class="mr-2 text-sm text-gray-700 font-bold">تحديد الكل</span>
                </label>
            </div>
            
            <div class="overflow-y-auto flex-1 border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-2" id="citiesListContainer">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <?php foreach ($all_cities as $city): ?>
                        <label class="inline-flex items-center cursor-pointer bg-white p-2 rounded border border-gray-200 hover:border-green-400 transition city-item-label">
                            <input type="checkbox" name="cities[]" value="<?php echo $city['id']; ?>" class="form-checkbox h-5 w-5 text-green-600 rounded border-gray-300 city-checkbox" <?php echo (in_array($city['id'], $assigned_cities ?? [])) ? 'checked' : ''; ?>>
                            <span class="mr-2 text-sm text-gray-700 city-name-text"><?php echo htmlspecialchars($city['city_name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="flex gap-3 mt-4 pt-4 border-t border-gray-200">
                <button type="submit" name="assign_cities" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition font-bold">
                    حفظ التغييرات
                </button>
                <button type="button" onclick="hideAssignCitiesModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function selectCompany(companyId) {
    const url = new URL(window.location);
    url.searchParams.set('company_id', companyId);
    window.location.href = url.toString();
}

function deselectCompany() {
    const url = new URL(window.location);
    url.searchParams.delete('company_id');
    window.location.href = url.toString();
}

function showAddCompanyModal() {
    document.getElementById('addCompanyModal').classList.remove('hidden');
    document.getElementById('addCompanyModal').classList.add('flex');
}

function hideAddCompanyModal() {
    document.getElementById('addCompanyModal').classList.add('hidden');
    document.getElementById('addCompanyModal').classList.remove('flex');
}

function showEditCompanyModal(id, name, phone, email, address) {
    document.getElementById('edit_company_id').value = id;
    document.getElementById('edit_company_name').value = name;
    document.getElementById('edit_company_phone').value = phone;
    document.getElementById('edit_company_email').value = email;
    document.getElementById('edit_company_address').value = address;
    
    document.getElementById('editCompanyModal').classList.remove('hidden');
    document.getElementById('editCompanyModal').classList.add('flex');
}

function hideEditCompanyModal() {
    document.getElementById('editCompanyModal').classList.add('hidden');
    document.getElementById('editCompanyModal').classList.remove('flex');
}

function confirmDeleteCompany(id, name) {
    document.getElementById('delete_company_id').value = id;
    document.getElementById('delete_company_name').textContent = name;
    
    document.getElementById('deleteCompanyModal').classList.remove('hidden');
    document.getElementById('deleteCompanyModal').classList.add('flex');
}

function hideDeleteCompanyModal() {
    document.getElementById('deleteCompanyModal').classList.add('hidden');
    document.getElementById('deleteCompanyModal').classList.remove('flex');
}

// Close modals when clicking outside
document.getElementById('addCompanyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideAddCompanyModal();
    }
});

document.getElementById('editCompanyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideEditCompanyModal();
    }
});

document.getElementById('deleteCompanyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDeleteCompanyModal();
    }
});

function showAssignCitiesModal() {
    document.getElementById('assignCitiesModal').classList.remove('hidden');
    document.getElementById('assignCitiesModal').classList.add('flex');
}

function hideAssignCitiesModal() {
    document.getElementById('assignCitiesModal').classList.add('hidden');
    document.getElementById('assignCitiesModal').classList.remove('flex');
}

document.getElementById('assignCitiesModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideAssignCitiesModal();
    }
});

function filterCitiesList() {
    const input = document.getElementById('citySearch').value.toLowerCase();
    const labels = document.querySelectorAll('.city-item-label');
    
    labels.forEach(label => {
        const text = label.querySelector('.city-name-text').textContent.toLowerCase();
        if (text.includes(input)) {
            label.style.display = 'inline-flex';
        } else {
            label.style.display = 'none';
        }
    });
}

function toggleAllCities(checkbox) {
    const checkboxes = document.querySelectorAll('.city-checkbox');
    checkboxes.forEach(cb => {
        // Only toggle visible checkboxes if filtering is active
        const label = cb.closest('.city-item-label');
        if (label.style.display !== 'none') {
            cb.checked = checkbox.checked;
        }
    });
}
</script>

<style>
.company-box {
    transition: all 0.3s ease;
}

.company-box:hover {
    transform: translateY(-2px);
}

.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}
</style>
