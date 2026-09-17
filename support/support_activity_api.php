<?php
// API لتتبع نشاط فريق الدعم الفني والمكالمات
error_reporting(0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// Config and helpers are included above

$action = $_GET['action'] ?? '';
$user_id = (int) ($_SESSION['admin_id'] ?? 0);
$user_name = (string) ($_SESSION['admin_username'] ?? 'unknown');
$role = (string) ($_SESSION['admin_role'] ?? '');

if (is_object($conn) && function_exists('support_calls_sync_schema')) {
    @support_calls_sync_schema($conn);
}

$activity_data = support_activity_load();

function support_activity_key(array $data, int $user_id): string
{
    if (isset($data[$user_id])) {
        return (string) $user_id;
    }
    $s = (string) $user_id;
    return isset($data[$s]) ? $s : $s;
}

if ($action === 'activity' && $role === 'support') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $key = support_activity_key($activity_data, $user_id);
    $now = time();

    if (!isset($activity_data[$key])) {
        $activity_data[$key] = [
            'name' => $user_name,
            'total_active_seconds' => 0,
            'session_seconds' => 0,
            'inactive_seconds' => 0,
            'session_start' => $now,
        ];
    }

    $js_active = (int) ($input['total_active_seconds'] ?? 0);
    $js_session = (int) ($input['session_seconds'] ?? 0);
    $js_inactive = (int) ($input['inactive_seconds'] ?? 0);
    $mouse_active = (bool) ($input['active'] ?? false);

    $activity_data[$key]['name'] = $user_name;
    $activity_data[$key]['total_active_seconds'] = max((int) ($activity_data[$key]['total_active_seconds'] ?? 0), $js_active);
    $activity_data[$key]['session_seconds'] = $js_session;
    $activity_data[$key]['inactive_seconds'] = $js_inactive;
    $activity_data[$key]['is_online'] = true;
    $activity_data[$key]['is_active'] = $mouse_active;
    $activity_data[$key]['status'] = $mouse_active ? 'نشط الآن' : 'متصل (خمول)';
    $activity_data[$key]['last_update'] = $now;
    unset($activity_data[$key]['offline_at']);

    if (!support_activity_save($activity_data)) {
        echo json_encode(['error' => 'Failed to save activity']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'is_online' => true,
        'is_active' => $mouse_active,
        'active_time_formatted' => format_activity_duration($js_active),
        'session_time_formatted' => format_activity_duration($js_session),
    ]);
    exit;
}

