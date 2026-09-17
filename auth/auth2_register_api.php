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

$name = trim((string) ($input['name'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? $input['username'] ?? '')));
$password = (string) ($input['password'] ?? '');
$role = trim((string) ($input['role'] ?? ''));
$allowedRoles = ['Admin', 'Manager', 'User'];

if ($name === '' || $email === '' || $password === '' || $role === '') {
    auth2_json(['success' => false, 'message' => 'name, email/username, password, role are required'], 422);
}

if (!in_array($role, $allowedRoles, true)) {
    auth2_json(['success' => false, 'message' => 'Invalid role. Use Admin, Manager, or User'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth2_json(['success' => false, 'message' => 'Invalid email format'], 422);
}

$check = $db->prepare('SELECT id FROM auth2_users WHERE email = ? LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
$exists = $check->get_result();
if ($exists && $exists->num_rows > 0) {
    auth2_json(['success' => false, 'message' => 'Email already exists'], 409);
}
$check->close();

$hash = password_hash($password, PASSWORD_DEFAULT);
$secret = auth2_generate_2fa_secret();
$otpauthUrl = auth2_otpauth_url($email, $secret);
$qrUrl = auth2_qr_url($email, $secret);

$stmt = $db->prepare('INSERT INTO auth2_users (name, email, password_hash, role, twofa_secret) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $name, $email, $hash, $role, $secret);

if (!$stmt->execute()) {
    auth2_json(['success' => false, 'message' => 'Registration failed', 'details' => $stmt->error], 500);
}

auth2_json([
    'success' => true,
    'message' => 'User registered. Scan QR for 2FA setup.',
    'user' => [
        'id' => $stmt->insert_id,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ],
    'twofa' => [
        'otpauth_url' => $otpauthUrl,
        'qr_url' => $qrUrl,
    ],
], 201);
?>
