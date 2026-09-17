<?php
// حماية الصفحة: مسموح للدعم الفني وشركات الشحن
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['support', 'shipping_company'])) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>ليس مصرح لك بالدخول</div>";
    return;
}

// 🔹 التحقق من وجود جداول الأوردرات
$check_orders_table = $conn->query("SHOW TABLES LIKE 'support_orders'");
$check_stats_table = $conn->query("SHOW TABLES LIKE 'support_daily_stats'");

// Ensure shipping_notes column exists
$check_notes = $conn->query("SHOW COLUMNS FROM support_orders LIKE 'shipping_notes'");
if ($check_notes && $check_notes->num_rows == 0) {
    $conn->query("ALTER TABLE support_orders ADD COLUMN shipping_notes TEXT NULL AFTER snap_dom_shipping");
}

if (!$check_orders_table || $check_orders_table->num_rows == 0 || !$check_stats_table || $check_stats_table->num_rows == 0) {
    echo "<div class='bg-yellow-50 border border-yellow-300 rounded-lg p-6 mb-6'>
        <h3 class='text-lg font-bold text-yellow-800 mb-2'>⚠️ جداول الأوردرات مش موجودة</h3>
        <p class='text-gray-700 mb-4'>لازم تإنشاء الجداول الأول عشان تقدر تستخدم الشيت.</p>
        <a href='setup_orders_tables.php' class='inline-block bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 font-bold'>
            🔧 إنشاء الجداول الآن
        </a>
    </div>";
    return;
}

$support_id = $_SESSION['admin_id'];
$company_reps = [];
$support_name = $_SESSION['admin_username'] ?? 'unknown';