if ($action === 'offline' && $role === 'support') {
    support_mark_offline($user_id, $user_name);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'screenshot' && $role === 'support') {
    $screenshot_log = __DIR__ . '/screenshot_attempts.json';
    $logs = [];
    if (file_exists($screenshot_log)) {
        $logs = json_decode((string) file_get_contents($screenshot_log), true) ?: [];
    }
    $logs[] = [
        'user_id' => $user_id,
        'user_name' => $user_name,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'type' => 'screenshot_or_record',
    ];
    file_put_contents($screenshot_log, json_encode($logs, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'call_start' && $role === 'support' && is_object($conn)) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $sheet_id = (int) ($input['sheet_id'] ?? 0);
    $row_index = (int) ($input['sheet_row_index'] ?? -1);
    $client_name = trim((string) ($input['client_name'] ?? ''));
    $client_phone = trim((string) ($input['client_phone'] ?? ''));

    $conn->query("UPDATE support_calls SET status = 'cancelled', ended_at = NOW()
        WHERE support_id = $user_id AND status = 'active'");

    $stmt = $conn->prepare("INSERT INTO support_calls
        (support_id, support_name, sheet_id, sheet_row_index, client_name, client_phone, started_at, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active')");
    if (!$stmt) {
        echo json_encode(['error' => 'prepare failed']);
        exit;
    }
    $stmt->bind_param('isiiss', $user_id, $user_name, $sheet_id, $row_index, $client_name, $client_phone);
    $ok = $stmt->execute();
    $call_id = $ok ? (int) $stmt->insert_id : 0;
    $stmt->close();

    echo json_encode(['success' => $ok, 'call_id' => $call_id, 'started_at' => date('Y-m-d H:i:s')]);
    exit;
}

if ($action === 'call_end' && $role === 'support' && is_object($conn)) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $call_id = (int) ($input['call_id'] ?? 0);
    $duration = max(0, (int) ($input['duration_seconds'] ?? 0));

    $stmt = $conn->prepare("UPDATE support_calls
        SET ended_at = NOW(), duration_seconds = ?, status = 'completed'
        WHERE id = ? AND support_id = ? AND status = 'active'");
    if (!$stmt) {
        echo json_encode(['error' => 'prepare failed']);
        exit;
    }
    $stmt->bind_param('iii', $duration, $call_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => $affected > 0, 'duration_seconds' => $duration]);
    exit;
}

if ($action === 'call_active' && $role === 'support' && is_object($conn)) {
    $row = $conn->query("SELECT id, sheet_id, sheet_row_index, client_name, client_phone, started_at
        FROM support_calls WHERE support_id = $user_id AND status = 'active' ORDER BY id DESC LIMIT 1");
    $call = $row ? $row->fetch_assoc() : null;
    echo json_encode(['success' => true, 'call' => $call ?: null]);
    exit;
}

$can_manage_support = ($role === 'super_admin' || admin_can_access_page('support', $role, $_SESSION['admin_allowed_pages'] ?? ''));

if ($action === 'get_activity' && $can_manage_support) {
    $result = [];
    foreach ($activity_data as $id => $data) {
        $last = normalize_activity_timestamp($data['last_update'] ?? 0);
        $online = support_activity_is_online($data, 90);
        $active_seconds = (int) ($data['total_active_seconds'] ?? 0);
        $session_seconds = (int) ($data['session_seconds'] ?? 0);

        $status_label = 'غير متصل';
        if ($online) {
            $status_label = !empty($data['is_active']) ? 'متصل — نشط' : 'متصل — خمول';
        } elseif ($last > 0) {
            $status_label = 'غير متصل';
        }

        $result[] = [
            'id' => (int) $id,
            'name' => $data['name'] ?? 'unknown',
            'total_active_seconds' => $active_seconds,
            'session_seconds' => $session_seconds,
            'active_time_formatted' => format_activity_duration($active_seconds),
            'session_time_formatted' => format_activity_duration($session_seconds),
            'is_online' => $online,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'status' => $status_label,
            'inactive_seconds' => (int) ($data['inactive_seconds'] ?? 0),
            'last_seen' => $last > 0 ? date('Y-m-d H:i:s', $last) : '—',
            'last_seen_ago' => $last > 0 ? format_activity_duration(time() - $last) : '—',
        ];
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_calls' && $can_manage_support && is_object($conn)) {
    $support_filter = (int) ($_GET['support_id'] ?? 0);
    $limit = min(50, max(5, (int) ($_GET['limit'] ?? 20)));
    $period = (string) ($_GET['period'] ?? 'all');
    $start_date = null;
    switch ($period) {
        case '1day': $start_date = date('Y-m-d', strtotime('-1 day')); break;
        case '1week': $start_date = date('Y-m-d', strtotime('-1 week')); break;
        case '1month': $start_date = date('Y-m-d', strtotime('-1 month')); break;
        case '3months': $start_date = date('Y-m-d', strtotime('-3 months')); break;
        case '6months': $start_date = date('Y-m-d', strtotime('-6 months')); break;
        case '9months': $start_date = date('Y-m-d', strtotime('-9 months')); break;
        case '1year': $start_date = date('Y-m-d', strtotime('-1 year')); break;
    }

    $calls = support_get_calls_list($conn, $support_filter > 0 ? $support_filter : 0, $start_date, $limit);
    if ($support_filter <= 0) {
        $where = '1=1';
        if ($start_date) {
            $esc = $conn->real_escape_string($start_date);
            $where .= " AND started_at >= '$esc'";
        }
        $q = $conn->query("SELECT * FROM support_calls WHERE $where ORDER BY id DESC LIMIT $limit");
        $calls = [];
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $secs = (int) ($row['duration_seconds'] ?? 0);
                if (($row['status'] ?? '') === 'active' && !empty($row['started_at'])) {
                    $started = strtotime((string) $row['started_at']);
                    if ($started > 0) {
                        $secs = max(0, time() - $started);
                    }
                }
                $row['duration_seconds'] = $secs;
                $row['duration_formatted'] = format_activity_duration($secs);
                $calls[] = $row;
            }
        }
    }
    echo json_encode($calls, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'my_calls' && $role === 'support' && is_object($conn)) {
    $q = $conn->query("SELECT * FROM support_calls WHERE support_id = $user_id ORDER BY id DESC LIMIT 30");
    $calls = [];
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $row['duration_formatted'] = format_activity_duration((int) ($row['duration_seconds'] ?? 0));
            $calls[] = $row;
        }
    }
    echo json_encode($calls, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_screenshots' && $can_manage_support) {
    $screenshot_log = __DIR__ . '/screenshot_attempts.json';
    $logs = [];
    if (file_exists($screenshot_log)) {
        $logs = json_decode((string) file_get_contents($screenshot_log), true) ?: [];
    }
    echo json_encode(array_slice(array_reverse($logs), 0, 15), JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'invalid action']);
