<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'manager';
$_SESSION['admin_logged_in'] = true;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_activity';
require 'c:\xampp\htdocs\System\support_activity_api.php';
