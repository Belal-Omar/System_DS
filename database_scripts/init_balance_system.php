<?php
// ملف: init_system.php - لتهيئة النظام بالكامل
include(__DIR__ . '/core/config.php");

echo "<h2>تهيئة نظام العمولات والسحب</h2>";

// إنشاء الجداول المطلوبة
$tables = [
    "CREATE TABLE IF NOT EXISTS marketer_balance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        total_earnings DECIMAL(10,2) DEFAULT 0.00,
        available_balance DECIMAL(10,2) DEFAULT 0.00,
        pending_balance DECIMAL(10,2) DEFAULT 0.00,
        withdrawn_balance DECIMAL(10,2) DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS commission_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        order_id INT DEFAULT 0,
        amount DECIMAL(10,2) NOT NULL,
        type ENUM('order_commission', 'withdrawal', 'adjustment') DEFAULT 'order_commission',
        status ENUM('pending', 'available', 'withdrawn', 'cancelled') DEFAULT 'pending',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $table_sql) {
    if ($conn->query($table_sql) === TRUE) {
        echo "✅ تم إنشاء الجدول بنجاح<br>";
    } else {
        echo "❌ خطأ في إنشاء الجدول: " . $conn->error . "<br>";
    }
}

// تهيئة أرصدة جميع المسوقين
echo "<h3>تهيئة أرصدة المسوقين</h3>";
$users = $conn->query("SELECT id FROM users WHERE user_type = 'مسوق'");
$user_count = 0;

while($user = $users->fetch_assoc()) {
    $user_id = $user['id'];
    
    // حساب الأرباح من الطلبات المكتملة
    $earnings_query = $conn->query("
        SELECT COALESCE(SUM(commission_total), 0) as total 
        FROM orders 
        WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل')
    ");
    $earnings = $earnings_query->fetch_assoc()['total'];
    
    // حساب الطلبات المعلقة
    $pending_query = $conn->query("
        SELECT COALESCE(SUM(commission_total), 0) as total 
        FROM orders 
        WHERE user_id = $user_id AND status NOT IN ('تم التوصيل', 'محصل', 'ملغي', 'مرفوض')
    ");
    $pending = $pending_query->fetch_assoc()['total'];
    
    // حساب المبلغ المسحوب
    $withdrawn_query = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM withdrawals 
        WHERE user_id = $user_id AND status = 'مكتمل'
    ");
    $withdrawn = $withdrawn_query->fetch_assoc()['total'];
    
    // الرصيد المتاح
    $available = $earnings - $withdrawn;
    if ($available < 0) $available = 0;
    
    // إدخال أو تحديث رصيد المستخدم
    $conn->query("
        INSERT INTO marketer_balance (user_id, total_earnings, available_balance, pending_balance, withdrawn_balance) 
        VALUES ($user_id, $earnings, $available, $pending, $withdrawn)
        ON DUPLICATE KEY UPDATE 
        total_earnings = $earnings,
        available_balance = $available,
        pending_balance = $pending,
        withdrawn_balance = $withdrawn
    ");
    
    // إضافة سجل العمولات للطلبات المكتملة
    $completed_orders = $conn->query("
        SELECT id, commission_total FROM orders 
        WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل')
    ");
    
    while($order = $completed_orders->fetch_assoc()) {
        $conn->query("
            INSERT IGNORE INTO commission_history (user_id, order_id, amount, type, status, description)
            VALUES ($user_id, {$order['id']}, {$order['commission_total']}, 'order_commission', 'available', 'عمولة طلب #{$order['id']}')
        ");
    }
    
    $user_count++;
    echo "✅ تم تهيئة رصيد المستخدم #$user_id<br>";
}

echo "<h3>✅ تم الانتهاء من تهيئة النظام!</h3>";
echo "تم تهيئة $user_count مسوق<br>";
echo "<a href='withdrawals.php'>الذهاب إلى صفحة السحب</a>";
?>