<?php
/**
 * تسجيل/حذف تحصيل المديونيات — يُستدعى من admin_panel.php قبل أي HTML
 */
if (!isset($conn) || !is_object($conn) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

if (!isset($_POST['add_payment']) && !isset($_POST['delete_record'])) {
    return;
}

if (!function_exists('get_accounts_period_dates') || !function_exists('calculate_shipping_collection_balance')) {
    return;
}

if (!function_exists('ensure_debts_schema')) {
    function ensure_debts_schema($conn) {
        if (!is_object($conn)) {
            return false;
        }
        static $done = false;
        static $has_period = null;
        if ($done) {
            return $has_period === true;
        }
        $done = true;
        @$conn->query("CREATE TABLE IF NOT EXISTS support_financial_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            type ENUM('collected', 'creditor', 'debtor') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            note TEXT,
            period_month VARCHAR(7) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $col = @$conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'period_month'");
        $has_period = ($col && $col->num_rows > 0);
        if (!$has_period) {
            @$conn->query("ALTER TABLE support_financial_records ADD COLUMN period_month VARCHAR(7) NULL AFTER note");
            $col2 = @$conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'period_month'");
            $has_period = ($col2 && $col2->num_rows > 0);
        }
        if ($has_period) {
            @$conn->query("UPDATE support_financial_records
                SET period_month = DATE_FORMAT(entry_date, '%Y-%m')
                WHERE period_month IS NULL OR period_month = ''");
        }
        return $has_period === true;
    }
}

if (!function_exists('debts_period_month_sql')) {
    function debts_period_month_sql($alias = '', $has_period_month = true) {
        $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        if (!$has_period_month) {
            return "DATE_FORMAT({$p}entry_date, '%Y-%m')";
        }
        return "COALESCE(NULLIF({$p}period_month, ''), DATE_FORMAT({$p}entry_date, '%Y-%m'))";
    }
}

if (function_exists('ensure_debts_schema')) {
    ensure_debts_schema($conn);
}

$scope = ($_POST['current_scope'] ?? 'period') === 'lifetime' ? 'lifetime' : 'period';
$range = ($_POST['current_range'] ?? 'month') === 'year' ? 'year' : 'month';
$selected_month = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['current_month'] ?? ''))
    ? $_POST['current_month']
    : date('Y-m');
$selected_year = preg_match('/^\d{4}$/', (string) ($_POST['current_year'] ?? ''))
    ? $_POST['current_year']
    : date('Y');
$selected_product = trim((string) ($_POST['current_product'] ?? 'all'));
if ($selected_product === '') {
    $selected_product = 'all';
}
$product_filter = ($selected_product !== 'all') ? $selected_product : null;

$redirect_qs = http_build_query([
    'page' => 'debts',
    'scope' => $scope,
    'range' => $range,
    'month' => $selected_month,
    'year' => $selected_year,
    'product' => $selected_product,
]);
$redirect = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : 'admin_panel.php?' . $redirect_qs;

[$period_start, $period_end] = get_accounts_period_dates($range, $selected_month, $selected_year);

try {
    if (isset($_POST['delete_record'])) {
        $del_id = (int) ($_POST['record_id'] ?? 0);
        if ($del_id > 0) {
            $conn->query("DELETE FROM support_financial_records WHERE id = $del_id AND type = 'debtor'");
            $_SESSION['debts_flash_success'] = 'تم حذف التحصيل بنجاح.';
        }
        header('Location: ' . $redirect);
        exit;
    }

    if (isset($_POST['add_payment'])) {
        $e_amount = round((float) ($_POST['amount'] ?? 0), 2);
        $e_note = trim((string) ($_POST['note'] ?? 'تحصيل من شركة شحن'));
        if ($e_note === '') {
            $e_note = 'تحصيل من شركة شحن';
        }

        // تاريخ الاستلام الفعلي (أي يوم تختاره — حتى لو بعد شهر الاستحقاق)
        $posted_date = trim((string) ($_POST['entry_date'] ?? ''));
        if ($posted_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted_date)) {
            $e_date = $posted_date;
        } else {
            $e_date = date('Y-m-d');
        }

        // شهر الاستحقاق الذي يُخصم منه (مستقل عن تاريخ الاستلام)
        if ($range === 'year') {
            // عند وضع السنة: نستخدم شهر من التاريخ إن وُجد داخل السنة، وإلا يناير
            $period_month = (substr($e_date, 0, 4) === (string) $selected_year)
                ? substr($e_date, 0, 7)
                : ($selected_year . '-01');
            // الأفضل: لو أُرسل current_month صالح ضمن السنة نستخدمه
            if (preg_match('/^\d{4}-\d{2}$/', $selected_month) && substr($selected_month, 0, 4) === (string) $selected_year) {
                $period_month = $selected_month;
            }
        } else {
            $period_month = $selected_month;
        }

        if ($e_amount <= 0) {
            $_SESSION['debts_flash_error'] = 'أدخل مبلغاً أكبر من صفر.';
            header('Location: ' . $redirect);
            exit;
        }

        // التحقق من المتبقي على شهر/فترة الاستحقاق فقط
        $check_start = date('Y-m-01', strtotime($period_month . '-01'));
        $check_end = date('Y-m-t', strtotime($period_month . '-01'));
        if ($range === 'year') {
            $check_start = $period_start;
            $check_end = $period_end;
        }

        $sc_id_val = isset($_POST['sc_id']) && is_numeric($_POST['sc_id']) && $_POST['sc_id'] > 0 ? (int)$_POST['sc_id'] : 'NULL';
        $sc_id_filter = $sc_id_val !== 'NULL' ? (int)$_POST['sc_id'] : null;

        $ship = calculate_shipping_collection_balance($conn, $check_start, $check_end, $product_filter, $sc_id_filter);
        $remaining = (float) ($ship['remaining'] ?? 0);

        $period_label = $range === 'year'
            ? ('سنة ' . $selected_year)
            : ('شهر ' . $period_month);

        if ($remaining <= 0) {
            $_SESSION['debts_flash_error'] = 'لا يوجد مبلغ مستحق في ' . $period_label . '. لم يتم تسجيل أي تحصيل.';
            header('Location: ' . $redirect);
            exit;
        }

        if ($e_amount > $remaining) {
            $_SESSION['debts_flash_error'] = 'المبلغ أكبر من المتبقي المستحق ('
                . number_format($remaining, 2) . ' دل) في ' . $period_label . '. لم يتم التسجيل.';
            header('Location: ' . $redirect);
            exit;
        }

        $e_date_sql = $conn->real_escape_string($e_date);
        $e_note_sql = $conn->real_escape_string($e_note . ' — استحقاق ' . $period_month);
        $pm_sql = $conn->real_escape_string($period_month);
        $has_pm = function_exists('ensure_debts_schema') ? ensure_debts_schema($conn) : false;

        if ($has_pm) {
            $ok = $conn->query(
                "INSERT INTO support_financial_records (entry_date, type, amount, note, period_month, shipping_company_id)
                 VALUES ('$e_date_sql', 'debtor', $e_amount, '$e_note_sql', '$pm_sql', $sc_id_val)"
            );
        } else {
            // بدون عمود period_month: نضع تاريخ الاستلام داخل شهر الاستحقاق حتى يظهر في السجل الصحيح
            $fallback_date = $period_month . '-15';
            $ok = $conn->query(
                "INSERT INTO support_financial_records (entry_date, type, amount, note, shipping_company_id)
                 VALUES ('$fallback_date', 'debtor', $e_amount, '$e_note_sql', $sc_id_val)"
            );
        }

        if ($ok) {
            $new_remaining = $remaining - $e_amount;
            $_SESSION['debts_flash_success'] = 'تم تسجيل تحصيل '
                . number_format($e_amount, 2) . ' دل على ' . $period_label
                . ' (تاريخ الاستلام: ' . $e_date . '). المتبقي الآن: '
                . number_format($new_remaining, 2) . ' دل.';
        } else {
            $_SESSION['debts_flash_error'] = 'فشل تسجيل التحصيل: ' . $conn->error;
        }

        header('Location: ' . $redirect);
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['debts_flash_error'] = 'حدث خطأ أثناء حفظ التحصيل.';
    header('Location: ' . $redirect);
    exit;
}
