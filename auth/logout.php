<?php
// ملف: logout.php
include(__DIR__ . '/../core/config.php");
session_start();// حذف بيانات المستخدم فقط والحفاظ على جلسة الإدارة إن وجدت
unset($_SESSION['user_id']);
unset($_SESSION['user_fullname']);
unset($_SESSION['user_email']);
unset($_SESSION['user_phone']);
unset($_SESSION['user_type']);
unset($_SESSION['wallet_balance']);
unset($_SESSION['total_earnings']);
unset($_SESSION['total_withdrawn']);
unset($_SESSION['balance']);
unset($_SESSION['customers_unlocked_week']); // Also lock the customers page

header("Location: login.html");
exit;
?>
