<?php
// ملف: admin_devices.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

require_once 'config.php';
require_once 'helpers.php';
/** @var mysqli $conn */

// Check role
$role = $_SESSION['admin_role'] ?? '';
$is_manager = ($role === 'super_admin' || $role === 'manager');
if (!$is_manager) {
    echo "غير مصرح لك بدخول هذه الصفحة.";
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Revoke Action (Grouped by User Agent)
    if ($action === 'revoke_group' && isset($_POST['account_type']) && isset($_POST['account_id']) && isset($_POST['user_agent_b64'])) {
        $acc_type = $_POST['account_type'];
        $acc_id = (int)$_POST['account_id'];
        $ua = base64_decode($_POST['user_agent_b64']);
        
        $stmt = $conn->prepare("UPDATE device_requests SET status = 'revoked' WHERE account_type = ? AND account_id = ? AND user_agent = ? AND status = 'approved'");
        $stmt->bind_param("sis", $acc_type, $acc_id, $ua);
        $stmt->execute();
        $stmt->close();
        
        $message = "تم إلغاء قفل الجهاز بنجاح. سيحتاج الموظف لطلب موافقة جديدة إذا حاول الدخول من هذا الجهاز.";
    }
    // Approve/Reject Pending Request Action
    elseif (isset($_POST['request_id'])) {
        $req_id = (int) $_POST['request_id'];
        
        // Fetch request
        $req_q = $conn->query("SELECT * FROM device_requests WHERE id = $req_id AND status = 'pending'");
        if ($req_q && $req_q->num_rows > 0) {
            $req = $req_q->fetch_assoc();
            $account_type = $req['account_type'];
            $account_id = (int) $req['account_id'];
            $device_id = $conn->real_escape_string($req['requested_device_id']);
            
            if ($action === 'approve') {
                $table = ($account_type === 'admin') ? 'admins' : 'users';
                $conn->query("UPDATE $table SET allowed_device_id = '$device_id' WHERE id = $account_id");
                $conn->query("UPDATE device_requests SET status = 'approved' WHERE id = $req_id");
                $message = "تمت الموافقة بنجاح وتم ربط الحساب على الجهاز الجديد.";
            } elseif ($action === 'reject') {
                $conn->query("UPDATE device_requests SET status = 'rejected' WHERE id = $req_id");
                $message = "تم رفض الطلب.";
            }
        } else {
            $error = "الطلب غير موجود أو تم معالجته مسبقاً.";
        }
    }
}

