<?php
declare(strict_types=1);

require_once __DIR__ . '/auth2_lib.php';

$email = $_GET['email'] ?? '';
if ($email === '') {
    auth2_json(['success' => false, 'message' => 'email is required'], 422);
}

$db = auth2_db();
$stmt = $db->prepare('SELECT id, email, role, twofa_secret FROM auth2_users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    auth2_json(['success' => false, 'message' => 'user not found'], 404);
}

$secret = (string) $user['twofa_secret'];
$serverCode = auth2_totp_code($secret);
$valid = auth2_verify_totp($secret, $serverCode, 10);

auth2_json([
    'success' => true,
    'email' => $user['email'],
    'role' => $user['role'],
    'server_generated_code' => $serverCode,
    'server_code_validates' => $valid,
    'time' => time(),
]);
?>
