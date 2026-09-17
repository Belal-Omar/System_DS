<?php
// ملف: balance_system.php - نظام إدارة الأرصدة والعمولات (محدث)
class BalanceSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    // تحديث رصيد المسوق عند تغيير حالة الطلب
    public function updateBalanceOnOrderStatus($order_id, $new_status, $old_status = null) {
        $order = $this->getOrderDetails($order_id);
        if (!$order || !$order['user_id']) return false;
        
        $user_id = $order['user_id'];
        $commission_total = $order['commission_total'] - ($order['total_special_commission'] ?? 0);
        
        // الحالات التي تجعل العمولة متاحة
        $available_statuses = ['تم التوصيل', 'محصل', 'مكتمل'];
        $pending_statuses = ['قيد الانتظار', 'تم التأكيد', 'في الشحن', 'تحت التحضير', 'قيد التنفيذ'];
        $cancelled_statuses = ['ملغي', 'مرفوض', 'مرتجع'];
        
        // إزالة العمولة من الحالة القديمة أولاً
        if ($old_status) {
            if (in_array($old_status, $available_statuses)) {
                $this->removeAvailableBalance($user_id, $commission_total, $order_id);
            } elseif (in_array($old_status, $pending_statuses)) {
                $this->removePendingBalance($user_id, $commission_total, $order_id);
            }
        }
        
        // إضافة العمولة للحالة الجديدة
        if (in_array($new_status, $available_statuses)) {
            $this->addAvailableBalance($user_id, $commission_total, $order_id);
        } elseif (in_array($new_status, $pending_statuses)) {
            $this->addPendingBalance($user_id, $commission_total, $order_id);
        } elseif (in_array($new_status, $cancelled_statuses)) {
            $this->cancelCommission($user_id, $commission_total, $order_id);
        }
        
        return true;
    }
    
    // إضافة رصيد متاح
    private function addAvailableBalance($user_id, $amount, $order_id) {
        // تحديث رصيد المسوق
        $this->conn->query("
            INSERT INTO marketer_balance (user_id, available_balance, total_earnings, last_updated)
            VALUES ($user_id, $amount, $amount, NOW())
            ON DUPLICATE KEY UPDATE 
            available_balance = available_balance + $amount,
            total_earnings = total_earnings + $amount,
            last_updated = NOW()
        ");
        
        // تسجيل في سجل العمولات
        $this->conn->query("
            INSERT INTO commission_history (user_id, order_id, amount, type, status, description)
            VALUES ($user_id, $order_id, $amount, 'order_commission', 'available', 'عمولة طلب #$order_id')
        ");
    }
    
    // إضافة رصيد معلق
    private function addPendingBalance($user_id, $amount, $order_id) {
        $this->conn->query("
            INSERT INTO marketer_balance (user_id, pending_balance, last_updated)
            VALUES ($user_id, $amount, NOW())
            ON DUPLICATE KEY UPDATE 
            pending_balance = pending_balance + $amount,
            last_updated = NOW()
        ");
        
        $this->conn->query("
            INSERT INTO commission_history (user_id, order_id, amount, type, status, description)
            VALUES ($user_id, $order_id, $amount, 'order_commission', 'pending', 'عمولة معلقة - طلب #$order_id')
        ");
    }
    
    // إزالة رصيد متاح (عند تغيير الحالة)
    private function removeAvailableBalance($user_id, $amount, $order_id) {
        $this->conn->query("
            UPDATE marketer_balance 
            SET available_balance = available_balance - $amount,
                total_earnings = total_earnings - $amount,
                last_updated = NOW()
            WHERE user_id = $user_id
        ");
    }
    
    // إزالة رصيد معلق (عند تغيير الحالة)
    private function removePendingBalance($user_id, $amount, $order_id) {
        $this->conn->query("
            UPDATE marketer_balance 
            SET pending_balance = pending_balance - $amount,
                last_updated = NOW()
            WHERE user_id = $user_id
        ");
    }
    
    // إلغاء العمولة
    private function cancelCommission($user_id, $amount, $order_id) {
        $this->conn->query("
            INSERT INTO commission_history (user_id, order_id, amount, type, status, description)
            VALUES ($user_id, $order_id, $amount, 'order_commission', 'cancelled', 'عمولة ملغاة - طلب #$order_id')
        ");
    }
    
    // سحب رصيد
    public function processWithdrawal($withdrawal_id) {
        $withdrawal = $this->conn->query("
            SELECT * FROM withdrawals WHERE id = $withdrawal_id
        ")->fetch_assoc();
        
        if (!$withdrawal) return false;
        
        $user_id = $withdrawal['user_id'];
        $amount = $withdrawal['amount'];
        
        // التحقق من وجود رصيد كافي
        $balance = $this->getUserBalance($user_id);
        if ($balance['available_balance'] < $amount) {
            return false;
        }
        
        // خصم المبلغ من الرصيد المتاح وإضافته للمسحوب
        $this->conn->query("
            UPDATE marketer_balance 
            SET available_balance = available_balance - $amount,
                withdrawn_balance = withdrawn_balance + $amount,
                last_updated = NOW()
            WHERE user_id = $user_id
        ");
        
        // تسجيل في سجل العمولات
        $this->conn->query("
            INSERT INTO commission_history (user_id, order_id, amount, type, status, description)
            VALUES ($user_id, 0, $amount, 'withdrawal', 'withdrawn', 'سحب رصيد - طلب سحب #$withdrawal_id')
        ");
        
        return true;
    }
    
    // الحصول على رصيد المستخدم
    public function getUserBalance($user_id) {
        $result = $this->conn->query("
            SELECT * FROM marketer_balance WHERE user_id = $user_id
        ");
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        } else {
            // إنشاء سجل جديد إذا لم يكن موجوداً
            $this->conn->query("
                INSERT INTO marketer_balance (user_id, total_earnings, available_balance, pending_balance, withdrawn_balance) 
                VALUES ($user_id, 0, 0, 0, 0)
            ");
            return [
                'total_earnings' => 0,
                'available_balance' => 0,
                'pending_balance' => 0,
                'withdrawn_balance' => 0
            ];
        }
    }
    
    // تحديث قسري للرصيد
    public function refreshUserBalance($user_id) {
        // حساب الأرباح من الطلبات المكتملة
        $earnings_query = $this->conn->query("
            SELECT COALESCE(SUM(commission_total), 0) as total_net, 
                   COALESCE(SUM(total_special_commission), 0) as total_special
            FROM orders 
            WHERE user_id = $user_id AND status IN ('تم التوصيل', 'محصل', 'مكتمل')
        ");
        $earnings_row = $earnings_query->fetch_assoc();
        $earnings = $earnings_row['total_net'];
        $total_special = $earnings_row['total_special'];
        
        // حساب الطلبات المعلقة
        $pending_query = $this->conn->query("
            SELECT COALESCE(SUM(commission_total), 0) as total 
            FROM orders 
            WHERE user_id = $user_id AND status NOT IN ('تم التوصيل', 'محصل', 'ملغي', 'مرفوض', 'مكتمل')
        ");
        $pending = $pending_query->fetch_assoc()['total'];
        
        // حساب المبلغ المسحوب
        $withdrawn_query = $this->conn->query("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM withdrawals 
            WHERE user_id = $user_id AND status = 'مكتمل'
        ");
        $withdrawn = $withdrawn_query->fetch_assoc()['total'];
        
        // الرصيد المتاح
        $available = $earnings - $withdrawn;
        if ($available < 0) $available = 0;
        
        // تحديث الرصيد
        $this->conn->query("
            INSERT INTO marketer_balance (user_id, total_earnings, available_balance, pending_balance, withdrawn_balance) 
            VALUES ($user_id, $earnings, $available, $pending, $withdrawn)
            ON DUPLICATE KEY UPDATE 
            total_earnings = $earnings,
            available_balance = $available,
            pending_balance = $pending,
            withdrawn_balance = $withdrawn
        ");
        
        return [
            'total_earnings' => $earnings,
            'available_balance' => $available,
            'pending_balance' => $pending,
            'withdrawn_balance' => $withdrawn,
            'total_special' => $total_special
        ];
    }
    
    // تفاصيل الطلب
    private function getOrderDetails($order_id) {
        $result = $this->conn->query("
            SELECT * FROM orders WHERE id = $order_id
        ");
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
}
?>