<?php
// ملف: force_update_balance.php
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/balance_system.php");

$balanceSystem = new BalanceSystem($conn);

echo "<h2>🔄 تحديث قسري للأرصدة</h2>";

// تحديث رصيد المستخدم #1
$new_balance = $balanceSystem->refreshUserBalance(1);

echo "<h3>الرصيد الجديد للمستخدم #1:</h3>";
echo "- إجمالي الأرباح (صافي الربح): " . $new_balance['total_earnings'] . " د.ل<br>";
echo "- المتاح للسحب: " . $new_balance['available_balance'] . " د.ل<br>";
echo "- الأرباح المعلقة: " . $new_balance['pending_balance'] . " د.ل<br>";
echo "- تم السحب: " . $new_balance['withdrawn_balance'] . " د.ل<br>";

echo "<h3>✅ تم التحديث بنجاح!</h3>";
echo "<a href='withdrawals.php' style='font-size: 18px; color: green; font-weight: bold;'>
        🚀 انتقل إلى صفحة السحب الآن
      </a>";
?>