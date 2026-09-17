<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$product_code = $_POST['product_code'] ?? '';
$link = $_POST['link'] ?? '';

if (!$product_code) {
    echo json_encode(['success' => false, 'error' => 'Product code missing']);
    exit;
}

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("UPDATE products SET creative_link = ? WHERE code = ? OR name = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed (DB schema issue, run setup_marketing_db_final.php)']);
    exit;
}
$stmt->bind_param("sss", $link, $product_code, $product_code);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'DB error']);
}
