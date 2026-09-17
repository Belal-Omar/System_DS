<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$_SESSION = ['admin_logged_in' => true, 'admin_role' => 'super_admin'];
$_GET['page'] = 'accounts';
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
try {
    include 'admin_panel.php';
} catch (Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
}
$output = ob_get_clean();

if (empty(trim($output))) {
    echo "OUTPUT IS EMPTY!";
} else {
    echo "OUTPUT STARTS WITH: \n" . substr($output, 0, 500);
    echo "\n\nAND ENDS WITH: \n" . substr($output, -500);
}
