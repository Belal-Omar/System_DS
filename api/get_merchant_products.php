<?php
// ملف: get_merchant_products.php - جلب منتجات التاجر
header('Content-Type: application/json; charset=utf-8');
session_start();
include(__DIR__ . '/core/config.php");

$response = ['success' => false, 'products' => [], 'stats' => []];

try {
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception("يجب تسجيل الدخول");
    }
    
    $user_id = (int)$user_id;
    
    // جلب منتجات التاجر فقط
    $products_result = $conn->query("
        SELECT p.*, pi.image_path 
        FROM products p 
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
        WHERE p.user_id = $user_id
        ORDER BY p.created_at DESC
    ");
    
    $products = [];
    while($product = $products_result->fetch_assoc()) {
        if (!$product['image_path']) {
            $first_image_result = $conn->query("
                SELECT image_path FROM product_images 
                WHERE product_id = {$product['id']} 
                LIMIT 1
            ");
            if ($first_image_result && $first_image_result->num_rows > 0) {
                $first_image = $first_image_result->fetch_assoc();
                $product['image_path'] = $first_image['image_path'];
            }
        }
        // حساب السعر للتاجر (ناقص العمولة)
        $product['merchant_price'] = floatval($product['price']) - floatval($product['commission']);
        $products[] = $product;
    }
    
    // التحقق من وجود عمود status
    $check_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
    $has_status_column = ($check_status && $check_status->num_rows > 0);
    
    // الإحصائيات
    $total = count($products);
    if ($has_status_column) {
        $active = count(array_filter($products, function($p) { return ($p['status'] ?? 'active') == 'active'; }));
    } else {
        $active = count(array_filter($products, function($p) { return ($p['stock'] ?? 0) > 0; }));
    }
    $inactive = $total - $active;
    
    $response['success'] = true;
    $response['products'] = $products;
    $response['stats'] = [
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>

