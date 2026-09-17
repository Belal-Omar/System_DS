<?php
$is_super_admin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin');
$weekly_seed = "SystemCustomers2026Secret!";
$week_number = date("Y-W");
// Generate a 6-character uppercase password
$weekly_password = strtoupper(substr(md5($weekly_seed . $week_number), 0, 6));

$access_granted = false;
$show_new_password_alert = false;

if ($is_super_admin) {
    // Check if super_admin has seen this week's password
    if (!isset($_COOKIE['customers_pwd_seen_week']) || $_COOKIE['customers_pwd_seen_week'] !== $week_number) {
        // First time this week!
        $show_new_password_alert = true;
        $access_granted = true;
        // Use JS to set cookie since headers are already sent
        echo "<script>document.cookie = 'customers_pwd_seen_week={$week_number}; path=/; max-age=' + (86400 * 30);</script>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_password'])) {
    if (strtoupper(trim($_POST['customer_password'])) === $weekly_password) {
        $_SESSION['customers_unlocked_week'] = $week_number;
        $access_granted = true;
    } else {
        $pass_error = "الرقم السري غير صحيح.";
    }
} elseif (isset($_SESSION['customers_unlocked_week']) && $_SESSION['customers_unlocked_week'] === $week_number) {
    $access_granted = true;
}

if (!$access_granted):
?>
<div class="max-w-md mx-auto mt-20 bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-500 mb-4">
            <i class='bx bx-lock-alt text-3xl'></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">منطقة محمية</h3>
        <p class="text-gray-500 text-sm">يرجى إدخال الرقم السري الخاص بهذا الأسبوع للوصول إلى بيانات العملاء. اطلب الرقم من المدير الرئيسي.</p>
    </div>
    
    <?php if (isset($pass_error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 p-3 rounded-lg mb-4 text-sm text-center font-medium"><?= htmlspecialchars($pass_error) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <input type="text" name="customer_password" class="w-full border-gray-300 rounded-xl p-3 focus:ring-blue-500 focus:border-blue-500 text-center text-lg uppercase tracking-widest font-mono shadow-sm" placeholder="XXXXXX" required autocomplete="off">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white rounded-xl p-3 font-bold hover:bg-blue-700 transition shadow-md">فتح البيانات</button>
    </form>
</div>
<?php
return; // Stop rendering the rest of the page for unauthorized users
endif;
?>

<?php if ($is_super_admin && $show_new_password_alert): ?>
    <div class="mb-6 bg-green-50 border border-green-200 text-green-800 p-6 rounded-xl shadow-md">
        <h3 class="text-xl font-bold mb-2 flex items-center"><i class='bx bx-check-shield text-2xl mr-2'></i> أهلاً بك في الأسبوع الجديد!</h3>
        <p class="text-green-700 mb-4">لقد تم إدخالك مباشرة هذه المرة فقط (لأن الرقم السري تغير). <strong>الرقم السري الجديد</strong> الخاص ببيانات العملاء لهذا الأسبوع هو:</p>
        <div class="inline-block bg-white px-6 py-2 rounded-lg border border-green-300 shadow-sm font-mono text-3xl font-bold tracking-widest text-green-900 mb-2">
            <?= htmlspecialchars($weekly_password) ?>
        </div>
        <p class="text-sm font-bold text-red-600 mt-2"><i class='bx bx-error'></i> يرجى الاحتفاظ بهذا الرقم! في المرة القادمة التي تفتح فيها هذه الصفحة، سيُطلب منك إدخاله.</p>
    </div>
<?php endif; ?>

