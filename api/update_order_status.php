<?php
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "يجب تسجيل الدخول"]);
    exit();
}

// التحقق من أن المستخدم هو مسوق
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT user_type FROM users WHERE id = $user_id");
if ($user_check && $user_check->num_rows > 0) {
    $user = $user_check->fetch_assoc();
    if ($user['user_type'] != 'مسوق') {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "هذه الصفحة للمسوقين فقط"]);
        exit();
    }
}

// معالجة طلب تغيير حالة الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    
    $order_id = intval($_POST['order_id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    // التحقق من صحة البيانات
    if ($order_id <= 0 || empty($new_status)) {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "بيانات غير صالحة"]);
        exit();
    }
    
    // التحقق من أن الطلب belongs للمسوق الحالي
    $order_check = $conn->prepare("SELECT id, status, commission_total, total_special_commission FROM orders WHERE id = ? AND user_id = ?");
    $order_check->bind_param("ii", $order_id, $user_id);
    $order_check->execute();
    $order_result = $order_check->get_result();
    
    if ($order_result->num_rows == 0) {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "الطلب غير موجود أو لا ينتمي لك"]);
        exit();
    }
    
    $order = $order_result->fetch_assoc();
    $old_status = $order['status'];
    $commission_total = $order['commission_total'];
    
    // الحالات المسموح بها
    $allowed_statuses = ['قيد الانتظار', 'تم التأكيد', 'قيد التنفيذ', 'في الشحن', 'تم التوصيل', 'محصل', 'مكتمل', 'ملغي', 'مرفوض'];
    
    if (!in_array($new_status, $allowed_statuses)) {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "حالة غير مسموحة"]);
        exit();
    }
    
    // تحديث حالة الطلب
    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND user_id = ?");
    $update_stmt->bind_param("sii", $new_status, $order_id, $user_id);
    
    if ($update_stmt->execute()) {
        // التحقق إذا كانت الحالة الجديدة تجعل العمولة متاحة
        $commission_added = false;
        $commission_amount = 0;
        
        if (in_array($new_status, ['تم التوصيل', 'محصل', 'مكتمل']) && 
            !in_array($old_status, ['تم التوصيل', 'محصل', 'مكتمل']) && 
            $commission_total > 0) {
            $commission_added = true;
            $commission_amount = $commission_total;
        }
        
        header("Content-Type: application/json");
        echo json_encode([
            "success" => true, 
            "message" => "تم تحديث حالة الطلب بنجاح",
            "old_status" => $old_status,
            "new_status" => $new_status,
            "commission_added" => $commission_added,
            "commission_amount" => $commission_amount
        ]);
    } else {
        header("Content-Type: application/json");
        echo json_encode(["success" => false, "message" => "فشل في تحديث الحالة"]);
    }
    
} else {
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "message" => "طريقة غير مسموحة"]);
}
?>