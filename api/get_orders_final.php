<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// الاتصال المباشر بقاعدة البيانات
$servername = "localhost";
$username = "root"; 
$password = "BelalOmar499881";
$dbname = "system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        "success" => false, 
        "message" => "فشل الاتصال بقاعدة البيانات"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn->set_charset("utf8mb4");

// بدء الجلسة
session_start();

$response = ['success' => false, 'orders' => [], 'message' => ''];

try {
    // الحصول على user_id من الجلسة
    $user_id = $_SESSION['user_id'] ?? null;
    
    // بناء استعلام الطلبات - جلب طلبات المستخدم المسجل فقط
    if (!$user_id) {
        $response['success'] = true;
        echo json_encode($response);
        exit();
    }
    
    $user_id = intval($user_id);
    $where_clause = "WHERE o.user_id = $user_id";
    
    // استعلام محسن لجلب الطلبات مع بيانات الشحن
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
        $where_clause
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
            // طلب جديد
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
                'shipping_city_name' => $row['shipping_city_name'],
                'shipping_cost' => floatval($row['shipping_cost']),
                'total' => floatval($row['total']),
                'commission_total' => floatval($row['commission_total']),
                'status' => $row['status'],
                'date' => $row['date'],
                'cancellation_reason' => $row['cancellation_reason'],
                'return_reason' => $row['return_reason'],
                'reason_type' => $row['reason_type'],
                'items' => []
            ];
        }
        
        // إضافة المنتج إذا كان موجوداً
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

    // إضافة آخر طلب
    if ($current_order) {
        $orders[] = $current_order;
    }

    $response['orders'] = $orders;
    $response['success'] = true;
    $response['message'] = 'تم جلب ' . count($orders) . ' طلب بنجاح';
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in get_orders.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
$conn->close();
?>
