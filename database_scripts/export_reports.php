<?php
// ملف: export_reports.php - لتصدير التقارير
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$merchant_id = $_GET['merchant_id'] ?? 0;
$report_type = $_GET['report_type'] ?? 'products';
$period = $_GET['period'] ?? 'all';
$export_format = $_GET['export'] ?? 'pdf';
$product_id = $_GET['product_id'] ?? 0;
$export_type = $_GET['export_type'] ?? '';

// جلب بيانات التاجر
$merchant_query = $conn->prepare("SELECT id, fullname, email, phone FROM users WHERE id = ?");
$merchant_query->bind_param("i", $merchant_id);
$merchant_query->execute();
$merchant_data = $merchant_query->get_result()->fetch_assoc();

if (!$merchant_data) {
    die("التاجر غير موجود");
}

// جلب بيانات السحب
$withdrawals_query = "
    SELECT w.*, DATE(w.created_at) as withdrawal_date
    FROM withdrawals w
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
";
$stmt = $conn->prepare($withdrawals_query);
$stmt->bind_param("i", $merchant_id);
$stmt->execute();
$withdrawals_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// جلب بيانات المنتجات
$products_data = [];
if ($export_type == 'all_products' || $product_id > 0) {
    $where_clause = $product_id > 0 ? "AND p.id = $product_id" : "";
    
    $products_query = "
        SELECT 
            p.*,
            COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN oi.quantity ELSE 0 END), 0) as sold_quantity,
            COALESCE(SUM(CASE WHEN o.status IN ('في الشحن') THEN oi.quantity ELSE 0 END), 0) as shipping_quantity,
            COALESCE(SUM(CASE WHEN o.status = 'مرتجع' THEN oi.quantity ELSE 0 END), 0) as returned_quantity,
            COALESCE(SUM(CASE WHEN o.status = 'ملغي' THEN oi.quantity ELSE 0 END), 0) as cancelled_quantity,
            COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN (oi.price - oi.commission) * oi.quantity ELSE 0 END), 0) as net_profit,
            COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN oi.commission * oi.quantity ELSE 0 END), 0) as total_commission
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE p.user_id = ? $where_clause
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $conn->prepare($products_query);
    $stmt->bind_param("i", $merchant_id);
    $stmt->execute();
    $products_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// تطبيق الفلتر الزمني
$filtered_withdrawals = [];
foreach($withdrawals_data as $withdrawal) {
    $include = true;
    switch($period) {
        case 'today':
            $include = (date('Y-m-d', strtotime($withdrawal['created_at'])) == date('Y-m-d'));
            break;
        case 'week':
            $include = (strtotime($withdrawal['created_at']) >= strtotime('-7 days'));
            break;
        case 'month':
            $include = (strtotime($withdrawal['created_at']) >= strtotime('-1 month'));
            break;
        case '3months':
            $include = (strtotime($withdrawal['created_at']) >= strtotime('-3 months'));
            break;
        case '6months':
            $include = (strtotime($withdrawal['created_at']) >= strtotime('-6 months'));
            break;
        case 'year':
            $include = (strtotime($withdrawal['created_at']) >= strtotime('-1 year'));
            break;
    }
    if ($include) {
        $filtered_withdrawals[] = $withdrawal;
    }
}