// Fetch pending requests
$pending_requests = [];
$res = $conn->query("
    SELECT r.*, 
           CASE WHEN r.account_type = 'admin' THEN a.fullname ELSE u.fullname END as account_name,
           CASE WHEN r.account_type = 'admin' THEN a.role ELSE u.user_type END as account_role
    FROM device_requests r
    LEFT JOIN admins a ON r.account_type = 'admin' AND r.account_id = a.id
    LEFT JOIN users u ON r.account_type = 'user' AND r.account_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pending_requests[] = $row;
    }
}
// Fetch connected devices (APPROVED) grouped by physical device (user_agent)
$connected_devices = [];
$res_dev = $conn->query("
    SELECT MAX(r.id) as id,
           r.account_type,
           r.account_id,
           r.user_agent,
           MAX(r.created_at) as created_at,
           COUNT(*) as request_count,
           CASE WHEN r.account_type = 'admin' THEN a.fullname ELSE u.fullname END as account_name,
           CASE WHEN r.account_type = 'admin' THEN a.role ELSE u.user_type END as account_role
    FROM device_requests r
    LEFT JOIN admins a ON r.account_type = 'admin' AND r.account_id = a.id
    LEFT JOIN users u ON r.account_type = 'user' AND r.account_id = u.id
    WHERE r.status = 'approved'
    GROUP BY r.account_type, r.account_id, r.user_agent, a.fullname, u.fullname, a.role, u.user_type
    ORDER BY MAX(r.created_at) DESC
");
if ($res_dev) {
    while ($row = $res_dev->fetch_assoc()) {
        $connected_devices[] = $row;
    }
}
?>

<div class="space-y-6">
    <!-- Pending Requests Section -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class='bx bx-laptop text-blue-600 mr-2'></i> 
                طلبات الأجهزة المعلقة
            </h2>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <i class='bx bx-check-circle mr-2'></i> <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <i class='bx bx-error-alt mr-2'></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

    <?php if (empty($pending_requests)): ?>
    <div class="text-center text-gray-500 py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
        <i class='bx bx-check-shield text-5xl mb-3 text-green-500'></i>
        <p class="text-lg">لا توجد طلبات معلقة.</p>
        <p class="text-sm">جميع الحسابات تعمل على أجهزتها المصرح بها.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-right border-collapse">
            <thead class="bg-blue-50 text-blue-700">
                <tr>
                    <th class="p-3 border-b">اسم الحساب</th>
                    <th class="p-3 border-b">نوع الحساب</th>
                    <th class="p-3 border-b">وقت الطلب</th>
                    <th class="p-3 border-b">الـ IP</th>
                    <th class="p-3 border-b">المتصفح/الجهاز</th>
                    <th class="p-3 border-b text-center">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_requests as $req): ?>
                <tr class="hover:bg-gray-50 border-b">
                    <td class="p-3 font-medium"><?= htmlspecialchars($req['account_name'] ?? 'مجهول') ?></td>
                    <td class="p-3">
                        <?php if ($req['account_type'] == 'admin'): ?>
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">إدارة / <?= htmlspecialchars($req['account_role']) ?></span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">عميل / <?= htmlspecialchars($req['account_role']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3" dir="ltr"><?= date('Y-m-d H:i', strtotime($req['created_at'])) ?></td>
                    <td class="p-3" dir="ltr"><?= htmlspecialchars($req['ip_address']) ?></td>
                    <td class="p-3 text-xs text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($req['user_agent']) ?>">
                        <?= htmlspecialchars($req['user_agent']) ?>
                    </td>
                    <td class="p-3 text-center flex justify-center gap-2">
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded flex items-center text-xs transition" onclick="return confirm('هل أنت متأكد من الموافقة وتغيير الجهاز الخاص بهذا الحساب؟');">
                                <i class='bx bx-check mr-1'></i> قبول
                            </button>
                        </form>
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded flex items-center text-xs transition" onclick="return confirm('هل أنت متأكد من رفض الطلب؟');">
                                <i class='bx bx-x mr-1'></i> رفض
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    </div>

    <!-- Connected Devices Section -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                <i class='bx bx-devices text-purple-600 mr-2'></i> 
                الأجهزة المرتبطة بالحسابات
            </h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right border-collapse">
                <thead class="bg-purple-50 text-purple-700">
                    <tr>
                        <th class="p-3 border-b">اسم الحساب</th>
                        <th class="p-3 border-b">نوع الحساب</th>
                        <th class="p-3 border-b">بيانات الجهاز (المتصفح)</th>
                        <th class="p-3 border-b">تاريخ الإضافة</th>
                        <th class="p-3 border-b text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($connected_devices)): ?>
                        <tr><td colspan="5" class="p-4 text-center text-gray-500">لا يوجد أجهزة معتمدة حالياً.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($connected_devices as $dev): ?>
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-3 font-medium"><?= htmlspecialchars($dev['account_name'] ?? 'مجهول') ?></td>
                        <td class="p-3">
                            <?php if ($dev['account_type'] == 'admin'): ?>
                                <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">إدارة / <?= htmlspecialchars($dev['account_role']) ?></span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs">مستخدم / <?= htmlspecialchars($dev['account_role']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-xs text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($dev['user_agent']) ?>">
                            <i class='bx bx-desktop mr-1 text-gray-400'></i>
                            <?= htmlspecialchars($dev['user_agent']) ?>
                        </td>
                        <td class="p-3" dir="ltr"><?= date('Y-m-d', strtotime($dev['created_at'])) ?></td>
                        <td class="p-3 text-center flex justify-center gap-2">
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="revoke_group">
                                <input type="hidden" name="account_type" value="<?= $dev['account_type'] ?>">
                                <input type="hidden" name="account_id" value="<?= $dev['account_id'] ?>">
                                <input type="hidden" name="user_agent_b64" value="<?= base64_encode($dev['user_agent']) ?>">
                                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1.5 rounded flex items-center text-xs transition" onclick="return confirm('هل أنت متأكد من إلغاء قفل هذا الجهاز؟ سيتم طلب موافقة جديدة له.');">
                                    <i class='bx bx-unlink mr-1'></i> إلغاء الجهاز
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
