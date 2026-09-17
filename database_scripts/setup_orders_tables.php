<?php
// ملف إنشاء جداول أوردرات الدعم الفني
// شغله مرة واحدة فقط!

require_once 'config.php';

// التحقق من الصلاحيات (لو فيه session)
$can_run = true;
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'super_admin') {
    $can_run = false;
}

// لو مش مسموح، نديه warning بس مش نمنعه (عشان التطوير)
if (!$can_run) {
    echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>
        ⚠️ تحذير: أنت مش مدير رئيسي، بس هنكمل عشان التطوير...
    </div>";
}

echo "<div style='font-family: Arial; direction: rtl; padding: 20px;'>";
echo "<h2>🔧 إنشاء جداول أوردرات الدعم الفني</h2>";

// 🔹 SQL لإنشاء الجداول - نسخة مبسطة
$sql_orders_simple = "CREATE TABLE IF NOT EXISTS support_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    sheet_id INT DEFAULT 0,
    support_name VARCHAR(100),
    product_code VARCHAR(50) DEFAULT 'Etala001',
    customer_name VARCHAR(200),
    phone VARCHAR(20),
    address TEXT,
    bundle_type VARCHAR(20) DEFAULT 'single',
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 150.00,
    total_price DECIMAL(10,2),
    order_status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sheet_id (sheet_id)
)";

$sql_stats_simple = "CREATE TABLE IF NOT EXISTS support_daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    stat_date DATE NOT NULL,
    total_orders INT DEFAULT 0,
    confirmed_orders INT DEFAULT 0,
    delivered_orders INT DEFAULT 0,
    cancelled_orders INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    UNIQUE KEY unique_support_date (support_id, stat_date)
)";

// تنفيذ الـ SQL
$errors = [];
$success = [];

// محاولة إنشاء الجداول مع معالجة الأخطاء
@$conn->query($sql_orders_simple);
if ($conn->error) {
    $errors[] = "❌ جدول orders: " . $conn->error;
} else {
    $success[] = "✅ جدول support_orders تم إنشاؤه بنجاح";
}

@$conn->query($sql_stats_simple);
if ($conn->error) {
    $errors[] = "❌ جدول stats: " . $conn->error;
} else {
    $success[] = "✅ جدول support_daily_stats تم إنشاؤه بنجاح";
}

// عرض النتائج
if (count($success) > 0) {
    echo "<div style='background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo implode("<br>", $success);
    echo "</div>";
}

if (count($errors) > 0) {
    echo "<div style='background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo implode("<br>", $errors);
    echo "</div>";
}

// لو معندناش جداول، نعرض زر الإنشاء
if (count($success) == 0 && count($errors) == 0) {
    echo "<form method='POST' style='margin-top: 20px;'>
        <button type='submit' name='create_tables' style='background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;'>
            🔧 إنشاء الجداول الآن
        </button>
    </form>";
}

echo "<br><a href='admin_panel.php?page=supervisor' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>العودة للوحة التحكم</a>";
echo "</div>";

// معالجة الـ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tables'])) {
    echo "<script>window.location.reload();</script>";
}
