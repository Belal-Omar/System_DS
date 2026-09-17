<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'helpers.php';
/** @var mysqli $conn */
// Check permissions
$sessionRole = $_SESSION['admin_role'] ?? '';
$sessionAllowedRaw = $_SESSION['admin_allowed_pages'] ?? null;
if ($sessionRole !== 'super_admin' && !admin_can_access_page('complaints', $sessionRole, $sessionAllowedRaw)) {
    echo "<div class='p-8'><div class='bg-red-100 text-red-700 p-4 rounded-xl font-bold'>ليس مصرح لك بالدخول</div></div>";
    exit;
}

$isAdmin = in_array($sessionRole, ['super_admin', 'admin']);
$currentUserId = (int)($_SESSION['admin_id'] ?? 0);
$defaultName = $_SESSION['admin_fullname'] ?? '';

// Status translation helper
function translate_status($status) {
    $statuses = [
        'pending' => ['text' => 'قيد المراجعة', 'color' => 'bg-gray-100 text-gray-700 border-gray-300', 'icon' => 'bx-time'],
        'in_progress' => ['text' => 'جاري العمل عليها', 'color' => 'bg-blue-100 text-blue-700 border-blue-300', 'icon' => 'bx-loader-circle'],
        'resolved' => ['text' => 'تم الحل / تم القبول', 'color' => 'bg-green-100 text-green-700 border-green-300', 'icon' => 'bx-check-double'],
        'rejected' => ['text' => 'مرفوض', 'color' => 'bg-red-100 text-red-700 border-red-300', 'icon' => 'bx-x-circle']
    ];
    return $statuses[$status] ?? $statuses['pending'];
}

