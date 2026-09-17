```php
<?php
// ملف إنشاء جدول العمولات للمسوقين

// Start output buffering
ob_start();

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// التعامل مع طلبات OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// الاتصال بقاعدة البيانات
$servername = "127.0.0.1";
$username   = "u497700233_medhatomar5555";
$password   = "BelalOmar49988155$";
$dbname     = "u497700233_System";

$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {

    ob_end_clean();

    echo json_encode([
        "success" => false,
        "message" => "فشل الاتصال بقاعدة البيانات: " . $conn->connect_error
    ], JSON_UNESCAPED_UNICODE);

    exit();
}

// ضبط الترميز
$conn->set_charset("utf8mb4");

// بدء Transaction
$conn->begin_transaction();

try {

    // إنشاء جدول العمولات
    $create_commissions_table = "
        CREATE TABLE IF NOT EXISTS marketer_commissions (

            id INT AUTO_INCREMENT PRIMARY KEY,

            user_id INT NOT NULL,

            order_id INT NOT NULL,

            commission_amount DECIMAL(10,2) NOT NULL,

            status ENUM('قيد المراجعة', 'مكتمل', 'ملغي')
            DEFAULT 'قيد المراجعة',

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE CASCADE,

            FOREIGN KEY (order_id)
            REFERENCES orders(id)
            ON DELETE CASCADE,

            INDEX idx_user_id (user_id),
            INDEX idx_order_id (order_id),
            INDEX idx_status (status)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci;
    ";

    if (!$conn->query($create_commissions_table)) {

        throw new Exception(
            "فشل في إنشاء جدول marketer_commissions: " . $conn->error
        );
    }

    // التحقق من وجود العمود total_commissions
    $check_column = $conn->query(
        "SHOW COLUMNS FROM users LIKE 'total_commissions'"
    );

    // إضافة العمود إذا لم يكن موجوداً
    if ($check_column->num_rows == 0) {

        $add_column = "
            ALTER TABLE users
            ADD COLUMN total_commissions DECIMAL(10,2) DEFAULT 0.00
        ";

        if (!$conn->query($add_column)) {

            throw new Exception(
                "فشل في إضافة عمود total_commissions: " . $conn->error
            );
        }
    }

    // تحديث إجمالي العمولات للمسوقين
    $update_existing_commissions = "
        UPDATE users u

        SET total_commissions = (

            SELECT COALESCE(SUM(o.commission_total), 0)

            FROM orders o

            WHERE o.user_id = u.id

            AND o.status IN ('تم التوصيل', 'محصل', 'مكتمل')

        )

        WHERE u.user_type = 'مسوق'
    ";

    if (!$conn->query($update_existing_commissions)) {

        throw new Exception(
            "فشل في تحديث إجمالي العمولات: " . $conn->error
        );
    }

    // نقل العمولات من الطلبات المكتملة
    $migrate_commissions = "
        INSERT INTO marketer_commissions (
            user_id,
            order_id,
            commission_amount,
            status,
            created_at
        )

        SELECT
            o.user_id,
            o.id,
            o.commission_total,
            'مكتمل',
            o.created_at

        FROM orders o

        WHERE o.status IN ('تم التوصيل', 'محصل', 'مكتمل')

        AND o.user_id IS NOT NULL

        AND o.commission_total > 0

        AND o.user_id IN (
            SELECT id
            FROM users
            WHERE user_type = 'مسوق'
        )

        AND NOT EXISTS (

            SELECT 1

            FROM marketer_commissions mc

            WHERE mc.order_id = o.id
        )
    ";

    if (!$conn->query($migrate_commissions)) {

        throw new Exception(
            "فشل في نقل العمولات: " . $conn->error
        );
    }

    // حفظ التغييرات
    $conn->commit();

    ob_end_clean();

    echo json_encode([

        "success" => true,

        "message" => "تم إنشاء جدول العمولات وتحديث البيانات بنجاح",

        "details" => [

            "table_created" =>
                "marketer_commissions",

            "column_added" =>
                "total_commissions في جدول users",

            "existing_commissions_updated" =>
                "تم تحديث إجمالي العمولات للمسوقين الحاليين",

            "commissions_migrated" =>
                "تم نقل العمولات من الطلبات المكتملة"
        ]

    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    // Rollback في حالة الخطأ
    $conn->rollback();

    // تنظيف أي Output سابق
    while (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode([

        "success" => false,

        "message" => "حدث خطأ: " . $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);
}

// إغلاق الاتصال
$conn->close();

?>
```
