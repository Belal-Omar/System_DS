<?php
// ملف: add_shipping_cities.php
header("Content-Type: text/html; charset=UTF-8");
include(__DIR__ . '/core/config.php");

// قائمة المدن وتكاليف الشحن
$cities = [
    ['city_name' => 'طرابلس', 'shipping_cost' => 5.00],
    ['city_name' => 'بنغازي', 'shipping_cost' => 8.00],
    ['city_name' => 'مصراتة', 'shipping_cost' => 6.00],
    ['city_name' => 'زليتن', 'shipping_cost' => 7.00],
    ['city_name' => 'أجدابيا', 'shipping_cost' => 10.00],
    ['city_name' => 'البيضاء', 'shipping_cost' => 9.00],
    ['city_name' => 'طبرق', 'shipping_cost' => 12.00],
    ['city_name' => 'الخمس', 'shipping_cost' => 11.00],
    ['city_name' => 'درنة', 'shipping_cost' => 13.00],
    ['city_name' => 'سرت', 'shipping_cost' => 14.00],
    ['city_name' => 'غدامس', 'shipping_cost' => 15.00],
    ['city_name' => 'غات', 'shipping_cost' => 16.00],
    ['city_name' => 'الجفرة', 'shipping_cost' => 17.00],
    ['city_name' => 'الكفرة', 'shipping_cost' => 18.00],
    ['city_name' => 'أوباري', 'shipping_cost' => 19.00],
    ['city_name' => 'مرزق', 'shipping_cost' => 20.00],
    ['city_name' => 'البراك', 'shipping_cost' => 21.00],
    ['city_name' => 'تازربو', 'shipping_cost' => 22.00],
    ['city_name' => 'تمنهنت', 'shipping_cost' => 23.00],
    ['city_name' => 'الجغبوب', 'shipping_cost' => 24.00],
    ['city_name' => 'القبة', 'shipping_cost' => 25.00],
    ['city_name' => 'الجفارة', 'shipping_cost' => 7.50],
    ['city_name' => 'الزوارة', 'shipping_cost' => 8.50],
    ['city_name' => 'الرجبان', 'shipping_cost' => 9.50],
    ['city_name' => 'صبراتة', 'shipping_cost' => 10.50],
    ['city_name' => 'الجميل', 'shipping_cost' => 11.50],
    ['city_name' => 'الزنتان', 'shipping_cost' => 12.50],
    ['city_name' => 'الواحة', 'shipping_cost' => 13.50],
    ['city_name' => 'رأس لانوف', 'shipping_cost' => 14.50],
    ['city_name' => 'أم الأرانب', 'shipping_cost' => 15.50],
    ['city_name' => 'سوق الجمعة', 'shipping_cost' => 16.50],
    ['city_name' => 'تاجوراء', 'shipping_cost' => 17.50],
    ['city_name' => 'الكفرة الجديدة', 'shipping_cost' => 18.50],
    ['city_name' => 'بني وليد', 'shipping_cost' => 6.50],
    ['city_name' => 'ترهونة', 'shipping_cost' => 5.50],
    ['city_name' => 'مسلاتة', 'shipping_cost' => 7.00],
    ['city_name' => 'تاجورة', 'shipping_cost' => 19.50],
    ['city_name' => 'الفزاني', 'shipping_cost' => 20.50],
    ['city_name' => 'القريات', 'shipping_cost' => 21.50],
    ['city_name' => 'المرج', 'shipping_cost' => 4.50],
    ['city_name' => 'خمس', 'shipping_cost' => 11.00],
    ['city_name' => 'البيضان', 'shipping_cost' => 12.00],
    ['city_name' => 'النقاز', 'shipping_cost' => 13.00],
    ['city_name' => 'العقيلة', 'shipping_cost' => 14.00],
    ['city_name' => 'غريان', 'shipping_cost' => 8.00],
    ['city_name' => 'مزدة', 'shipping_cost' => 9.00],
    ['city_name' => 'سواني', 'shipping_cost' => 10.00],
    ['city_name' => 'الوادي', 'shipping_cost' => 11.00],
    ['city_name' => 'الشعيفة', 'shipping_cost' => 12.00],
    ['city_name' => 'البريكة', 'shipping_cost' => 13.00],
    ['city_name' => 'القصر', 'shipping_cost' => 14.00],
    ['city_name' => 'القرمان', 'shipping_cost' => 15.00],
    ['city_name' => 'النوفلية', 'shipping_cost' => 5.50],
    ['city_name' => 'جندوبة', 'shipping_cost' => 6.00],
    ['city_name' => 'كاباو', 'shipping_cost' => 7.00],
    ['city_name' => 'القلعة', 'shipping_cost' => 8.00],
    ['city_name' => 'الجديد', 'shipping_cost' => 9.00],
    ['city_name' => 'الزعفران', 'shipping_cost' => 10.00],
    ['city_name' => 'الشورى', 'shipping_cost' => 11.00],
    ['city_name' => 'أم الحجاج', 'shipping_cost' => 12.00],
    ['city_name' => 'القاهرة', 'shipping_cost' => 13.00],
    ['city_name' => 'المنطقة الصناعية', 'shipping_cost' => 6.00],
    ['city_name' => 'السواني', 'shipping_cost' => 7.00],
    ['city_name' => 'العوينات', 'shipping_cost' => 8.00],
    ['city_name' => 'بئر الغنم', 'shipping_cost' => 9.00],
    ['city_name' => 'الغدبية', 'shipping_cost' => 10.00],
    ['city_name' => 'المرج', 'shipping_cost' => 4.50],
    ['city_name' => 'سيدي عبدالله', 'shipping_cost' => 5.00],
    ['city_name' => 'سيدي السوسي', 'shipping_cost' => 5.50],
    ['city_name' => 'سيدي غيث', 'shipping_cost' => 6.00],
    ['city_name' => 'سيدي خليفة', 'shipping_cost' => 6.50],
    ['city_name' => 'سيدي حميدة', 'shipping_cost' => 7.00],
    ['city_name' => 'سيدي مسعود', 'shipping_cost' => 7.50],
    ['city_name' => 'سيدي عمران', 'shipping_cost' => 8.00],
    ['city_name' => 'سيدي يوسف', 'shipping_cost' => 8.50],
    ['city_name' => 'سيدي عبد العزيز', 'shipping_cost' => 9.00],
    ['city_name' => 'سيدي الصالح', 'shipping_cost' => 9.50],
    ['city_name' => 'سيدي المنصور', 'shipping_cost' => 10.00],
    ['city_name' => 'سيدي مبارك', 'shipping_cost' => 10.50],
    ['city_name' => 'سيدي محمد', 'shipping_cost' => 11.00],
    ['city_name' => 'سيدي إبراهيم', 'shipping_cost' => 11.50],
    ['city_name' => 'سيدي أحمد', 'shipping_cost' => 12.00],
    ['city_name' => 'سيدي خالد', 'shipping_cost' => 12.50],
    ['city_name' => 'سيدي الطيب', 'shipping_cost' => 13.00],
    ['city_name' => 'سيدي الحاج', 'shipping_cost' => 13.50],
    ['city_name' => 'سيدي العيد', 'shipping_cost' => 14.00],
    ['city_name' => 'سيدي الشريف', 'shipping_cost' => 14.50],
    ['city_name' => 'سيدي أمحمد', 'shipping_cost' => 15.00],
    ['city_name' => 'سيدي زيان', 'shipping_cost' => 15.50],
    ['city_name' => 'سيدي بوزيد', 'shipping_cost' => 16.00],
    ['city_name' => 'سيدي بوسعيد', 'shipping_cost' => 16.50],
    ['city_name' => 'سيدي بلقاسم', 'shipping_cost' => 17.00],
    ['city_name' => 'سيدي بوركبة', 'shipping_cost' => 17.50],
    ['city_name' => 'سيدي براك', 'shipping_cost' => 18.00],
    ['city_name' => 'سيدي بولنوار', 'shipping_cost' => 18.50],
    ['city_name' => 'سيدي بوعلي', 'shipping_cost' => 19.00],
    ['city_name' => 'سيدي بوشعيب', 'shipping_cost' => 19.50],
    ['city_name' => 'سيدي بوسحاق', 'shipping_cost' => 20.00],
    ['city_name' => 'سيدي بوسالم', 'shipping_cost' => 20.50],
    ['city_name' => 'سيدي بومعيزة', 'shipping_cost' => 21.00],
    ['city_name' => 'سيدي بومدين', 'shipping_cost' => 21.50],
    ['city_name' => 'سيدي بومزيان', 'shipping_cost' => 22.00],
    ['city_name' => 'سيدي بومرزاق', 'shipping_cost' => 22.50],
    ['city_name' => 'سيدي بوربيع', 'shipping_cost' => 23.00],
    ['city_name' => 'سيدي بورحيل', 'shipping_cost' => 23.50],
    ['city_name' => 'سيدي بوركوس', 'shipping_cost' => 24.00],
    ['city_name' => 'سيدي بورنان', 'shipping_cost' => 24.50],
    ['city_name' => 'سيدي بورقبة', 'shipping_cost' => 25.00],
    ['city_name' => 'سيدي بورقعة', 'shipping_cost' => 25.50],
    ['city_name' => 'سيدي بورفعة', 'shipping_cost' => 26.00],
    ['city_name' => 'سيدي بورفيس', 'shipping_cost' => 26.50],
    ['city_name' => 'سيدي بورقان', 'shipping_cost' => 27.00],
    ['city_name' => 'سيدي بورحمان', 'shipping_cost' => 27.50],
    ['city_name' => 'سيدي بورحيم', 'shipping_cost' => 28.00],
    ['city_name' => 'سيدي بورحسين', 'shipping_cost' => 28.50],
    ['city_name' => 'سيدي بورحج', 'shipping_cost' => 29.00],
    ['city_name' => 'سيدي بورحاج', 'shipping_cost' => 29.50],
    ['city_name' => 'سيدي بورحاب', 'shipping_cost' => 30.00],
    ['city_name' => 'سيدي بورحام', 'shipping_cost' => 30.50],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 31.00],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 31.50],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 32.00],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 32.50],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 33.00],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 33.50],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 34.00],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 34.50],
    ['city_name' => 'سيدي بورحامد', 'shipping_cost' => 35.00]
];