// معالجة حفظ تحديثات شركة الشحن (ملاحظات، حالة، مندوب)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipping_updates'])) {
    if (isset($_POST['notes']) && is_array($_POST['notes'])) {
        $stmt = $conn->prepare("UPDATE support_orders SET shipping_notes = ?, order_status = ?, shipping_rep_id = ? WHERE id = ? AND shipping_company_id = ?");
        $stmt_check = $conn->prepare("SELECT shipping_notes, order_status, shipping_rep_id, customer_name FROM support_orders WHERE id = ? AND shipping_company_id = ?");
        $sc_id = (int)($_SESSION['shipping_company_id'] ?? 0);
        
        $changes_made = 0;
        foreach ($_POST['notes'] as $id => $note) {
            $id = (int)$id;
            $note_clean = clean_input($note);
            $new_status = clean_input($_POST['order_status'][$id] ?? 'pending');
            $new_rep = (int)($_POST['shipping_rep_id'][$id] ?? 0);
            $new_rep = $new_rep > 0 ? $new_rep : null;
            
            // Fetch old values
            $old_note = '';
            $old_status = '';
            $old_rep = null;
            $customer_name = '';
            $product_code = 'Etala001';
            $pieces = 1;
            $stmt_check = $conn->prepare("SELECT shipping_notes, order_status, shipping_rep_id, customer_name, product_code, pieces FROM support_orders WHERE id = ? AND shipping_company_id = ?");
            $stmt_check->bind_param("ii", $id, $sc_id);
            $stmt_check->execute();
            $res = $stmt_check->get_result();
            if ($row = $res->fetch_assoc()) {
                $old_note = $row['shipping_notes'] ?? '';
                $old_status = $row['order_status'] ?? '';
                $old_rep = $row['shipping_rep_id'] ? (int)$row['shipping_rep_id'] : null;
                $customer_name = $row['customer_name'] ?? 'مجهول';
                $product_code = $row['product_code'] ?? 'Etala001';
                $pieces = (int)($row['pieces'] ?? 1);
            }
            
            // Inventory Logic:
            $inventory_error = false;
            if ($old_status !== 'handed_to_rep' && $new_status === 'handed_to_rep') {
                if (!shipping_inventory_deduct($conn, $sc_id, $product_code, $pieces)) {
                    $inventory_error = true;
                    echo "<div class='bg-red-100 text-red-700 p-4 rounded mb-4'>❌ رصيدك من المنتج ($product_code) لا يكفي لتسليم الطلب للعميل ($customer_name) للمندوب!</div>";
                    continue; // Skip this order update
                } else {
                    shipping_inventory_check_low_stock($conn, $sc_id, $product_code);
                }
            } elseif ($old_status === 'handed_to_rep' && !in_array($new_status, ['handed_to_rep', 'delivered', 'order_delivered'])) {
                shipping_inventory_add($conn, $sc_id, $product_code, $pieces);
            }
            
            // Main Inventory Logic (for Support & General):
            $is_old_cancelled = in_array($old_status, ['cancelled', 'order_cancelled', 'returned']);
            $is_new_cancelled = in_array($new_status, ['cancelled', 'order_cancelled', 'returned']);
            
            if (!$is_old_cancelled && $is_new_cancelled) {
                // Order became cancelled -> Refund Main Stock
                if (function_exists('main_inventory_refund')) {
                    main_inventory_refund($conn, $product_code, $pieces);
                }
            } elseif ($is_old_cancelled && !$is_new_cancelled) {
                // Order reactivated -> Deduct Main Stock
                if (function_exists('main_inventory_deduct')) {
                    main_inventory_deduct($conn, $product_code, $pieces);
                }
            }
            
            if ($old_note !== $note_clean || $old_status !== $new_status || $old_rep !== $new_rep) {
                $stmt->bind_param("ssiii", $note_clean, $new_status, $new_rep, $id, $sc_id);
                $stmt->execute();
                $changes_made++;

                if ($old_status !== $new_status) {
                    if ($new_status === 'handed_to_rep') {
                        $conn->query("UPDATE support_orders SET handed_to_rep_at = NOW() WHERE id = $id AND handed_to_rep_at IS NULL");
                    } elseif ($new_status === 'received') {
                        $conn->query("UPDATE support_orders SET received_at = NOW() WHERE id = $id AND received_at IS NULL");
                    }
                }
                
                // إشعار للإدارة إذا كانت هناك ملاحظة جديدة أو تغيير حالة
                $sc_name = $_SESSION['admin_fullname'] ?? 'شركة الشحن';
                $title = "تحديث طلب من $sc_name";
                $message = "تم تحديث طلب العميل $customer_name";
                if ($old_status !== $new_status) {
                    $message .= " (الحالة: $new_status)";
                }
                if ($old_note !== $note_clean && !empty($note_clean)) {
                    $message .= " | ملاحظة: " . mb_substr($note_clean, 0, 50) . "...";
                }
                
                $link = "admin_panel.php?page=support";
                send_notification($conn, 'admin', 0, $title, $message, $link);
            }
        }
        if ($changes_made > 0) {
            echo "<div class='bg-green-100 text-green-700 p-4 rounded mb-4'>✅ تم حفظ التحديثات بنجاح وإرسال إشعار للإدارة!</div>";
        } else {
            echo "<div class='bg-blue-100 text-blue-700 p-4 rounded mb-4'>ℹ️ لم يتم العثور على تغييرات جديدة.</div>";
        }
    }
}

