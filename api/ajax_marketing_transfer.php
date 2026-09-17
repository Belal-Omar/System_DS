<?php
require 'config.php';
/** @var mysqli $conn */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
if (!in_array($_SESSION['admin_role'], ['super', 'manager', 'marketing_main'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marketer_code = $_POST['marketer_code'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';

    if (empty($marketer_code) || $amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO marketing_budgets (marketer_code, amount, transfer_date, notes) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sdss", $marketer_code, $amount, $date, $notes);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}
