<?php
// get_updated_stock.php
session_start();
include(__DIR__ . '/core/config.php");

// Check if user is logged in and is a merchant
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'تاجر') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// Get the product IDs from the request
$product_ids = json_decode($_POST['product_ids'] ?? '[]', true);

if (empty($product_ids)) {
    echo json_encode(['success' => false, 'message' => 'لا توجد معرفات منتجات']);
    exit;
}

// Prepare the response
$response = [
    'success' => true,
    'stock' => []
];

// Convert all IDs to integers for safety
$product_ids = array_map('intval', $product_ids);
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

// Get the current stock for each product
$query = "SELECT id, stock FROM products WHERE id IN ($placeholders) AND user_id = ?";
$stmt = $conn->prepare($query);

// Create types string (all 'i' for integers)
$types = str_repeat('i', count($product_ids)) . 'i';
$params = array_merge([$types], $product_ids, [$_SESSION['user_id']]);

// Bind parameters
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $response['stock'][$row['id']] = (int)$row['stock'];
}

// Return the response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
