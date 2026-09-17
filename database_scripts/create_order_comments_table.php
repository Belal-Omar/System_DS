<?php
// إنشاء جدول التعليقات على الطلبات
include(__DIR__ . '/core/config.php");

// إنشاء الجدول
$sql = "CREATE TABLE IF NOT EXISTS order_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    admin_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<h2 style='color: green;'>✅ تم إنشاء جدول order_comments بنجاح!</h2>";
    echo "<p>يمكنك الآن استخدام نظام التعليقات على الطلبات.</p>";
} else {
    echo "<h2 style='color: red;'>❌ حدث خطأ: " . $conn->error . "</h2>";
}

// التحقق من وجود الجدول
$result = $conn->query("SHOW TABLES LIKE 'order_comments'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ الجدول موجود في قاعدة البيانات</p>";
    
    // عرض هيكل الجدول
    $structure = $conn->query("DESCRIBE order_comments");
    echo "<h3>هيكل الجدول:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr><th>العمود</th><th>النوع</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
