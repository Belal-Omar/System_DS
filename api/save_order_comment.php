<?php
// API لحفظ تعليق جديد على طلب
session_start();
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

header('Content-Type: application/json; charset=utf-8');

// التحقق من وجود جدول التعليقات - إنشاؤه تلقائياً إذا لم يكن موجوداً
$table_check = $conn->query("SHOW TABLES LIKE 'order_comments'");
if ($table_check->num_rows === 0) {
    $create_sql = "CREATE TABLE order_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        admin_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($create_sql);
}

// التحقق من تسجيل دخول المدير
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح - يجب تسجيل الدخول كمدير']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

if (!verify_csrf()) {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح. يرجى تحديث الصفحة.']);
    exit;
}

// استقبال البيانات
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$admin_id = $_SESSION['admin_id'] ?? 0;

// التحقق من البيانات
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'رقم الطلب غير صحيح']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'التعليق لا يمكن أن يكون فارغاً']);
    exit;
}

if ($admin_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'جلسة المدير غير صالحة']);
    exit;
}

// التحقق من وجود الطلب
$order_check = $conn->query("SELECT id FROM orders WHERE id = $order_id");
if ($order_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
    exit;
}

// حفظ التعليق
$stmt = $conn->prepare("INSERT INTO order_comments (order_id, admin_id, comment) VALUES (?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iis", $order_id, $admin_id, $comment);

if ($stmt->execute()) {
    $comment_id = $stmt->insert_id;
    
    // جلب اسم المدير
    $admin_query = $conn->query("SELECT fullname FROM admins WHERE id = $admin_id");
    $admin_row = $admin_query->fetch_assoc();
    $admin_name = $admin_row['fullname'] ?? 'مدير';
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إضافة التعليق بنجاح',
        'comment' => [
            'id' => $comment_id,
            'order_id' => $order_id,
            'admin_name' => $admin_name,
            'comment' => htmlspecialchars($comment),
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'فشل في حفظ التعليق: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
