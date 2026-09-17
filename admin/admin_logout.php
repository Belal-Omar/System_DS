<?php
// ملف: admin_logout.php
include(__DIR__ . '/core/config.php");
session_start();
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'support' && !empty($_SESSION['admin_id'])) {
    require_once __DIR__ . '/helpers.php';
    support_mark_offline((int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? ''));
}

session_destroy();
header('Location: admin_login.php');
exit;