// معالجة حفظ الأوردر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_orders'])) {
    $orders = $_POST['orders'] ?? [];
    $today = date('Y-m-d');
    
    $stats = [
        'total' => 0,
        'confirmed' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'revenue' => 0
    ];
    
    foreach ($orders as $order) {
        if (empty($order['customer_name'])) continue;
        
        $bundle_type = $order['bundle_type'] ?? 'single';
        if ($bundle_type === 'custom') {
            $quantity = max(1, (int) ($order['custom_quantity'] ?? 1));
            $total_price = (float) ($order['custom_price'] ?? 0.0);
            $unit_price = $total_price / $quantity;
            
            $pricing = function_exists('resolve_support_order_pricing')
                ? resolve_support_order_pricing($conn, 1, 'Etala001', date('Y-m'))
                : null;
            if ($pricing) {
                $snap_product_cost = $pricing['snap_product_cost'] * $quantity;
                $snap_bundle_cost = $snap_product_cost; 
                $snap_dom_shipping = $pricing['snap_dom_shipping'];
            } else {
                $snap_product_cost = null;
                $snap_bundle_cost = null;
                $snap_dom_shipping = null;
            }
        } else {
            $pieces = ($bundle_type === 'bundle') ? 3 : 1;
            $pricing = function_exists('resolve_support_order_pricing')
                ? resolve_support_order_pricing($conn, $pieces, 'Etala001', date('Y-m'))
                : null;
            if ($pricing) {
                $bundle_type = $pricing['bundle_type'];
                $quantity = (int) $pricing['quantity'];
                $unit_price = (float) $pricing['unit_price'];
                $total_price = (float) $pricing['total_price'];
                $snap_product_cost = $pricing['snap_product_cost'];
                $snap_bundle_cost = $pricing['snap_bundle_cost'];
                $snap_dom_shipping = $pricing['snap_dom_shipping'];
            } else {
                $quantity = ($bundle_type === 'bundle') ? 3 : 1;
                $unit_price = ($bundle_type === 'bundle') ? 95.00 : 150.00;
                $total_price = ($bundle_type === 'bundle') ? 285.00 : 150.00;
                $snap_product_cost = null;
                $snap_bundle_cost = null;
                $snap_dom_shipping = null;
            }
        }
        $status = $order['status'] ?? 'pending';
        
        // إدخال أو تحديث الأوردر
        $stmt = $conn->prepare("INSERT INTO support_orders 
            (support_id, support_name, product_code, customer_name, phone, address, bundle_type, quantity, unit_price, total_price, order_status) 
            VALUES (?, ?, 'Etala001', ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            phone = VALUES(phone),
            address = VALUES(address),
            bundle_type = VALUES(bundle_type),
            quantity = VALUES(quantity),
            unit_price = VALUES(unit_price),
            total_price = VALUES(total_price),
            order_status = VALUES(order_status)");
        
        // For simplicity, using INSERT without duplicate check for new orders
        $stmt = $conn->prepare("INSERT INTO support_orders 
            (support_id, support_name, product_code, customer_name, phone, address, bundle_type, quantity, unit_price, total_price, order_status, snap_product_cost, snap_bundle_cost, snap_dom_shipping) 
            VALUES (?, ?, 'Etala001', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("issssidddsddd", 
            $support_id, 
            $support_name,
            $order['customer_name'], 
            $order['phone'], 
            $order['address'], 
            $bundle_type,
            $quantity,
            $unit_price,
            $total_price,
            $status,
            $snap_product_cost,
            $snap_bundle_cost,
            $snap_dom_shipping
        );
        $stmt->execute();
        
        // تحديث الإحصائيات
        $stats['total']++;
        $stats['revenue'] += $total_price;
        
        if ($status === 'confirmed') $stats['confirmed']++;
        elseif ($status === 'delivered') $stats['delivered']++;
        elseif ($status === 'cancelled') $stats['cancelled']++;
    }
    
    // تحديث ملخص اليوم
    $stmt = $conn->prepare("INSERT INTO support_daily_stats 
        (support_id, stat_date, total_orders, confirmed_orders, delivered_orders, cancelled_orders, total_revenue)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        total_orders = total_orders + VALUES(total_orders),
        confirmed_orders = confirmed_orders + VALUES(confirmed_orders),
        delivered_orders = delivered_orders + VALUES(delivered_orders),
        cancelled_orders = cancelled_orders + VALUES(cancelled_orders),
        total_revenue = total_revenue + VALUES(total_revenue)");
    
    $stmt->bind_param("isiiiid", $support_id, $today, $stats['total'], $stats['confirmed'], $stats['delivered'], $stats['cancelled'], $stats['revenue']);
    $stmt->execute();
    
    echo "<div class='bg-green-100 text-green-700 p-4 rounded mb-4'>✅ تم حفظ " . $stats['total'] . " أوردر بنجاح!</div>";
}

// جلب أوردرات اليوم
$today = date('Y-m-d');
$orders = false;
if ($_SESSION['admin_role'] === 'shipping_company') {
    $sc_id = (int)($_SESSION['shipping_company_id'] ?? 0);
    $search = clean_input($_GET['search'] ?? '');
    $search_sql = "";
    if (!empty($search)) {
        $search_esc = $conn->real_escape_string($search);
        $search_sql = " AND (customer_name LIKE '%$search_esc%' OR phone LIKE '%$search_esc%' OR address LIKE '%$search_esc%' OR id = '$search_esc') ";
    }
    
    $orders = $conn->query("SELECT * FROM support_orders 
        WHERE shipping_company_id = $sc_id $search_sql 
        ORDER BY created_at DESC");
        
    // جلب مناديب الشركة
    $reps_q = $conn->query("SELECT id, name FROM shipping_company_reps WHERE company_id = $sc_id ORDER BY name ASC");
    $company_reps = [];
    if ($reps_q) {
        while ($r = $reps_q->fetch_assoc()) {
            $company_reps[] = $r;
        }
    }
} else {
    $orders = $conn->query("SELECT * FROM support_orders 
        WHERE support_id = $support_id 
        AND DATE(created_at) = '$today'
        ORDER BY created_at DESC");
}

$existing_orders = [];
if ($orders && $orders->num_rows > 0) {
    while ($row = $orders->fetch_assoc()) {
        $existing_orders[] = $row;
    }
}

// ملخص اليوم
$summary = $conn->query("SELECT * FROM support_daily_stats 
    WHERE support_id = $support_id AND stat_date = '$today'
    LIMIT 1");
$today_stats = $summary ? $summary->fetch_assoc() : null;
?>

<?php if ($_SESSION['admin_role'] === 'shipping_company'): ?>
    <!-- Shipping company custom read-only view with notes -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6 font-sans">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <h2 class="text-2xl font-bold flex items-center text-indigo-800">
                <i class='bx bx-package mr-2 text-indigo-500'></i>
                الطلبات المسندة للشركة
            </h2>
            <div class="flex gap-2 w-full md:w-auto relative">
                <i class='bx bx-search absolute right-3 top-3 text-gray-400'></i>
                <input type="text" id="live-search-input" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="رقم الهاتف، الاسم، الكود..." class="w-full md:w-80 p-2 pr-10 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-200" onkeyup="filterOrdersTable()">
            </div>
        </div>
        
        <form method="POST">
            <?= csrf_field() ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-indigo-50 border-b-2 border-indigo-100">
                        <tr>
                            <th class="py-3 px-4 text-right font-bold text-indigo-800">#</th>
                            <th class="py-3 px-4 text-right font-bold text-indigo-800">معلومات الطلب</th>
                            <th class="py-3 px-4 text-center font-bold text-indigo-800">النوع</th>
                            <th class="py-3 px-4 text-center font-bold text-indigo-800">السعر النهائي</th>
                            <th class="py-3 px-4 text-center font-bold text-indigo-800 w-48">تحديث الطلب (حالة/مندوب)</th>
                            <th class="py-3 px-4 text-right font-bold text-indigo-800 w-1/4">ملاحظاتك للإدارة</th>
                        </tr>
                    </thead>
                    <tbody id="orders-table-body">
                        <?php foreach ($existing_orders as $i => $order): 
                            $status = $order['order_status'] ?? 'pending';
                            $row_class = 'hover:bg-slate-50 transition-colors';
                            $status_map = [
                                'no_answer_1' => ['class' => 'bg-yellow-100 text-yellow-800', 'text' => 'لم يتم الرد أول مرة'],
                                'no_answer_2' => ['class' => 'bg-yellow-200 text-yellow-900', 'text' => 'لم يتم الرد ثانية'],
                                'no_answer_3' => ['class' => 'bg-yellow-300 text-yellow-900', 'text' => 'لم يتم الرد نهائياً'],
                                'contact_later' => ['class' => 'bg-gray-200 text-gray-800', 'text' => 'يتواصل بوقت لاحق'],
                                'wrong_number' => ['class' => 'bg-red-100 text-red-600', 'text' => 'الرقم خطأ'],
                                'order_confirmed' => ['class' => 'bg-blue-100 text-blue-700', 'text' => '✅ تم التأكيد'],
                                'confirmed' => ['class' => 'bg-blue-100 text-blue-700', 'text' => '✅ تم التأكيد'],
                                'handed_to_rep' => ['class' => 'bg-teal-50 text-teal-800', 'text' => '🤝 تم تسليم المنتج للمندوب'],
                                'received' => ['class' => 'bg-teal-100 text-teal-700', 'text' => '✔️ تم الاستلام'],
                                'ready_to_ship' => ['class' => 'bg-indigo-100 text-indigo-700', 'text' => '📦 جاهز للشحن'],
                                'out_for_delivery' => ['class' => 'bg-purple-100 text-purple-700', 'text' => '🚚 تم التوصيل'],
                                'delivered' => ['class' => 'bg-green-100 text-green-700', 'text' => '📦 تم التسليم'],
                                'order_cancelled' => ['class' => 'bg-red-100 text-red-700', 'text' => '❌ تم الإلغاء'],
                                'cancelled' => ['class' => 'bg-red-100 text-red-700', 'text' => '❌ تم الإلغاء'],
                                'pending' => ['class' => 'bg-gray-100 text-gray-700', 'text' => '⏳ قيد الانتظار']
                            ];
                            $mapped = $status_map[$status] ?? ['class' => 'bg-gray-100 text-gray-700', 'text' => $status];
                            $status_html = '<span class="px-3 py-1 rounded-full font-bold text-xs ' . $mapped['class'] . '">' . htmlspecialchars($mapped['text']) . '</span>';
                        ?>
                        <tr class="border-b <?= $row_class ?>">
                            <td class="py-4 px-4 font-bold text-gray-400"><?= $i + 1 ?></td>
                            <td class="py-4 px-4">
                                <div class="font-bold text-gray-800 text-base mb-1"><?= htmlspecialchars($order['customer_name'] ?? '') ?></div>
                                <div class="text-sm font-semibold text-indigo-600 mb-1" dir="ltr"><?= htmlspecialchars($order['phone'] ?? '') ?></div>
                                <?php 
                                    $full_addr = trim(($order['governorate'] ?? '') . ' - ' . ($order['address'] ?? ''), ' -');
                                    if (empty($full_addr)) $full_addr = 'العنوان غير مسجل';
                                ?>
                                <div class="text-xs text-gray-500 bg-gray-50 p-1.5 rounded inline-block mt-1"><i class='bx bx-map text-gray-400'></i> <?= htmlspecialchars($full_addr) ?></div>
                                <?php
                                if (!empty($order['handed_to_rep_at']) && !empty($order['received_at'])) {
                                    try {
                                        $t1 = new DateTime($order['handed_to_rep_at']);
                                        $t2 = new DateTime($order['received_at']);
                                        $diff = $t1->diff($t2);
                                        $time_diff = "";
                                        if ($diff->d > 0) $time_diff .= $diff->d . " أيام و ";
                                        if ($diff->h > 0) $time_diff .= $diff->h . " ساعات و ";
                                        $time_diff .= $diff->i . " دقائق";
                                        echo "<div class='text-xs text-indigo-700 bg-indigo-50 p-1.5 rounded inline-block mt-1 font-bold'><i class='bx bx-time-five'></i> أستغرق: $time_diff</div>";
                                    } catch(Exception $e) {}
                                }
                                ?>
                            </td>
                            <td class="py-4 px-4 text-center font-bold text-gray-600">
                                <?php
                                    $bt = $order['bundle_type'] ?? 'single';
                                    if ($bt === 'bundle') {
                                        echo '<span class="text-orange-600">Bundle</span> (3 قطع)';
                                    } elseif ($bt === 'custom') {
                                        $q = (int)($order['quantity'] ?? 1);
                                        echo '<span class="text-purple-600">مخصص</span> ('.$q.' قطعة)';
                                    } else {
                                        echo '<span class="text-blue-600">Single</span> (قطعة واحدة)';
                                    }
                                ?>
                            </td>
                            <td class="py-4 px-4 text-center font-black text-lg text-emerald-600">
                                <?= (float)($order['total_price'] ?? 0) ?> <span class="text-xs font-normal">دل</span>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <div class="space-y-2">
                                    <select name="order_status[<?= $order['id'] ?>]" class="w-full text-xs font-bold p-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-indigo-200">
                                        <?php foreach ($status_map as $st_key => $st_val): ?>
                                            <option value="<?= $st_key ?>" <?= $status === $st_key ? 'selected' : '' ?>><?= $st_val['text'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <select name="shipping_rep_id[<?= $order['id'] ?>]" class="w-full text-xs p-2 border border-gray-300 rounded shadow-sm focus:ring-2 focus:ring-indigo-200 <?= ($order['shipping_rep_id'] > 0) ? 'bg-indigo-50 text-indigo-800 font-bold border-indigo-300' : '' ?>">
                                        <option value="">-- تعيين مندوب --</option>
                                        <?php foreach ($company_reps as $rep): ?>
                                            <option value="<?= $rep['id'] ?>" <?= ($order['shipping_rep_id'] ?? 0) == $rep['id'] ? 'selected' : '' ?>><?= htmlspecialchars($rep['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <textarea name="notes[<?= $order['id'] ?>]" rows="2" class="w-full p-2.5 border border-gray-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 text-sm shadow-sm transition" placeholder="أضف ملاحظاتك (مثال: العميل طلب تأجيل، لا يرد، الخ...)"><?= htmlspecialchars($order['shipping_notes'] ?? '') ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($existing_orders)): ?>
                        <tr><td colspan="6" class="text-center py-10 text-gray-500 font-bold bg-gray-50 rounded-b-lg"><i class='bx bx-inbox text-3xl mb-2 text-gray-300 block'></i> لا توجد طلبات مسندة لك حتى الآن</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($existing_orders)): ?>
            <div class="mt-6 flex justify-end bg-gray-50 p-4 rounded-xl">
                <button type="submit" name="save_shipping_updates" class="bg-indigo-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-bold shadow-lg flex items-center transition-transform hover:-translate-y-0.5">
                    <i class='bx bx-save mr-2 text-xl'></i> حفظ التحديثات
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

<?php else: ?>
    <!-- The normal Support view starts here -->

<div class="bg-white rounded-lg shadow-lg p-6 mb-6">
    <h2 class="text-2xl font-bold mb-6 flex items-center">
        <i class='bx bx-cart-alt mr-2 text-orange-500'></i>
        شيت أوردرات اليوم
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded text-center">
            <p class="text-2xl font-bold text-blue-600"><?= $today_stats['total_orders'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">إجمالي الأوردرات</p>
        </div>
        <div class="bg-green-50 p-4 rounded text-center">
            <p class="text-2xl font-bold text-green-600"><?= $today_stats['confirmed_orders'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">تم التأكيد</p>
        </div>
        <div class="bg-purple-50 p-4 rounded text-center">
            <p class="text-2xl font-bold text-purple-600"><?= $today_stats['delivered_orders'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">تم التوصيل</p>
        </div>
        <div class="bg-red-50 p-4 rounded text-center">
            <p class="text-2xl font-bold text-red-600"><?= $today_stats['cancelled_orders'] ?? 0 ?></p>
            <p class="text-sm text-gray-600">ملغي</p>
        </div>
    </div>
    
    <form method="POST" id="orders-form">
        <?= csrf_field() ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="orders-table">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-2 text-center">#</th>
                        <th class="py-3 px-2 text-right">الاسم</th>
                        <th class="py-3 px-2 text-right">رقم الهاتف</th>
                        <th class="py-3 px-2 text-right">العنوان</th>
                        <th class="py-3 px-2 text-center">النوع</th>
                        <th class="py-3 px-2 text-center">السعر</th>
                        <th class="py-3 px-2 text-center">الحالة</th>
                        <th class="py-3 px-2 text-right">ملاحظات الشحن</th>
                        <th class="py-3 px-2 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_count = max(count($existing_orders), 10);
                    for ($i = 0; $i < $row_count; $i++): 
                        $order = $existing_orders[$i] ?? null;
                        $status = $order['order_status'] ?? 'pending';
                        $row_class = '';
                        if ($status === 'cancelled') $row_class = 'bg-red-50';
                        elseif ($status === 'confirmed') $row_class = 'bg-blue-50';
                        elseif ($status === 'delivered') $row_class = 'bg-green-50';
                    ?>
                    <tr class="border-b <?= $row_class ?>" data-row="<?= $i ?>">
                        <td class="py-2 px-2 text-center"><?= $i + 1 ?></td>
                        <td class="py-2 px-2">
                            <input type="text" 
                                   name="orders[<?= $i ?>][customer_name]" 
                                   value="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300"
                                   placeholder="اسم العميل">
                        </td>
                        <td class="py-2 px-2">
                            <input type="text" 
                                   name="orders[<?= $i ?>][phone]" 
                                   value="<?= htmlspecialchars($order['phone'] ?? '') ?>"
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300"
                                   placeholder="رقم الهاتف">
                        </td>
                        <td class="py-2 px-2">
                            <input type="text" 
                                   name="orders[<?= $i ?>][address]" 
                                   value="<?= htmlspecialchars($order['address'] ?? '') ?>"
                                   class="w-full p-2 border rounded focus:ring-2 focus:ring-orange-300"
                                   placeholder="العنوان">
                        </td>
                        <td class="py-2 px-2 text-center">
                            <select name="orders[<?= $i ?>][bundle_type]" 
                                    class="bundle-select p-2 border rounded"
                                    onchange="updatePrice(this, <?= $i ?>)">
                                <option value="single" <?= ($order['bundle_type'] ?? 'single') === 'single' ? 'selected' : '' ?>>
                                    Single (1 قطعة - 150ج)
                                </option>
                                <option value="bundle" <?= ($order['bundle_type'] ?? '') === 'bundle' ? 'selected' : '' ?>>
                                    Bundle (3 قطع - 285ج)
                                </option>
                                <option value="custom" <?= ($order['bundle_type'] ?? '') === 'custom' ? 'selected' : '' ?>>
                                    مخصص (إدخال يدوي)
                                </option>
                            </select>
                        </td>
                        <td class="py-2 px-2 text-center relative" id="price-container-<?= $i ?>">
                            <?php if (($order['bundle_type'] ?? '') === 'custom'): ?>
                                <div class="flex gap-1 justify-center custom-inputs-<?= $i ?>">
                                    <input type="number" name="orders[<?= $i ?>][custom_quantity]" value="<?= (int)($order['quantity'] ?? 1) ?>" placeholder="العدد" class="w-16 p-1 border rounded text-xs">
                                    <input type="number" name="orders[<?= $i ?>][custom_price]" value="<?= (float)($order['total_price'] ?? 0) ?>" placeholder="السعر" class="w-16 p-1 border rounded text-xs">
                                </div>
                            <?php else: ?>
                                <span class="price-display font-bold text-orange-600" id="price-display-<?= $i ?>">
                                    <?= ($order['bundle_type'] ?? 'single') === 'bundle' ? '285' : '150' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-2 text-center">
                            <select name="orders[<?= $i ?>][status]" 
                                    class="status-select p-2 border rounded"
                                    onchange="updateRowColor(this)">
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>⏳ قيد الانتظار</option>
                                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>✅ تم التأكيد</option>
                                <option value="out_for_delivery" <?= $status === 'out_for_delivery' ? 'selected' : '' ?>>🚚 تم التوصيل</option>
                                <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>📦 تم التسليم</option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>❌ تم الإلغاء</option>
                            </select>
                        </td>
                        <td class="py-2 px-2">
                            <textarea readonly class="w-full p-2 border border-dashed border-gray-300 rounded text-xs text-indigo-700 bg-gray-50 focus:outline-none" rows="1" placeholder="لا توجد ملاحظات"><?= htmlspecialchars($order['shipping_notes'] ?? '') ?></textarea>
                        </td>
                        <td class="py-2 px-2 text-center">
                            <button type="button" onclick="clearRow(this)" class="text-red-500 hover:text-red-700">
                                <i class='bx bx-trash'></i>
                            </button>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        
        <div class="flex justify-between items-center mt-6">
            <button type="button" onclick="addRow()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                <i class='bx bx-plus mr-1'></i> إضافة صف جديد
            </button>
            
            <button type="submit" name="save_orders" class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-8 py-3 rounded-lg hover:from-orange-600 hover:to-orange-700 font-bold shadow-lg">
                <i class='bx bx-save mr-2'></i> حفظ الأوردرات
            </button>
        </div>
    </form>
</div>

<script>
function updatePrice(select, rowIndex) {
    const row = select.closest('tr');
    const container = row.querySelector('#price-container-' + (rowIndex !== undefined ? rowIndex : select.getAttribute('data-row-index')));
    
    if (select.value === 'custom') {
        container.innerHTML = `
            <div class="flex gap-1 justify-center">
                <input type="number" name="orders[${rowIndex !== undefined ? rowIndex : select.getAttribute('data-row-index')}][custom_quantity]" placeholder="العدد" class="w-16 p-1 border rounded text-xs" required>
                <input type="number" name="orders[${rowIndex !== undefined ? rowIndex : select.getAttribute('data-row-index')}][custom_price]" placeholder="السعر" class="w-16 p-1 border rounded text-xs" required>
            </div>
        `;
    } else {
        const price = select.value === 'bundle' ? '285' : '150';
        container.innerHTML = `
            <span class="price-display font-bold text-orange-600">
                ${price}
            </span>
        `;
    }
}

function updateRowColor(select) {
    const row = select.closest('tr');
    const status = select.value;
    
    // إزالة جميع الألوان
    row.classList.remove('bg-red-50', 'bg-blue-50', 'bg-green-50', 'bg-purple-50');
    
    // إضافة اللون المناسب
    if (status === 'cancelled') {
        row.classList.add('bg-red-50');
    } else if (status === 'confirmed') {
        row.classList.add('bg-blue-50');
    } else if (status === 'delivered') {
        row.classList.add('bg-green-50');
    } else if (status === 'out_for_delivery') {
        row.classList.add('bg-purple-50');
    }
}

function clearRow(btn) {
    const row = btn.closest('tr');
    row.querySelectorAll('input').forEach(input => input.value = '');
    row.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    row.classList.remove('bg-red-50', 'bg-blue-50', 'bg-green-50', 'bg-purple-50');
}

function addRow() {
    const table = document.querySelector('#orders-table tbody');
    const rowCount = table.querySelectorAll('tr').length;
    const newRow = document.createElement('tr');
    newRow.className = 'border-b';
    newRow.innerHTML = `
        <td class="py-2 px-2 text-center">${rowCount + 1}</td>
        <td class="py-2 px-2">
            <input type="text" name="orders[${rowCount}][customer_name]" 
                   class="w-full p-2 border rounded" placeholder="اسم العميل">
        </td>
        <td class="py-2 px-2">
            <input type="text" name="orders[${rowCount}][phone]" 
                   class="w-full p-2 border rounded" placeholder="رقم الهاتف">
        </td>
        <td class="py-2 px-2">
            <input type="text" name="orders[${rowCount}][address]" 
                   class="w-full p-2 border rounded" placeholder="العنوان">
        </td>
        <td class="py-2 px-2 text-center">
            <select name="orders[${rowCount}][bundle_type]" data-row-index="${rowCount}" class="p-2 border rounded" onchange="updatePrice(this)">
                <option value="single">Single (1 قطعة - 150ج)</option>
                <option value="bundle">Bundle (3 قطع - 285ج)</option>
                <option value="custom">مخصص (إدخال يدوي)</option>
            </select>
        </td>
        <td class="py-2 px-2 text-center" id="price-container-${rowCount}">
            <span class="price-display font-bold text-orange-600">150</span>
        </td>
        <td class="py-2 px-2 text-center">
            <select name="orders[${rowCount}][status]" class="p-2 border rounded" onchange="updateRowColor(this)">
                <option value="pending">⏳ قيد الانتظار</option>
                <option value="confirmed">✅ تم التأكيد</option>
                <option value="out_for_delivery">🚚 تم التوصيل</option>
                <option value="delivered">📦 تم التسليم</option>
                <option value="cancelled">❌ تم الإلغاء</option>
            </select>
        </td>
        <td class="py-2 px-2">
            <textarea readonly class="w-full p-2 border border-dashed border-gray-300 rounded text-xs text-indigo-700 bg-gray-50 focus:outline-none" rows="1" placeholder="لا توجد ملاحظات"></textarea>
        </td>
        <td class="py-2 px-2 text-center">
            <button type="button" onclick="clearRow(this)" class="text-red-500">
                <i class='bx bx-trash'></i>
            </button>
        </td>
    `;
    table.appendChild(newRow);
}
</script>
<?php endif; ?>

<script>
function filterOrdersTable() {
    let input = document.getElementById('live-search-input');
    if (!input) return;
    let filter = input.value.toLowerCase();
    let tbody = document.getElementById('orders-table-body');
    if (!tbody) return;
    let tr = tbody.getElementsByTagName('tr');

    for (let i = 0; i < tr.length; i++) {
        if (tr[i].getElementsByTagName('td').length === 1) continue;
        let idCol = tr[i].getElementsByTagName('td')[0];
        let infoCol = tr[i].getElementsByTagName('td')[1];
        
        let repName = '';
        let repSelect = tr[i].querySelector('select[name^="shipping_rep_id"]');
        if (repSelect && repSelect.selectedIndex > -1) {
            repName = repSelect.options[repSelect.selectedIndex].text;
            if (repSelect.value === '') repName = ''; // Don't match the "Assign to rep" placeholder
        }

        if (idCol || infoCol) {
            let idText = idCol.textContent || idCol.innerText;
            let infoText = infoCol.textContent || infoCol.innerText;
            if (idText.toLowerCase().indexOf(filter) > -1 || 
                infoText.toLowerCase().indexOf(filter) > -1 || 
                repName.toLowerCase().indexOf(filter) > -1) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }
}
</script>