if ($export_type == 'all_products' || $product_id > 0) {
    // تصدير المنتجات
    if ($export_format == 'pdf') {
        require_once('TCPDF-main/tcpdf.php');
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Digital Soldiers System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('تقرير المنتجات - ' . $merchant_data['fullname']);
        
        $pdf->AddPage();
        $pdf->SetFont('aefurat', '', 14);
        
        // عنوان التقرير
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('aefurat', 'B', 16);
        $pdf->Cell(0, 18, 'تقرير المنتجات', 1, 1, 'C', 1, '', 0, false, 'M', 'M');
        
        // معلومات التاجر في جدول منظم
        $pdf->SetFont('aefurat', 'B', 14);
        $pdf->SetFillColor(44, 62, 80);
        $pdf->Cell(70, 15, 'Merchant:', 1, 0, 'R', 1);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('aefurat', 'B', 14);
        $pdf->Cell(140, 15, $merchant_data['fullname'], 1, 1, 'L', 1);
        
        $pdf->SetFillColor(44, 62, 80);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('aefurat', 'B', 14);
        $pdf->Cell(70, 15, 'Email:', 1, 0, 'R', 1);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('aefurat', 'B', 14);
        $pdf->Cell(140, 15, $merchant_data['email'], 1, 1, 'L', 1);
        
        $pdf->Ln(10);
        
        // جدول المنتجات - عناوين أكبر
        $pdf->SetFont('aefurat', 'B', 12);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(15, 12, '#', 1, 0, 'C', 1);
        $pdf->Cell(60, 12, 'اسم المنتج', 1, 0, 'C', 1);
        $pdf->Cell(25, 12, 'السعر', 1, 0, 'C', 1);
        $pdf->Cell(20, 12, 'المخزون', 1, 0, 'C', 1);
        $pdf->Cell(20, 12, 'المباع', 1, 0, 'C', 1);
        $pdf->Cell(30, 12, 'صافي الربح', 1, 0, 'C', 1);
        $pdf->Cell(30, 12, 'العمولة', 1, 1, 'C', 1);
        
        // بيانات الجدول
        $pdf->SetFont('aefurat', '', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        
        $count = 1;
        $total_profit = 0;
        $total_commission = 0;
        foreach($products_data as $product) {
            // تلوير الصفوف بالتبادل
            $fill = ($count % 2 == 0) ? 1 : 0;
            if ($fill) {
                $pdf->SetFillColor(248, 249, 250);
            }
            
            $pdf->Cell(15, 10, $count, 1, 0, 'C', $fill);
            $pdf->Cell(60, 10, $product['name'], 1, 0, 'R', $fill);
            $pdf->Cell(25, 10, number_format($product['price'], 2) . ' د.ل', 1, 0, 'C', $fill);
            $pdf->Cell(20, 10, $product['stock'], 1, 0, 'C', $fill);
            $pdf->Cell(20, 10, $product['sold_quantity'], 1, 0, 'C', $fill);
            $pdf->Cell(30, 10, number_format($product['net_profit'], 2) . ' د.ل', 1, 0, 'C', $fill);
            $pdf->Cell(30, 10, number_format($product['total_commission'], 2) . ' د.ل', 1, 1, 'C', $fill);
            
            $total_profit += $product['net_profit'];
            $total_commission += $product['total_commission'];
            $count++;
        }
        
        // الإجماليات في جدول منظم
        $pdf->Ln(15);
        $pdf->SetFont('aefurat', 'B', 12);
        
        // إجمالي العمولات باللون الأحمر
        $pdf->SetFillColor(220, 53, 69);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(105, 12, 'إجمالي العمولات', 1, 0, 'C', 1);
        $pdf->Cell(105, 12, number_format($total_commission, 2) . ' د.ل', 1, 1, 'C', 1);
        
        // الإجمالي الصافي باللون الرمادي
        $pdf->SetFillColor(108, 117, 125);
        $pdf->Cell(105, 12, 'الإجمالي الصافي', 1, 0, 'C', 1);
        $pdf->Cell(105, 12, number_format($total_profit, 2) . ' د.ل', 1, 1, 'C', 1);
        
        $filename = $product_id > 0 ? 'تقرير_منتج_' . $product_id . '.pdf' : 'تقرير_المنتجات.pdf';
        $pdf->Output($filename, 'D');
        
    } elseif ($export_format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        $filename = $product_id > 0 ? 'تقرير_منتج_' . $product_id . '.xls' : 'تقرير_المنتجات.xls';
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        
        echo "<style>";
        echo "table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }";
        echo "th { background-color: #34495e; color: white; font-weight: bold; text-align: center; padding: 12px; border: 1px solid #ddd; font-size: 14px; }";
        echo "td { padding: 12px; border: 1px solid #ddd; text-align: center; font-size: 13px; }";
        echo ".header { background-color: #2c3e50; color: white; font-weight: bold; text-align: center; padding: 20px; font-size: 20px; letter-spacing: 1px; }";
        echo ".merchant-label { background-color: #34495e; color: white; padding: 15px; font-weight: bold; font-size: 16px; text-align: left; width: 180px; border-right: 3px solid #fff; }";
        echo ".merchant-value { background-color: #f8f9fa; color: #000; padding: 15px; font-weight: bold; font-size: 16px; text-align: left; border-left: 3px solid #34495e; }";
        echo ".total-commission { background: linear-gradient(135deg, #dc3545, #c82333); color: white; font-weight: bold; text-align: center; font-size: 15px; letter-spacing: 0.5px; }";
        echo ".total-profit { background: linear-gradient(135deg, #6c757d, #5a6268); color: white; font-weight: bold; text-align: center; font-size: 15px; letter-spacing: 0.5px; }";
        echo ".even { background-color: #f8f9fa; }";
        echo ".profit-cell { color: #28a745; font-weight: bold; }";
        echo ".commission-cell { color: #6f42c1; font-weight: bold; }";
        echo "</style>";
        
        echo "<table>";
        echo "<tr><td colspan='7' class='header'>تقرير المنتجات</td></tr>";
        echo "<tr><td colspan='7' style='padding: 0; border: none;'>";
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr>";
        echo "<td class='merchant-label'>Merchant:</td>";
        echo "<td class='merchant-value' colspan='6'>" . $merchant_data['fullname'] . "</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td class='merchant-label'>Email:</td>";
        echo "<td class='merchant-value' colspan='6'>" . $merchant_data['email'] . "</td>";
        echo "</tr>";
        echo "</table>";
        echo "</td></tr>";
        echo "<tr><th>#</th><th>اسم المنتج</th><th>السعر</th><th>المخزون</th><th>المباع</th><th>صافي الربح</th><th>العمولة</th></tr>";
        
        $count = 1;
        $total_profit = 0;
        $total_commission = 0;
        foreach($products_data as $product) {
            $row_class = ($count % 2 == 0) ? "class='even'" : "";
            echo "<tr $row_class>";
            echo "<td style='font-weight: bold;'>" . $count . "</td>";
            echo "<td style='text-align: right; font-weight: bold; color: #2c3e50;'>" . $product['name'] . "</td>";
            echo "<td>" . number_format($product['price'], 2) . " د.ل</td>";
            echo "<td>" . $product['stock'] . "</td>";
            echo "<td>" . $product['sold_quantity'] . "</td>";
            echo "<td class='profit-cell'>" . number_format($product['net_profit'], 2) . " د.ل</td>";
            echo "<td class='commission-cell'>" . number_format($product['total_commission'], 2) . " د.ل</td>";
            echo "</tr>";
            
            $total_profit += $product['net_profit'];
            $total_commission += $product['total_commission'];
            $count++;
        }
        
        echo "<tr><td colspan='5' class='total-commission'>إجمالي العمولات</td><td colspan='2' class='total-commission'>" . number_format($total_commission, 2) . " د.ل</td></tr>";
        echo "<tr><td colspan='5' class='total-profit'>الإجمالي الصافي</td><td colspan='2' class='total-profit'>" . number_format($total_profit, 2) . " د.ل</td></tr>";
        echo "</table>";
    }
    exit;
} elseif ($export_type == 'withdrawals') {
    // تصدير PDF باستخدام TCPDF
    require_once('TCPDF-main/tcpdf.php');
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Digital Soldiers System');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('تقرير السحب - ' . $merchant_data['fullname']);
    
    $pdf->AddPage();
    $pdf->SetFont('aefurat', '', 14);
    
    // عنوان التقرير
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 15, 'تقرير السحب', 1, 1, 'C', 1, '', 0, false, 'M', 'M');
    $pdf->SetFont('aefurat', '', 12);
    $pdf->Cell(0, 10, 'التاجر: ' . $merchant_data['fullname'], 0, 1, 'C', 0, '', 0, false, 'M', 'M');
    $pdf->Cell(0, 10, 'البريد: ' . $merchant_data['email'], 0, 1, 'C', 0, '', 0, false, 'M', 'M');
    $pdf->Cell(0, 10, 'الفترة: ' . getPeriodName($period), 0, 1, 'C', 0, '', 0, false, 'M', 'M');
    $pdf->Ln(15);
    
    // جدول السحب - عناوين أكبر
    $pdf->SetFont('aefurat', 'B', 12);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(15, 12, '#', 1, 0, 'C', 1);
    $pdf->Cell(35, 12, 'المبلغ', 1, 0, 'C', 1);
    $pdf->Cell(30, 12, 'الحالة', 1, 0, 'C', 1);
    $pdf->Cell(40, 12, 'طريقة الدفع', 1, 0, 'C', 1);
    $pdf->Cell(45, 12, 'التاريخ', 1, 0, 'C', 1);
    $pdf->Cell(45, 12, 'ملاحظات', 1, 1, 'C', 1);
    
    // بيانات الجدول
    $pdf->SetFont('aefurat', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    
    $count = 1;
    $total_withdrawn = 0;
    foreach($filtered_withdrawals as $withdrawal) {
        // تلوير الصفوف بالتبادل
        $fill = ($count % 2 == 0) ? 1 : 0;
        if ($fill) {
            $pdf->SetFillColor(248, 249, 250);
        }
        
        $pdf->Cell(15, 10, $count, 1, 0, 'C', $fill);
        $pdf->Cell(35, 10, number_format($withdrawal['amount'], 2) . ' د.ل', 1, 0, 'C', $fill);
        $pdf->Cell(30, 10, $withdrawal['status'], 1, 0, 'C', $fill);
        $pdf->Cell(40, 10, $withdrawal['payment_method'] ?? '-', 1, 0, 'C', $fill);
        $pdf->Cell(45, 10, date('Y-m-d H:i', strtotime($withdrawal['created_at'])), 1, 0, 'C', $fill);
        $pdf->Cell(45, 10, $withdrawal['notes'] ?? '-', 1, 1, 'C', $fill);
        
        if ($withdrawal['status'] == 'مكتمل') {
            $total_withdrawn += $withdrawal['amount'];
        }
        $count++;
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('aefurat', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 12, 'إجمالي المسحوب: ' . number_format($total_withdrawn, 2) . ' د.ل', 1, 1, 'C', 1, '', 0, false, 'M', 'M');
    
    $pdf->Output('تقرير_السحب_' . $merchant_data['fullname'] . '.pdf', 'D');
    
} elseif ($export_format == 'excel') {
    // تصدير Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="تقرير_السحب_' . $merchant_data['fullname'] . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }";
    echo "th { background-color: #34495e; color: white; font-weight: bold; text-align: center; padding: 10px; border: 1px solid #ddd; }";
    echo "td { padding: 8px; border: 1px solid #ddd; text-align: center; }";
    echo ".header { background-color: #f0f0f0; font-weight: bold; text-align: center; padding: 12px; font-size: 16px; }";
    echo ".merchant-info { background-color: #e8f4f8; padding: 8px; font-weight: bold; }";
    echo ".total { background-color: #d4edda; font-weight: bold; text-align: center; }";
    echo ".even { background-color: #f8f9fa; }";
    echo ".status-completed { background-color: #d4edda; color: #155724; font-weight: bold; }";
    echo ".status-pending { background-color: #fff3cd; color: #856404; font-weight: bold; }";
    echo "</style>";
    
    echo "<table>";
    echo "<tr><td colspan='6' class='header'>تقرير السحب</td></tr>";
    echo "<tr><td colspan='2' class='merchant-info'>التاجر: " . $merchant_data['fullname'] . "</td><td colspan='2' class='merchant-info'>البريد: " . $merchant_data['email'] . "</td><td colspan='2' class='merchant-info'>الفترة: " . getPeriodName($period) . "</td></tr>";
    echo "<tr><th>#</th><th>المبلغ</th><th>الحالة</th><th>طريقة الدفع</th><th>التاريخ</th><th>ملاحظات</th></tr>";
    
    $count = 1;
    $total_withdrawn = 0;
    foreach($filtered_withdrawals as $withdrawal) {
        $row_class = ($count % 2 == 0) ? "class='even'" : "";
        $status_class = ($withdrawal['status'] == 'مكتمل') ? "class='status-completed'" : "class='status-pending'";
        
        echo "<tr $row_class>";
        echo "<td>" . $count . "</td>";
        echo "<td style='color: #28a745; font-weight: bold;'>" . number_format($withdrawal['amount'], 2) . " د.ل</td>";
        echo "<td $status_class>" . $withdrawal['status'] . "</td>";
        echo "<td>" . ($withdrawal['payment_method'] ?? '-') . "</td>";
        echo "<td>" . date('Y-m-d H:i', strtotime($withdrawal['created_at'])) . "</td>";
        echo "<td>" . ($withdrawal['notes'] ?? '-') . "</td>";
        echo "</tr>";
        
        if ($withdrawal['status'] == 'مكتمل') {
            $total_withdrawn += $withdrawal['amount'];
        }
        $count++;
    }
    
    echo "<tr><td colspan='6' class='total'>إجمالي المسحوب: " . number_format($total_withdrawn, 2) . " د.ل</td></tr>";
    echo "</table>";
}

function getPeriodName($period) {
    switch($period) {
        case 'today': return 'اليوم';
        case 'week': return 'أسبوع';
        case 'month': return 'شهر';
        case '3months': return '3 أشهر';
        case '6months': return '6 أشهر';
        case 'year': return 'سنة';
        default: return 'الكل';
    }
}
?>
