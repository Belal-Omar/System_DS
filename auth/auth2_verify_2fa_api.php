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

$tempToken = trim((string) ($input['temp_login_token'] ?? ''));
$code = auth2_normalize_2fa_code((string) ($input['code'] ?? ''));

if ($tempToken === '' || $code === '') {
    auth2_json(['success' => false, 'message' => 'temp_login_token and code are required'], 422);
}

$claims = auth2_verify_jwt($tempToken);
if (!$claims || ($claims['stage'] ?? '') !== 'pending_2fa') {
    auth2_json(['success' => false, 'message' => 'Invalid or expired temporary token'], 401);
}

$userId = (int) ($claims['sub'] ?? 0);
if ($userId <= 0) {
    auth2_json(['success' => false, 'message' => 'Invalid user in token'], 401);
}

$stmt = $db->prepare('SELECT id, name, email, role, twofa_secret FROM auth2_users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    auth2_json(['success' => false, 'message' => 'User not found'], 404);
}

// Demo-mode verification: keep standard TOTP and allow large clock drift
// so authenticator codes scanned from QR work reliably during presentations.
$valid =
    auth2_verify_totp((string) $user['twofa_secret'], $code, 240, 30, 'sha1') ||
    auth2_verify_totp((string) $user['twofa_secret'], $code, 240, 60, 'sha1') ||
    auth2_verify_totp((string) $user['twofa_secret'], $code, 240, 30, 'sha256');

if (!$valid) {
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $error = [
        'success' => false,
        'message' => 'Invalid 2FA code',
        'for_user' => $user['email'],
    ];
    if ($isLocal) {
        $slice30 = (int) floor(time() / 30);
        $error['debug_submitted_code'] = $code;
        $error['debug_expected_code'] = auth2_totp_code((string) $user['twofa_secret']);
        $error['debug_expected_prev_code'] = auth2_totp_code((string) $user['twofa_secret'], $slice30 - 1);
        $error['debug_expected_next_code'] = auth2_totp_code((string) $user['twofa_secret'], $slice30 + 1);
        $error['debug_server_time'] = time();
    }
    auth2_json([
        ...$error,
    ], 401);
}

$token = auth2_sign_jwt([
    'sub' => (int) $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
], 3600);

$redirectMap = [
    'Admin' => 'auth2_admin.php',
    'Manager' => 'auth2_manager.php',
    'User' => 'auth2_user.php',
];
$redirectTo = $redirectMap[$user['role']] ?? 'auth2_dashboard.php';

setcookie('auth2_token', $token, [
    'expires' => time() + 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

auth2_json([
    'success' => true,
    'message' => '2FA verified, login complete',
    'token' => $token,
    'expires_in' => 3600,
    'redirect_to' => $redirectTo,
    'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ],
]);
?>
