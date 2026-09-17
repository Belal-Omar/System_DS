<?php
// ملف: test_login.php
session_start();
include(__DIR__ . '/core/config.php");

// تسجيل الدخول كمستخدم #1
$_SESSION['user_id'] = 2;
$_SESSION['user_fullname'] = 'Belal Medhat';
$_SESSION['user_type'] = 'مسوق';

echo "✅ تم تسجيل الدخول كمستخدم #1 - Belal Medhat<br>";
echo "🎯 الرصيد المتاح: 40.00 د.ل<br>";
echo "⏳ جاري التوجيه إلى صفحة السحب...";

echo "<script>
    setTimeout(function() {
        window.location.href = 'withdrawals.php';
    }, 2000);
</script>";
?>