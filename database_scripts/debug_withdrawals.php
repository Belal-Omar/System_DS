<?php
// ملف: debug_withdrawals.php
session_start();
include_once 'config.php';
/** @var mysqli $conn */

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>تصحيح البيانات</title>
    <link rel='icon' type='image/png' href='brand_logo.php?f=logo'>
    <link rel='shortcut icon' type='image/png' href='brand_logo.php?f=favicon'>
    <link rel='apple-touch-icon' href='brand_logo.php?f=logo'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-info { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>تصحيح بيانات السحب</h1>";

// فحص الجلسة والمستخدم
echo "<div class='debug-info'>";
echo "<h3>معلومات الجلسة والمستخدم:</h3>";
echo "user_id في الجلسة: " . ($_SESSION['user_id'] ?? 'غير مسجل') . "<br>";

$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($user_query && $user_query->num_rows > 0) {
        $user = $user_query->fetch_assoc();
        echo "المستخدم: {$user['fullname']} (ID: {$user['id']})<br>";
        echo "البريد: {$user['email']}<br>";
        echo "نوع المستخدم: {$user['user_type']}<br>";
    } else {
        echo "<span class='error'>المستخدم غير موجود في قاعدة البيانات!</span><br>";
    }
} else {
    echo "<span class='error'>لم يتم تسجيل الدخول!</span><br>";
}
echo "</div>";

if ($user_id) {
    // فحص الطلبات
    echo "<div class='debug-info'>";
    echo "<h3>فحص الطلبات:</h3>";
    
    $orders_query = $conn->query("SELECT * FROM orders WHERE user_id = $user_id");
    if ($orders_query) {
        echo "عدد الطلبات: " . $orders_query->num_rows . "<br>";
        
        if ($orders_query->num_rows > 0) {
            echo "<table>
                <tr>
                    <th>ID</th>
                    <th>العميل</th>
                    <th>المجموع</th>
                    <th>العمولة</th>
                    <th>الحالة</th>
                    <th>user_id</th>
                </tr>";
            
            $total_commission = 0;
            $available_commission = 0;
            
            while($order = $orders_query->fetch_assoc()) {
                echo "<tr>
                    <td>{$order['id']}</td>
                    <td>{$order['customer_name']}</td>
                    <td>{$order['total']}</td>
                    <td>{$order['commission_total']}</td>
                    <td>{$order['status']}</td>
                    <td>{$order['user_id']}</td>
                </tr>";
                
                $total_commission += $order['commission_total'];
                if (in_array($order['status'], ['تم التوصيل', 'محصل'])) {
                    $available_commission += $order['commission_total'];
                }
            }
            
            echo "</table>";
            echo "إجمالي العمولة: $total_commission د.ل<br>";
            echo "العمولة المتاحة: $available_commission د.ل<br>";
        } else {
            echo "<span class='error'>لا توجد طلبات لهذا المستخدم!</span><br>";
        }
    } else {
        echo "<span class='error'>خطأ في استعلام الطلبات: " . $conn->error . "</span><br>";
    }
    echo "</div>";

    // فحص السحوبات
    echo "<div class='debug-info'>";
    echo "<h3>فحص طلبات السحب:</h3>";
    
    $withdrawals_query = $conn->query("SELECT * FROM withdrawals WHERE user_id = $user_id");
    if ($withdrawals_query) {
        echo "عدد طلبات السحب: " . $withdrawals_query->num_rows . "<br>";
        
        if ($withdrawals_query->num_rows > 0) {
            echo "<table>
                <tr>
                    <th>ID</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                </tr>";
            
            $total_withdrawn = 0;
            
            while($withdrawal = $withdrawals_query->fetch_assoc()) {
                echo "<tr>
                    <td>{$withdrawal['id']}</td>
                    <td>{$withdrawal['amount']}</td>
                    <td>{$withdrawal['status']}</td>
                    <td>{$withdrawal['created_at']}</td>
                </tr>";
                
                if ($withdrawal['status'] == 'مكتمل') {
                    $total_withdrawn += $withdrawal['amount'];
                }
            }
            
            echo "</table>";
            echo "إجمالي المسحوب: $total_withdrawn د.ل<br>";
        } else {
            echo "لا توجد طلبات سحب<br>";
        }
    } else {
        echo "<span class='error'>خطأ في استعلام السحوبات: " . $conn->error . "</span><br>";
    }
    echo "</div>";

    // فحص الاستعلامات المستخدمة في withdrawals.php
    echo "<div class='debug-info'>";
    echo "<h3>فحص الاستعلامات الأساسية:</h3>";
    
    // الاستعلام 1: العمولة المتاحة
    $query1 = "SELECT SUM(commission_total) as total FROM orders WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل')";
    $result1 = $conn->query($query1);
    $available_profit = 0;
    if ($result1) {
        $row1 = $result1->fetch_assoc();
        if ($row1 && isset($row1['total'])) {
            $available_profit = $row1['total'];
        }
    }
    echo "الاستعلام 1: $query1<br>";
    echo "النتيجة: $available_profit د.ل<br><br>";
    
    // الاستعلام 2: المبلغ المسحوب
    $query2 = "SELECT SUM(amount) as total FROM withdrawals WHERE user_id = $user_id AND status = 'مكتمل'";
    $result2 = $conn->query($query2);
    $withdrawn_amount = 0;
    if ($result2) {
        $row2 = $result2->fetch_assoc();
        if ($row2 && isset($row2['total'])) {
            $withdrawn_amount = $row2['total'];
        }
    }
    echo "الاستعلام 2: $query2<br>";
    echo "النتيجة: $withdrawn_amount د.ل<br><br>";
    
    // الاستعلام 3: إجمالي الطلبات
    $query3 = "SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id";
    $result3 = $conn->query($query3);
    $total_orders = 0;
    if ($result3) {
        $row3 = $result3->fetch_assoc();
        if ($row3 && isset($row3['total'])) {
            $total_orders = $row3['total'];
        }
    }
    echo "الاستعلام 3: $query3<br>";
    echo "النتيجة: $total_orders طلب<br>";
    echo "</div>";
}

echo "</body></html>";
