<?php
// ملف: admin_orders.php
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'config.php';
include_once 'helpers.php';
/** @var mysqli $conn */

define('STATUS_PENDING', 'قيد الانتظار');
define('STATUS_CONFIRMED', 'تم التأكيد');
define('STATUS_PROCESSING', 'قيد التنفيذ');
define('STATUS_SHIPPING', 'في الشحن');
define('STATUS_DELIVERED', 'تم التوصيل');
define('STATUS_RETURNED', 'مرتجع');
define('STATUS_CANCELLED', 'ملغي');
define('STATUS_COLLECTED', 'محصل');
define('STATUS_PREPARING', 'تحت التحضير');
define('STATUS_REJECTED', 'مرفوض');
define('STATUS_COMPLETED', 'مكتمل');


// دالة لتحديث المخزون (بما في ذلك المتغيرات)
function updateProductStock(int $product_id, int $quantity, $operation = 'decrease', $color = null, $size = null) {
    global $conn;

    $product_id = intval($product_id);
    $quantity = intval($quantity);

    if ($operation === 'decrease') {
        // تحديث المخزون العام
        $conn->query("UPDATE products SET stock = stock - $quantity WHERE id = $product_id");

        // تحديث مخزون المتغيرات إذا وجد
        if ($color !== null || $size !== null) {
            $stmt = $conn->prepare("UPDATE product_inventory SET quantity = quantity - ? WHERE product_id = ? AND color = ? AND size = ?");
            $stmt->bind_param("iiss", $quantity, $product_id, $color, $size);
            $stmt->execute();
        }
    } elseif ($operation === 'increase') {
        // إرجاع للمخزون العام
        $conn->query("UPDATE products SET stock = stock + $quantity WHERE id = $product_id");

        // إرجاع لمخزون المتغيرات إذا وجد
        if ($color !== null || $size !== null) {
            $stmt = $conn->prepare("UPDATE product_inventory SET quantity = quantity + ? WHERE product_id = ? AND color = ? AND size = ?");
            $stmt->bind_param("iiss", $quantity, $product_id, $color, $size);
            $stmt->execute();
        }
    }
}

