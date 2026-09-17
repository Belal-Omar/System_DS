<?php
// ملف: add_commission_from_cart.php
// إضافة العمولة من cart.html مباشرة ل withdrawals.php

session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false, "message"=>"يجب تسجيل الدخول"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// التحقق من أن المستخدم مسوق
$user_check = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();

if ($user_result->num_rows == 0) {
    echo json_encode(["success"=>false, "message"=>"المستخدم غير موجود"]);
    exit();
}

$user_data = $user_result->fetch_assoc();
if ($user_data['user_type'] != 'مسوق') {
    echo json_encode(["success"=>false, "message"=>"هذا الحساب ليس مسوق"]);
    exit();
}

// جلب بيانات POST
$order_id = intval($_POST['order_id'] ?? 0);
$commission_amount = floatval($_POST['commission_amount'] ?? 0);

if ($order_id <= 0 || $commission_amount <= 0) {
    echo json_encode(["success"=>false, "message"=>"بيانات غير صالحة"]);
    exit();
}

// التحقق من وجود جدول العمولات
$conn->query("CREATE TABLE IF NOT EXISTS marketer_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    commission_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('قيد المراجعة', 'مكتمل', 'ملغي') DEFAULT 'مكتمل',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// التحقق من أن العمولة لم تتم إضافتها من قبل
$check_commission = $conn->prepare("SELECT id FROM marketer_commissions WHERE order_id = ? AND user_id = ?");
$check_commission->bind_param("ii", $order_id, $user_id);
$check_commission->execute();

if ($check_commission->get_result()->num_rows > 0) {
    echo json_encode(["success"=>false, "message"=>"العمولة مضافة مسبقاً لهذا الطلب"]);
    exit();
}

// إضافة العمولة
$insert_commission = $conn->prepare("
    INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status) 
    VALUES (?, ?, ?, 'مكتمل')
");
$insert_commission->bind_param("iid", $user_id, $order_id, $commission_amount);

if ($insert_commission->execute()) {
    // تحديث إجمالي العمولات للمسوق
    $update_user = $conn->prepare("UPDATE users SET total_commissions = total_commissions + ? WHERE id = ?");
    $update_user->bind_param("di", $commission_amount, $user_id);
    $update_user->execute();
    
    echo json_encode([
        "success"=>true, 
        "message"=>"تم إضافة العمولة بنجاح",
        "commission_amount"=>$commission_amount,
        "order_id"=>$order_id
    ]);
} else {
    echo json_encode(["success"=>false, "message"=>"فشل في إضافة العمولة"]);
}

$conn->close();
?>
