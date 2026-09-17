<?php
// ملف: get_products.php
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php"); // إضافة ملف المساعدات

// التحقق من تسجيل الدخول للمسوق
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'مسوق') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
    exit;
}

$user_id = $_SESSION['user_id'];

$response = [];

try {
    // إنشاء جدول product_marketers إذا لم يكن موجوداً
    $conn->query("CREATE TABLE IF NOT EXISTS product_marketers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        marketer_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_product_marketer (product_id, marketer_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (marketer_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // التحقق من وجود عمود status
    $check_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
    $has_status_column = ($check_status && $check_status->num_rows > 0);
    
    // جلب المنتجات المخصصة للمسوق الحالي فقط
    if ($has_status_column) {
        $products_result = $conn->query("
            SELECT DISTINCT p.*, pi.image_path 
            FROM products p 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
            LEFT JOIN product_marketers pm ON p.id = pm.product_id 
            WHERE (p.status = 'active' OR p.status IS NULL)
            AND (pm.marketer_id = $user_id OR pm.marketer_id IS NULL AND NOT EXISTS (
                SELECT 1 FROM product_marketers pm2 WHERE pm2.product_id = p.id
            ))
            ORDER BY p.created_at DESC
        ");
    } else {
        $products_result = $conn->query("
            SELECT DISTINCT p.*, pi.image_path 
            FROM products p 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
            LEFT JOIN product_marketers pm ON p.id = pm.product_id 
            WHERE (pm.marketer_id = $user_id OR pm.marketer_id IS NULL AND NOT EXISTS (
                SELECT 1 FROM product_marketers pm2 WHERE pm2.product_id = p.id
            ))
            ORDER BY p.created_at DESC
        ");
    }
    $products = [];
    
    while($product = $products_result->fetch_assoc()) {
        // إذا لم تكن هناك صورة رئيسية، جلب أول صورة
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
        $products[] = $product;
    }
    
    // استخدام الدالة الموحدة للحصول على الإحصائيات
    $stats = get_product_counts($conn);
    
    $response['success'] = true;
    $response['products'] = $products;
    $response['stats'] = $stats;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>