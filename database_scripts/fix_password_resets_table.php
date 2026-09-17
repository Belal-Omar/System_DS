<?php
// ملف: fix_password_resets_table.php
// إصلاح جدول password_resets
include(__DIR__ . '/core/config.php");

echo "<h2>إصلاح جدول password_resets</h2>";

// حذف الجدول القديم إذا كان موجوداً
$drop_result = $conn->query("DROP TABLE IF EXISTS password_resets");
if ($drop_result) {
    echo "<p style='color: green;'>✓ تم حذف الجدول القديم (إن وجد)</p>";
}

// إنشاء الجدول الجديد بدون FOREIGN KEY
$create_result = $conn->query("CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    used TINYINT(1) DEFAULT 0,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($create_result) {
    echo "<p style='color: green;'>✓ تم إنشاء جدول password_resets بنجاح</p>";
    echo "<p style='color: green; margin-top: 20px;'><strong>✓ تم إصلاح الجدول بنجاح!</strong></p>";
    echo "<p><a href='forgot_password.php'>العودة إلى صفحة نسيان كلمة المرور</a></p>";
} else {
    echo "<p style='color: red;'>✗ خطأ في إنشاء الجدول: " . $conn->error . "</p>";
}
?>