// معالجة تحديث حالة الطلب
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = clean_input($_POST['status']);
    $cancellation_reason = isset($_POST['cancellation_reason']) ? clean_input($_POST['cancellation_reason']) : '';
    $return_reason = isset($_POST['return_reason']) ? clean_input($_POST['return_reason']) : '';

    // التحقق من صحة الحالة
    $allowed_statuses = [
        STATUS_PENDING, STATUS_CONFIRMED, STATUS_PROCESSING, STATUS_SHIPPING,
        STATUS_DELIVERED, STATUS_RETURNED, STATUS_CANCELLED, STATUS_COLLECTED, STATUS_PREPARING, STATUS_REJECTED
    ];

    if (!in_array($new_status, $allowed_statuses)) {
        $error = "حالة الطلب غير صالحة!";
    } else {
        // جلب الطلب الحالي لمعرفة الحالة القديمة
        $order_result = $conn->query("SELECT * FROM orders WHERE id = $order_id");
        $order = $order_result ? $order_result->fetch_assoc() : null;
        $old_status = $order['status'] ?? '';

        // جلب منتجات الطلب
        $items_result = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
        $order_items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];

        // تحديث الحالة مع الأسباب
        $reason_type = null;
        $reason_text = '';

        $skip_update = false;

        if ($new_status === STATUS_CANCELLED || $new_status === STATUS_REJECTED) {
            $reason_type = 'cancellation';
            $reason_text = $cancellation_reason;
            if (empty($reason_text)) {
                $error = "يجب إدخال سبب الإلغاء!";
                $skip_update = true;
            }
        } elseif ($new_status === STATUS_RETURNED) {
            $reason_type = 'return';
            $reason_text = $return_reason;
            if (empty($reason_text)) {
                $error = "يجب إدخال سبب الإرجاع!";
                $skip_update = true;
            }
        }

        if (!$skip_update) {
            // Use different queries based on whether we have a reason_type or not
            if ($reason_type !== null) {
                $stmt = $conn->prepare("UPDATE orders SET status = ?, cancellation_reason = ?, return_reason = ?, reason_type = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ssssi", $new_status, $cancellation_reason, $return_reason, $reason_type, $order_id);
                }
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ?, cancellation_reason = ?, return_reason = ?, reason_type = NULL WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssi", $new_status, $cancellation_reason, $return_reason, $order_id);
                }
            }

            if ($stmt && $stmt->execute()) {
            // تضمين نظام الأرصدة إذا كان الملف موجوداً
            if (file_exists("balance_system.php")) {
                include_once 'balance_system.php';
                $balanceSystem = new BalanceSystem($conn);
                // تحديث رصيد المسوق بناءً على تغيير الحالة
                $balanceSystem->updateBalanceOnOrderStatus($order_id, $new_status, $old_status);
            }

            $success = "تم تحديث حالة الطلب بنجاح!";

            // إشعار للمستخدم إذا تغيرت الحالة
            if ($old_status !== $new_status) {
                if (function_exists('send_notification')) {
                    $u_res = $conn->query("SELECT user_id, customer_name FROM orders WHERE id = $order_id");
                    if ($u_res && $u_row = $u_res->fetch_assoc()) {
                        $uid = (int)$u_row['user_id'];
                        if ($uid > 0) {
                            $title = "تحديث حالة الطلب";
                            $msg = "تم تحديث طلب العميل ({$u_row['customer_name']}) إلى الحالة: " . $new_status;
                            $link = "orders.html";
                            send_notification($conn, 'user', $uid, $title, $msg, $link);
                        }
                    }
                }

                // الحالات التي تعتبر "خارج المخزون" (محجوزة أو مباعة)
                $out_statuses = [STATUS_PENDING, STATUS_CONFIRMED, STATUS_PROCESSING, STATUS_PREPARING, STATUS_SHIPPING, STATUS_DELIVERED, STATUS_COLLECTED, STATUS_COMPLETED];

                // الحالات التي تعيد المنتج للمخزون
                $in_statuses = [STATUS_CANCELLED, STATUS_REJECTED, STATUS_RETURNED];

                // 1. إذا تحول الطلب من حالة "في المخزون" إلى حالة "محجوز/مباع" -> اخصم من المخزون
                if (!in_array($old_status, $out_statuses) && in_array($new_status, $out_statuses)) {
                    foreach ($order_items as $item) {
                        updateProductStock($item['product_id'], $item['quantity'], 'decrease', $item['color'], $item['size']);
                    }
                }

                // 2. إذا تحول الطلب من حالة "محجوز/مباع" إلى حالة "في المخزون/ملغي/مرتجع" -> ارجع للمخزون
                if (in_array($old_status, $out_statuses) && in_array($new_status, $in_statuses)) {
                    foreach ($order_items as $item) {
                        updateProductStock($item['product_id'], $item['quantity'], 'increase', $item['color'], $item['size']);
                    }
                }
            }

            $success = "تم تحديث حالة الطلب بنجاح!";

            } else {
                $error = "فشل في تحديث حالة الطلب";
            }
        }
    }
}

// معالجة حذف طلب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = intval($_POST['delete_order']);

    // جلب بيانات الطلب قبل الحذف
    $order_result = $conn->query("SELECT status FROM orders WHERE id = $order_id");
    $order = $order_result ? $order_result->fetch_assoc() : null;

    // إذا كان الطلب نشطاً (ليس ملغياً أو مرتجعاً)، إرجاع المخزون عند الحذف
    if ($order && !in_array($order['status'], [STATUS_CANCELLED, STATUS_REJECTED, STATUS_RETURNED])) {
        $items_result = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
        if ($items_result) {
            while($item = $items_result->fetch_assoc()) {
                updateProductStock((int)$item['product_id'], (int)$item['quantity'], 'increase', $item['color'] ?? null, $item['size'] ?? null);
            }
        }
    }

    $conn->query("DELETE FROM orders WHERE id = $order_id");
    $conn->query("DELETE FROM order_items WHERE order_id = $order_id");

    $success = "تم حذف الطلب بنجاح!";
}

