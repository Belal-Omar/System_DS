<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'super_admin';
$_SESSION['admin_logged_in'] = true;
require 'c:\xampp\htdocs\System\api_notifications.php';
