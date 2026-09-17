<?php
require_once 'config.php';
/** @var mysqli $conn */
session_start();

header('Content-Type: application/json');

// Only allow super_admin, admin, manager
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'delete_order') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id > 0) {
        $stmt = $conn->prepare("DELETE FROM support_orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    }
} elseif ($action === 'delete_table') {
    $sheet_id = $_POST['sheet_id'] ?? '';
    
    // If it's legacy_0, we delete where sheet_id = 0
    if ($sheet_id === 'legacy_0') {
        $sheet_id_val = 0;
    } else {
        $sheet_id_val = (int)$sheet_id;
    }
    
    $stmt = $conn->prepare("DELETE FROM support_orders WHERE sheet_id = ?");
    $stmt->bind_param("i", $sheet_id_val);
    
    if ($stmt->execute()) {
        // Also remove the sheet from support_sheets if it's a real sheet
        if ($sheet_id_val > 0) {
            $conn->query("DELETE FROM support_sheets WHERE id = $sheet_id_val");
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
