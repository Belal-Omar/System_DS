<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// الاتصال المباشر بقاعدة البيانات (نفس بيانات get_cities_api.php)
$servername = "127.0.0.1";
$username = "u497700233_medhatomar5555"; 
$password = "BelalOmar49988155$";
$dbname = "u497700233_System";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        "success" => false, 
        "message" => "فشل الاتصال بقاعدة البيانات: " . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn->set_charset("utf8mb4");

// بدء الجلسة للحصول على user_id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

$response = ['success' => false, 'orders' => [], 'message' => ''];

if (!$user_id) {
    echo json_encode([
        'success' => true,
        'orders' => [],
        'message' => 'يرجى تسجيل الدخول لعرض طلباتك'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $user_id = intval($user_id);
    
    // استعلام مباشر لجلب الطلبات مع بيانات الشحن لمنتسب معين
    $result = $conn->query("
        SELECT 
            o.id,
            o.customer_name as name,
            o.customer_phone as phone,
            o.region,
            o.address,
            o.shipping_city_id,
            o.shipping_city_name,
            o.shipping_cost,
            o.total,
            o.commission_total,
            o.total_special_commission,
            o.status,
            o.created_at as date,
            o.cancellation_reason,
            o.return_reason,
            o.reason_type,
            p.name as product_name,
            oi.quantity,
            oi.color,
            oi.size,
            oi.original_price,
            oi.price as final_price,
            oi.commission,
            pi.image_path as image
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
        WHERE o.user_id = $user_id
        ORDER BY o.created_at DESC
    ");

    if (!$result) {
        throw new Exception("خطأ في استعلام الطلبات: " . $conn->error);
    }

    $orders = [];
    $current_order_id = null;
    $current_order = null;

    while($row = $result->fetch_assoc()) {
        if ($row['id'] !== $current_order_id) {
            if ($current_order) {
                $orders[] = $current_order;
            }
            
            $current_order_id = $row['id'];
            $current_order = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'phone' => $row['phone'],
                'region' => $row['region'],
                'address' => $row['address'],
                'shipping_city_id' => $row['shipping_city_id'],
                'shipping_city_name' => $row['shipping_city_name'] ?: '---',
                'shipping_cost' => floatval($row['shipping_cost']),
                'total' => floatval($row['total']),
                'commission_total' => floatval($row['commission_total']),
                'total_special_commission' => floatval($row['total_special_commission']),
                'status' => $row['status'],
                'date' => $row['date'],
                'cancellation_reason' => $row['cancellation_reason'],
                'return_reason' => $row['return_reason'],
                'reason_type' => $row['reason_type'],
                'items' => []
            ];
        }
        
        if ($row['product_name']) {
            $current_order['items'][] = [
                'name' => $row['product_name'],
                'quantity' => intval($row['quantity']),
                'color' => $row['color'],
                'size' => $row['size'],
                'original_price' => floatval($row['original_price']),
                'price' => floatval($row['final_price']),
                'commission' => floatval($row['commission']),
                'image' => $row['image'] ?: 'imgs/default-product.jpg'
            ];
        }
    }

    if ($current_order) {
        $orders[] = $current_order;
    }

    $response['orders'] = $orders;
    $response['success'] = true;
    $response['message'] = 'تم جلب ' . count($orders) . ' طلب بنجاح';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
$conn->close();
?>