// عرض تفاصيل الطلب
$order = null;
$order_items = [];
if (isset($_GET['view'])) {
    $order_id = intval($_GET['view']);
    $order_query = $conn->query("
        SELECT o.*,
               COALESCE(sc.city_name, o.shipping_city_name, o.shipping_city) AS shipping_city_label,
               scmp.name AS shipping_company_name,
               scmp.phone AS shipping_company_phone,
               u.fullname AS store_name,
               u.phone AS store_phone
        FROM orders o
        LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
        LEFT JOIN shipping_companies scmp ON o.shipping_company_id = scmp.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = $order_id
    ");
    $order = $order_query ? $order_query->fetch_assoc() : null;

    if ($order) {
        $items_query = $conn->query("
            SELECT oi.*, p.name as product_name, pi.image_path
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
            WHERE oi.order_id = $order_id
        ");
        $order_items = $items_query ? $items_query->fetch_all(MYSQLI_ASSOC) : [];
    }
}

// جلب جميع الطلبات من قاعدة البيانات مع معلومات المنتجات
$orders_query = $conn->query("
    SELECT o.*,
           u.fullname as marketer_name,
           sc.city_name as shipping_city_name,
           scmp.name as assigned_shipping_company,
           COUNT(oi.id) as items_count,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR '، ') as product_names
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN shipping_cities sc ON o.shipping_city_id = sc.id
    LEFT JOIN shipping_companies scmp ON o.shipping_company_id = scmp.id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">إدارة الطلبات</h1>
</div>

<!-- رسائل النجاح/الخطأ -->
<?php if(isset($success)): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-check-circle mr-2'></i>
    <?= $success ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-error-alt mr-2'></i>
    <?= $error ?>
</div>
<?php endif; ?>

<!-- إذا كان هناك طلب للعرض -->
<?php if(isset($_GET['view']) && $order): ?>
<div class="stat-card p-6 mb-6">
    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
        <h3 class="text-xl font-bold text-gray-800">تفاصيل الطلب #<?= $order['id'] ?></h3>
        <div class="flex items-center gap-2">
            <button type="button" onclick="printOrderShippingLabel(<?= (int) $order['id'] ?>)" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center transition">
                <i class='bx bx-printer mr-1'></i>
                طباعة بوليصة الشحن
            </button>
            <a href="?page=orders" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">العودة للقائمة</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <h4 class="font-bold mb-2">معلومات العميل:</h4>
            <p><strong>الاسم:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
            <p><strong>الهاتف:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
            <p><strong>المنطقة:</strong> <?= htmlspecialchars($order['region']) ?></p>
            <p><strong>العنوان:</strong> <?= htmlspecialchars($order['address']) ?></p>
        </div>
        <div>
            <h4 class="font-bold mb-2">معلومات الطلب:</h4>
            <p><strong>المجموع:</strong> <?= number_format($order['total'], 2) ?> د.ل</p>
            <p><strong>العمولة:</strong> <?= number_format($order['commission_total'], 2) ?> د.ل</p>
            <?php if(!empty($order['shipping_city_label'])): ?>
            <p><strong>مدينة الشحن:</strong> <?= htmlspecialchars($order['shipping_city_label']) ?></p>
            <?php endif; ?>
            <?php if(isset($order['shipping_cost']) && $order['shipping_cost'] > 0): ?>
            <p><strong>مصاريف الشحن:</strong> <?= number_format($order['shipping_cost'], 2) ?> د.ل</p>
            <?php endif; ?>
            <p><strong>الحالة:</strong>
                <?php
                if ($order['status'] === STATUS_DELIVERED) {
                    $status_badge = 'bg-green-500';
                } elseif ($order['status'] === STATUS_REJECTED) {
                    $status_badge = 'bg-red-500';
                } elseif ($order['status'] === STATUS_CANCELLED) {
                    $status_badge = 'bg-gray-500';
                } else {
                    $status_badge = 'bg-yellow-500';
                }
                ?>
                <span class="px-2 py-1 rounded text-white <?= $status_badge ?>">
                    <?= htmlspecialchars($order['status']) ?>
                </span>
            </p>
            <p><strong>التاريخ:</strong> <?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></p>
        </div>
    </div>

    <h4 class="font-bold mb-2">المنتجات:</h4>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="p-3 text-right">الصورة</th>
                    <th class="p-3 text-right">المنتج</th>
                    <th class="p-3 text-right">اللون</th>
                    <th class="p-3 text-right">المقاس</th>
                    <th class="p-3 text-right">الكمية</th>
                    <th class="p-3 text-right">السعر</th>
                    <th class="p-3 text-right">العمولة</th>
                    <th class="p-3 text-right">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($order_items as $item): ?>
                <tr class="border-b">
                    <td class="p-3">
                        <?php if($item['image_path']): ?>
                            <img src="<?= $item['image_path'] ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-12 h-12 object-cover rounded-lg border border-gray-200">
                        <?php else: ?>
                            <div class="w-12 h-12 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                                <i class='bx bx-image text-gray-400'></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3"><?= htmlspecialchars($item['product_name'] ?: 'منتج #' . $item['product_id']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($item['color']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($item['size']) ?></td>
                    <td class="p-3"><?= $item['quantity'] ?></td>
                    <td class="p-3"><?= number_format($item['price'], 2) ?> د.ل</td>
                    <td class="p-3"><?= number_format($item['commission'], 2) ?> د.ل</td>
                    <td class="p-3"><?= number_format(($item['price'] + $item['commission']) * $item['quantity'], 2) ?> د.ل</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function printOrderShippingLabel(orderId) {
    fetch('order_shipping_label_print.php?id=' + encodeURIComponent(orderId) + '&raw=1', {
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('load failed');
        }
        return response.text();
    })
    .then(function(html) {
        var iframe = document.createElement('iframe');
        iframe.setAttribute('style', 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;');
        document.body.appendChild(iframe);

        var printFrame = function() {
            try {
                var win = iframe.contentWindow;
                win.focus();
                win.print();
            } finally {
                setTimeout(function() {
                    if (iframe.parentNode) {
                        iframe.parentNode.removeChild(iframe);
                    }
                }, 1500);
            }
        };

        iframe.onload = function() {
            setTimeout(printFrame, 400);
        };

        var doc = iframe.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
    })
    .catch(function() {
        alert('تعذر تحميل البوليصة للطباعة. حاول مرة أخرى.');
    });
}
</script>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<?php $status_count_query = "SELECT COUNT(*) as total FROM orders WHERE status = '"; ?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                <i class='bx bx-cart text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي الطلبات</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php $q = $conn->query("SELECT COUNT(*) as total FROM orders"); echo $q ? $q->fetch_assoc()['total'] : 0; ?></h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6 border-l-4 border-l-yellow-500">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                <i class='bx bx-time text-yellow-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">قيد الانتظار</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php $q = $conn->query($status_count_query.STATUS_PENDING."'"); echo $q ? $q->fetch_assoc()['total'] : 0; ?></h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6 border-l-4 border-l-green-500">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg mr-4">
                <i class='bx bx-check-circle text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">مكتملة</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php $q = $conn->query($status_count_query.STATUS_DELIVERED."'"); echo $q ? $q->fetch_assoc()['total'] : 0; ?></h3>
            </div>
        </div>
    </div>

    <div class="stat-card p-6 border-l-4 border-l-red-500">
        <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg mr-4">
                <i class='bx bx-x-circle text-red-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">ملغية</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php $q = $conn->query($status_count_query.STATUS_CANCELLED."'"); echo $q ? $q->fetch_assoc()['total'] : 0; ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- جدول الطلبات -->
<div class="stat-card p-6">
    <h3 class="text-xl font-bold mb-4 text-gray-800">قائمة الطلبات</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                    <th class="p-4 text-right text-gray-700 font-bold">#</th>
                    <th class="p-4 text-right text-gray-700 font-bold">العميل</th>
                    <th class="p-4 text-right text-gray-700 font-bold">المنتجات</th>
                    <th class="p-4 text-right text-gray-700 font-bold">المنطقة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">المجموع</th>
                    <th class="p-4 text-right text-gray-700 font-bold">العمولة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">شركة الشحن</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الشحن</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الحالة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">سبب الإلغاء/الإرجاع</th>
                    <th class="p-4 text-right text-gray-700 font-bold">التاريخ</th>
                    <th class="p-4 text-right text-gray-700 font-bold">التعليقات</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if($orders_query && $orders_query->num_rows > 0): ?>
                    <?php while($order = $orders_query->fetch_assoc()): ?>
                        <?php
                        $status_color = [
                            STATUS_PENDING => 'bg-yellow-500',
                            STATUS_CONFIRMED => 'bg-blue-500',
                            STATUS_PROCESSING => 'bg-purple-500',
                            STATUS_SHIPPING => 'bg-indigo-500',
                            STATUS_DELIVERED => 'bg-green-500',
                            STATUS_RETURNED => 'bg-orange-500',
                            STATUS_CANCELLED => 'bg-gray-500',
                            STATUS_COLLECTED => 'bg-teal-500',
                            STATUS_PREPARING => 'bg-cyan-500',
                            STATUS_REJECTED => 'bg-red-500'
                        ][$order['status']] ?? 'bg-gray-500';
                        ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="p-4 text-gray-600 font-medium">#<?= $order['id'] ?></td>
                            <td class="p-4">
                                <div class="text-gray-800 font-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                <div class="text-gray-500 text-sm"><?= htmlspecialchars($order['customer_phone']) ?></div>
                            </td>
                            <td class="p-4">
                                <div class="text-gray-800 font-medium"><?= htmlspecialchars($order['product_names'] ?: 'لا توجد منتجات') ?></div>
                                <div class="text-gray-500 text-sm"><?= $order['items_count'] ?> منتج</div>
                            </td>
                            <td class="p-4">
                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">
                                    <?= htmlspecialchars($order['region']) ?>
                                </span>
                            </td>
                            <td class="p-4 font-bold text-green-600"><?= number_format($order['total'], 2) ?> د.ل</td>
                            <td class="p-4 font-bold text-purple-600"><?= number_format($order['commission_total'], 2) ?> د.ل</td>
                            <td class="p-4">
                                <?php if(!empty($order['assigned_shipping_company'])): ?>
                                    <div class="text-indigo-600 font-medium text-sm"><?= htmlspecialchars($order['assigned_shipping_company']) ?></div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if(isset($order['shipping_city_name']) && $order['shipping_city_name']): ?>
                                    <div class="text-gray-800 font-medium text-sm"><?= htmlspecialchars($order['shipping_city_name']) ?></div>
                                    <?php if(isset($order['shipping_cost']) && $order['shipping_cost'] > 0): ?>
                                        <div class="text-blue-600 font-bold text-sm"><?= number_format($order['shipping_cost'], 2) ?> د.ل</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <form method="POST" class="flex flex-col space-y-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <label for="status_select_<?= $order['id'] ?>" class="sr-only">حالة الطلب</label>
                                    <select name="status" id="status_select_<?= $order['id'] ?>" onchange="handleStatusChange(this)"
                                            class="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-[#4b6b2f]">
                                        <option value="<?= STATUS_PENDING ?>" <?= $order['status'] == STATUS_PENDING ? 'selected' : '' ?>><?= STATUS_PENDING ?></option>
                                        <option value="<?= STATUS_CONFIRMED ?>" <?= $order['status'] == STATUS_CONFIRMED ? 'selected' : '' ?>><?= STATUS_CONFIRMED ?></option>
                                        <option value="<?= STATUS_PROCESSING ?>" <?= $order['status'] == STATUS_PROCESSING ? 'selected' : '' ?>><?= STATUS_PROCESSING ?></option>
                                        <option value="<?= STATUS_SHIPPING ?>" <?= $order['status'] == STATUS_SHIPPING ? 'selected' : '' ?>><?= STATUS_SHIPPING ?></option>
                                        <option value="<?= STATUS_DELIVERED ?>" <?= $order['status'] == STATUS_DELIVERED ? 'selected' : '' ?>><?= STATUS_DELIVERED ?></option>
                                        <option value="<?= STATUS_RETURNED ?>" <?= $order['status'] == STATUS_RETURNED ? 'selected' : '' ?>><?= STATUS_RETURNED ?></option>
                                        <option value="<?= STATUS_CANCELLED ?>" <?= $order['status'] == STATUS_CANCELLED ? 'selected' : '' ?>><?= STATUS_CANCELLED ?></option>
                                        <option value="<?= STATUS_COLLECTED ?>" <?= $order['status'] == STATUS_COLLECTED ? 'selected' : '' ?>><?= STATUS_COLLECTED ?></option>
                                        <option value="<?= STATUS_PREPARING ?>" <?= $order['status'] == STATUS_PREPARING ? 'selected' : '' ?>><?= STATUS_PREPARING ?></option>
                                        <option value="<?= STATUS_REJECTED ?>" <?= $order['status'] == STATUS_REJECTED ? 'selected' : '' ?>><?= STATUS_REJECTED ?></option>
                                    </select>
                                    <div id="reason_<?= $order['id'] ?>" style="display: none;" class="space-y-2">
                                        <label for="cancellation_reason_<?= $order['id'] ?>" class="sr-only">سبب الإلغاء</label>
                                        <input type="text" name="cancellation_reason" id="cancellation_reason_<?= $order['id'] ?>" placeholder="سبب الإلغاء"
                                               class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-[#4b6b2f]">
                                        <label for="return_reason_<?= $order['id'] ?>" class="sr-only">سبب الإرجاع</label>
                                        <input type="text" name="return_reason" id="return_reason_<?= $order['id'] ?>" placeholder="سبب الإرجاع"
                                               class="w-full text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:border-[#4b6b2f]">
                                    </div>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td class="p-4">
                                <?php if ($order['status'] === STATUS_CANCELLED || $order['status'] === STATUS_REJECTED): ?>
                                    <div class="text-red-600 text-sm">
                                        <i class='bx bx-x-circle mr-1'></i>
                                        <?= htmlspecialchars($order['cancellation_reason'] ?: 'غير محدد') ?>
                                    </div>
                                <?php elseif ($order['status'] === STATUS_RETURNED): ?>
                                    <div class="text-orange-600 text-sm">
                                        <i class='bx bx-undo mr-1'></i>
                                        <?= htmlspecialchars($order['return_reason'] ?: 'غير محدد') ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-gray-500 text-sm"><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                            <td class="p-4">
                                <button onclick="openCommentsModal(<?= $order['id'] ?>)"
                                        class="bg-purple-500 text-white px-3 py-2 rounded-lg hover:bg-purple-600 text-sm flex items-center transition">
                                    <i class='bx bx-comment-detail mr-1'></i>
                                    تعليقات
                                </button>
                            </td>
                            <td class="p-4">
                                <div class="flex space-x-2 space-x-reverse">
                                    <a href="?page=orders&view=<?= $order['id'] ?>"
                                       class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm flex items-center transition">
                                        <i class='bx bx-show mr-1'></i>
                                        عرض
                                    </a>
                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب؟')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_order" value="<?= $order['id'] ?>">
                                        <button type="submit"
                                       class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 text-sm flex items-center transition">
                                        <i class='bx bx-trash mr-1'></i>
                                        حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="p-4 text-center text-gray-500">
                            <i class='bx bx-package text-4xl mb-2 block'></i>
                            لا توجد طلبات في قاعدة البيانات
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal للتعليقات -->
<div id="commentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
        <div class="bg-purple-600 text-white p-4 rounded-t-xl flex justify-between items-center">
            <h3 class="text-xl font-bold"><i class='bx bx-comment-detail mr-2'></i>تعليقات الطلب #<span id="modalOrderId"></span></h3>
            <button onclick="closeCommentsModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
        </div>

        <!-- قائمة التعليقات -->
        <div id="commentsList" class="flex-1 overflow-y-auto p-4 space-y-3 max-h-80">
            <div class="text-gray-500 text-center py-8">
                <i class='bx bx-loader-alt bx-spin text-3xl'></i>
                <p class="mt-2">جاري تحميل التعليقات...</p>
            </div>
        </div>

        <!-- نموذج إضافة تعليق -->
        <div class="border-t p-4">
            <form id="addCommentForm" class="flex gap-3">
                <input type="hidden" id="commentOrderId" value="">
                <label for="commentText" class="sr-only">نص التعليق</label>
                <textarea id="commentText"
                          placeholder="اكتب تعليقك هنا..."
                          class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:border-purple-500 resize-none"
                          rows="2"></textarea>
                <button type="submit"
                        class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition flex items-center">
                    <i class='bx bx-send mr-1'></i>
                    إرسال
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Handle status change to show/hide reason fields
function handleStatusChange(select) {
    const orderId = select.form.order_id.value;
    const reasonDiv = document.getElementById('reason_' + orderId);
    const cancellationInput = select.form.cancellation_reason;
    const returnInput = select.form.return_reason;

    const STATUS_CANCELLED = '<?= STATUS_CANCELLED ?>';
    const STATUS_REJECTED = '<?= STATUS_REJECTED ?>';
    const STATUS_RETURNED = '<?= STATUS_RETURNED ?>';

    if (select.value === STATUS_CANCELLED || select.value === STATUS_REJECTED) {
        reasonDiv.style.display = 'block';
        cancellationInput.style.display = 'block';
        returnInput.style.display = 'none';
        cancellationInput.required = true;
        returnInput.required = false;
    } else if (select.value === STATUS_RETURNED) {
        reasonDiv.style.display = 'block';
        cancellationInput.style.display = 'none';
        returnInput.style.display = 'block';
        cancellationInput.required = false;
        returnInput.required = true;
    } else {
        reasonDiv.style.display = 'none';
        cancellationInput.required = false;
        returnInput.required = false;
    }
}

// Submit form when status changes
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('select[name="status"]');
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const form = this.form;
            const orderId = form.order_id.value;
            const reasonDiv = document.getElementById('reason_' + orderId);
            const STATUS_CANCELLED = '<?= STATUS_CANCELLED ?>';
            const STATUS_REJECTED = '<?= STATUS_REJECTED ?>';
            const STATUS_RETURNED = '<?= STATUS_RETURNED ?>';

            if (this.value === STATUS_CANCELLED || this.value === STATUS_REJECTED || this.value === STATUS_RETURNED) {
                // Show reason fields first, don't submit immediately
                handleStatusChange(this);
            } else {
                // Submit immediately for other status changes
                form.submit();
            }
        });
    });

    // Add submit button for reason forms
    const reasonDivs = document.querySelectorAll('[id^="reason_"]');
    reasonDivs.forEach(div => {
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 mt-2';
        submitBtn.innerHTML = '<i class="bx bx-check mr-1"></i>تحديث';
        div.appendChild(submitBtn);
    });
});

