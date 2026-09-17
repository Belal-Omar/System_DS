<?php
// ملف: update_order_status_commission.php
// لتحديث حالة الطلب وإضافة العمولة تلقائياً عند التوصيل
session_start();
include(__DIR__ . '/core/config.php");

// التحقق من تسجيل الدخول ومن صلاحيات الأدمن
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// معالجة طلب تحديث الحالة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = $conn->real_escape_string($_POST['status'] ?? '');
    
    if ($order_id <= 0 || empty($new_status)) {
        $_SESSION['error'] = "بيانات غير صالحة";
        header("Location: admin_orders.php");
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        // جلب بيانات الطلب الحالية
        $order_query = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $order_query->bind_param("i", $order_id);
        $order_query->execute();
        $order_result = $order_query->get_result();
        
        if ($order_result->num_rows == 0) {
            throw new Exception("الطلب غير موجود");
        }
        
        $order = $order_result->fetch_assoc();
        $old_status = $order['status'];
        $user_id = $order['user_id'];
        $commission_total = $order['commission_total'];
        
        // تحديث حالة الطلب
        $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("فشل في تحديث حالة الطلب: " . $update_stmt->error);
        }
        
        // إذا كانت الحالة الجديدة هي "تم التوصيل" والحالة القديمة لم تكن مكتملة، أضف العمولة للمسوق
        if (in_array($new_status, ['تم التوصيل', 'محصل', 'مكتمل']) && 
            !in_array($old_status, ['تم التوصيل', 'محصل', 'مكتمل']) && 
            $user_id && 
            $commission_total > 0) {
            
            // التحقق من أن المستخدم هو مسوق
            $user_check = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
            $user_check->bind_param("i", $user_id);
            $user_check->execute();
            $user_result = $user_check->get_result();
            
            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                
                if ($user_data['user_type'] == 'مسوق') {
                    // إضافة سجل عمولة جديد في جدول marketer_commissions
                    $commission_insert = $conn->prepare("
                        INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status, created_at) 
                        VALUES (?, ?, ?, 'مكتمل', NOW())
                    ");
                    $commission_insert->bind_param("iid", $user_id, $order_id, $commission_total);
                    
                    if (!$commission_insert->execute()) {
                        throw new Exception("فشل في إضافة سجل العمولة: " . $commission_insert->error);
                    }
                    
                    // تحديث إجمالي العمولات للمسوق في جدول users
                    $update_user_commission = $conn->prepare("
                        UPDATE users SET total_commissions = total_commissions + ? WHERE id = ?
                    ");
                    $update_user_commission->bind_param("di", $commission_total, $user_id);
                    $update_user_commission->execute();
                    
                    $_SESSION['success'] = "تم تحديث حالة الطلب وإضافة العمولة للمسوق بنجاح";
                } else {
                    $_SESSION['success'] = "تم تحديث حالة الطلب بنجاح";
                }
            } else {
                $_SESSION['success'] = "تم تحديث حالة الطلب بنجاح";
            }
        } else {
            $_SESSION['success'] = "تم تحديث حالة الطلب بنجاح";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
    
    header("Location: admin_orders.php");
    exit();
}

// إذا لم يكن طلب POST، أعد التوجيه
header("Location: admin_orders.php");
exit();
?>
