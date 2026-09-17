<?php
/**
 * حفظ بيانات حسابات الأرباح — يُستدعى من admin_panel.php قبل أي HTML
 */
if (!isset($conn) || !is_object($conn) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

if (!isset($_POST['save_daily_lead']) && !isset($_POST['save_product_monthly'])) {
    return;
}

migrate_legacy_product_monthly($conn);

$session_role = $_SESSION['admin_role'] ?? '';
$is_super_admin = ($session_role === 'super_admin');

try {
    if (isset($_POST['save_daily_lead'])) {
        $d_date = $conn->real_escape_string($_POST['date'] ?? '');
        $p_code = $conn->real_escape_string($_POST['current_product'] ?? 'all');
        $l_cost = (float) ($_POST['lead_cost'] ?? 0);
        if ($d_date !== '') {
            $conn->query("INSERT INTO support_daily_leads (date, product_code, lead_cost) VALUES ('$d_date', '$p_code', $l_cost)
                ON DUPLICATE KEY UPDATE lead_cost = VALUES(lead_cost)");
            $_SESSION['accounts_flash_success'] = 'تم حفظ سعر الليد بنجاح.';
        }
    }

    if (isset($_POST['save_product_monthly'])) {
        if (!$is_super_admin) {
            throw new Exception('حفظ إعدادات المنتدلتاح للمدير الرئيسي فقط.');
        }
        $m_month = $conn->real_escape_string($_POST['month'] ?? date('Y-m'));
        $p_code = trim($_POST['product_code'] ?? '');
        if ($p_code === '') {
            throw new Exception('كود المنتدلطلوب');
        }
        $p_code = $conn->real_escape_string($p_code);
        $p_name = $conn->real_escape_string(trim($_POST['product_name'] ?? $p_code));
        $s_qty = (int) ($_POST['stock_quantity'] ?? 0);
        $p_sale = (float) ($_POST['product_sale_price'] ?? 0);
        $b_sale = (float) ($_POST['bundle_sale_price'] ?? 0);
        $p_cost = (float) ($_POST['product_cost'] ?? 0);
        $b_cost = (float) ($_POST['bundle_cost'] ?? 0);
        if ($b_sale <= 0 && $p_sale > 0) {
            $b_sale = $p_sale * 3;
        }
        if ($b_cost <= 0 && $p_cost > 0) {
            $b_cost = $p_cost * 3;
        }
        $i_ship = (float) ($_POST['intl_shipping'] ?? 0);
        $d_ship = (float) ($_POST['dom_shipping'] ?? 0);
        $o_cost = (float) ($_POST['ops_cost'] ?? 0);

        $ok = $conn->query("INSERT INTO support_product_monthly 
            (month, product_code, product_name, stock_quantity, product_sale_price, bundle_sale_price, product_cost, bundle_cost, intl_shipping, dom_shipping, ops_cost)
            VALUES ('$m_month', '$p_code', '$p_name', $s_qty, $p_sale, $b_sale, $p_cost, $b_cost, $i_ship, $d_ship, $o_cost)
            ON DUPLICATE KEY UPDATE
            product_name = VALUES(product_name),
            stock_quantity = VALUES(stock_quantity),
            product_sale_price = VALUES(product_sale_price),
            bundle_sale_price = VALUES(bundle_sale_price),
            product_cost = VALUES(product_cost),
            bundle_cost = VALUES(bundle_cost),
            intl_shipping = VALUES(intl_shipping),
            dom_shipping = VALUES(dom_shipping),
            ops_cost = VALUES(ops_cost)");

        if (!$ok) {
            throw new Exception($conn->error ?: 'فشل حفظ إعدادات المنتج');
        }

        if ($p_code === 'Etala001') {
            $conn->query("INSERT INTO support_monthly_expenses (month, stock_quantity, product_sale_price, product_cost, intl_shipping, dom_shipping, ops_cost)
                VALUES ('$m_month', $s_qty, $p_sale, $p_cost, $i_ship, $d_ship, $o_cost)
                ON DUPLICATE KEY UPDATE
                stock_quantity = VALUES(stock_quantity),
                product_sale_price = VALUES(product_sale_price),
                product_cost = VALUES(product_cost),
                intl_shipping = VALUES(intl_shipping),
                dom_shipping = VALUES(dom_shipping),
                ops_cost = VALUES(ops_cost)");
        }

        $_SESSION['accounts_flash_success'] = 'تم حفظ إعدادات المنتج بنجاح.';
    }
} catch (Throwable $e) {
    $_SESSION['accounts_flash_error'] = $e->getMessage();
}

$redirect_section = $_POST['redirect_section'] ?? 'accounts';
if (!$is_super_admin) {
    $redirect_section = 'leads';
}
$redirect_month = $_POST['current_month'] ?? date('Y-m');
$redirect_year = $_POST['current_year'] ?? date('Y');
$redirect_range = ($_POST['current_range'] ?? 'month') === 'year' ? 'year' : 'month';
$redirect_product = $_POST['current_product'] ?? 'all';
$redirect_view = ($_POST['current_view'] ?? 'month') === 'product' ? 'product' : 'month';
$edit_product = trim($_POST['product_code'] ?? '');

$url = 'admin_panel.php?page=accounts&section=' . urlencode($redirect_section)
    . '&view=' . urlencode($redirect_view)
    . '&range=' . urlencode($redirect_range)
    . '&month=' . urlencode($redirect_month)
    . '&year=' . urlencode($redirect_year)
    . '&product=' . urlencode($redirect_product);

if ($edit_product !== '' && $redirect_section === 'accounts') {
    $url .= '&edit_product=' . urlencode($edit_product);
}

header('Location: ' . $url);
exit;
