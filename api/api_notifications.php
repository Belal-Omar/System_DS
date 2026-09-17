<?php
error_reporting(0);
include(__DIR__ . '/core/config.php');
session_start();
/** @var mysqli $conn */

header('Content-Type: application/json');

file_put_contents(__DIR__ . '/scratch/session_debug.txt', date('Y-m-d H:i:s') . " - Session: " . json_encode($_SESSION) . "\n", FILE_APPEND);

// Determine user type and id
$recipient_type = '';
$recipient_id = 0;

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $role = $_SESSION['admin_role'] ?? 'admin';
    if ($role === 'shipping_company') {
        $recipient_type = 'shipping_company';
    } else {
        $recipient_type = 'admin';
    }
    $recipient_id = (int)($_SESSION['admin_id'] ?? 0);
} elseif (isset($_SESSION['user_id'])) {
    $recipient_type = 'user';
    $recipient_id = (int)($_SESSION['user_id']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'fetch';

if ($action === 'fetch') {
    // Fetch notifications logic
    $sql = "SELECT * FROM system_notifications 
            WHERE recipient_type = '$recipient_type' 
            AND (recipient_id = $recipient_id OR recipient_id = 0) 
            ORDER BY created_at DESC 
            LIMIT 50";
    
    $result = $conn->query($sql);
    $notifications = [];
    $unread_count = 0;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
            if ($row['is_read'] == 0) {
                $unread_count++;
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
    exit;
}

if ($action === 'mark_read') {
    $notif_id = (int)($_POST['id'] ?? 0);
    if ($notif_id > 0) {
        $sql = "UPDATE system_notifications 
                SET is_read = 1 
                WHERE id = $notif_id 
                AND recipient_type = '$recipient_type' 
                AND (recipient_id = $recipient_id OR recipient_id = 0)";
        $conn->query($sql);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'mark_all_read') {
    $sql = "UPDATE system_notifications 
            SET is_read = 1 
            WHERE is_read = 0 
            AND recipient_type = '$recipient_type' 
            AND (recipient_id = $recipient_id OR recipient_id = 0)";
    $conn->query($sql);
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
