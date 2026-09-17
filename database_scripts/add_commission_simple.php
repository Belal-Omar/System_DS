<?php
// ملف: add_commission_simple.php
// إضافة العمولة للطلب مباشرة

// الاتصال المباشر بقاعدة البيانات
$servername = "127.0.0.1";
$username = "u497700233_medhatomar5555"; 
$password = "BelalOmar49988155$";
$dbname = "u497700233_System";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// إنشاء جدول العمولات إذا لم يكن موجود
$conn->query("CREATE TABLE IF NOT EXISTS marketer_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    commission_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('قيد المراجعة', 'مكتمل', 'ملغي') DEFAULT 'مكتمل',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// إضافة عمود total_commissions إذا لم يكن موجود
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS total_commissions DECIMAL(10, 2) DEFAULT 0.00");

// إضافة العمولات لجميع الطلبات المكتملة
$sql = "
    INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status)
    SELECT o.user_id, o.id, o.commission_total, 'مكتمل'
    FROM orders o
    WHERE o.status IN ('تم التوصيل', 'محصل', 'مكتمل') 
    AND o.user_id IS NOT NULL 
    AND o.commission_total > 0
    AND o.user_id IN (SELECT id FROM users WHERE user_type = 'مسوق')
    AND NOT EXISTS (SELECT 1 FROM marketer_commissions mc WHERE mc.order_id = o.id)
";

if ($conn->query($sql)) {
    $affected_rows = $conn->affected_rows;
    echo "تم إضافة $affected_rows عمولة جديدة<br>";
    
    // تحديث إجمالي العمولات للمسوقين
    $update_sql = "
        UPDATE users u 
        SET total_commissions = (
            SELECT COALESCE(SUM(mc.commission_amount), 0)
            FROM marketer_commissions mc
            WHERE mc.user_id = u.id AND mc.status = 'مكتمل'
        )
        WHERE u.user_type = 'مسوق'
    ";
    
    if ($conn->query($update_sql)) {
        echo "تم تحديث إجمالي العمولات للمسوقين<br>";
    }
    
    // عرض الإجماليات
    $result = $conn->query("
        SELECT COUNT(*) as total_orders, SUM(commission_total) as total_amount
        FROM orders 
        WHERE status IN ('تم التوصيل', 'محصل', 'مكتمل') 
        AND user_id IN (SELECT id FROM users WHERE user_type = 'مسوق')
    ");
    
    if ($row = $result->fetch_assoc()) {
        echo "إجمالي الطلبات المكتملة: " . $row['total_orders'] . "<br>";
        echo "إجمالي العمولات: " . number_format($row['total_amount'], 2) . " د.ل<br>";
    }
    
    echo "<br><strong>تمت العملية بنجاح!</strong> العمولات متاحة الآن في صفحة السحب.";
    
} else {
    echo "خطأ: " . $conn->error;
}

$conn->close();
?>
