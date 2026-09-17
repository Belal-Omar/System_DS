<?php
require_once 'config.php';
session_start();
require_once 'helpers.php';

global $conn;
/** @var mysqli $conn */

// Only Admin or Manager
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit;
}
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'manager'])) {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['append_file']) && isset($_POST['sheet_id']) && isset($_POST['support_id'])) {
    $sheet_id = (int)$_POST['sheet_id'];
    $support_id = (int)$_POST['support_id'];
    $period = $_POST['period'] ?? 'all';

    $sheet_check = $conn->query("SELECT id, support_id FROM support_sheets WHERE id = $sheet_id AND support_id = $support_id");
    if (!$sheet_check || $sheet_check->num_rows === 0) {
        die("Invalid sheet or support ID.");
    }

    $support_name = 'support';
    $sn = $conn->query("SELECT fullname, username FROM admins WHERE id = $support_id LIMIT 1");
    if ($sn && $sn->num_rows > 0) {
        $sn_row = $sn->fetch_assoc();
        $support_name = $sn_row['fullname'] . ' (' . $sn_row['username'] . ')';
    }

    $file = $_FILES['append_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $data = [];
    $headers = [];

    if ($ext === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            $is_first = true;
            while (($row = fgetcsv($handle)) !== false) {
                if ($is_first) {
                    $headers = $row;
                    // Fix BOM
                    if (!empty($headers[0]) && strpos($headers[0], "\xEF\xBB\xBF") === 0) {
                        $headers[0] = substr($headers[0], 3);
                    }
                    $is_first = false;
                } else {
                    $has_data = false;
                    foreach ($row as $cell) {
                        if (!empty(trim($cell))) {
                            $has_data = true;
                            break;
                        }
                    }
                    if ($has_data) {
                        $data[] = $row;
                    }
                }
            }
            fclose($handle);
        }
    } elseif (in_array($ext, ['xlsx', 'xls'])) {
        if (file_exists('simple_xlsx_reader.php')) {
            include_once('simple_xlsx_reader.php');
            if (class_exists('SimpleXLSXReader')) {
                $xlsx = new SimpleXLSXReader($file['tmp_name']);
                if ($xlsx) {
                    $result = $xlsx->read();
                    if ($result) {
                        $headers = $result['headers'] ?? [];
                        $sheet_data = $result['data'] ?? [];
                        foreach ($sheet_data as $row) {
                            $has_data = false;
                            foreach ($row as $cell) {
                                if (!empty(trim((string)$cell))) {
                                    $has_data = true;
                                    break;
                                }
                            }
                            if ($has_data) {
                                $data[] = $row;
                            }
                        }
                    }
                }
            }
        }
    }

    if (empty($data)) {
        header("Location: admin_panel.php?page=support_detail&id=$support_id&period=$period&message=error_no_data");
        exit;
    }

    // Detect columns
    list($name_col, $phone_col, $product_col, $agent_col, $pieces_col, $price_col, $campaign_col, $notes_col, $gov_col, $address_col) = support_sheet_detect_name_phone_cols($headers);

    // Fetch existing phones for this sheet to prevent duplicates
    $existing_phones = [];
    $res = $conn->query("SELECT phone FROM support_orders WHERE sheet_id = $sheet_id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $clean_phone = preg_replace('/[^0-9]/', '', $row['phone']);
            if ($clean_phone) {
                $existing_phones[$clean_phone] = true;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO support_orders
        (support_id, sheet_id, sheet_row_index, support_name, product_code,
        order_date, recipient_name, customer_name, phone,
        order_status, notes, governorate, address,
        pieces, quantity, unit_price, total_price,
        customer_status, agent_code, marketing_agent, campaign, version, article,
        bundle_type, snap_product_cost, snap_bundle_cost, snap_dom_shipping, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, '', '', 'single', ?, ?, ?, NOW())");
    
    if (!$stmt) {
        header("Location: admin_panel.php?page=support_detail&id=$support_id&period=$period&message=error_db");
        exit;
    }

    $appended_count = 0;
    $order_date = date('Y-m-d');
    $agent_code = '';
    $snap_product_cost = 0;
    $snap_bundle_cost = 0;
    $snap_dom_shipping = 0;

    foreach ($data as $i => $row) {
        $phone = trim((string) ($row[$phone_col] ?? ''));
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        $name = trim((string) ($row[$name_col] ?? ''));
        
        if ($name === '' && $phone === '') {
            continue;
        }

        // Check if phone already exists in this sheet
        if ($clean_phone && isset($existing_phones[$clean_phone])) {
            continue; // Skip duplicate
        }

        $row_product_code = 'Etala001';
        if ($product_col !== -1 && isset($row[$product_col]) && trim((string)$row[$product_col]) !== '') {
            $row_product_code = trim((string)$row[$product_col]);
        }

        $marketing_agent = '';
        if ($agent_col !== -1 && isset($row[$agent_col])) {
            $marketing_agent = trim((string)$row[$agent_col]);
        }

        $row_pieces = 1;
        if ($pieces_col !== -1 && isset($row[$pieces_col]) && is_numeric(trim((string)$row[$pieces_col]))) {
            $row_pieces = (int) trim((string)$row[$pieces_col]);
            if ($row_pieces <= 0) $row_pieces = 1;
        }

        $row_total_price = 150.0;
        if ($price_col !== -1 && isset($row[$price_col]) && is_numeric(trim((string)$row[$price_col]))) {
            $row_total_price = (float) trim((string)$row[$price_col]);
        }
        $row_unit_price = $row_pieces > 0 ? $row_total_price / $row_pieces : $row_total_price;

        $row_campaign = '';
        if ($campaign_col !== -1 && isset($row[$campaign_col])) {
            $row_campaign = trim((string)$row[$campaign_col]);
        }

        $row_notes = '';
        if ($notes_col !== -1 && isset($row[$notes_col])) {
            $row_notes = trim((string)$row[$notes_col]);
        }

        $row_gov = '';
        if ($gov_col !== -1 && isset($row[$gov_col])) {
            $row_gov = trim((string)$row[$gov_col]);
        }

        $row_address = '';
        if ($address_col !== -1 && isset($row[$address_col])) {
            $row_address = trim((string)$row[$address_col]);
        }

        $idx = (int) ($i + 1); // sheet row index
        $stmt->bind_param(
            'iiisssssssssiiddsssddd',
            $support_id,
            $sheet_id,
            $idx,
            $support_name,
            $row_product_code,
            $order_date,
            $name,
            $name,
            $phone,
            $row_notes,
            $row_gov,
            $row_address,
            $row_pieces,
            $row_pieces,
            $row_unit_price,
            $row_total_price,
            $agent_code,
            $marketing_agent,
            $row_campaign,
            $snap_product_cost,
            $snap_bundle_cost,
            $snap_dom_shipping
        );

        if ($stmt->execute()) {
            $appended_count++;
            
            // الخصم من المخزون الرئيسي
            if (function_exists('main_inventory_deduct')) {
                main_inventory_deduct($conn, $row_product_code, $row_pieces);
            }

            if ($clean_phone) {
                $existing_phones[$clean_phone] = true;
            }
        }
    }
    $stmt->close();

    if ($appended_count > 0) {
        $conn->query("UPDATE support_sheets SET names_count = names_count + $appended_count WHERE id = $sheet_id");
        if (function_exists('sync_support_stats')) {
            @sync_support_stats($conn, $support_id);
        }
    }

    header("Location: admin_panel.php?page=support_detail&id=$support_id&period=$period&message=appended&count=$appended_count");
    exit;
}