// إنشاء الجدول إذا لم يكن موجوداً
$conn->query("CREATE TABLE IF NOT EXISTS shipping_cities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(100) NOT NULL UNIQUE,
    shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$added_count = 0;
$skipped_count = 0;
$errors = [];

foreach ($cities as $city) {
    try {
        // التحقق من وجود المدينة
        $check_stmt = $conn->prepare("SELECT id FROM shipping_cities WHERE city_name = ?");
        $check_stmt->bind_param("s", $city['city_name']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // إضافة المدينة الجديدة
            $stmt = $conn->prepare("INSERT INTO shipping_cities (city_name, shipping_cost) VALUES (?, ?)");
            $stmt->bind_param("sd", $city['city_name'], $city['shipping_cost']);
            
            if ($stmt->execute()) {
                $added_count++;
                echo "<p style='color: green;'>✅ تم إضافة: " . htmlspecialchars($city['city_name']) . " - " . $city['shipping_cost'] . " د.ل</p>";
            } else {
                $errors[] = "خطأ في إضافة " . $city['city_name'] . ": " . $stmt->error;
                echo "<p style='color: red;'>❌ خطأ في إضافة: " . htmlspecialchars($city['city_name']) . "</p>";
            }
        } else {
            $skipped_count++;
            echo "<p style='color: orange;'>⚠️ موجودة بالفعل: " . htmlspecialchars($city['city_name']) . "</p>";
        }
    } catch (Exception $e) {
        $errors[] = "استثناء في " . $city['city_name'] . ": " . $e->getMessage();
        echo "<p style='color: red;'>❌ استثناء في: " . htmlspecialchars($city['city_name']) . " - " . $e->getMessage() . "</p>";
    }
}

echo "<h2>📊 ملخص الإضافة:</h2>";
echo "<p>✅ تم إضافة: <strong>$added_count</strong> مدينة جديدة</p>";
echo "<p>⚠️ موجودة بالفعل: <strong>$skipped_count</strong> مدينة</p>";
echo "<p>❌ أخطاء: <strong>" . count($errors) . "</strong></p>";

if (!empty($errors)) {
    echo "<h3>📋 تفاصيل الأخطاء:</h3>";
    foreach ($errors as $error) {
        echo "<p style='color: red;'>• $error</p>";
    }
}

echo "<br><a href='get_cities_api.php' style='background: #4b6b2f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 عرض المدن</a>";
echo "<br><br><a href='Home1.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 العودة للرئيسية</a>";
?>
