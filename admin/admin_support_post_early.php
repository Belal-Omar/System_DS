<?php
/**
 * معالجة POST لحفظ أوردرات الدعم — يُستدعى من admin_panel.php قبل أي مخرجات HTML
 * حتى يعمل header(Location) بشكل صحيح (لا صفحة بيضاء).
 */
if (!isset($conn) || !is_object($conn) || !method_exists($conn, 'prepare')) {
    return;
}

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

/**
 * @return array{order_date:string,recipient_name:string,phone:string,order_status:string,notes:string,governorate:string,address:string,pieces:int,customer_status:string,agent_code:string,campaign:string,version:string,article:string,sheet_row_index:int,order_id:int,bundle_type:string,quantity:int,unit_price:float,total_price:float,product_code:string}
 */
function support_normalize_order_payload(array $order, $row_key, mysqli $conn, int $sheet_id = 0, string $default_product_code = 'Etala001'): array
{
    $order_date = (string) ($order['order_date'] ?? date('Y-m-d'));
    $recipient_name = trim((string) ($order['recipient_name'] ?? ''));
    $phone = trim((string) ($order['phone'] ?? ''));
    $order_status = trim((string) ($order['order_status'] ?? ''));
    if ($order_status === '') {
        // حفظ الشيت كامل حتى بدون اختيار حالة — انتظار / لم يتم التواصل
        $order_status = 'pending';
    }
    $pieces = (int) ($order['pieces'] ?? 1);
    if ($pieces < 1) {
        $pieces = 1;
    }
    $product_code = trim((string) ($order['product_code'] ?? $default_product_code));
    if ($product_code === '') {
        $product_code = $default_product_code;
    }
    $month = function_exists('get_support_sheet_month')
        ? get_support_sheet_month($conn, $sheet_id)
        : date('Y-m');

    $bundle_type_raw = trim((string) ($order['bundle_type'] ?? ''));
    if ($bundle_type_raw === 'custom') {
        $quantity = (int) ($order['custom_quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;
        $total_price = (float) ($order['custom_price'] ?? 0);
        $unit_price = $total_price / $quantity;
        
        $pricing_ref = function_exists('resolve_support_order_pricing')
            ? resolve_support_order_pricing($conn, 1, $product_code, $month)
            : ['snap_product_cost' => null, 'snap_bundle_cost' => null, 'snap_dom_shipping' => null];
        
        $snap_product_cost = isset($pricing_ref['snap_product_cost']) ? $pricing_ref['snap_product_cost'] * $quantity : null;
        
        $pricing = [
            'bundle_type' => 'custom',
            'pieces' => $quantity,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_price' => $total_price,
            'snap_product_cost' => $snap_product_cost,
            'snap_bundle_cost' => $snap_product_cost,
            'snap_dom_shipping' => $pricing_ref['snap_dom_shipping'] ?? null,
        ];
    } else {
        $pricing = function_exists('resolve_support_order_pricing')
            ? resolve_support_order_pricing($conn, $pieces, $product_code, $month)
            : [
                'bundle_type' => ($pieces >= 3) ? 'bundle' : 'single',
                'pieces' => ($pieces >= 3) ? 3 : 1,
                'quantity' => ($pieces >= 3) ? 3 : 1,
                'unit_price' => ($pieces >= 3) ? 95.0 : 150.0,
                'total_price' => ($pieces >= 3) ? 285.0 : 150.0,
                'snap_product_cost' => null,
                'snap_bundle_cost' => null,
                'snap_dom_shipping' => null,
            ];
    }

    return [
        'order_date' => $order_date,
        'recipient_name' => $recipient_name,
        'phone' => $phone,
        'order_status' => $order_status,
        'notes' => (string) ($order['notes'] ?? ''),
        'governorate' => (string) ($order['governorate'] ?? ''),
        'address' => (string) ($order['address'] ?? ''),
        'pieces' => (int) $pricing['pieces'],
        'customer_status' => (string) ($order['customer_status'] ?? 'new'),
        'agent_code' => trim((string) ($order['agent_code'] ?? '')),
        'campaign' => (string) ($order['campaign'] ?? ''),
        'version' => (string) ($order['version'] ?? ''),
        'article' => (string) ($order['article'] ?? ''),
        'sheet_row_index' => (int) ($order['sheet_row_index'] ?? (is_numeric($row_key) ? (int) $row_key : -1)),
        'order_id' => (int) ($order['id'] ?? 0),
        'bundle_type' => (string) $pricing['bundle_type'],
        'quantity' => (int) $pricing['quantity'],
        'unit_price' => (float) $pricing['unit_price'],
        'total_price' => (float) $pricing['total_price'],
        'product_code' => $product_code,
        'snap_product_cost' => $pricing['snap_product_cost'] ?? null,
        'snap_bundle_cost' => $pricing['snap_bundle_cost'] ?? null,
        'snap_dom_shipping' => $pricing['snap_dom_shipping'] ?? null,
    ];
}

function support_find_existing_order_id(mysqli $conn, int $support_id, int $sheet_id, array $payload): int
{
    if ($payload['order_id'] > 0) {
        $oid = (int) $payload['order_id'];
        $q = $conn->query("SELECT id FROM support_orders WHERE id = $oid LIMIT 1");
        if ($q && ($row = $q->fetch_assoc())) {
            return (int) $row['id'];
        }
    }
    
    $owner_cond = ($sheet_id > 0) ? "sheet_id = $sheet_id" : "support_id = $support_id AND sheet_id = 0";

    if ($payload['sheet_row_index'] >= 0) {
        $idx = $payload['sheet_row_index'];
        $q = $conn->query("SELECT id FROM support_orders WHERE $owner_cond AND sheet_row_index = $idx LIMIT 1");
        if ($q && ($row = $q->fetch_assoc())) {
            return (int) $row['id'];
        }
    }
    if ($payload['sheet_row_index'] < 0) {
        if ($payload['phone'] !== '') {
            $phone = $conn->real_escape_string($payload['phone']);
            $q = $conn->query("SELECT id FROM support_orders WHERE $owner_cond AND phone = '$phone' ORDER BY id ASC LIMIT 1");
            if ($q && ($row = $q->fetch_assoc())) {
                return (int) $row['id'];
            }
        }
        if ($payload['recipient_name'] !== '' && $payload['phone'] !== '') {
            $phone = $conn->real_escape_string($payload['phone']);
            $name = $conn->real_escape_string($payload['recipient_name']);
            $q = $conn->query("SELECT id FROM support_orders WHERE $owner_cond AND phone = '$phone' AND recipient_name = '$name' LIMIT 1");
            if ($q && ($row = $q->fetch_assoc())) {
                return (int) $row['id'];
            }
        }
    }
    return 0;
}

/**
 * @return array{ok:bool,is_new:bool,error:string}
 */
function support_save_order_row(mysqli $conn, int $support_id, string $support_name, int $sheet_id, array $order, $row_key, string $default_product_code = 'Etala001'): array
{
    $order_id = (int) ($order['id'] ?? 0);
    $status_raw = trim((string) ($order['order_status'] ?? ''));
    $phone_raw = trim((string) ($order['phone'] ?? ''));
    $name_raw = trim((string) ($order['recipient_name'] ?? ''));

    if ($order_id === 0 && $status_raw === '') {
        return ['ok' => false, 'is_new' => false, 'error' => ''];
    }
    // يُسمح بالحفظ بالاسم/الهاتف حتى لو الحالة فاضية (تتحول لـ pending)

    $p = support_normalize_order_payload($order, $row_key, $conn, $sheet_id, $default_product_code);
    if ($p['phone'] === '' && $p['recipient_name'] === '') {
        return ['ok' => false, 'is_new' => false, 'error' => ''];
    }

    // Agent Code تلقائي من اسم الدعم + تسلسل إنشاء الحساب
    if (function_exists('get_support_agent_code')) {
        $auto_agent = get_support_agent_code($conn, $support_id);
        if ($auto_agent !== '') {
            $p['agent_code'] = $auto_agent;
        }
    }

    // جلب الحالة السابقة إذا كان الأوردر موجود
    $prev_status = '';
    $existing_probe = support_find_existing_order_id($conn, $support_id, $sheet_id, $p);
    
    if ($order_id > 0 && $existing_probe === 0) {
        // The UI submitted an order_id, but it doesn't exist in the DB (it was likely deleted).
        // Prevent resurrecting it as a new order.
        return ['ok' => true, 'is_new' => false, 'error' => ''];
    }

    if ($existing_probe > 0) {
        $prev_q = $conn->query("SELECT order_status FROM support_orders WHERE id = $existing_probe LIMIT 1");
        if ($prev_q && ($prev_row = $prev_q->fetch_assoc())) {
            $prev_status = (string) ($prev_row['order_status'] ?? '');
        }
    }

    // قيود الدعم الفني على حالة «تم الاستلام»
    $session_role = $_SESSION['admin_role'] ?? '';
    if ($session_role === 'support') {
        // لو المدير عيّن تم الاستلام: الدعم لا يقدر يغيّرها بأي شكل
        if (in_array($prev_status, ['received', 'delivered'], true)) {
            $p['order_status'] = 'received';
        } elseif (in_array($p['order_status'], ['received', 'delivered'], true)) {
            // الدعم لا يقدر يعيّن تم الاستلام — أقصى حاجة تأكيد الطلب
            $p['order_status'] = ($prev_status !== '') ? $prev_status : 'order_confirmed';
        }
    }

    // تحديث تاريخ الأوردر لتاريخ اليوم لو تحولت الحالة إلى نهائية (تأكيد/إلغاء)
    $final_statuses = ['order_confirmed', 'received', 'ready_to_ship', 'order_cancelled'];
    if ($prev_status !== '' && !in_array($prev_status, $final_statuses, true) && in_array($p['order_status'], $final_statuses, true)) {
        $p['order_date'] = date('Y-m-d');
    }

    $existing_id = support_find_existing_order_id($conn, $support_id, $sheet_id, $p);
    if ($existing_id > 0) {
        $update_cond = ($sheet_id > 0) ? "id = ? AND sheet_id = ?" : "id = ? AND support_id = ? AND sheet_id = 0";
        $stmt = $conn->prepare("UPDATE support_orders SET
            support_id = ?,
            order_date = ?, recipient_name = ?, customer_name = ?, phone = ?,
            order_status = ?, notes = ?, governorate = ?, address = ?,
            pieces = ?, quantity = ?, unit_price = ?, total_price = ?,
            customer_status = ?, agent_code = ?, campaign = ?, version = ?, article = ?,
            bundle_type = ?, sheet_row_index = ?, product_code = ?
            WHERE $update_cond");
        if (!$stmt) {
            return ['ok' => false, 'is_new' => false, 'error' => $conn->error ?: 'prepare failed'];
        }

        $order_date = $p['order_date'];
        $recipient_name = $p['recipient_name'];
        $customer_name = $p['recipient_name'];
        $phone = $p['phone'];
        $order_status = $p['order_status'];
        $notes = $p['notes'];
        $governorate = $p['governorate'];
        $address = $p['address'];
        $pieces = $p['pieces'];
        $quantity = $p['quantity'];
        $unit_price = $p['unit_price'];
        $total_price = $p['total_price'];
        $customer_status = $p['customer_status'];
        $agent_code = $p['agent_code'];
        $campaign = $p['campaign'];
        $version = $p['version'];
        $article = $p['article'];
        $bundle_type = $p['bundle_type'];
        $sheet_row_index = $p['sheet_row_index'];
        $product_code_val = $p['product_code'];

        if ($sheet_id > 0) {
            $stmt->bind_param(
                'i' . 'ssssssss' . 'ii' . 'dd' . 'ssssss' . 'is' . 'ii',
                $support_id,
                $order_date,
                $recipient_name,
                $customer_name,
                $phone,
                $order_status,
                $notes,
                $governorate,
                $address,
                $pieces,
                $quantity,
                $unit_price,
                $total_price,
                $customer_status,
                $agent_code,
                $campaign,
                $version,
                $article,
                $bundle_type,
                $sheet_row_index,
                $product_code_val,
                $existing_id,
                $sheet_id
            );
        } else {
            $stmt->bind_param(
                'i' . 'ssssssss' . 'ii' . 'dd' . 'ssssss' . 'is' . 'ii',
                $support_id,
                $order_date,
                $recipient_name,
                $customer_name,
                $phone,
                $order_status,
                $notes,
                $governorate,
                $address,
                $pieces,
                $quantity,
                $unit_price,
                $total_price,
                $customer_status,
                $agent_code,
                $campaign,
                $version,
                $article,
                $bundle_type,
                $sheet_row_index,
                $product_code_val,
                $existing_id,
                $support_id
            );
        }
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        return ['ok' => $ok, 'is_new' => false, 'error' => $ok ? '' : ($err ?: 'update failed')];
    }

    $product_code_val = $p['product_code'];

    $stmt = $conn->prepare("INSERT INTO support_orders
        (support_id, sheet_id, sheet_row_index, support_name, product_code,
        order_date, recipient_name, customer_name, phone,
        order_status, notes, governorate, address,
        pieces, quantity, unit_price, total_price,
        customer_status, agent_code, campaign, version, article,
        bundle_type, snap_product_cost, snap_bundle_cost, snap_dom_shipping, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        return ['ok' => false, 'is_new' => true, 'error' => $conn->error ?: 'prepare failed'];
    }

    $sheet_row_index = $p['sheet_row_index'];
    $order_date = $p['order_date'];
    $recipient_name = $p['recipient_name'];
    $customer_name = $p['recipient_name'];
    $phone = $p['phone'];
    $order_status = $p['order_status'];
    $notes = $p['notes'];
    $governorate = $p['governorate'];
    $address = $p['address'];
    $pieces = $p['pieces'];
    $quantity = $p['quantity'];
    $unit_price = $p['unit_price'];
    $total_price = $p['total_price'];
    $customer_status = $p['customer_status'];
    $agent_code = $p['agent_code'];
    $campaign = $p['campaign'];
    $version = $p['version'];
    $article = $p['article'];
    $bundle_type = $p['bundle_type'];
    $snap_product_cost = $p['snap_product_cost'];
    $snap_bundle_cost = $p['snap_bundle_cost'];
    $snap_dom_shipping = $p['snap_dom_shipping'];

    $stmt->bind_param(
        'iii' . str_repeat('s', 10) . 'ii' . 'dd' . str_repeat('s', 6) . 'ddd',
        $support_id,
        $sheet_id,
        $sheet_row_index,
        $support_name,
        $product_code_val,
        $order_date,
        $recipient_name,
        $customer_name,
        $phone,
        $order_status,
        $notes,
        $governorate,
        $address,
        $pieces,
        $quantity,
        $unit_price,
        $total_price,
        $customer_status,
        $agent_code,
        $campaign,
        $version,
        $article,
        $bundle_type,
        $snap_product_cost,
        $snap_bundle_cost,
        $snap_dom_shipping
    );
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    return ['ok' => $ok, 'is_new' => true, 'error' => $ok ? '' : ($err ?: 'insert failed')];
}

function support_finish_orders_save(mysqli $conn, int $support_id, int $view_sheet_id, int $saved_count, string $support_name, int $updated_count = 0, int $new_count = 0): void
{
    if ($saved_count > 0 && function_exists('sync_support_stats')) {
        sync_support_stats($conn, $support_id);
    }
    if ($saved_count > 0) {
        if ($updated_count > 0 && $new_count > 0) {
            $msg = "✅ تم تحديث {$updated_count} أوردر وإضافة {$new_count} جديد — التغييرات تظهر في المتابعة والحسابات والتسويق";
        } elseif ($updated_count > 0) {
            $msg = "✅ تم تحديث {$updated_count} أوردر — الحالة والملاحظات محفوظة في كل النظام";
        } else {
            $msg = "✅ تم حفظ {$saved_count} أوردر بنجاح! وتم إرسال الشيت للمدير الرئيسي في تبويب متابعة";
        }
        $_SESSION['success_message'] = $msg;
        $_SESSION['supervisor_notification'] = [
            'sender_name' => $support_name,
            'sheet_id' => $view_sheet_id,
            'saved_count' => $saved_count,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
    $redir = 'admin_panel.php?page=support&saved=' . $saved_count;
    if ($view_sheet_id > 0) {
        $redir .= '&view_sheet=' . $view_sheet_id;
    }
    header('Location: ' . $redir);
    exit;
}

$support_id = (int) ($_SESSION['admin_id'] ?? 0);
$support_name = trim((string) ($_SESSION['fullname'] ?? ''));
if ($support_name === '') {
    $support_name = trim((string) ($_SESSION['admin_username'] ?? 'unknown'));
}

// ——— حفظ دفعة أوردرات (جدول الشيت) ———
if (isset($_POST['save_orders_batch_v2']) || isset($_POST['save_orders_edit'])) {
    $orders = $_POST['orders'] ?? [];
    $sheet_id = (int) ($_POST['sheet_id'] ?? 0);
    $view_sheet_id = (int) ($_POST['view_sheet_id'] ?? 0);

    // If saving a specific sheet, use the sheet owner's ID and Name
    if ($sheet_id > 0) {
        $sq = $conn->query("SELECT s.support_id, a.fullname, a.username FROM support_sheets s LEFT JOIN admins a ON s.support_id = a.id WHERE s.id = $sheet_id LIMIT 1");
        if ($sq && $srow = $sq->fetch_assoc()) {
            $support_id = (int) $srow['support_id'];
            $support_name = trim((string) $srow['fullname']);
            if ($support_name === '') $support_name = trim((string) $srow['username']);
        }
    }

    $redirectBase = 'admin_panel.php?page=support' . ($view_sheet_id > 0 ? '&view_sheet=' . $view_sheet_id : '');

    try {
        $check_table = $conn->query("SHOW TABLES LIKE 'support_orders'");
        if (!$check_table || $check_table->num_rows === 0) {
            $_SESSION['support_flash_error'] = 'جدول أوردرات الدعم غير موجود. شغّل setup_orders_tables.php ثم setup_orders_tables_v2.php إن لزم.';
            header('Location: ' . $redirectBase);
            exit;
        }

        $schemaErr = support_orders_sync_schema($conn);
        if ($schemaErr !== null) {
            throw new Exception('هيكل الجدول يحتاج تحديث: ' . $schemaErr);
        }

        $saved_count = 0;
        $updated_count = 0;
        $new_count = 0;
        $last_sql_error = '';
        $default_product_code = trim((string) ($_POST['orders_product_code'] ?? 'Etala001'));
        if ($default_product_code === '') {
            $default_product_code = 'Etala001';
        }

        foreach ($orders as $row_key => $order) {
            if (!is_array($order)) {
                continue;
            }
            $result = support_save_order_row($conn, $support_id, $support_name, $sheet_id, $order, $row_key, $default_product_code);
            if ($result['ok']) {
                $saved_count++;
                if (!empty($result['is_new'])) {
                    $new_count++;
                } else {
                    $updated_count++;
                }
            } elseif ($result['error'] !== '') {
                $last_sql_error = $result['error'];
            }
        }
    } catch (Throwable $e) {
        $_SESSION['support_flash_error'] = 'خطأ أثناء الحفظ: ' . $e->getMessage();
        header('Location: ' . $redirectBase . '&support_err=1');
        exit;
    }

    if ($saved_count > 0) {
        support_finish_orders_save($conn, $support_id, $view_sheet_id, $saved_count, $support_name, $updated_count, $new_count);
    }

    $_SESSION['support_flash_error'] = $last_sql_error !== ''
        ? ('تعذر الحفظ: ' . $last_sql_error)
        : 'لم يتم حفظ أي أوردر. أدخل اسم مستلم أو رقم هاتف في صف واحد على الأقل، وتأكد من اختيار حالة الطلب.';
    header('Location: ' . $redirectBase . '&support_err=1');
    exit;
}

// ——— حفظ أوردر واحد (نموذج قديم) ———
if (isset($_POST['add_order'])) {
    $redirectBaseAdd = 'admin_panel.php?page=support' . ((int) ($_POST['view_sheet_id'] ?? 0) > 0 ? '&view_sheet=' . (int) ($_POST['view_sheet_id'] ?? 0) : '');
    try {
        $customer_name = (string) ($_POST['customer_name'] ?? '');
        $phone = (string) ($_POST['phone'] ?? '');
        $address = (string) ($_POST['address'] ?? '');
        $bundle_type = (string) ($_POST['bundle_type'] ?? 'single');
        $order_status = (string) ($_POST['order_status'] ?? 'pending');
        $sheet_id = (int) ($_POST['sheet_id'] ?? 0);
        $view_sheet_id = (int) ($_POST['view_sheet_id'] ?? 0);
        $month = function_exists('get_support_sheet_month') ? get_support_sheet_month($conn, $sheet_id) : date('Y-m');

        if ($sheet_id > 0) {
            $sq = $conn->query("SELECT s.support_id, a.fullname, a.username FROM support_sheets s LEFT JOIN admins a ON s.support_id = a.id WHERE s.id = $sheet_id LIMIT 1");
            if ($sq && $srow = $sq->fetch_assoc()) {
                $support_id = (int) $srow['support_id'];
                $support_name = trim((string) $srow['fullname']);
                if ($support_name === '') $support_name = trim((string) $srow['username']);
            }
        }
        if ($bundle_type === 'custom') {
            $quantity = (int) ($_POST['custom_quantity'] ?? 1);
            if ($quantity < 1) $quantity = 1;
            $total_price = (float) ($_POST['custom_price'] ?? 0);
            $unit_price = $total_price / $quantity;
            
            $pricing_ref = function_exists('resolve_support_order_pricing')
                ? resolve_support_order_pricing($conn, 1, 'Etala001', $month)
                : ['snap_product_cost' => null, 'snap_bundle_cost' => null, 'snap_dom_shipping' => null];
            
            $snap_product_cost = isset($pricing_ref['snap_product_cost']) ? $pricing_ref['snap_product_cost'] * $quantity : null;
            $snap_bundle_cost = $snap_product_cost;
            $snap_dom_shipping = $pricing_ref['snap_dom_shipping'] ?? null;
        } else {
            $pieces = ($bundle_type === 'bundle') ? 3 : 1;
            $pricing = function_exists('resolve_support_order_pricing')
                ? resolve_support_order_pricing($conn, $pieces, 'Etala001', $month)
                : ['quantity' => $pieces, 'unit_price' => 150.0, 'total_price' => 150.0, 'bundle_type' => $bundle_type, 'snap_product_cost' => null, 'snap_bundle_cost' => null, 'snap_dom_shipping' => null];
            $bundle_type = (string) $pricing['bundle_type'];
            $quantity = (int) $pricing['quantity'];
            $unit_price = (float) $pricing['unit_price'];
            $total_price = (float) $pricing['total_price'];
            $snap_product_cost = $pricing['snap_product_cost'] ?? null;
            $snap_bundle_cost = $pricing['snap_bundle_cost'] ?? null;
            $snap_dom_shipping = $pricing['snap_dom_shipping'] ?? null;
        }

        $check_table = $conn->query("SHOW TABLES LIKE 'support_orders'");
        if (!$check_table || $check_table->num_rows === 0) {
            $_SESSION['support_flash_error'] = 'جدول الأوردرات غير موجود. شغّل setup_orders_tables.php أولاً.';
            header('Location: ' . $redirectBaseAdd);
            exit;
        }

        $schemaErr = support_orders_sync_schema($conn);
        if ($schemaErr !== null) {
            throw new Exception('هيكل الجدول يحتاج تحديث: ' . $schemaErr);
        }

        $stmt = $conn->prepare("INSERT INTO support_orders 
            (support_id, sheet_id, support_name, product_code, customer_name, phone, address, bundle_type, quantity, unit_price, total_price, order_status, snap_product_cost, snap_bundle_cost, snap_dom_shipping) 
            VALUES (?, ?, ?, 'Etala001', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception($conn->error ?: 'prepare failed');
        }

        $stmt->bind_param(
            'iisssssiddsddd',
            $support_id,
            $sheet_id,
            $support_name,
            $customer_name,
            $phone,
            $address,
            $bundle_type,
            $quantity,
            $unit_price,
            $total_price,
            $order_status,
            $snap_product_cost,
            $snap_bundle_cost,
            $snap_dom_shipping
        );

        if ($stmt->execute()) {
            if (function_exists('sync_support_stats')) {
                sync_support_stats($conn, $support_id);
            }
            if ($view_sheet_id > 0) {
                header('Location: admin_panel.php?page=support&view_sheet=' . $view_sheet_id . '&saved=1');
            } else {
                header('Location: admin_panel.php?page=support&saved=1');
            }
            $stmt->close();
            exit;
        }

        $_SESSION['support_flash_error'] = 'فشل حفظ الأوردر: ' . $stmt->error;
        $stmt->close();
        header('Location: ' . $redirectBaseAdd . '&support_err=1');
        exit;
    } catch (Throwable $e) {
        $_SESSION['support_flash_error'] = 'خطأ أثناء الحفظ: ' . $e->getMessage();
        header('Location: ' . $redirectBaseAdd . '&support_err=1');
        exit;
    }
}

// ——— طلبات القفل ———
if (isset($_POST['request_unlock'])) {
    $req_id = (int) $_POST['request_unlock'];
    $conn->query("UPDATE support_sheets SET unlock_request = 1 WHERE id = $req_id AND support_id = $support_id");
    header('Location: admin_panel.php?page=support&unlock_req=1');
    exit;
}

if (isset($_POST['unlock_sheet'])) {
    $un_id = (int) $_POST['unlock_sheet'];
    $conn->query("UPDATE support_sheets SET is_unlocked = 1, unlocked_at = NOW(), force_locked = 0, unlock_request = 0 WHERE id = $un_id");
    
    // لو من صفحة التفاصيل
    $redir = 'admin_panel.php?page=support&unlocked=1';
    if (isset($_POST['from_detail']) && $_POST['from_detail'] != '') {
        $redir = 'admin_panel.php?page=support_detail&id=' . intval($_POST['from_detail']) . '&unlocked=1';
    }
    header('Location: ' . $redir);
    exit;
}

if (isset($_POST['force_lock_sheet'])) {
    $lock_id = (int) $_POST['force_lock_sheet'];
    $conn->query("UPDATE support_sheets SET force_locked = 1 WHERE id = $lock_id");
    
    // لو من صفحة التفاصيل
    $redir = 'admin_panel.php?page=support&force_locked=1';
    if (isset($_POST['from_detail']) && $_POST['from_detail'] != '') {
        $redir = 'admin_panel.php?page=support_detail&id=' . intval($_POST['from_detail']) . '&force_locked=1';
    }
    header('Location: ' . $redir);
    exit;
}

if (isset($_POST['reject_unlock'])) {
    $rej_id = (int) $_POST['reject_unlock'];
    $conn->query("UPDATE support_sheets SET unlock_request = 0 WHERE id = $rej_id");
    
    // لو من صفحة التفاصيل
    $redir = 'admin_panel.php?page=support&unlock_rej=1';
    if (isset($_POST['from_detail']) && $_POST['from_detail'] != '') {
        $redir = 'admin_panel.php?page=support_detail&id=' . intval($_POST['from_detail']) . '&unlock_rej=1';
    }
    header('Location: ' . $redir);
    exit;
}

