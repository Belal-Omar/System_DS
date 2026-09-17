<?php
// ملف: update_admin_role_support.php
// تعديل عمود role في جدول admins لإضافة 'support' و 'marketing' + صلاحيات التبويبات

include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

ensure_admin_permissions_schema($conn);

echo "✅ تم تحديث عمود role لدعم: super_admin, admin, support, marketing<br>";
echo "✅ وتم التأكد من وجود عمود allowed_pages لصلاحيات التبويبات";
?>
