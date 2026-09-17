<?php
declare(strict_types=1);

require_once __DIR__ . '/auth2_lib.php';

$route = trim((string) ($_GET['route'] ?? 'dashboard'));

switch ($route) {
    case 'admin':
        $claims = auth2_require_auth(['Admin']);
        break;
    case 'manager':
        $claims = auth2_require_auth(['Manager']);
        break;
    case 'user':
    case 'profile':
        $claims = auth2_require_auth(['User']);
        break;
    case 'dashboard':
    default:
        $claims = auth2_require_auth(['Admin', 'Manager', 'User']);
        break;
}

auth2_json([
    'success' => true,
    'route' => $route,
    'message' => "Access granted to {$route}",
    'claims' => $claims,
]);
?>
