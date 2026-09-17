<?php
// ملف: trigger_commission_on_delivery.php
// يتم تشغيله عند تحديث حالة الطلب

// الاتصال المباشر بقاعدة البيانات
$servername = "127.0.0.1";
$username = "u497700233_medhatomar5555"; 
$password = "BelalOmar49988155$";
$dbname = "u497700233_System";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// إنشاء TRIGGER لإضافة العمولة تلقائياً عند تحديث حالة الطلب
$trigger_sql = "
DELIMITER //

CREATE TRIGGER IF NOT EXISTS after_order_status_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    -- التحقق من أن الحالة الجديدة هي مكتملة والحالة القديمة ليست مكتملة
    IF (NEW.status IN ('تم التوصيل', 'محصل', 'مكتمل') AND 
        OLD.status NOT IN ('تم التوصيل', 'محصل', 'مكتمل') AND
        NEW.user_id IS NOT NULL AND 
        NEW.commission_total > 0) THEN
        
        -- التحقق من أن المستخدم مسوق
        DECLARE is_marketer INT;
        SELECT COUNT(*) INTO is_marketer 
        FROM users 
        WHERE id = NEW.user_id AND user_type = 'مسوق';
        
        IF is_marketer > 0 THEN
            INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status, created_at)
            VALUES (NEW.user_id, NEW.id, NEW.commission_total, 'مكتمل', NOW());
            
            -- تحديث إجمالي العمولات للمسوق
            UPDATE users 
            SET total_commissions = total_commissions + NEW.commission_total
            WHERE id = NEW.user_id;
        END IF;
    END IF;
END//

DELIMITER ;
";

if ($conn->multi_query($trigger_sql)) {
    echo "تم إنشاء TRIGGER لإضافة العمولات تلقائياً بنجاح!<br>";
    
    // مسح النتائج
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
} else {
    echo "خطأ في إنشاء TRIGGER: " . $conn->error . "<br>";
}

// إنشاء TRIGGER آخر عند إضافة طلب جديد بحالة مكتملة
$trigger_insert_sql = "
DELIMITER //

CREATE TRIGGER IF NOT EXISTS after_order_insert_delivered
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    -- إذا تم إضافة طلب بحالة مكتملة مباشرة
    IF (NEW.status IN ('تم التوصيل', 'محصل', 'مكتمل') AND
        NEW.user_id IS NOT NULL AND 
        NEW.commission_total > 0) THEN
        
        -- التحقق من أن المستخدم مسوق
        DECLARE is_marketer INT;
        SELECT COUNT(*) INTO is_marketer 
        FROM users 
        WHERE id = NEW.user_id AND user_type = 'مسوق';
        
        IF is_marketer > 0 THEN
            INSERT INTO marketer_commissions (user_id, order_id, commission_amount, status, created_at)
            VALUES (NEW.user_id, NEW.id, NEW.commission_total, 'مكتمل', NOW());
            
            -- تحديث إجمالي العمولات للمسوق
            UPDATE users 
            SET total_commissions = total_commissions + NEW.commission_total
            WHERE id = NEW.user_id;
        END IF;
    END IF;
END//

DELIMITER ;
";

if ($conn->multi_query($trigger_insert_sql)) {
    echo "تم إنشاء TRIGGER للطلبات الجديدة بنجاح!<br>";
    
    // مسح النتائج
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
} else {
    echo "خطأ في إنشاء TRIGGER للطلبات الجديدة: " . $conn->error . "<br>";
}

// عرض الـ TRIGGERs الحالية
echo "<br><strong>الـ TRIGGERs الحالية:</strong><br>";
$triggers = $conn->query("SHOW TRIGGERS");
while ($trigger = $triggers->fetch_assoc()) {
    echo "- " . $trigger['Trigger'] . " (على جدول " . $trigger['Table'] . ")<br>";
}

$conn->close();
?>
