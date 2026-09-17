<?php
require 'config.php';
session_start();
/** @var mysqli $conn */

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$date = $_POST['date'] ?? '';
$spends = json_decode($_POST['spends'] ?? '[]', true);

if (empty($date) || !is_array($spends)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data provided']);
    exit;
}

$success_count = 0;
$conn->begin_transaction();
try {
    foreach ($spends as $spend) {
        $product_code = $conn->real_escape_string($spend['product_code'] ?? '');
        $cost = (float)($spend['cost'] ?? 0);
        
        $marketer_code = $conn->real_escape_string($spend['marketer_code'] ?? '');
        
        if (empty($product_code) || empty($marketer_code)) continue;
        
        $check = $conn->prepare("SELECT id FROM support_daily_leads WHERE date = ? AND product_code = ? AND marketer_code = ? LIMIT 1");
        $check->bind_param("sss", $date, $product_code, $marketer_code);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE support_daily_leads SET lead_cost = ? WHERE date = ? AND product_code = ? AND marketer_code = ?");
            $stmt->bind_param("dsss", $cost, $date, $product_code, $marketer_code);
        } else {
            $stmt = $conn->prepare("INSERT INTO support_daily_leads (date, product_code, marketer_code, lead_cost) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssd", $date, $product_code, $marketer_code, $cost);
        }
        
        if ($stmt) {
            $stmt->execute();
            $success_count++;
        }
    }
    $conn->commit();
    echo json_encode(['success' => true, 'count' => $success_count]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