// تنظيف localStorage من البيانات القديمة
document.addEventListener('DOMContentLoaded', function() {
    if (typeof(Storage) !== 'undefined') {
        // الاحتفاظ فقط بالطلبات الموجودة في قاعدة البيانات
        fetch('get_orders.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.orders) {
                    localStorage.setItem('orders', JSON.stringify(data.orders));
                } else {
                    localStorage.removeItem('orders');
                }
            })
            .catch(error => {
                console.error('Error cleaning localStorage:', error);
                localStorage.removeItem('orders');
            });
    }
});

// =============== نظام التعليقات ===============
let currentOrderId = null;

// فتح modal التعليقات
function openCommentsModal(orderId) {
    currentOrderId = orderId;
    document.getElementById('modalOrderId').textContent = orderId;
    document.getElementById('commentOrderId').value = orderId;
    document.getElementById('commentsModal').classList.remove('hidden');
    loadComments(orderId);
}

// إغلاق modal التعليقات
function closeCommentsModal() {
    document.getElementById('commentsModal').classList.add('hidden');
    currentOrderId = null;
    document.getElementById('commentText').value = '';
}

// تحميل التعليقات
function loadComments(orderId) {
    const commentsList = document.getElementById('commentsList');
    commentsList.innerHTML = `
        <div class="text-gray-500 text-center py-8">
            <i class='bx bx-loader-alt bx-spin text-3xl'></i>
            <p class="mt-2">جاري تحميل التعليقات...</p>
        </div>
    `;

    fetch('get_order_comments.php?order_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.comments.length > 0) {
                commentsList.innerHTML = data.comments.map(comment => `
                    <div class="bg-gray-50 rounded-lg p-4 border-r-4 border-purple-500">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-bold text-purple-700">
                                <i class='bx bx-user-circle mr-1'></i>
                                ${comment.admin_name}
                            </span>
                            <span class="text-gray-400 text-sm">${comment.formatted_date}</span>
                        </div>
                        <p class="text-gray-700">${comment.comment}</p>
                    </div>
                `).join('');
            } else {
                commentsList.innerHTML = `
                    <div class="text-gray-400 text-center py-8">
                        <i class='bx bx-comment-x text-5xl'></i>
                        <p class="mt-2">لا توجد تعليقات بعد</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading comments:', error);
            commentsList.innerHTML = `
                <div class="text-red-500 text-center py-8">
                    <i class='bx bx-error-circle text-5xl'></i>
                    <p class="mt-2">فشل في تحميل التعليقات</p>
                </div>
            `;
        });
}

// إرسال تعليق جديد
document.getElementById('addCommentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const orderId = document.getElementById('commentOrderId').value;
    const comment = document.getElementById('commentText').value.trim();

    if (!comment) {
        showWarning('يرجى كتابة تعليق');
        return;
    }

    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('comment', comment);
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        formData.append('csrf_token', csrfMeta.content);
    }

    fetch('save_order_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('commentText').value = '';
            loadComments(orderId);
        } else {
            showError('فشل في إضافة التعليق: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error saving comment:', error);
        showError('حدث خطأ أثناء حفظ التعليق');
    });
});

// إغلاق Modal عند النقر خارجها
document.getElementById('commentsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCommentsModal();
    }
});
</script>
