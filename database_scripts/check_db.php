<?php
// ملف: check_db.php (للتشخيص فقط)
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>فحص الاتصال بقاعدة البيانات</h1>";

// محاولة تضمين ملف الإعدادات
echo "<h3>1. فحص ملف config.php</h3>";
if (file_exists("config.php")) {
    echo "ملف config.php موجود.<br>";
    include(__DIR__ . '/core/config.php");
} else {
    echo "<span style='color:red'>خطأ: ملف config.php غير موجود!</span><br>";
    exit;
}

echo "<h3>2. فحص متغير الاتصال</h3>";
if (isset($conn) && $conn instanceof mysqli) {
    echo "متغير \$conn موجود وهو كائن mysqli.<br>";
    
    if ($conn->connect_error) {
        echo "<span style='color:red'>فشل الاتصال: " . $conn->connect_error . "</span><br>";
    } else {
        echo "<span style='color:green'>الاتصال ناجح!</span><br>";
        echo "معلومات السيرفر: " . $conn->server_info . "<br>";
        echo "معلومات المضيف: " . $conn->host_info . "<br>";
        
        // فحص الترميز
        echo "الترميز الحالي: " . $conn->character_set_name() . "<br>";
        
        echo "<h3>3. فحص الجداول</h3>";
        $tables = $conn->query("SHOW TABLES");
        if ($tables) {
            echo "تم جلب الجداول بنجاح. عدد الجداول: " . $tables->num_rows . "<br>";
            echo "<ul>";
            while ($row = $tables->fetch_array()) {
                echo "<li>" . $row[0] . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<span style='color:red'>فشل جلب الجداول: " . $conn->error . "</span><br>";
        }
        
        echo "<h3>4. فحص الأعمدة (products)</h3>";
        $cols = $conn->query("SHOW COLUMNS FROM products");
        if ($cols) {
            echo "<table border='1' cellspacing='0' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Default</th></tr>";
            while ($row = $cols->fetch_assoc()) {
                $highlight = ($row['Field'] == 'special_commission') ? "style='background:yellow'" : "";
                echo "<tr $highlight><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Default']}</td></tr>";
            }
            echo "</table>";
        } else {
             echo "<span style='color:red'>فشل جلب أعمدة products (قد يكون الجدول غير موجود)</span><br>";
        }
        
    }
} else {
    echo "<span style='color:red'>متغير \$conn غير معرف أو ليس كائن mysqli. (ربما تم كبت الخطأ في config.php)</span><br>";
    
    // محاولة الاتصال اليدوي لإظهار الخطأ
    echo "جاري محاولة الاتصال اليدوي لإظهار الخطأ الكامن...<br>";
    $test_conn = new mysqli($servername, $username, $password, $dbname, 3306);
    if ($test_conn->connect_error) {
         echo "<span style='color:red'>الخطأ الحقيقي: " . $test_conn->connect_error . "</span><br>";
    } else {
         echo "<span style='color:green'>نجح الاتصال اليدوي! المشكلة في config.php نفسه.</span><br>";
    }
}
?>
