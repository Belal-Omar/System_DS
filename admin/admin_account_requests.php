<?php
// ملف: admin_account_requests.php - إدارة طلبات الحسابات

// معالجة الموافقة أو الرفض
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($user_id > 0 && in_array($action, ['approve', 'reject'])) {
        if ($action == 'approve') {
            // الموافقة على الحساب
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = "تم الموافقة على الحساب بنجاح";
            } else {
                $error = "حدث خطأ أثناء الموافقة على الحساب";
            }
        } elseif ($action == 'reject') {
            // رفض الحساب
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = "تم رفض الحساب بنجاح";
            } else {
                $error = "حدث خطأ أثناء رفض الحساب";
            }
        }
    }
}

// جلب طلبات الحسابات المعلقة
$pending_requests = $conn->query("
    SELECT id, fullname, email, phone, user_type, region, created_at 
    FROM users 
    WHERE is_active = 0 
    ORDER BY created_at DESC
");
?>

<div class="container mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class='bx bx-user-plus ml-2 text-blue-600'></i>
                طلبات الحسابات
            </h2>
            <div class="text-sm text-gray-600">
                <i class='bx bx-time-five ml-1'></i>
                طلبات بانتظار المراجعة: <?= $pending_requests->num_rows ?>
            </div>
        </div>

        <?php if(isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class='bx bx-check-circle ml-2'></i><?= $success ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class='bx bx-error-circle ml-2'></i><?= $error ?>
            </div>
        <?php endif; ?>

        <?php if($pending_requests->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">#</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">الاسم</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">البريد الإلكتروني</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">الهاتف</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">النوع</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">المنطقة</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">تاريخ الطلب</th>
                            <th class="px-4 py-3 text-right text-gray-700 font-bold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; while($request = $pending_requests->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3"><?= $count ?></td>
                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($request['fullname']) ?></td>
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($request['email']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($request['phone']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $request['user_type'] == 'تاجر' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                        <?= htmlspecialchars($request['user_type']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($request['region'] ?? '---') ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <?= date('Y-m-d H:i', strtotime($request['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <form method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $request['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" 
                                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition"
                                                    onclick="return confirm('هل أنت متأكد من الموافقة على هذا الحساب؟')">
                                                <i class='bx bx-check ml-1'></i> موافقة
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $request['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" 
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition"
                                                    onclick="return confirm('هل أنت متأكد من رفض هذا الحساب؟')">
                                                <i class='bx bx-x ml-1'></i> رفض
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php $count++; endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class='bx bx-check-circle text-6xl text-green-500 mb-4 block'></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد طلبات معلقة</h3>
                <p class="text-gray-500">جميع طلبات الحسابات تمت معالجتها</p>
            </div>
        <?php endif; ?>
    </div>
</div>
