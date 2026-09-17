<?php
// API لجلب تعليقات طلب معين
session_start();
include(__DIR__ . '/core/config.php");

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
    
    // إرجاع قائمة فارغة لأن الجدول جديد
    echo json_encode([
        'success' => true,
        'order_id' => intval($_GET['order_id'] ?? 0),
        'count' => 0,
        'comments' => []
    ]);
    exit;
}

// استقبال رقم الطلب
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'رقم الطلب غير صحيح', 'comments' => []]);
    exit;
}

// جلب التعليقات مع اسم المدير
$query = "
    SELECT 
        oc.id,
        oc.order_id,
        oc.comment,
        oc.created_at,
        COALESCE(a.fullname, 'مدير') as admin_name
    FROM order_comments oc
    LEFT JOIN admins a ON oc.admin_id = a.id
    WHERE oc.order_id = ?
    ORDER BY oc.created_at DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات', 'comments' => []]);
    exit;
}

$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = [
        'id' => $row['id'],
        'order_id' => $row['order_id'],
        'admin_name' => htmlspecialchars($row['admin_name']),
        'comment' => htmlspecialchars($row['comment']),
        'created_at' => $row['created_at'],
        'formatted_date' => date('Y-m-d H:i', strtotime($row['created_at']))
    ];
}

echo json_encode([
    'success' => true,
    'order_id' => $order_id,
    'count' => count($comments),
    'comments' => $comments
]);

$stmt->close();
$conn->close();
?>
