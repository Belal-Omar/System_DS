<?php
session_start();
include(__DIR__ . '/core/config.php");

// Start output buffering to catch any unwanted output
ob_start();

// Disable error display for JSON response
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// التعامل مع طلبات OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle GET requests (like fetching product commissions)
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['get_product_commissions'])) {
        $product_id = intval($_GET['get_product_commissions']);
        $stmt = $conn->prepare("SELECT special_commission, merchant_commission FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            ob_end_clean();
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "success" => true,
                "special_commission" => $row['special_commission'] ?? 0,
                "merchant_commission" => $row['merchant_commission'] ?? 0
            ]);
            exit();
        } else {
            ob_end_clean();
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode(["success"=>false, "message"=>"المنتج غير موجود"]);
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success"=>false, "message"=>"طريقة غير مسموحة"], JSON_UNESCAPED_UNICODE);
    exit();
}

// تنظيف البيانات
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "success"=>false, 
        "message"=>"بيانات غير صالحة"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$name = $conn->real_escape_string($data['name'] ?? '');
$phone = $conn->real_escape_string($data['phone'] ?? '');
$region = $conn->real_escape_string($data['region'] ?? '');
$address = $conn->real_escape_string($data['address'] ?? '');
$shipping_city_id = intval($data['shipping_city_id'] ?? 0);
$shipping_city_name = $conn->real_escape_string($data['shipping_city_name'] ?? '');
$shipping_cost = floatval($data['shipping_cost'] ?? 0);
$total = floatval($data['total'] ?? 0);
$commission_total_input = floatval($data['commission_total'] ?? 0);
$total_special_commission = 0;
$status = 'قيد الانتظار';

// استخدام المستخدم المسجل في الجلسة
$user_id = null;

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    
    // التحقق من أن المستخدم مسوق
    $user_check = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
    $user_check->bind_param("i", $user_id);
    $user_check->execute();
    $user_result = $user_check->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        if ($user_data['user_type'] != 'مسوق') {
            // إذا لم يكن مسوق، لا تسمح بإنشاء الطلب
            ob_end_clean();
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "success"=>false, 
                "message"=>"يجب أن تكون مسوقاً لإنشاء الطلب"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    } else {
        // المستخدم غير موجود
        $user_id = null;
    }
}

// التأكد من وجود اسم المدينة - حل جذري
if (empty($shipping_city_name) && $shipping_city_id > 0) {
    // جلب اسم المدينة من جدول shipping_cities
    $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result->bind_param("i", $shipping_city_id);
    $city_result->execute();
    $city_data = $city_result->get_result()->fetch_assoc();
    
    if ($city_data) {
        $shipping_city_name = $city_data['city_name'];
        if ($shipping_cost == 0) {
            $shipping_cost = $city_data['shipping_cost'];
        }
    }
}

// الحل الجذري - فرض اسم المدينة دائماً
if ($shipping_city_id > 0 && empty($shipping_city_name)) {
    // محاولة ثانية لجلب اسم المدينة
    $city_result2 = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result2->bind_param("i", $shipping_city_id);
    $city_result2->execute();
    $city_data2 = $city_result2->get_result()->fetch_assoc();
    
    if ($city_data2) {
        $shipping_city_name = $city_data2['city_name'];
        if ($shipping_cost == 0) {
            $shipping_cost = $city_data2['shipping_cost'];
        }
    } else {
        // إذا لم يتم العثور على المدينة، ضع اسم افتراضي
        $shipping_city_name = "مدينة غير معروفة";
        if ($shipping_cost == 0) {
            $shipping_cost = 30.00; // تكلفة افتراضية
        }
    }
}

$items = $data['items'] ?? [];

$conn->begin_transaction();

