<?php
// miske.php - Landing Page for Miske Product
require_once 'config.php'; // Includes DB connection

// Auto-create table if not exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS landing_page_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    governorate VARCHAR(100) NOT NULL,
    product_package VARCHAR(255) NOT NULL,
    product_name VARCHAR(100) DEFAULT 'Miske',
    marketer_code VARCHAR(100) NOT NULL,
    status ENUM('new', 'processed', 'cancelled') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (isset($conn) && $conn) {
    $conn->query($createTableSQL);
}

$source = 'Organic';
if (isset($_SERVER['PATH_INFO'])) {
    $path = trim($_SERVER['PATH_INFO'], '/');
    if (!empty($path)) {
        $source = htmlspecialchars($path);
    }
} elseif (isset($_GET['source'])) {
    $source = htmlspecialchars($_GET['source']);
}
$successMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($conn) && $conn) {
        $name = $conn->real_escape_string($_POST['name']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $gov = $conn->real_escape_string($_POST['governorate']);
        $package = $conn->real_escape_string($_POST['package']);
        
        $insertSQL = "INSERT INTO landing_page_leads (customer_name, customer_phone, governorate, product_package, product_name, marketer_code) 
                      VALUES ('$name', '$phone', '$gov', '$package', 'Miske', '$source')";
                      
        if ($conn->query($insertSQL) === TRUE) {
            $successMessage = 'تم تسجيل طلبك بنجاح! سنتواصل معك قريباً لتأكيد الشحن.';
        } else {
            $successMessage = 'حدث خطأ أثناء تسجيل الطلب. يرجى المحاولة مرة أخرى.';
        }
    } else {
        $successMessage = 'عذراً، حدث خطأ في الاتصال بقاعدة البيانات.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Miske - مسكي للتخسيس الطبيعي</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; }
        .hero-bg { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-emerald-500 selection:text-white">

    <!-- Header / Hero Section -->
    <header class="hero-bg text-white py-16 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-64 h-64 bg-emerald-900 opacity-20 rounded-full blur-2xl"></div>
        
        <div class="container mx-auto px-4 relative z-10 text-center">
            <h1 class="text-5xl md:text-7xl font-black mb-6 tracking-tight drop-shadow-md text-emerald-50">MISKE</h1>
            <h2 class="text-2xl md:text-4xl font-bold mb-4 leading-tight">كورس "مسكي" للتخسيس الطبيعي</h2>
            <p class="text-lg md:text-xl text-emerald-100 max-w-2xl mx-auto font-medium">
                هيساعدك على نزول وزنك بشكل طبيعي وأمن تماماً.<br>
                بدون حرمان قاسي، بدون كيماويات، ومناسب لمرضى الضغط والسكر.
            </p>
        </div>
    </header>

    <!-- Features Section -->
    <section class="py-12 bg-white">
        <div class="container mx-auto px-4 max-w-4xl">
            <h3 class="text-center text-3xl font-bold text-slate-800 mb-10">لماذا تختار <span class="text-emerald-600">Miske</span> ؟</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-slate-50 p-6 rounded-2xl text-center border border-slate-100 shadow-sm hover:shadow-md transition">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                        <i class='bx bx-check-shield'></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">طبيعي 100%</h4>
                    <p class="text-slate-500 text-sm">مكونات نباتية فعالة وأمنة تماماً على الصحة العامة.</p>
                </div>
                <div class="bg-slate-50 p-6 rounded-2xl text-center border border-slate-100 shadow-sm hover:shadow-md transition">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                        <i class='bx bx-restaurant'></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">تنظيم الشهية</h4>
                    <p class="text-slate-500 text-sm">يقلل الشعور بالجوع ويمنحك إحساساً طبيعياً بالشبع.</p>
                </div>
                <div class="bg-slate-50 p-6 rounded-2xl text-center border border-slate-100 shadow-sm hover:shadow-md transition">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                        <i class='bx bx-run'></i>
                    </div>
                    <h4 class="font-bold text-lg mb-2">حرق الدهون</h4>
                    <p class="text-slate-500 text-sm">يحفز الحرق الطبيعي للدهون المتراكمة بفعالية.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Form Section -->
    <section class="py-16 bg-slate-50" id="order">
        <div class="container mx-auto px-4 max-w-xl">
            <div class="bg-white rounded-3xl shadow-xl p-8 md:p-10 border border-slate-100">
                <div class="text-center mb-8">
                    <div class="inline-block bg-emerald-100 text-emerald-600 px-4 py-1.5 rounded-full text-sm font-bold mb-4">اطلب الآن الدفع عند الاستلام</div>
                    <h3 class="text-2xl font-bold text-slate-800">سجل بياناتك وسنتواصل معك للشحن</h3>
                </div>

                <?php if ($successMessage): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-4 rounded-xl mb-6 flex items-center shadow-sm">
                        <i class='bx bxs-check-circle text-2xl mr-3 ml-3 text-emerald-500'></i>
                        <span class="font-bold text-lg"><?= $successMessage ?></span>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">الاسم بالكامل</label>
                                <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">رقم الهاتف</label>
                                <input type="tel" name="phone" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition" placeholder="مثال: 01000000000" dir="ltr">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">المحافظة</label>
                                <select name="governorate" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition">
                                    <option value="" disabled selected>اختر المحافظة...</option>
                                    <option value="القاهرة">القاهرة</option>
                                    <option value="الجيزة">الجيزة</option>
                                    <option value="الإسكندرية">الإسكندرية</option>
                                    <option value="الدقهلية">الدقهلية</option>
                                    <option value="الشرقية">الشرقية</option>
                                    <option value="المنوفية">المنوفية</option>
                                    <option value="الغربية">الغربية</option>
                                    <option value="البحيرة">البحيرة</option>
                                    <option value="القليوبية">القليوبية</option>
                                    <option value="باقي المحافظات">أخرى (سيتم تحديدها في المكالمة)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">المنتج المطلوب</label>
                                <select name="package" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition font-bold">
                                    <option value="" disabled selected>اختر الكورس المناسب لك...</option>
                                    <option value="كورس شهر (1 قطعة) / 890ج">كورس شهر (1 قطعة) - 890 جنيه</option>
                                    <option value="كورس شهرين (2 قطعة) / 1600ج">كورس شهرين (2 قطعة) - 1600 جنيه</option>
                                    <option value="كورس 3 شهور (3 قطعة) / 2000ج">كورس 3 شهور (3 قطعة) - 2000 جنيه (الأوفر)</option>
                                </select>
                            </div>

                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg py-4 rounded-xl shadow-lg shadow-emerald-500/30 transition transform hover:-translate-y-1 mt-4">
                                تأكيد الطلب
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="bg-slate-900 text-slate-400 py-8 text-center text-sm">
        <div class="container mx-auto px-4">
            <p>جميع الحقوق محفوظة &copy; 2026 Miske.</p>
        </div>
    </footer>

</body>
</html>
