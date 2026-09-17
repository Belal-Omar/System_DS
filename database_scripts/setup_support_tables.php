<?php
// ملف: setup_support_tables.php
// إعداد جداول إحصائيات الدعم الفني والشيتات

include(__DIR__ . '/core/config.php");
/** @var mysqli $conn */

echo "<h2>إعداد جداول نظام الدعم الفني</h2>";

// جدول إحصائيات الدعم
$sql1 = "CREATE TABLE IF NOT EXISTS support_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL UNIQUE,
    confirmation_rate DECIMAL(5,2) DEFAULT 0,
    delivery_rate DECIMAL(5,2) DEFAULT 0,
    cancellation_rate DECIMAL(5,2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    completed_orders INT DEFAULT 0,
    cancelled_orders INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (support_id) REFERENCES admins(id) ON DELETE CASCADE
)";

if ($conn->query($sql1) === TRUE) {
    echo "✅ تم إنشاء جدول support_stats بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول support_stats: " . $conn->error . "<br>";
}

// جدول الشيتات
$sql2 = "CREATE TABLE IF NOT EXISTS support_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    support_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    sheet_name VARCHAR(255) DEFAULT 'شيت',
    uploaded_by INT NOT NULL,
    names_count INT DEFAULT 0,
    call_duration INT DEFAULT 0,
    is_unlocked TINYINT(1) DEFAULT 0,
    unlock_request TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (support_id) REFERENCES admins(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE CASCADE
)";

if ($conn->query($sql2) === TRUE) {
    echo "✅ تم إنشاء/تحديث جدول support_sheets بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء جدول support_sheets: " . $conn->error . "<br>";
}

// التأكد من وجود الأعمدة الجديدة للقفل
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS names_count INT DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS call_duration INT DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS is_unlocked TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS unlock_request TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS force_locked TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE support_sheets ADD COLUMN IF NOT EXISTS unlocked_at DATETIME NULL");

echo "✅ تم التحقق من أعمدة support_sheets<br>";

// تعديل عمود role في جدول admins لإضافة 'support'
$sql3 = "ALTER TABLE admins MODIFY COLUMN role enum('super_admin','admin','support') DEFAULT 'admin'";

if ($conn->query($sql3) === TRUE) {
    echo "✅ تم تعديل عمود role لدعم 'support' بنجاح<br>";
} else {
    echo "❌ خطأ في تعديل عمود role: " . $conn->error . "<br>";
}

// إنشاء مجلد uploads
$upload_dir = 'uploads/support_sheets/';
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ تم إنشاء مجلد uploads/support_sheets/ بنجاح<br>";
    } else {
        echo "❌ فشل في إنشاء مجلد uploads/support_sheets/<br>";
    }
} else {
    echo "✅ مجلد uploads/support_sheets/ موجود بالفعل<br>";
}

echo "<hr><p><a href='admin_panel.php?page=supervisor' class='bg-blue-500 text-white px-4 py-2 rounded'>الذهاب إلى صفحة المتابعة</a></p>";
?>