try {
    // التحقق من توفر المخزون وحساب العمولات
    $final_commission_total = 0;
    $total_special_commission_calc = 0;
    $processed_items = [];
    
    foreach ($items as $item) {
        $pid = intval($item['product_id'] ?? 1);
        $qty = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $com_input = floatval($item['commission'] ?? 0);
        $color = $conn->real_escape_string($item['color'] ?? '');
        $size = $conn->real_escape_string($item['size'] ?? '');
        
        // 1. Check main product stock and special commission
        $product_query = $conn->query("SELECT price, stock, special_commission FROM products WHERE id = $pid");
        $product = $product_query->fetch_assoc();
        
        if (!$product || $product['stock'] < $qty) {
            throw new Exception("المنتج (ID: $pid) غير متوفر أو الكمية المطلوبة غير متوفرة في المخزون العام");
        }

        // 2. Check variant stock if color/size provided
        if (!empty($color) || !empty($size)) {
            $inv_query = $conn->prepare("SELECT quantity FROM product_inventory WHERE product_id = ? AND color = ? AND size = ?");
            $inv_query->bind_param("iss", $pid, $color, $size);
            $inv_query->execute();
            $inv_res = $inv_query->get_result();
            if ($inv_item = $inv_res->fetch_assoc()) {
                if ($inv_item['quantity'] < $qty) {
                    throw new Exception("الكمية المطلوبة للون ($color) ومقاس ($size) غير متوفرة. المتاح: " . $inv_item['quantity']);
                }
            } else {
                // Check if this product has any inventory records at all
                $has_inv_check = $conn->query("SELECT id FROM product_inventory WHERE product_id = $pid LIMIT 1");
                if ($has_inv_check && $has_inv_check->num_rows > 0) {
                    throw new Exception("هذا المنتج يتطلب اختيار لون ومقاس صحيحين متوفرين في المخزون");
                }
                // If no inventory records at all, we rely on main products.stock (already checked)
            }
        }
        
        $original_price = $product['price'] ?? $price;
        $special_com = floatval($product['special_commission'] ?? 0);

        $net_commission = $com_input - $special_com;
        $final_commission_total += ($com_input * $qty); // Store GROSS commission
        $total_special_commission_calc += ($special_com * $qty);
        
        $processed_items[] = [
            'pid' => $pid,
            'qty' => $qty,
            'price' => $price,
            'original_price' => $original_price,
            'net_commission' => $net_commission,
            'com_input' => $com_input,
            'special_com' => $special_com,
            'color' => $color,
            'size' => $size,
            'has_variant' => (!empty($color) || !empty($size))
        ];
    }
    
    $commission_total = $final_commission_total;
    $total_special_commission = $total_special_commission_calc;
    
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
    
    $check_shipping_company = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_company_id'");
    if ($check_shipping_company->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_company_id INT NULL");
    }
    
    // إبقاء العمود القديم للتوافق إن وجد
    $check_old_shipping = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_company'");
    if ($check_old_shipping->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_company VARCHAR(100) NULL");
    }
    
    // تحديد شركة الشحن المناسبة للطلب بناءً على المدينة وتوفر المخزون
    $shipping_company_id = null;
    
    // جلب كل شركات الشحن التي تدعم توصيل مدينتنا
    $city_shippers_query = $conn->query("SELECT shipping_company_id FROM shipping_company_cities WHERE city_id = $shipping_city_id");
    $covered_shippers = [];
    if ($city_shippers_query) {
        while($row = $city_shippers_query->fetch_assoc()) {
            $covered_shippers[] = intval($row['shipping_company_id']);
        }
    }
    
    error_log("Covered shippers for city $shipping_city_id: " . implode(',', $covered_shippers));
    
    if (!empty($covered_shippers)) {
        // تحديد شركة شحن تملك المخزون الكافي لجميع منتجات الطلب
        $shipper_scores = [];
        foreach ($covered_shippers as $sh_id) {
            $can_fulfill_all = true;
            $total_stock_available = 0;
            foreach ($processed_items as $item) {
                $pid = $item['pid'];
                $qty = $item['qty'];
                
                $ps_q = $conn->query("SELECT stock FROM product_shippers WHERE product_id = $pid AND shipping_company_id = $sh_id");
                if ($ps_q && $ps_row = $ps_q->fetch_assoc()) {
                    if ($ps_row['stock'] < $qty) {
                        $can_fulfill_all = false;
                        break;
                    }
                    $total_stock_available += $ps_row['stock'];
                } else {
                    // إذا لم يكن لهذه الشركة حصة من هذا المنتج، لا يمكنها تلبيته
                    $can_fulfill_all = false;
                    break;
                }
            }
            if ($can_fulfill_all) {
                $shipper_scores[$sh_id] = $total_stock_available;
            }
        }
        
        if (!empty($shipper_scores)) {
            // اختيار الشركة التي تملك أكبر رصيد إجمالي متاح للمنتجات المطلوبة كأفضل خيار
            arsort($shipper_scores);
            $shipping_company_id = array_key_first($shipper_scores);
            error_log("Selected shipper via inventory score: $shipping_company_id");
        }
    }
    
    // إذا لم تتمكن أي شركة من تلبية المخزون بالكامل من حصتها، نختار أول شركة تدعم المدينة كبديل قسري
    if (!$shipping_company_id && !empty($covered_shippers)) {
        $shipping_company_id = $covered_shippers[0];
        error_log("Falling back to first covered shipper: $shipping_company_id");
    }
    
    if (!$shipping_company_id) {
        error_log("WARNING: No shipping company found for city $shipping_city_id");
    }
    
    // جلب اسم شركة الشحن للحفاظ على التوافق مع العمود القديم
    $shipping_company_name_old = '';
    if ($shipping_company_id) {
        $sc_q = $conn->query("SELECT name FROM shipping_companies WHERE id = $shipping_company_id");
        if ($sc_q && $sc_row = $sc_q->fetch_assoc()) {
            $shipping_company_name_old = $sc_row['name'];
        }
    }
    
    // إنشاء الطلب مع التأكد من حفظ اسم المدينة وشركة الشحن
    if ($user_id) {
        $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company_id, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssisddddsis", $user_id, $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company_id, $shipping_company_name_old);
    } else {
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, total_special_commission, status, shipping_company_id, shipping_company) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssisddddsis", $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $total_special_commission, $status, $shipping_company_id, $shipping_company_name_old);
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
        
        $com_item = $item['com_input'];
        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, original_price, commission, special_commission, color, size) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt2->bind_param("iiiddddss", $order_id, $pid, $qty, $price, $original_price, $com_item, $special_com, $color, $size);
        
        if (!$stmt2->execute()) {
            throw new Exception("فشل في إضافة المنتجات: " . $stmt2->error);
        }
        
        // خصم المخزون من الجدول الرئيسي ومن جدول التفاصيل
        $conn->query("UPDATE products SET stock = GREATEST(0, stock - $qty) WHERE id = $pid");
        if ($item['has_variant']) {
            $update_inv = $conn->prepare("UPDATE product_inventory SET quantity = GREATEST(0, quantity - ?) WHERE product_id = ? AND color = ? AND size = ?");
            $update_inv->bind_param("iiss", $qty, $pid, $color, $size);
            $update_inv->execute();
        }
        // خصم المخزون المخصص لشركة الشحن إن وجد
        if ($shipping_company_id) {
            $conn->query("UPDATE product_shippers SET stock = GREATEST(0, stock - $qty) WHERE product_id = $pid AND shipping_company_id = $shipping_company_id");
        }
    }

    $conn->commit();
    
    // Clean output buffer before sending JSON
    ob_end_clean();
    
    // Ensure clean headers
    header("Content-Type: application/json; charset=UTF-8");
    
    $order_data = [
        'id' => $order_id,
        'name' => $name,
        'phone' => $phone,
        'region' => $region,
        'address' => $address,
        'shipping_city_id' => $shipping_city_id,
        'shipping_city_name' => $shipping_city_name,
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
    
    // Clean any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Ensure clean JSON error response
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "success"=>false, 
        "message"=>"حدث خطأ: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    $conn->rollback();
    
    // Clean any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Ensure clean JSON error response
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "success"=>false, 
        "message"=>"خطأ في النظام: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
