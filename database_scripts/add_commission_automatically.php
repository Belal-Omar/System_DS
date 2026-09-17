<?php
// ملف: add_commission_automatically.php
// لإضافة العمولة تلقائياً عند تغيير حالة الطلب إلى "تم التوصيل"

// الاتصال المباشر بقاعدة البيانات
$servername = "127.0.0.1";
$username = "u497700233_medhatomar5555"; 
$password = "BelalOmar49988155$";
$dbname = "u497700233_System";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// إنشاء جدول العمولات إذا لم يكن موجود
$create_commissions_table = "
CREATE TABLE IF NOT EXISTS marketer_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    commission_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('قيد المراجعة', 'مكتمل', 'ملغي') DEFAULT 'مكتمل',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if (!$conn->query($create_commissions_table)) {
    die("فشل في إنشاء جدول marketer_commissions: " . $conn->error);
}

// إضافة عمود total_commissions لجدول users إذا لم يكن موجود
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'total_commissions'");
if ($check_column->num_rows == 0) {
    $add_column = "ALTER TABLE users ADD COLUMN total_commissions DECIMAL(10, 2) DEFAULT 0.00";
    if (!$conn->query($add_column)) {
        die("فشل في إضافة عمود total_commissions: " . $conn->error);
    }
}

echo "تم إنشاء الجداول والأعمدة بنجاح<br>";

// جلب جميع الطلبات المكتملة التي لم تتم إضافة عمولاتها بعد
$completed_orders_query = "
    SELECT o.*, u.user_type
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status IN ('تم التوصيل', 'محصل', 'مكتمل') 
    AND o.user_id IS NOT NULL 
    AND o.commission_total > 0
    AND o.user_id IN (SELECT id FROM users WHERE user_type = 'مسوق')
    AND NOT EXISTS (
        SELECT 1 FROM marketer_commissions mc 
        WHERE mc.order_id = o.id
    )
";

$result = $conn->query($completed_orders_query);
if (!$result) {
    die("فشل في جلب الطلبات: " . $conn->error);
}

$added_count = 0;
$total_commission_amount = 0;

if ($result->num_rows > 0) {
    $conn->begin_transaction();
    
    try {
        while ($order = $result->fetch_assoc()) {
            $user_id = $order['user_id'];
            $order_id = $order['id'];
            $commission_amount = $order['commission_total'];
            
            // إضافة سجل عمولة جديد
            $insert_commission = $conn->prepare("
                INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status, created_at) 
                VALUES (?, ?, ?, 'مكتمل', NOW())
            ");
            $insert_commission->bind_param("iid", $user_id, $order_id, $commission_amount);
            
            if (!$insert_commission->execute()) {
                throw new Exception("فشل في إضافة عمولة للطلب #" . $order_id . ": " . $insert_commission->error);
            }
            
            // تحديث إجمالي العمولات للمسوق
            $update_user_commission = $conn->prepare("
                UPDATE users SET total_commissions = total_commissions + ? WHERE id = ?
            ");
            $update_user_commission->bind_param("di", $commission_amount, $user_id);
            $update_user_commission->execute();
            
            $added_count++;
            $total_commission_amount += $commission_amount;
            
            echo "تمت إضافة عمولة " . number_format($commission_amount, 2) . " د.ل للطلب #" . $order_id . " للمسوق #" . $user_id . "<br>";
        }
        
        $conn->commit();
        
        echo "<br><br><strong>تمت العملية بنجاح!</strong><br>";
        echo "عدد العمولات المضافة: " . $added_count . "<br>";
        echo "إجمالي مبلغ العمولات: " . number_format($total_commission_amount, 2) . " د.ل<br>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "حدث خطأ: " . $e->getMessage();
    }
} else {
    echo "لا توجد طلبات مكتملة بدون عمولات. كل العمولات محدثة.<br>";
}

// التحقق من الإجماليات الحالية
$check_totals = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(commission_total) as total_commissions
    FROM orders 
    WHERE status IN ('تم التوصيل', 'محصل', 'مكتمل') 
    AND user_id IS NOT NULL 
    AND commission_total > 0
    AND user_id IN (SELECT id FROM users WHERE user_type = 'مسوق')
");

if ($check_totals) {
    $totals = $check_totals->fetch_assoc();
    echo "<br><br><strong>الإجماليات الحالية:</strong><br>";
    echo "إجمالي الطلبات المكتملة: " . $totals['total_orders'] . "<br>";
    echo "إجمالي العمولات: " . number_format($totals['total_commissions'], 2) . " د.ل<br>";
}

$conn->close();
?>
