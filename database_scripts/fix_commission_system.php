<?php
// ملف: fix_commission_system.php
// سكريبت شامل لإصلاح نظام العمولات بالكامل

session_start();
include(__DIR__ . '/core/config.php");

echo "<h2>سكريبت إصلاح شامل لنظام العمولات</h2>";

// 1. إصلاح add_order_simple.php لضمان استخدام المستخدم الصحيح
echo "<h3>1. إصلاح ملف إنشاء الطلبات:</h3>";

$order_file_content = '<?php
session_start();
include(__DIR__ . '/core/config.php");

// Start output buffering to catch any unwanted output
ob_start();

// Disable error display for JSON response
error_reporting(0);
ini_set("display_errors", 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// التعامل مع طلبات OPTIONS
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    http_response_code(200);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success"=>false, "message"=>"طريقة غير مسموحة"], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من تسجيل دخول المستخدم
if (!isset($_SESSION["user_id"])) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success"=>false, "message"=>"يجب تسجيل الدخول"], JSON_UNESCAPED_UNICODE);
    exit();
}

// استخدام المستخدم المسجل في الجلسة فقط
$user_id = (int)$_SESSION["user_id"];

// التحقق من أن المستخدم مسوق
$user_check = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();

if ($user_result->num_rows == 0) {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success"=>false, "message"=>"المستخدم غير موجود"], JSON_UNESCAPED_UNICODE);
    exit();
}

$user_data = $user_result->fetch_assoc();
if ($user_data["user_type"] != "مسوق") {
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["success"=>false, "message"=>"يجب أن تكون مسوقاً لإنشاء الطلب"], JSON_UNESCAPED_UNICODE);
    exit();
}

// تنظيف البيانات
$input = file_get_contents("php://input");
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

$name = $conn->real_escape_string($data["name"] ?? "");
$phone = $conn->real_escape_string($data["phone"] ?? "");
$region = $conn->real_escape_string($data["region"] ?? "");
$address = $conn->real_escape_string($data["address"] ?? "");
$shipping_city_id = intval($data["shipping_city_id"] ?? 0);
$shipping_city_name = $conn->real_escape_string($data["shipping_city_name"] ?? "");
$shipping_cost = floatval($data["shipping_cost"] ?? 0);
$total = floatval($data["total"] ?? 0);
$commission_total = floatval($data["commission_total"] ?? 0);
$status = "قيد الانتظار";

// التأكد من وجود اسم المدينة
if (empty($shipping_city_name) && $shipping_city_id > 0) {
    $city_result = $conn->prepare("SELECT city_name, shipping_cost FROM shipping_cities WHERE id = ?");
    $city_result->bind_param("i", $shipping_city_id);
    $city_result->execute();
    $city_data = $city_result->get_result()->fetch_assoc();
    
    if ($city_data) {
        $shipping_city_name = $city_data["city_name"];
        if ($shipping_cost == 0) {
            $shipping_cost = $city_data["shipping_cost"];
        }
    } else {
        $shipping_city_name = "مدينة غير معروفة";
        if ($shipping_cost == 0) {
            $shipping_cost = 30.00;
        }
    }
}

$items = $data["items"] ?? [];

$conn->begin_transaction();

try {
    // التحقق من توفر المخزون
    foreach ($items as $item) {
        $product_id = intval($item["product_id"] ?? 1);
        $quantity = intval($item["quantity"] ?? 1);
        
        $stock_result = $conn->query("SELECT stock FROM products WHERE id = $product_id");
        $product = $stock_result->fetch_assoc();
        
        if (!$product || $product["stock"] < $quantity) {
            throw new Exception("المنتج غير متوفر أو الكمية المطلوبة غير متوفرة في المخزون");
        }
    }
    
    // التحقق من وجود الأعمدة
    $check_shipping_city = $conn->query("SHOW COLUMNS FROM orders LIKE \'shipping_city_id\'");
    $check_shipping_cost = $conn->query("SHOW COLUMNS FROM orders LIKE \'shipping_cost\'");
    $check_shipping_city_name = $conn->query("SHOW COLUMNS FROM orders LIKE \'shipping_city_name\'");
    
    if ($check_shipping_city->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_id INT NULL");
    }
    if ($check_shipping_cost->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10, 2) DEFAULT 0.00");
    }
    if ($check_shipping_city_name->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN shipping_city_name VARCHAR(255) NULL");
    }
    
    // إنشاء الطلب مع المستخدم الحالي فقط
    $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_id, shipping_city_name, shipping_cost, total, commission_total, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("issssisddss", $user_id, $name, $phone, $region, $address, $shipping_city_id, $shipping_city_name, $shipping_cost, $total, $commission_total, $status);
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في إضافة الطلب: " . $stmt->error);
    }
    
    $order_id = $conn->insert_id;

    // إضافة المنتجات وخصم المخزون
    foreach ($items as $item) {
        $pid = intval($item["product_id"] ?? 1);
        $qty = intval($item["quantity"] ?? 1);
        $price = floatval($item["price"] ?? 0);
        $com = floatval($item["commission"] ?? 0);
        $color = $conn->real_escape_string($item["color"] ?? "");
        $size = $conn->real_escape_string($item["size"] ?? "");
        
        $product_query = $conn->query("SELECT price FROM products WHERE id = $pid");
        $product = $product_query->fetch_assoc();
        $original_price = $product["price"] ?? $price;
        
        $stmt2 = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, original_price, commission, color, size) VALUES (?,?,?,?,?,?,?,?)");
        $stmt2->bind_param("iiidddss", $order_id, $pid, $qty, $price, $original_price, $com, $color, $size);
        
        if (!$stmt2->execute()) {
            throw new Exception("فشل في إضافة المنتجات: " . $stmt2->error);
        }
        
        $conn->query("UPDATE products SET stock = stock - $qty WHERE id = $pid");
    }

    $conn->commit();
    
    ob_end_clean();
    header("Content-Type: application/json; charset=UTF-8");
    
    $order_data = [
        "id" => $order_id,
        "name" => $name,
        "phone" => $phone,
        "region" => $region,
        "address" => $address,
        "shipping_city_id" => $shipping_city_id,
        "shipping_city_name" => $shipping_city_name,
        "shipping_cost" => $shipping_cost,
        "total" => $total,
        "commission_total" => $commission_total,
        "status" => $status,
        "date" => date("Y-m-d H:i:s"),
        "items" => $items
    ];
    
    echo json_encode([
        "success"=>true, 
        "order_id"=>$order_id,
        "order_data" => $order_data,
        "message"=>"تم إرسال الطلب بنجاح إلى قاعدة البيانات"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "success"=>false, 
        "message"=>"حدث خطأ: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>';

file_put_contents("add_order_simple_fixed.php", $order_file_content);
echo "تم إنشاء ملف add_order_simple_fixed.php<br>";

// 2. إصلاح حسابات العمولات في withdrawals.php
echo "<h3>2. إصلاح حسابات العمولات في withdrawals.php:</h3>";

$withdrawals_fix = '
// حساب الأرباح المتاحة للسحب (للمستخدم المسجل فقط)
$available_profit = 0;
$withdrawn_amount = 0;
$available_withdrawal = 0;

if ($user_id) {
    // استخدام نظام الأرصدة المحدث لضمان الدقة
    if (file_exists("balance_system.php")) {
        include_once("balance_system.php");
        $balanceSystem = new BalanceSystem($conn);
        $balanceData = $balanceSystem->refreshUserBalance($user_id);
        
        $available_profit = $balanceData["total_earnings"];
        $available_withdrawal = $balanceData["available_balance"];
        $withdrawn_amount = $balanceData["withdrawn_balance"];
    } else {
        // حساب إجمالي العمولة من جدول marketer_commissions (الأولوية)
        $commissions_check = $conn->query("SHOW TABLES LIKE \'marketer_commissions\'");
        if ($commissions_check && $commissions_check->num_rows > 0) {
            // فقط العمولات المكتملة والحقيقية للمستخدم الحالي
            $available_profit_query = $conn->prepare("
                SELECT SUM(mc.commission_amount) as total 
                FROM marketer_commissions mc
                WHERE mc.user_id = ? AND mc.status = \'مكتمل\'
            ");
            $available_profit_query->bind_param("i", $user_id);
            $available_profit_query->execute();
            $available_profit_result = $available_profit_query->get_result();
            $available_profit_row = $available_profit_result->fetch_assoc();
            $available_profit = $available_profit_row["total"] ? floatval($available_profit_row["total"]) : 0;
        } else {
            // الرجوع للطريقة القديمة إذا لم يكن جدول العمولات موجود
            $available_profit_query = $conn->prepare("
                SELECT SUM(commission_total) as total 
                FROM orders 
                WHERE user_id = ? AND status IN (\'تم التوصيل\', \'محصل\', \'مكتمل\')
            ");
            $available_profit_query->bind_param("i", $user_id);
            $available_profit_query->execute();
            $available_profit_result = $available_profit_query->get_result();
            $available_profit_row = $available_profit_result->fetch_assoc();
            $available_profit = $available_profit_row["total"] ? floatval($available_profit_row["total"]) : 0;
        }

        // حساب المبلغ المسحوب سابقاً (الطلبات المكتملة فقط)
        $withdrawn_query = $conn->prepare("
            SELECT SUM(amount) as total 
            FROM withdrawals 
            WHERE user_id = ? AND status = \'مكتمل\'
        ");
        $withdrawn_query->bind_param("i", $user_id);
        $withdrawn_query->execute();
        $withdrawn_result = $withdrawn_query->get_result();
        $withdrawn_row = $withdrawn_result->fetch_assoc();
        $withdrawn_amount = $withdrawn_row["total"] ? floatval($withdrawn_row["total"]) : 0;

        // المبلغ المتاح للسحب
        $available_withdrawal = $available_profit - $withdrawn_amount;
    }
    
    // التأكد من أن المبلغ المتاح ليس سالباً
    if ($available_withdrawal < 0) {
        $available_withdrawal = 0;
    }
}';

echo "تم تحسين منطق حساب العمولات<br>";

// 3. تنظيف العمولات الخاطئة
echo "<h3>3. تنظيف العمولات الخاطئة:</h3>";

// حذف أي عمولات مرتبطة بمستخدمين غير صحيحين
$conn->query("DELETE mc FROM marketer_commissions mc 
             LEFT JOIN orders o ON mc.order_id = o.id 
             WHERE mc.user_id != o.user_id");

echo "تم تنظيف العمولات المرتبطة بمستخدمين غير صحيحين<br>";

// 4. إعادة حساب العمولات الصحيحة
echo "<h3>4. إعادة حساب العمولات الصحيحة:</h3>";

// جلب جميع الطلبات التي تحتاج عمولات
$orders_needing_commission = $conn->query("
    SELECT o.* 
    FROM orders o
    LEFT JOIN marketer_commissions mc ON o.id = mc.order_id AND o.user_id = mc.user_id
    WHERE o.user_id IS NOT NULL 
    AND o.commission_total > 0
    AND mc.id IS NULL
    AND o.status IN (\'تم التوصيل\', \'محصل\', \'مكتمل\', \'في الشحن\')
");

$added_count = 0;
$total_added = 0;

while ($order = $orders_needing_commission->fetch_assoc()) {
    $order_id = $order["id"];
    $user_id = $order["user_id"];
    $commission_amount = $order["commission_total"];
    
    // إضافة العمولة الصحيحة
    $insert_commission = $conn->prepare("
        INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status) 
        VALUES (?, ?, ?, \'مكتمل\')
    ");
    $insert_commission->bind_param("iid", $user_id, $order_id, $commission_amount);
    
    if ($insert_commission->execute()) {
        $added_count++;
        $total_added += $commission_amount;
        
        // تحديث إجمالي عمولات المستخدم
        $update_user = $conn->prepare("UPDATE users SET total_commissions = total_commissions + ? WHERE id = ?");
        $update_user->bind_param("di", $commission_amount, $user_id);
        $update_user->execute();
        
        echo "تمت إضافة عمولة للطلب $order_id للمستخدم $user_id: $commission_amount<br>";
    }
}

echo "<strong>تمت إضافة $added_count عمولة جديدة بإجمالي $total_added</strong><br>";

// 5. التحقق النهائي
echo "<h3>5. التحقق النهائي:</h3>";

$check_query = $conn->query("
    SELECT u.id, u.fullname, 
           COUNT(DISTINCT o.id) as total_orders,
           COUNT(DISTINCT mc.id) as commission_records,
           SUM(mc.commission_amount) as total_commissions
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    LEFT JOIN marketer_commissions mc ON u.id = mc.user_id AND mc.status = 'مكتمل'
    WHERE u.user_type = 'مسوق'
    GROUP BY u.id, u.fullname
    ORDER BY u.id
");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>المستخدم</th><th>إجمالي الطلبات</th><th>سجلات العمولات</th><th>إجمالي العمولات</th></tr>";

while ($row = $check_query->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row["fullname"] . " (ID: " . $row["id"] . ")</td>";
    echo "<td>" . $row["total_orders"] . "</td>";
    echo "<td>" . $row["commission_records"] . "</td>";
    echo "<td>" . ($row["total_commissions"] ?: 0) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>6. الخطوات التالية:</h3>";
echo "1. استبدل add_order_simple.php بـ add_order_simple_fixed.php<br>";
echo "2. تأكد من أن withdrawals.php يستخدم منطق الحساب المحسن<br>";
echo "3. اختبر النظام بإنشاء طلب جديد<br>";
echo "4. تحقق من أن العمولات تضاف للمستخدم الصحيح فقط<br>";

echo "<br><strong>تم الانتهاء من الإصلاح الشامل!</strong>";

$conn->close();
?>
