<?php
declare(strict_types=1);

function auth2_db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $host = getenv('AUTH2_DB_HOST') ?: '127.0.0.1';
    $user = getenv('AUTH2_DB_USER') ?: 'root';
    $pass = getenv('AUTH2_DB_PASS') ?: '';
    $name = getenv('AUTH2_DB_NAME') ?: 'system';
    $port = (int) (getenv('AUTH2_DB_PORT') ?: 3306);

    $db = @new mysqli($host, $user, $pass, $name, $port);
    if ($db->connect_errno) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'details' => $db->connect_error,
        ]);
        exit;
    }

    $db->set_charset('utf8mb4');
    return $db;
}
?>