function translate_role($role) {
    $roles = [
        'super_admin' => 'مدير رئيسي',
        'admin' => 'مدير عام',
        'support' => 'دعم فني',
        'marketing' => 'التسويق',
        'shipping_company' => 'شركة شحن'
    ];
    return $roles[$role] ?? $role;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf();
    
    // Action 1: Submit new complaint (Employees)
    if ($_POST['action'] === 'submit_complaint' && !$isAdmin) {
        $sender_name = $conn->real_escape_string(trim($_POST['sender_name']));
        $phone = $conn->real_escape_string(trim($_POST['phone']));
        $type = $_POST['type'] === 'suggestion' ? 'suggestion' : 'complaint';
        $message = $conn->real_escape_string(trim($_POST['message']));
        
        if ($sender_name !== '' && $message !== '') {
            $stmt = $conn->prepare("INSERT INTO system_complaints (sender_id, sender_role, sender_name, phone, type, message, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("isssss", $currentUserId, $sessionRole, $sender_name, $phone, $type, $message);
            
            if ($stmt->execute()) {
                $_SESSION['complaints_flash_success'] = 'تم إرسال رسالتك بنجاح، شكراً لك!';
                
                // Notify Admins
                $notif_title = $type === 'suggestion' ? "اقتراح جديد من $sender_name" : "شكوى جديدة من $sender_name";
                $notif_message = "قسم: " . translate_role($sessionRole);
                $notif_link = "admin_panel.php?page=complaints";
                
                $admins = $conn->query("SELECT id FROM admins WHERE role IN ('super_admin', 'admin')");
                if ($admins) {
                    $insert_notif = $conn->prepare("INSERT INTO system_notifications (recipient_type, recipient_id, title, message, link) VALUES ('admin', ?, ?, ?, ?)");
                    while ($admin = $admins->fetch_assoc()) {
                        $admin_id = $admin['id'];
                        $insert_notif->bind_param("isss", $admin_id, $notif_title, $notif_message, $notif_link);
                        $insert_notif->execute();
                    }
                }
            } else {
                $_SESSION['complaints_flash_error'] = 'حدث خطأ أثناء الإرسال، يرجى المحاولة مرة أخرى.';
            }
        }
        echo "<script>window.location.href='admin_panel.php?page=complaints';</script>";
        exit;
    }
    
    // Action 2: Admin Reply and Status Update (Admins Only)
    if ($_POST['action'] === 'admin_reply' && $isAdmin) {
        $complaint_id = (int)$_POST['complaint_id'];
        $status = in_array($_POST['status'], ['pending', 'in_progress', 'resolved', 'rejected']) ? $_POST['status'] : 'pending';
        $admin_reply = $conn->real_escape_string(trim($_POST['admin_reply']));
        
        // Fetch sender info before updating
        $senderRes = $conn->query("SELECT sender_id, sender_role, type FROM system_complaints WHERE id = $complaint_id");
        $senderData = $senderRes ? $senderRes->fetch_assoc() : null;

        $stmt = $conn->prepare("UPDATE system_complaints SET status = ?, admin_reply = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $admin_reply, $complaint_id);
        if ($stmt->execute()) {
            $_SESSION['complaints_flash_success'] = 'تم تحديث حالة الرسالة وإرسال الرد بنجاح.';
            
            // Notify Sender
            if ($senderData) {
                $recipient_id = $senderData['sender_id'];
                $recipient_type = ($senderData['sender_role'] === 'shipping_company') ? 'shipping_company' : 'admin';
                $msg_type = $senderData['type'] === 'suggestion' ? 'اقتراحك' : 'شكواك';
                
                $notif_title = "تم تحديث حالة $msg_type من قبل الإدارة";
                $notif_message = translate_status($status)['text'];
                $notif_link = "admin_panel.php?page=complaints";
                
                $insert_notif = $conn->prepare("INSERT INTO system_notifications (recipient_type, recipient_id, title, message, link) VALUES (?, ?, ?, ?, ?)");
                $insert_notif->bind_param("sisss", $recipient_type, $recipient_id, $notif_title, $notif_message, $notif_link);
                $insert_notif->execute();
            }
        } else {
            $_SESSION['complaints_flash_error'] = 'حدث خطأ أثناء التحديث.';
        }
        echo "<script>window.location.href='admin_panel.php?page=complaints';</script>";
        exit;
    }
}

$roleFilter = '';
if ($isAdmin && isset($_GET['dept']) && in_array($_GET['dept'], ['support', 'marketing', 'shipping_company'])) {
    $roleFilter = $conn->real_escape_string($_GET['dept']);
}

// Fetch Data
$items = [];
if ($isAdmin) {
    // Admin sees everything (filtered optionally)
    $sql = "SELECT c.*, s.name as company_name 
            FROM system_complaints c 
            LEFT JOIN admins a ON c.sender_id = a.id 
            LEFT JOIN shipping_accounts s ON a.shipping_company_id = s.id";
    if ($roleFilter !== '') {
        $sql .= " WHERE c.sender_role = '$roleFilter'";
    }
    $sql .= " ORDER BY c.created_at DESC";
    $res = $conn->query($sql);
} else {
    // User sees only their own
    $sql = "SELECT c.*, s.name as company_name 
            FROM system_complaints c 
            LEFT JOIN admins a ON c.sender_id = a.id 
            LEFT JOIN shipping_accounts s ON a.shipping_company_id = s.id 
            WHERE c.sender_id = $currentUserId AND c.sender_role = '$sessionRole' 
            ORDER BY c.created_at DESC";
    $res = $conn->query($sql);
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
}

// Split for Admin View
$complaints = array_filter($items, fn($i) => $i['type'] === 'complaint');
$suggestions = array_filter($items, fn($i) => $i['type'] === 'suggestion');

function groupByRole($array) {
    $grouped = [];
    foreach ($array as $item) {
        $role = $item['sender_role'];
        if (!isset($grouped[$role])) {
            $grouped[$role] = [];
        }
        $grouped[$role][] = $item;
    }
    return $grouped;
}
$grouped_complaints = groupByRole($complaints);
$grouped_suggestions = groupByRole($suggestions);

?>

<div class="px-4 py-8 font-sans" dir="rtl">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center">
            <i class='bx bx-message-dots text-indigo-600 mr-3 text-4xl'></i>
            <h1 class="text-3xl font-extrabold text-slate-800">الشكاوي والاقتراحات</h1>
        </div>
    </div>

    <?php if (isset($_SESSION['complaints_flash_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl shadow-sm mb-6 flex items-center">
            <i class='bx bx-check-circle text-2xl mr-3'></i>
            <span class="font-bold text-lg"><?= htmlspecialchars($_SESSION['complaints_flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['complaints_flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['complaints_flash_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl shadow-sm mb-6 flex items-center">
            <i class='bx bx-error-circle text-2xl mr-3'></i>
            <span class="font-bold text-lg"><?= htmlspecialchars($_SESSION['complaints_flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['complaints_flash_error']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <?php if (!$isAdmin): ?>
            <!-- نموذج الإرسال للموظفين -->
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 sticky top-6">
                    <h3 class="text-xl font-bold text-slate-700 mb-6 flex items-center">
                        <i class='bx bx-edit text-indigo-500 mr-2 text-2xl'></i> إرسال رسالة جديدة
                    </h3>
                    <form method="POST" action="admin_panel.php?page=complaints">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="submit_complaint">
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">الاسم</label>
                            <input type="text" name="sender_name" value="<?= htmlspecialchars($defaultName) ?>" required class="shadow-sm border border-gray-300 rounded-xl w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">رقم الهاتف (اختياري)</label>
                            <input type="text" name="phone" value="" class="shadow-sm border border-gray-300 rounded-xl w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-left" dir="ltr">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">نوع الرسالة</label>
                            <select name="type" required class="shadow-sm border border-gray-300 rounded-xl w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white transition-all">
                                <option value="complaint">شكوى ⚠️</option>
                                <option value="suggestion">اقتراح 💡</option>
                            </select>
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">التفاصيل</label>
                            <textarea name="message" required rows="5" class="shadow-sm border border-gray-300 rounded-xl w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all" placeholder="اكتب رسالتك هنا بوضوح..."></textarea>
                        </div>

                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl w-full transition-colors flex items-center justify-center text-lg shadow-md hover:shadow-lg">
                            <i class='bx bx-send mr-2'></i> إرسال إلى الإدارة
                        </button>
                    </form>
                </div>
            </div>

            <!-- سجل الموظف -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-8 py-5 border-b border-slate-200">
                        <h3 class="text-xl font-bold text-slate-700">سجل رسائلك السابقة</h3>
                    </div>
                    <div class="p-8">
                        <?php if (empty($items)): ?>
                            <div class="text-center text-gray-500 py-12">
                                <div class="bg-gray-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-4">
                                    <i class='bx bx-ghost text-5xl text-gray-400'></i>
                                </div>
                                <p class="text-lg font-bold text-gray-600">لم تقم بإرسال أي رسائل حتى الآن.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($items as $item): 
                                    $sInfo = translate_status($item['status'] ?? 'pending');
                                ?>
                                    <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                        <!-- Card Header -->
                                        <div class="flex justify-between items-center bg-gray-50 px-6 py-4 border-b border-gray-200">
                                            <div class="flex items-center gap-3">
                                                <?php if ($item['type'] === 'complaint'): ?>
                                                    <span class="bg-red-100 text-red-700 px-3 py-1.5 rounded-lg text-sm font-bold flex items-center"><i class='bx bx-error-circle mr-1'></i> شكوى</span>
                                                <?php else: ?>
                                                    <span class="bg-blue-100 text-blue-700 px-3 py-1.5 rounded-lg text-sm font-bold flex items-center"><i class='bx bx-bulb mr-1'></i> اقتراح</span>
                                                <?php endif; ?>
                                                <span class="text-sm text-gray-500" dir="ltr"><?= date('M d, Y h:i A', strtotime($item['created_at'])) ?></span>
                                            </div>
                                            <div class="<?= $sInfo['color'] ?> px-3 py-1.5 rounded-lg text-sm font-bold flex items-center border">
                                                <i class='bx <?= $sInfo['icon'] ?> mr-1 text-lg'></i> <?= $sInfo['text'] ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Card Body -->
                                        <div class="p-6">
                                            <h4 class="text-gray-500 text-xs font-bold uppercase mb-2">نص الرسالة:</h4>
                                            <p class="text-gray-800 whitespace-pre-wrap text-md leading-relaxed mb-4"><?= htmlspecialchars($item['message']) ?></p>
                                            
                                            <?php if (!empty($item['admin_reply'])): ?>
                                                <div class="bg-indigo-50 border-r-4 border-indigo-500 p-4 rounded-l-lg mt-4">
                                                    <h4 class="text-indigo-800 text-xs font-bold uppercase mb-2 flex items-center"><i class='bx bx-reply mr-1'></i> رد الإدارة:</h4>
                                                    <p class="text-indigo-900 whitespace-pre-wrap text-md leading-relaxed"><?= htmlspecialchars($item['admin_reply']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: // Admin View ?>
            <?php if ($roleFilter === ''): 
                // DASHBOARD VIEW
                $counts = ['support' => 0, 'shipping_company' => 0, 'marketing' => 0];
                $q_counts = $conn->query("SELECT sender_role, COUNT(*) as c FROM system_complaints WHERE status = 'pending' GROUP BY sender_role");
                if ($q_counts) {
                    while ($r = $q_counts->fetch_assoc()) {
                        $counts[$r['sender_role']] = (int)$r['c'];
                    }
                }
            ?>
            <div class="lg:col-span-3 space-y-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-3xl font-bold text-slate-800"><i class='bx bx-category text-indigo-500'></i> أقسام الشكاوي والاقتراحات</h2>
                        <p class="text-gray-500 mt-2">اختر القسم الذي تود مراجعة رسائله.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Support -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="bg-indigo-100 p-3 rounded-lg text-indigo-600">
                                    <i class='bx bx-support text-3xl'></i>
                                </div>
                                <?php if ($counts['support'] > 0): ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                        <i class='bx bx-error-circle mr-1'></i> <?= $counts['support'] ?> معلقة
                                    </span>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                        لا يوجد
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">الدعم الفني</h3>
                            <p class="text-gray-500 text-sm mb-6">شكاوي واقتراحات موظفي الدعم الفني وخدمة العملاء.</p>
                            <a href="?page=complaints&dept=support" class="block w-full text-center bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold py-2 rounded-lg transition">
                                عرض رسائل القسم <i class='bx bx-left-arrow-alt align-middle ml-1'></i>
                            </a>
                        </div>
                    </div>

                    <!-- Shipping -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="bg-blue-100 p-3 rounded-lg text-blue-600">
                                    <i class='bx bx-car text-3xl'></i>
                                </div>
                                <?php if ($counts['shipping_company'] > 0): ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                        <i class='bx bx-error-circle mr-1'></i> <?= $counts['shipping_company'] ?> معلقة
                                    </span>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                        لا يوجد
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">شركات الشحن</h3>
                            <p class="text-gray-500 text-sm mb-6">شكاوي واقتراحات مسئولي شركات الشحن والمناديب.</p>
                            <a href="?page=complaints&dept=shipping_company" class="block w-full text-center bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold py-2 rounded-lg transition">
                                عرض رسائل القسم <i class='bx bx-left-arrow-alt align-middle ml-1'></i>
                            </a>
                        </div>
                    </div>

                    <!-- Marketing -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="bg-purple-100 p-3 rounded-lg text-purple-600">
                                    <i class='bx bx-trending-up text-3xl'></i>
                                </div>
                                <?php if ($counts['marketing'] > 0): ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                        <i class='bx bx-error-circle mr-1'></i> <?= $counts['marketing'] ?> معلقة
                                    </span>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full flex items-center">
                                        لا يوجد
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">التسويق (الماركتينج)</h3>
                            <p class="text-gray-500 text-sm mb-6">شكاوي واقتراحات فريق التسويق وإدارة الحملات.</p>
                            <a href="?page=complaints&dept=marketing" class="block w-full text-center bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold py-2 rounded-lg transition">
                                عرض رسائل القسم <i class='bx bx-left-arrow-alt align-middle ml-1'></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: // DETAILS VIEW FOR SPECIFIC DEPARTMENT ?>
            
            <div class="lg:col-span-3 space-y-10">
                <!-- Header with Back Button -->
                <div class="flex justify-between items-center bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                    <div class="flex items-center">
                        <i class='bx bxs-briefcase-alt-2 text-indigo-500 text-3xl mr-3'></i>
                        <div>
                            <h3 class="text-xl font-bold text-slate-700">رسائل قسم: <span class="text-indigo-600"><?= htmlspecialchars(translate_role($roleFilter)) ?></span></h3>
                            <p class="text-sm text-gray-500">مراجعة وإدارة الشكاوي والاقتراحات الخاصة بهذا القسم</p>
                        </div>
                    </div>
                    <a href="?page=complaints" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded-xl font-bold flex items-center transition">
                        <i class='bx bx-arrow-back ml-2'></i> رجوع للأقسام
                    </a>
                </div>

                <!-- الشكاوي الواردة -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-red-50 px-8 py-5 border-b border-red-100 flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="bg-red-200 text-red-700 p-2 rounded-xl mr-3"><i class='bx bx-error-circle text-2xl'></i></div>
                            <h3 class="text-2xl font-extrabold text-red-800">الشكاوي الواردة</h3>
                        </div>
                        <span class="bg-white text-red-800 py-1.5 px-4 rounded-full text-sm font-bold border border-red-200 shadow-sm"><?= count($complaints) ?> شكوى</span>
                    </div>
                    <div class="p-8">
                        <?php if (empty($grouped_complaints)): ?>
                            <div class="text-center py-10">
                                <i class='bx bx-check-shield text-6xl text-gray-300 mb-3'></i>
                                <p class="text-gray-500 text-lg font-bold">لا توجد شكاوي، الأمور ممتازة!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($grouped_complaints as $role => $role_complaints): ?>
                                <div class="mb-10 last:mb-0">
                                    <h4 class="text-xl font-bold text-slate-800 mb-4 flex items-center border-b pb-3 border-gray-100">
                                        <i class='bx bxs-briefcase-alt-2 mr-2 text-gray-400'></i> قسم: <span class="text-indigo-600 mr-2"><?= htmlspecialchars(translate_role($role)) ?></span>
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                        <?php foreach ($role_complaints as $c): 
                                            $sInfo = translate_status($c['status'] ?? 'pending');
                                        ?>
                                            <div class="border border-gray-200 bg-white rounded-2xl shadow-sm hover:shadow-md transition-all flex flex-col overflow-hidden">
                                                <div class="p-5 flex-grow">
                                                    <div class="flex justify-between items-start mb-4">
                                                        <div>
                                                            <p class="font-extrabold text-slate-800 text-lg flex items-center flex-wrap gap-2">
                                                                <?= htmlspecialchars($c['sender_name']) ?>
                                                                <?php if (!empty($c['company_name']) && $c['sender_role'] === 'shipping_company'): ?>
                                                                    <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-md border border-indigo-200 shadow-sm whitespace-nowrap"><i class='bx bx-buildings mr-1'></i> <?= htmlspecialchars($c['company_name']) ?></span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <?php if (!empty($c['phone'])): ?>
                                                            <p class="text-sm text-gray-500 mt-1 font-medium"><i class='bx bx-phone-call'></i> <span dir="ltr"><?= htmlspecialchars($c['phone']) ?></span></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="<?= $sInfo['color'] ?> px-2.5 py-1 rounded-lg text-xs font-bold flex items-center border">
                                                            <i class='bx <?= $sInfo['icon'] ?> mr-1'></i> <?= $sInfo['text'] ?>
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-gray-400 mb-3" dir="ltr"><i class='bx bx-time-five'></i> <?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></p>
                                                    <div class="bg-gray-50 p-4 rounded-xl text-sm text-gray-700 leading-relaxed whitespace-pre-wrap border border-gray-100 max-h-40 overflow-y-auto"><?= htmlspecialchars($c['message']) ?></div>
                                                </div>
                                                
                                                <div class="bg-slate-50 p-5 border-t border-gray-200">
                                                    <form method="POST" action="admin_panel.php?page=complaints" class="space-y-3">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                        <input type="hidden" name="action" value="admin_reply">
                                                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                                        
                                                        <div>
                                                            <label class="block text-xs font-bold text-gray-600 mb-1">تحديث الحالة:</label>
                                                            <select name="status" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-2">
                                                                <option value="pending" <?= ($c['status'] ?? '') === 'pending' ? 'selected' : '' ?>>قيد المراجعة</option>
                                                                <option value="in_progress" <?= ($c['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>جاري العمل عليها</option>
                                                                <option value="resolved" <?= ($c['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>تم الحل / تم القبول</option>
                                                                <option value="rejected" <?= ($c['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div>
                                                            <label class="block text-xs font-bold text-gray-600 mb-1">رد الإدارة (سيصل للموظف):</label>
                                                            <textarea name="admin_reply" rows="2" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-2" placeholder="اكتب ردك هنا..."><?= htmlspecialchars($c['admin_reply'] ?? '') ?></textarea>
                                                        </div>
                                                        
                                                        <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 rounded-lg text-sm transition-colors flex items-center justify-center">
                                                            <i class='bx bx-save mr-2'></i> حفظ الرد والتحديث
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- الاقتراحات الواردة -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-blue-50 px-8 py-5 border-b border-blue-100 flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="bg-blue-200 text-blue-700 p-2 rounded-xl mr-3"><i class='bx bx-bulb text-2xl'></i></div>
                            <h3 class="text-2xl font-extrabold text-blue-800">الاقتراحات الواردة</h3>
                        </div>
                        <span class="bg-white text-blue-800 py-1.5 px-4 rounded-full text-sm font-bold border border-blue-200 shadow-sm"><?= count($suggestions) ?> اقتراح</span>
                    </div>
                    <div class="p-8">
                        <?php if (empty($grouped_suggestions)): ?>
                            <div class="text-center py-10">
                                <i class='bx bx-box text-6xl text-gray-300 mb-3'></i>
                                <p class="text-gray-500 text-lg font-bold">لا توجد اقتراحات حالياً.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($grouped_suggestions as $role => $role_suggestions): ?>
                                <div class="mb-10 last:mb-0">
                                    <h4 class="text-xl font-bold text-slate-800 mb-4 flex items-center border-b pb-3 border-gray-100">
                                        <i class='bx bxs-briefcase-alt-2 mr-2 text-gray-400'></i> قسم: <span class="text-indigo-600 mr-2"><?= htmlspecialchars(translate_role($role)) ?></span>
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                        <?php foreach ($role_suggestions as $c): 
                                            $sInfo = translate_status($c['status'] ?? 'pending');
                                        ?>
                                            <div class="border border-gray-200 bg-white rounded-2xl shadow-sm hover:shadow-md transition-all flex flex-col overflow-hidden">
                                                <div class="p-5 flex-grow">
                                                    <div class="flex justify-between items-start mb-4">
                                                        <div>
                                                            <p class="font-extrabold text-slate-800 text-lg flex items-center flex-wrap gap-2">
                                                                <?= htmlspecialchars($c['sender_name']) ?>
                                                                <?php if (!empty($c['company_name']) && $c['sender_role'] === 'shipping_company'): ?>
                                                                    <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-md border border-indigo-200 shadow-sm whitespace-nowrap"><i class='bx bx-buildings mr-1'></i> <?= htmlspecialchars($c['company_name']) ?></span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <?php if (!empty($c['phone'])): ?>
                                                            <p class="text-sm text-gray-500 mt-1 font-medium"><i class='bx bx-phone-call'></i> <span dir="ltr"><?= htmlspecialchars($c['phone']) ?></span></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="<?= $sInfo['color'] ?> px-2.5 py-1 rounded-lg text-xs font-bold flex items-center border">
                                                            <i class='bx <?= $sInfo['icon'] ?> mr-1'></i> <?= $sInfo['text'] ?>
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-gray-400 mb-3" dir="ltr"><i class='bx bx-time-five'></i> <?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></p>
                                                    <div class="bg-blue-50/50 p-4 rounded-xl text-sm text-gray-700 leading-relaxed whitespace-pre-wrap border border-blue-100 max-h-40 overflow-y-auto"><?= htmlspecialchars($c['message']) ?></div>
                                                </div>
                                                
                                                <div class="bg-slate-50 p-5 border-t border-gray-200">
                                                    <form method="POST" action="admin_panel.php?page=complaints" class="space-y-3">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                        <input type="hidden" name="action" value="admin_reply">
                                                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                                        
                                                        <div>
                                                            <label class="block text-xs font-bold text-gray-600 mb-1">تحديث الحالة:</label>
                                                            <select name="status" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-2">
                                                                <option value="pending" <?= ($c['status'] ?? '') === 'pending' ? 'selected' : '' ?>>قيد المراجعة</option>
                                                                <option value="in_progress" <?= ($c['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>جاري العمل عليها</option>
                                                                <option value="resolved" <?= ($c['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>تم الحل / تم القبول</option>
                                                                <option value="rejected" <?= ($c['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div>
                                                            <label class="block text-xs font-bold text-gray-600 mb-1">رد الإدارة (سيصل للموظف):</label>
                                                            <textarea name="admin_reply" rows="2" class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 py-2" placeholder="اكتب ردك هنا..."><?= htmlspecialchars($c['admin_reply'] ?? '') ?></textarea>
                                                        </div>
                                                        
                                                        <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 rounded-lg text-sm transition-colors flex items-center justify-center">
                                                            <i class='bx bx-save mr-2'></i> حفظ الرد والتحديث
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; // End details view ?>
        <?php endif; // End isAdmin view ?>
    </div>
</div>
