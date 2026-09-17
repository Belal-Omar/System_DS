<?php
// ملف: get_product_images.php
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/core/config.php");

$product_id = intval($_GET['id'] ?? 0);
$response = [];

if ($product_id > 0) {
    $images_result = $conn->query("SELECT * FROM product_images WHERE product_id = $product_id ORDER BY is_main DESC, id ASC");
    $images = [];
    
    while($image = $images_result->fetch_assoc()) {
        $images[] = $image;
    }
    
    $response['success'] = true;
    $response['images'] = $images;
} else {
    $response['success'] = false;
    $response['message'] = 'معرف المنتج غير صالح';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>