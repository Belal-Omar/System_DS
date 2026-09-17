<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// التعامل مع طلبات OPTIONS (لـ CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(["success"=>false, "message"=>"طريقة غير مسموحة"], JSON_UNESCAPED_UNICODE);
    exit();
}

// الاتصال المباشر بقاعدة البيانات
$servername = "localhost";
$username = "root"; 
$password = "BelalOmar499881";
$dbname = "system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        "success"=>false, 
        "message"=>"فشل الاتصال بقاعدة البيانات"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn->set_charset("utf8mb4");

// بدء الجلسة
session_start();

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        "success"=>false, 
        "message"=>"بيانات غير صالحة"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// تنظيف البيانات
$name = $conn->real_escape_string($data['name'] ?? '');
$phone = $conn->real_escape_string($data['phone'] ?? '');
$region = $conn->real_escape_string($data['region'] ?? '');
$address = $conn->real_escape_string($data['address'] ?? '');
$shipping_city_id = intval($data['shipping_city_id'] ?? 0);
$shipping_cost = floatval($data['shipping_cost'] ?? 0);
$total = floatval($data['total'] ?? 0);
$commission_total_input = floatval($data['commission_total'] ?? 0);
$total_special_commission = 0;
$status = 'قيد الانتظار';

// الحصول على user_id من الجلسة
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    $user_query = $conn->query("SELECT id FROM users WHERE user_type = 'مسوق' LIMIT 1");
    if ($user_query && $user_query->num_rows > 0) {
        $user_row = $user_query->fetch_assoc();
        $user_id = $user_row['id'];
    }
} else {
    $user_id = intval($user_id);
}

$items = $data['items'] ?? [];

$conn->begin_transaction();

try {
    // التحقق من توفر المخزون وحساب العمولات
    $final_commission_total = 0;
    $total_special_commission_calc = 0; // Using different name to avoid confusion
    $processed_items = [];
    
    foreach ($items as $item) {
        $pid = intval($item['product_id'] ?? 1);
        $qty = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $com_input = floatval($item['commission'] ?? 0);
        $color = $conn->real_escape_string($item['color'] ?? '');
        $size = $conn->real_escape_string($item['size'] ?? '');
        
        // الحصول على السعر الأصلي والعمولة الخاصة
        $product_query = $conn->query("SELECT price, stock, special_commission, shipping_company FROM products WHERE id = $pid");
        $product = $product_query->fetch_assoc();
        
        if (!$product || $product['stock'] < $qty) {
            throw new Exception("المنتج غير متوفر أو الكمية غير متوفرة");
        }
        
        $original_price = $product['price'] ?? $price;
        $special_com = floatval($product['special_commission'] ?? 0);

        $net_commission = $com_input - $special_com;
        $final_commission_total += ($net_commission * $qty);
        $total_special_commission_calc += ($special_com * $qty);
        
        $processed_items[] = [
            'pid' => $pid,
            'qty' => $qty,
            'price' => $price,
            'original_price' => $original_price,
            'net_commission' => $net_commission,
            'special_com' => $special_com,
            'color' => $color,
            'size' => $size,
            'shipping_company' => $product['shipping_company'] ?? ''
        ];
    }
    
    $commission_total = $final_commission_total;
    $total_special_commission = $total_special_commission_calc;

    // الحصول على شركة الشحن من أول منتج إذا لم تكن موجودة
    $shipping_company = null; // Initialize here to ensure it's set correctly
    if (!empty($processed_items)) {
        $shipping_company = $processed_items[0]['shipping_company'];
    }
    
    // التحقق من وجود الأعمدة
    $check_shipping_city = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_city_id'");
    $check_shipping_cost = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_cost'");
    $check_shipping_city_name = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_city_name'");
    
    if ($check_shipping_city->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_id INT NULL");
    }
    if ($check_shipping_cost->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10, 2) DEFAULT 0.00");
    }
    if ($check_shipping_city_name->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_name VARCHAR(255) NULL");
    }
    
    // جلب اسم المدينة
    $shipping_city_name = null;
    if ($shipping_city_id > 0) {
        $city_query = $conn->prepare("SELECT city_name FROM shipping_cities WHERE id = ?");
        $city_query->bind_param("i", $shipping_city_id);
        $city_query->execute();
        $city_result = $city_query->get_result();
        if ($city_row = $city_result->fetch_assoc()) {
            $shipping_city_name = $city_row['city_name'];
        }
    }
    
    // التحقق من وجود عمود shipping_company
    $check_shipping_company = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_company'");
    if ($check_shipping_company->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_company VARCHAR(100) NULL");
    }
    
    // جلب شركة الشحن من أول منتج في الطلب
    $shipping_company = null;
    if (!empty($items)) {
        $first_product_id = intval($items[0]['product_id'] ?? 1);
        $product_query = $conn->query("SELECT shipping_company FROM products WHERE id = $first_product_id");
        if ($product = $product_query->fetch_assoc()) {
            $shipping_company = $product['shipping_company'];
        }
    }
    
    // إنشاء الطلب
    if ($user_id && $shipping_city_id > 0) {
        $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssisddddss", $user_id, $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company);
    } elseif ($user_id) {
        $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssddddss", $user_id, $name, $phone, $region, $address, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company);
    } elseif ($shipping_city_id > 0) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssisddddss", $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company);
    } else {
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssddddss", $name, $phone, $region, $address, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في إضافة الطلب: " . $stmt->error);
    }
    
    $order_id = $conn->insert_id;

    // إضافة المنتجات وخصم المخزون
    foreach ($processed_items as $item) {
        $pid = $item['pid'];
        $qty = $item['qty'];
        $price = $item['price'];
        $original_price = $item['original_price'];
        $net_commission = $item['net_commission'];
        $special_com = $item['special_com'];
        $color = $item['color'];
        $size = $item['size'];
        
        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, original_price, commission, special_commission, color, size) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt2->bind_param("iiiddddss", $order_id, $pid, $qty, $price, $original_price, $com_input, $special_com, $color, $size);
        
        if (!$stmt2->execute()) {
            throw new Exception("فشل في إضافة المنتجات: " . $stmt2->error);
        }
        
        // خصم المخزون
        $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $pid");
    }

    $conn->commit();
    
    $order_data = [
        'id' => $order_id,
        'name' => $name,
        'phone' => $phone,
        'region' => $region,
        'address' => $address,
        'shipping_city_id' => $shipping_city_id,
        'shipping_city_name' => $data['shipping_city_name'] ?? '',
        'shipping_cost' => $shipping_cost,
        'total' => $total,
        'commission_total' => $commission_total,
        'status' => $status,
        'date' => date('Y-m-d H:i:s'),
        'items' => $items
    ];
    
    echo json_encode([
        "success"=>true, 
        "order_id"=>$order_id,
        "order_data" => $order_data,
        "message"=>"تم إرسال الطلب بنجاح إلى قاعدة البيانات"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success"=>false, 
        "message"=>"حدث خطأ: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
