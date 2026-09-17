<?php
declare(strict_types=1);
require_once __DIR__ . '/auth2_lib.php';

$token = auth2_get_bearer_token();
$claims = $token ? auth2_verify_jwt($token) : null;
if (!$claims || ($claims['role'] ?? '') !== 'User') {
    http_response_code(403);
    exit('Access Denied');
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><title>Profile</title>
    <link rel="icon" type="image/png" href="brand_logo.php?f=logo">
    <link rel="shortcut icon" type="image/png" href="brand_logo.php?f=favicon">
    <link rel="apple-touch-icon" href="brand_logo.php?f=logo"></head>
<body>
  <h2>User Profile</h2>
  <p>Name: <?php echo htmlspecialchars((string) ($claims['name'] ?? '')); ?></p>
  <p>Email: <?php echo htmlspecialchars((string) ($claims['email'] ?? '')); ?></p>
</body>
</html>
