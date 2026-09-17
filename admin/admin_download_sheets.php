<?php
// admin_download_sheets.php
require_once 'config.php';
session_start();

// Only Admin or Manager
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit;
}
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'manager', 'marketing', 'marketing_main'])) {
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'download') {
    if (isset($conn) && $conn) {
        $date = $conn->real_escape_string($_GET['date']);
        $marketer = $conn->real_escape_string($_GET['marketer']);
        
        $sql = "SELECT id, customer_name, customer_phone, governorate, product_name, product_package, marketer_code, created_at 
                FROM landing_page_leads 
                WHERE DATE(created_at) = '$date' AND marketer_code = '$marketer'
                ORDER BY created_at ASC";
        $res = $conn->query($sql);
        
        // Clear any previous output buffer to avoid corrupting CSV
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Leads_' . $marketer . '_' . $date . '.csv');
        // Output BOM for Excel UTF-8 support
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        
        fputcsv($output, [
            'التاريخ', 'إسم المستلم', 'رقم الهاتف', 'حالة الطلب', 'إسناد شركة شحن', 'المندوب', 
            'ملاحظات الشحن', 'ملاحظات', 'المحافظة', 'العنوان', 'القطع', 'حالة العميل', 'Agent', 'Campaign', 'السعر'
        ]);
        
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                // Extract price and pieces from package
                $pieces = 1;
                $price = 0;
                $packageStr = $row['product_package'];
                
                if (strpos($packageStr, '3') !== false) {
                    $pieces = 3;
                    $price = 2000;
                } elseif (strpos($packageStr, '2') !== false) {
                    $pieces = 2;
                    $price = 1600;
                } else {
                    $pieces = 1;
                    $price = 890;
                }

                $notes = $row['product_name'] . ' - ' . $packageStr;

                fputcsv($output, [
                    $row['created_at'],        // التاريخ
                    $row['customer_name'],     // إسم المستلم
                    $row['customer_phone'],    // رقم الهاتف
                    'جديد',                   // حالة الطلب
                    '',                        // إسناد شركة شحن
                    '',                        // المندوب
                    '',                        // ملاحظات الشحن
                    $notes,                    // ملاحظات
                    $row['governorate'],       // المحافظة
                    $row['governorate'],       // العنوان
                    $pieces,                   // القطع
                    '',                        // حالة العميل
                    $row['marketer_code'],     // Agent
                    'Miske Landing Page',      // Campaign
                    $price                     // السعر
                ]);
            }
        }
        fclose($output);
        exit;
    }
}
