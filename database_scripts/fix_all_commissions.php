<?php
// ملف: fix_all_commissions.php
// سكريبت شامل لإصلاح جميع مشاكل العمولات

session_start();
include(__DIR__ . '/core/config.php");

echo "<h2>سكريبت إصلاح جميع مشاكل العمولات</h2>";

// 1. التحقق من بيانات المستخدمين 5 و 8
echo "<h3>1. بيانات المستخدمين:</h3>";
$user5_query = $conn->query("SELECT * FROM users WHERE id = 5");
$user8_query = $conn->query("SELECT * FROM users WHERE id = 8");

echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
echo "<tr><th>User ID</th><th>الاسم</th><th>نوع المستخدم</th><th>البريد الإلكتروني</th></tr>";

if ($user5 = $user5_query->fetch_assoc()) {
    echo "<tr><td>5</td><td>" . $user5['fullname'] . "</td><td>" . $user5['user_type'] . "</td><td>" . $user5['email'] . "</td></tr>";
}

if ($user8 = $user8_query->fetch_assoc()) {
    echo "<tr><td>8</td><td>" . $user8['fullname'] . "</td><td>" . $user8['user_type'] . "</td><td>" . $user8['email'] . "</td></tr>";
}
echo "</table>";

// 2. إنشاء جدول العمولات إذا لم يكن موجوداً
echo "<h3>2. التحقق من جدول العمولات:</h3>";
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
echo "تم التحقق من جدول marketer_commissions<br>";

// 3. إضافة العمولات المفقودة للمستخدم 8 (الطلبات في الشحن والمكتملة)
echo "<h3>3. إضافة العمولات المفقودة للمستخدم 8:</h3>";
$user8_orders = [30, 32, 33]; // طلبات في الشحن
$user8_completed = [24]; // طلب مكتمل (لديه عمولة بالفعل)

$added_user8 = 0;
$total_user8 = 0;

foreach ($user8_orders as $order_id) {
    // التحقق من وجود الطلب
    $order_check = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = 8");
    if ($order = $order_check->fetch_assoc()) {
        // التحقق من عدم وجود العمولة مسبقاً
        $commission_check = $conn->query("SELECT id FROM marketer_commissions WHERE order_id = $order_id AND user_id = 8");
        
        if ($commission_check->num_rows == 0) {
            // إضافة العمولة
            $commission_amount = $order['commission_total'];
            $insert_commission = $conn->prepare("
                INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status) 
                VALUES (8, ?, ?, 'مكتمل')
            ");
            $insert_commission->bind_param("id", $order_id, $commission_amount);
            
            if ($insert_commission->execute()) {
                echo "تمت إضافة عمولة للطلب $order_id: $commission_amount<br>";
                $added_user8++;
                $total_user8 += $commission_amount;
                
                // تحديث إجمالي العمولات للمستخدم
                $update_user = $conn->prepare("UPDATE users SET total_commissions = total_commissions + ? WHERE id = 8");
                $update_user->bind_param("d", $commission_amount);
                $update_user->execute();
            } else {
                echo "خطأ في إضافة عمولة للطلب $order_id<br>";
            }
        } else {
            echo "الطلب $order_id لديه عمولة بالفعل<br>";
        }
    }
}

echo "<strong>تمت إضافة $added_user8 عمولة للمستخدم 8 بإجمالي $total_user8</strong><br>";

// 4. إضافة العمولات المفقودة للمستخدم 5 (الطلبات المكتملة)
echo "<h3>4. إضافة العمولات المفقودة للمستخدم 5:</h3>";
$user5_completed_orders = [50, 54, 57, 58]; // طلبات مكتملة
$user5_shipping_orders = [42, 43, 44, 45, 52]; // طلبات في الشحن

$all_user5_orders = array_merge($user5_completed_orders, $user5_shipping_orders);
$added_user5 = 0;
$total_user5 = 0;

foreach ($all_user5_orders as $order_id) {
    // التحقق من وجود الطلب
    $order_check = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = 5");
    if ($order = $order_check->fetch_assoc()) {
        // التحقق من عدم وجود العمولة مسبقاً
        $commission_check = $conn->query("SELECT id FROM marketer_commissions WHERE order_id = $order_id AND user_id = 5");
        
        if ($commission_check->num_rows == 0) {
            // إضافة العمولة
            $commission_amount = $order['commission_total'];
            $status = in_array($order_id, $user5_completed_orders) ? 'مكتمل' : 'مكتمل'; // جميع العمولات مكتملة للشحن والتوصيل
            
            $insert_commission = $conn->prepare("
                INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status) 
                VALUES (5, ?, ?, ?)
            ");
            $insert_commission->bind_param("ids", $order_id, $commission_amount, $status);
            
            if ($insert_commission->execute()) {
                echo "تمت إضافة عمولة للطلب $order_id: $commission_amount (حالة: $status)<br>";
                $added_user5++;
                $total_user5 += $commission_amount;
                
                // تحديث إجمالي العمولات للمستخدم
                $update_user = $conn->prepare("UPDATE users SET total_commissions = total_commissions + ? WHERE id = 5");
                $update_user->bind_param("d", $commission_amount);
                $update_user->execute();
            } else {
                echo "خطأ في إضافة عمولة للطلب $order_id<br>";
            }
        } else {
            echo "الطلب $order_id لديه عمولة بالفعل<br>";
        }
    }
}

echo "<strong>تمت إضافة $added_user5 عمولة للمستخدم 5 بإجمالي $total_user5</strong><br>";

// 5. عرض الحالة النهائية
echo "<h3>5. الحالة النهائية بعد الإصلاح:</h3>";

// المستخدم 8
$user8_final_query = $conn->query("
    SELECT SUM(mc.commission_amount) as total 
    FROM marketer_commissions mc
    WHERE mc.user_id = 8 AND mc.status = 'مكتمل'
");
$user8_final = $user8_final_query->fetch_assoc();
echo "المستخدم 8 - إجمالي العمولات المكتملة: " . ($user8_final['total'] ?: 0) . "<br>";

// المستخدم 5
$user5_final_query = $conn->query("
    SELECT SUM(mc.commission_amount) as total 
    FROM marketer_commissions mc
    WHERE mc.user_id = 5 AND mc.status = 'مكتمل'
");
$user5_final = $user5_final_query->fetch_assoc();
echo "المستخدم 5 - إجمالي العمولات المكتملة: " . ($user5_final['total'] ?: 0) . "<br>";

// 6. رابط للعودة لصفحة السحب
echo "<h3>6. روابط مفيدة:</h3>";
echo "<a href='withdrawals.php' target='_blank'>صفحة السحب للمستخدم الحالي</a><br>";
echo "<a href='debug_orders_withdrawals.php' target='_blank'>فحص الطلبات والعمولات</a><br>";

echo "<br><strong>تم الانتهاء من إصلاح جميع مشاكل العمولات!</strong>";

$conn->close();
?>
