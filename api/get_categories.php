<?php
header('Content-Type: application/json');
include(__DIR__ . '/core/config.php");

try {
    // Get all unique categories from products table
    $result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = [];
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }
    
    echo json_encode($categories);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في جلب الفئات']);
}

$conn->close();
?>
