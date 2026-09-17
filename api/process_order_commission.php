<?php
// ملف: process_order_commission.php
// معالجة العمولات عند تحديث حالة الطلب

// الاتصال المباشر بقاعدة البيانات
$servername = "127.0.0.1";
$username = "u497700233_medhatomar5555"; 
$password = "BelalOmar49988155$";
$dbname = "u497700233_System";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["success"=>false, "message"=>"فشل الاتصال بقاعدة البيانات"]));
}

$conn->set_charset("utf8mb4");

// التحقق من وجود جدول العمولات
$check_table = $conn->query("SHOW TABLES LIKE 'marketer_commissions'");
if ($check_table->num_rows == 0) {
    // إنشاء جدول العمولات
    $create_table = "
    CREATE TABLE marketer_commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_id INT NOT NULL,
        commission_amount DECIMAL(10, 2) NOT NULL,
        status ENUM('قيد المراجعة', 'مكتمل', 'ملغي') DEFAULT 'مكتمل',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($create_table);
}

// التحقق من وجود عمود total_commissions
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'total_commissions'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN total_commissions DECIMAL(10, 2) DEFAULT 0.00");
}

// معالجة طلب POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = $conn->real_escape_string($_POST['status'] ?? '');
    
    if ($order_id > 0 && !empty($new_status)) {
        // جلب بيانات الطلب
        $order_query = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $order_query->bind_param("i", $order_id);
        $order_query->execute();
        $order_result = $order_query->get_result();
        
        if ($order_result->num_rows > 0) {
            $order = $order_result->fetch_assoc();
            $old_status = $order['status'];
            $user_id = $order['user_id'];
            $commission_total = $order['commission_total'];
            
            // تحديث حالة الطلب
            $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_status, $order_id);
            $update_stmt->execute();
            
            // إضافة العمولة إذا تم التوصيل
            if (in_array($new_status, ['تم التوصيل', 'محصل', 'مكتمل']) && 
                !in_array($old_status, ['تم التوصيل', 'محصل', 'مكتمل']) && 
                $user_id && 
                $commission_total > 0) {
                
                // التحقق من أن المستخدم مسوق
                $user_check = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
                $user_check->bind_param("i", $user_id);
                $user_check->execute();
                $user_result = $user_check->get_result();
                
                if ($user_result->num_rows > 0) {
                    $user_data = $user_result->fetch_assoc();
                    
                    if ($user_data['user_type'] == 'مسوق') {
                        // التحقق من أن العمولة لم تتم إضافتها من قبل
                        $check_commission = $conn->prepare("SELECT id FROM marketer_commissions WHERE order_id = ?");
                        $check_commission->bind_param("i", $order_id);
                        $check_commission->execute();
                        
                        if ($check_commission->get_result()->num_rows == 0) {
                            // إضافة سجل عمولة
                            $insert_commission = $conn->prepare("
                                INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status) 
                                VALUES (?, ?, ?, 'مكتمل')
                            ");
                            $insert_commission->bind_param("iid", $user_id, $order_id, $commission_total);
                            $insert_commission->execute();
                            
                            // تحديث إجمالي العمولات للمسوق
                            $update_user = $conn->prepare("UPDATE users SET total_commissions = total_commissions + ? WHERE id = ?");
                            $update_user->bind_param("di", $commission_total, $user_id);
                            $update_user->execute();
                            
                            echo json_encode([
                                "success"=>true, 
                                "message"=>"تم تحديث الحالة وإضافة العمولة بنجاح",
                                "commission_added"=>true,
                                "commission_amount"=>$commission_total
                            ]);
                        } else {
                            echo json_encode([
                                "success"=>true, 
                                "message"=>"تم تحديث الحالة (العمولة مضافة مسبقاً)",
                                "commission_added"=>false
                            ]);
                        }
                    } else {
                        echo json_encode([
                            "success"=>true, 
                            "message"=>"تم تحديث الحالة (المستخدم ليس مسوق)",
                            "commission_added"=>false
                        ]);
                    }
                } else {
                    echo json_encode([
                        "success"=>true, 
                        "message"=>"تم تحديث الحالة (المستخدم غير موجود)",
                        "commission_added"=>false
                    ]);
                }
            } else {
                echo json_encode([
                    "success"=>true, 
                    "message"=>"تم تحديث الحالة بنجاح",
                    "commission_added"=>false
                ]);
            }
        } else {
            echo json_encode(["success"=>false, "message"=>"الطلب غير موجود"]);
        }
    } else {
        echo json_encode(["success"=>false, "message"=>"بيانات غير صالحة"]);
    }
} else {
    echo json_encode(["success"=>false, "message"=>"طريقة غير مسموحة"]);
}

$conn->close();
?>
