<?php
declare(strict_types=1);

require_once __DIR__ . '/auth2_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth2_json(['success' => false, 'message' => 'Method not allowed'], 405);
}

$db = auth2_db();
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$identifier = strtolower(trim((string) ($input['identifier'] ?? $input['email'] ?? $input['username'] ?? '')));
$password = (string) ($input['password'] ?? '');

if ($identifier === '' || $password === '') {
    auth2_json(['success' => false, 'message' => 'identifier and password are required'], 422);
}

$stmt = $db->prepare('SELECT id, name, email, password_hash, role, twofa_secret FROM auth2_users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    auth2_json(['success' => false, 'message' => 'Invalid credentials'], 401);
}

$tempToken = auth2_sign_jwt([
    'sub' => (int) $user['id'],
    'email' => $user['email'],
    'name' => $user['name'],
    'role' => $user['role'],
    'stage' => 'pending_2fa',
], 900);

auth2_json([
    'success' => true,
    'message' => 'Password verified. Please enter 2FA code.',
    'temp_login_token' => $tempToken,
    'pending_user' => [
        'email' => $user['email'],
        'name' => $user['name'],
    ],
    'next' => 'auth2_verify_2fa_api.php',
    'twofa' => [
        'qr_url' => auth2_qr_url((string) $user['email'], (string) $user['twofa_secret']),
        'otpauth_url' => auth2_otpauth_url((string) $user['email'], (string) $user['twofa_secret']),
    ],
]);
?>
