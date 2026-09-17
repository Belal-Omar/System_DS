<?php
// ملف: navbar.php - Navbar موحد لجميع الصفحات
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
$current_page = str_replace(['.php', '.html'], '', $current_page);

// الحصول على بيانات المستخدم إذا كان مسجل دخول
$user_name = '';
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    if (!isset($conn)) {
        include(__DIR__ . '/../core/config.php");
    }
    $user_query = $conn->query("SELECT fullname FROM users WHERE id = $user_id");
    if ($user_query && $user_query->num_rows > 0) {
        $user = $user_query->fetch_assoc();
        $user_name = $user['fullname'];
    }
}
?>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<nav style="position: fixed; top: 1rem; left: 50%; transform: translateX(-50%); width: 90%; max-width: 72rem; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px); box-shadow: 0 4px 18px rgba(0, 0, 0, 0.1); border-radius: 1rem; z-index: 1000; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 2rem;">
        <a href="Home1.html" style="font-size: 2rem; font-weight: 900; color: #4b6b2f; letter-spacing: 1px; text-transform: uppercase; text-decoration: none; transition: all 0.3s ease;">
            لوحة المسوق
        </a>
        <div style="display: flex; gap: 1.5rem; align-items: center;">
            <a href="Home1.html" style="font-weight: 600; color: <?php echo ($current_page == 'Home1' || $current_page == 'home') ? '#4b6b2f' : '#1e293b'; ?>; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                الرئيسية
                <?php if ($current_page == 'Home1' || $current_page == 'home'): ?>
                    <span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>
                <?php endif; ?>
            </a>
            <a href="product.html" style="font-weight: 600; color: <?php echo ($current_page == 'product') ? '#4b6b2f' : '#1e293b'; ?>; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                المنتجات
                <?php if ($current_page == 'product'): ?>
                    <span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>
                <?php endif; ?>
            </a>
            <a href="orders.html" style="font-weight: 600; color: <?php echo ($current_page == 'orders') ? '#4b6b2f' : '#1e293b'; ?>; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                الطلبات
                <?php if ($current_page == 'orders'): ?>
                    <span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>
                <?php endif; ?>
            </a>
            <a href="withdrawals.php" style="font-weight: 600; color: <?php echo ($current_page == 'withdrawals') ? '#4b6b2f' : '#1e293b'; ?>; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                السحب
                <?php if ($current_page == 'withdrawals'): ?>
                    <span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <?php if ($user_id && $user_name): ?>
            <!-- Notification Bell -->
            <div style="position: relative;" class="nav-notif-container">
                <button id="nav-notification-bell" style="background: none; border: none; cursor: pointer; padding: 0.5rem; position: relative; color: #4b6b2f;">
                    <i class='bx bx-bell' style="font-size: 1.5rem;"></i>
                    <span id="nav-notification-badge" style="position: absolute; top: 0; right: 0; background: #ef4444; color: white; font-size: 0.65rem; font-weight: bold; border-radius: 50%; height: 18px; width: 18px; display: none; align-items: center; justify-content: center;">0</span>
                </button>
                <div id="nav-notification-dropdown" class="hidden" style="position: absolute; left: 0; top: 100%; margin-top: 0.5rem; width: 350px; min-width: 350px; background: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden; z-index: 1050; display: none;">
                    <div style="padding: 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold; color: #334155;">الإشعارات</span>
                        <button id="nav-notification-mark-all" style="background:none; border:none; color: #4f46e5; font-size: 0.75rem; cursor: pointer;">تحديد الكل كمقروء</button>
                    </div>
                    <div id="nav-notification-list" style="max-height: 320px; overflow-y: auto;">
                        <div style="padding: 1rem; text-align: center; color: #64748b; font-size: 0.875rem;">جاري التحميل...</div>
                    </div>
                </div>
            </div>
            
            <a href="profile.php" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: #1e293b; font-weight: 600; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s ease; background: rgba(75, 107, 47, 0.1);">
                <i class='bx bx-user-circle' style="font-size: 1.5rem; color: #4b6b2f;"></i>
                <span><?php echo htmlspecialchars($user_name); ?></span>
            </a>
            <a href="logout.php" style="background: #ef4444; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                تسجيل الخروج
            </a>
        <?php else: ?>
            <a href="login.html" style="background: #4b6b2f; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                تسجيل الدخول
            </a>
            <a href="register.html" style="background: transparent; color: #4b6b2f; border: 2px solid #4b6b2f; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                إنشاء حساب
            </a>
        <?php endif; ?>
    </div>
</nav>
<style>
.nav-notif-container #nav-notification-dropdown.hidden { display: none !important; }
.nav-notif-container #nav-notification-dropdown:not(.hidden) { display: block !important; }
</style>
<script src="bell_notifications.js?v=<?= time() ?>"></script>

<script>
setInterval(function() { fetch('keep_alive.php').catch(() => {}); }, 600000);
</script>
