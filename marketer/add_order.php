<?php
// ملف: add_order.php (محدث)
// التأكد من عدم وجود أي output قبل JSON
if (ob_get_length()) ob_clean();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// تعديل error reporting لتجنب أي output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// التأكد من أن الجلسة لا تسبب مشاكل
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/../core/config.php");
include(__DIR__ . '/../core/helpers.php");

// التعامل مع طلبات OPTIONS (لـ CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من الاتصال بقاعدة البيانات
    if (!$conn || $conn->connect_error) {
        echo json_encode([
            "success"=>false, 
            "message"=>"فشل الاتصال بقاعدة البيانات"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode([
            "success"=>false, 
            "message"=>"بيانات غير صالحة"
        ]);
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
    $total_special_commission = 0; // سيتم حسابه
    $status = 'قيد الانتظار'; // الحالة الافتراضية
    
    // الحصول على user_id من الجلسة (المستخدم المسجل دخول)
    $user_id = $_SESSION['user_id'] ?? null;
    
    // إذا لم يكن هناك user_id في الجلسة، البحث عن أول مستخدم مسوق
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
        $total_special_commission = 0;
        $processed_items = [];

        foreach ($items as $item) {
            $product_id = intval($item['product_id'] ?? 1);
            $quantity = intval($item['quantity'] ?? 1);
            $price_input = floatval($item['price'] ?? 0);
            $com_input = floatval($item['commission'] ?? 0);
            $color = $conn->real_escape_string($item['color'] ?? '');
            $size = $conn->real_escape_string($item['size'] ?? '');
            
            // 1. Check main product stock and special commission
            $stock_result = $conn->query("SELECT price, stock, special_commission FROM products WHERE id = $product_id");
            $product = $stock_result->fetch_assoc();
            
            if (!$product || $product['stock'] < $quantity) {
                throw new Exception("المنتج (ID: $product_id) غير متوفر أو الكمية المطلوبة غير متوفرة في المخزون العام");
            }

            // 2. Check variant stock if color/size provided
            if (!empty($color) || !empty($size)) {
                $inv_query = $conn->prepare("SELECT quantity FROM product_inventory WHERE product_id = ? AND color = ? AND size = ?");
                $inv_query->bind_param("iss", $product_id, $color, $size);
                $inv_query->execute();
                $inv_res = $inv_query->get_result();
                if ($inv_item = $inv_res->fetch_assoc()) {
                    if ($inv_item['quantity'] < $quantity) {
                        throw new Exception("الكمية المطلوبة للون ($color) ومقاس ($size) غير متوفرة. المتاح: " . $inv_item['quantity']);
                    }
                } else {
                    // Check if this product has any inventory records at all
                    $has_inv_check = $conn->query("SELECT id FROM product_inventory WHERE product_id = $product_id LIMIT 1");
                    if ($has_inv_check && $has_inv_check->num_rows > 0) {
                        throw new Exception("هذا المنتج يتطلب اختيار لون ومقاس صحيحين متوفرين في المخزون");
                    }
                }
            }

            $special_com = floatval($product['special_commission'] ?? 0);
            $net_commission = $com_input - $special_com;
            
            $final_commission_total += ($net_commission * $quantity);
            $total_special_commission += ($special_com * $quantity);
            
            $processed_items[] = [
                'pid' => $product_id,
                'qty' => $quantity,
                'price' => $price_input,
                'original_price' => $product['price'] ?? $price_input,
                'net_commission' => $net_commission,
                'special_com' => $special_com,
                'color' => $color,
                'size' => $size,
                'has_variant' => (!empty($color) || !empty($size))
            ];
        }
        
        // تحديث إجمالي العمولة للطلب
        $commission_total = $final_commission_total;
        
        // التحقق من وجود الأعمدة في جدول orders
        $check_shipping_city = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_city_id'");
        $check_shipping_cost = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_cost'");
        $check_shipping_city_name = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_city_name'");
        $check_shipping_company = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_company'");
        
        if ($check_shipping_city->num_rows == 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_id INT NULL");
        }
        if ($check_shipping_cost->num_rows == 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10, 2) DEFAULT 0.00");
        }
        if ($check_shipping_city_name->num_rows == 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_name VARCHAR(255) NULL");
        }
        if ($check_shipping_company->num_rows == 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN shipping_company VARCHAR(100) NULL");
        }
        
        // جلب اسم المدينة إذا كان shipping_city_id موجود
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
        
        // جلب شركة الشحن من أول منتج في الطلب
        $shipping_company_name = null;
        $shipping_company_id = null;
        if (!empty($items)) {
            $first_product_id = intval($items[0]['product_id'] ?? 1);
            $product_query = $conn->query("SELECT shipping_company, shipping_company_id FROM products WHERE id = $first_product_id");
            if ($product = $product_query->fetch_assoc()) {
                $shipping_company_name = $product['shipping_company'];
                $shipping_company_id = $product['shipping_company_id'];
            }
        }
        
        // إنشاء الطلب (مع أو بدون user_id)
        if ($user_id && $shipping_city_id > 0) {
            $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company_id, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssisddddsis", $user_id, $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company_id, $shipping_company_name);
        } elseif ($user_id) {
            $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company_id, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssddddsis", $user_id, $name, $phone, $region, $address, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company_id, $shipping_company_name);
        } elseif ($shipping_city_id > 0) {
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company_id, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssiddddsis", $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company_id, $shipping_company_name);
        } else {
            $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company_id, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssddddsis", $name, $phone, $region, $address, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company_id, $shipping_company_name);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("فشل في إضافة الطلب: " . $stmt->error);
        }
        
        $order_id = $conn->insert_id;

        // إشعار للإدارة بطلب جديد
        if (function_exists('send_notification')) {
            $sender = $_SESSION['fullname'] ?? 'مستخدم';
            $title = "طلب جديد من $sender";
            $msg = "تم إضافة طلب جديد (رقم: $order_id) للعميل $name";
            $link = "admin_panel.php?page=orders";
            send_notification($conn, 'admin', 0, $title, $msg, $link);
        }

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
            $stmt2->bind_param("iiiddddss", $order_id, $pid, $qty, $price, $original_price, $net_commission, $special_com, $color, $size);
            
            if (!$stmt2->execute()) {
                throw new Exception("فشل في إضافة المنتجات: " . $stmt2->error);
            }
            
            // خصم المخزون مباشرة عند إنشاء الطلب
            // خصم المخزون من الجدول الرئيسي ومن جدول التفاصيل
            $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $pid");
            if ($item['has_variant']) {
                $update_inv = $conn->prepare("UPDATE product_inventory SET quantity = quantity - ? WHERE product_id = ? AND color = ? AND size = ?");
                $update_inv->bind_param("iiss", $qty, $pid, $color, $size);
                $update_inv->execute();
            }
        }

        $conn->commit();
        
        // حفظ الطلب في localStorage للعرض في صفحة الطلبات
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
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            "success"=>false, 
            "message"=>"حدث خطأ: " . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(["success"=>false, "message"=>"طريقة غير مسموحة"]);
}
?>