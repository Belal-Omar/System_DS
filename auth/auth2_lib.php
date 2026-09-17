<?php
declare(strict_types=1);

require_once __DIR__ . '/auth2_db.php';

const AUTH2_JWT_SECRET = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET_FOR_PRODUCTION';
const AUTH2_JWT_ISSUER = 'system-auth2';

function auth2_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function auth2_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function auth2_b64url_decode(string $data): string
{
    $padding = strlen($data) % 4;
    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'));
}

function auth2_sign_jwt(array $claims, int $ttlSeconds = 3600): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload = array_merge([
        'iss' => AUTH2_JWT_ISSUER,
        'iat' => $now,
        'exp' => $now + $ttlSeconds,
    ], $claims);

    $h = auth2_b64url_encode((string) json_encode($header, JSON_UNESCAPED_UNICODE));
    $p = auth2_b64url_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    $s = hash_hmac('sha256', $h . '.' . $p, AUTH2_JWT_SECRET, true);

    return $h . '.' . $p . '.' . auth2_b64url_encode($s);
}

function auth2_verify_jwt(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    [$h, $p, $s] = $parts;
    $signature = auth2_b64url_decode($s);
    $expected = hash_hmac('sha256', $h . '.' . $p, AUTH2_JWT_SECRET, true);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode(auth2_b64url_decode($p), true);
    if (!is_array($payload)) {
        return null;
    }

    if (!isset($payload['exp']) || time() > (int) $payload['exp']) {
        return null;
    }

    return $payload;
}

function auth2_base32_encode(string $binary): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $out = '';

    $len = strlen($binary);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
    }

    $chunks = str_split($bits, 5);
    foreach ($chunks as $chunk) {
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $out .= $alphabet[bindec($chunk)];
    }

    return $out;
}

function auth2_base32_decode(string $base32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32) ?? '');
    $bits = '';
    $out = '';

    $len = strlen($base32);
    for ($i = 0; $i < $len; $i++) {
        $pos = strpos($alphabet, $base32[$i]);
        if ($pos === false) {
            return '';
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $bytes = str_split($bits, 8);
    foreach ($bytes as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }

    return $out;
}

function auth2_generate_2fa_secret(): string
{
    return auth2_base32_encode(random_bytes(20));
}

function auth2_totp_code(string $secret, ?int $timeSlice = null, string $algo = 'sha1'): string
{
    $timeSlice = $timeSlice ?? (int) floor(time() / 30);
    $key = auth2_base32_decode($secret);
    if ($key === '') {
        return '';
    }

    $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac($algo, $binaryTime, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = substr($hash, $offset, 4);
    $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;

    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function auth2_verify_totp(string $secret, string $code, int $window = 1, int $period = 30, string $algo = 'sha1'): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $slice = (int) floor(time() / $period);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(auth2_totp_code($secret, $slice + $i, $algo), $code)) {
            return true;
        }
    }
    return false;
}

function auth2_normalize_2fa_code(string $code): string
{
    $code = trim($code);
    $code = str_replace([' ', '-', "\t", "\n", "\r"], '', $code);

    $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $easternArabicIndic = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $code = str_replace($arabicIndic, $western, $code);
    $code = str_replace($easternArabicIndic, $western, $code);

    if (preg_match('/^\d{1,6}$/', $code)) {
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    return $code;
}

function auth2_otpauth_url(string $email, string $secret): string
{
    $issuer = 'SystemAuth2';
    $fingerprint = substr($secret, -4);
    $accountLabel = $email . ' [' . $fingerprint . ']';
    $label = rawurlencode($issuer) . ':' . rawurlencode($accountLabel);
    return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

function auth2_qr_url(string $email, string $secret): string
{
    $otpauth = auth2_otpauth_url($email, $secret);
    return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . rawurlencode($otpauth);
}

function auth2_get_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }

    if (!empty($_GET['token'])) {
        return (string) $_GET['token'];
    }

    if (!empty($_COOKIE['auth2_token'])) {
        return (string) $_COOKIE['auth2_token'];
    }

    return null;
}

function auth2_require_auth(array $allowedRoles = []): array
{
    $token = auth2_get_bearer_token();
    if (!$token) {
        auth2_json(['success' => false, 'message' => 'Missing token'], 401);
    }

    $claims = auth2_verify_jwt($token);
    if (!$claims || empty($claims['sub']) || empty($claims['role'])) {
        auth2_json(['success' => false, 'message' => 'Invalid token'], 401);
    }

    if ($allowedRoles && !in_array($claims['role'], $allowedRoles, true)) {
        auth2_json(['success' => false, 'message' => 'Forbidden for this role'], 403);
    }

    return $claims;
}
?>
