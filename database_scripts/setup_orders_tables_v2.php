<?php
// ملف تحديث جداول الأوردرات بالأعمدة الجديدة
require_once 'config.php';

echo "<div style='font-family: Arial; direction: rtl; padding: 20px;'>";
echo "<h2>🔧 تحديث جداول الأوردرات</h2>";

// التحقق من وجود الأعمدة الجديدة
$columns_to_add = [
    'sheet_id' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS sheet_id INT NOT NULL DEFAULT 0",
    'order_date' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS order_date DATE DEFAULT NULL",
    'recipient_name' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(200) AFTER customer_name",
    'notes' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS notes TEXT",
    'governorate' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS governorate VARCHAR(100)",
    'pieces' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS pieces INT DEFAULT 1",
    'customer_status' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS customer_status VARCHAR(50) DEFAULT 'new'",
    'agent_code' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS agent_code VARCHAR(50)",
    'campaign' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS campaign VARCHAR(100)",
    'version' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS version VARCHAR(50)",
    'article' => "ALTER TABLE support_orders ADD COLUMN IF NOT EXISTS article VARCHAR(100)"
];

$success = [];
$errors = [];

foreach ($columns_to_add as $column => $sql) {
    @$conn->query($sql);
    if ($conn->error) {
        // Column might already exist
        $errors[] = "⚠️ $column: " . $conn->error;
    } else {
        $success[] = "✅ العمود $column تم إضافته/التحقق منه";
    }
}

echo "<div style='background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo implode("<br>", $success);
echo "</div>";

if (count($errors) > 0) {
    echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "ملاحظات (قد تكون الأعمدة موجودة):<br>";
    echo implode("<br>", $errors);
    echo "</div>";
}

echo "<br><a href='admin_panel.php?page=support' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>العودة للدعم الفني</a>";
echo "</div>";
