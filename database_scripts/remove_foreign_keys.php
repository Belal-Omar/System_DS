<?php
// ملف: remove_foreign_keys.php - تشغيله مرة واحدة فقط
include(__DIR__ . '/core/config.php");

echo "<h2>إزالة المفاتيح الخارجية المؤقتة</h2>";

// إزالة المفاتيح الخارجية من الجداول
$foreign_keys = [
    "ALTER TABLE marketer_balance DROP FOREIGN KEY marketer_balance_ibfk_1",
    "ALTER TABLE commission_history DROP FOREIGN KEY commission_history_ibfk_1"
];

foreach ($foreign_keys as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ تم إزالة المفتاح الخارجي بنجاح<br>";
    } else {
        echo "⚠️ لم يتم إزالة المفتاح (قد يكون غير موجود): " . $conn->error . "<br>";
    }
}

echo "<h3>✅ تم الانتهاء!</h3>";
echo "المفاتيح الخارجية تمت إزالتها. النظام جاهز للعمل.";
?>