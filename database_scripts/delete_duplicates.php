<?php
require 'config.php';
session_start();
/** @var mysqli $conn */

$session_role = $_SESSION['admin_role'] ?? '';
if (empty($session_role)) {
    die("Not Authorized. Please log in first.");
}

if (!isset($_GET['confirm'])) {
    // 1) Find exact duplicates (same phone AND same sheet_row_index AND same sheet_id)
    $sql1 = "
        SELECT 
            o2.phone, o2.sheet_id, o2.sheet_row_index,
            MAX(o2.recipient_name) as customer_name,
            COUNT(*) as cnt,
            GROUP_CONCAT(o2.id ORDER BY o2.id ASC SEPARATOR ', ') as ids,
            GROUP_CONCAT(o2.order_status ORDER BY o2.id ASC SEPARATOR ' | ') as statuses
        FROM support_orders o1
        INNER JOIN support_orders o2 
            ON o1.phone = o2.phone 
            AND o1.sheet_id = o2.sheet_id 
            AND o1.sheet_row_index = o2.sheet_row_index
            AND o1.support_id = o2.support_id
            AND o1.id < o2.id
        WHERE o1.sheet_id > 0 AND o1.sheet_row_index >= 0 AND o1.phone != ''
        GROUP BY o2.phone, o2.sheet_id, o2.sheet_row_index
        HAVING cnt > 1
        ORDER BY o1.id DESC
    ";
    $res1 = $conn->query($sql1);
    $rows1 = $res1 ? $res1->fetch_all(MYSQLI_ASSOC) : [];
    $total_to_delete1 = 0;
    foreach ($rows1 as $r) {
        $total_to_delete1 += ($r['cnt'] - 1);
    }

    // 2) Find duplicates created significantly LATER (the bug's duplicates)
    $sql2 = "
        SELECT 
            o2.phone, o2.sheet_id,
            MAX(o2.recipient_name) as customer_name,
            COUNT(*) as cnt,
            GROUP_CONCAT(o2.id ORDER BY o2.id ASC SEPARATOR ', ') as ids,
            GROUP_CONCAT(o2.created_at ORDER BY o2.id ASC SEPARATOR ', ') as times,
            GROUP_CONCAT(o2.order_status ORDER BY o2.id ASC SEPARATOR ', ') as statuses
        FROM support_orders o1
        INNER JOIN support_orders o2 
            ON o1.phone = o2.phone 
            AND o1.sheet_id = o2.sheet_id
            AND o1.support_id = o2.support_id
            AND o1.id < o2.id 
            AND TIMESTAMPDIFF(MINUTE, o1.created_at, o2.created_at) > 2
            AND o2.order_status = 'pending'
        WHERE o1.sheet_id > 0 AND o1.phone != ''
        GROUP BY o2.phone, o2.sheet_id
    ";
    $res2 = $conn->query($sql2);
    $rows2 = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
    $total_to_delete2 = 0;
    foreach ($rows2 as $r) {
        $total_to_delete2 += $r['cnt'];
    }

    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>تنظيف التكرارات</title>
        <style>
            body { font-family: Tahoma, Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; background: #fff; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
            th { background-color: #3b82f6; color: white; }
            .btn { padding: 10px 15px; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .btn-danger { background-color: #ef4444; }
            .btn-orange { background-color: #f97316; }
            .green { color: #10b981; }
        </style>
    </head>
    <body>
        <h1>تنظيف التكرارات الناتجة عن مشكلة التحديث</h1>
        <p>هذه الأداة صُممت خصيصاً لاصطياد الأوردرات المكررة التي نتجت عن خطأ التحديث (حيث كان النظام ينشئ صفاً جديداً بدلاً من التحديث).</p>
        
        <?php if (count($rows1) > 0): ?>
        <h2>1. تكرار في نفس الصف (sheet_row_index)</h2>
        <table>
            <thead>
                <tr>
                    <th>اسم العميل</th>
                    <th>رقم التليفون</th>
                    <th>sheet_id</th>
                    <th>صف الشيت</th>
                    <th>العدد</th>
                    <th>IDs</th>
                    <th>الحالات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows1 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['customer_name'] ?? '') ?></td>
                    <td dir="ltr"><?= $r['phone'] ?></td>
                    <td><?= $r['sheet_id'] ?></td>
                    <td><?= $r['sheet_row_index'] ?></td>
                    <td style="color:red; font-weight:bold;"><?= $r['cnt'] ?></td>
                    <td style="font-size:11px"><?= $r['ids'] ?></td>
                    <td style="font-size:11px"><?= $r['statuses'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="?confirm=row_index" class="btn btn-danger" onclick="return confirm('تأكيد الحذف؟')">حذف المكرر في نفس الصف (<?= $total_to_delete1 ?> أوردر)</a>
        <?php endif; ?>

        <?php if (count($rows2) > 0): ?>
        <hr style="margin: 40px 0;">
        <h2>2. أوردرات أُنشئت لاحقاً (بسبب خطأ التحديث)</h2>
        <p>هذه الأوردرات تابعة لنفس الشيت ونفس العميل، لكن تم إنشاؤها بفارق زمني (أكثر من دقيقتين) مما يدل على أنها نتجت عن خطأ تحديث الحالة.</p>
        <table>
            <thead>
                <tr>
                    <th>اسم العميل</th>
                    <th>رقم التليفون</th>
                    <th>sheet_id</th>
                    <th>عدد المكرر</th>
                    <th>الـ IDs المكررة</th>
                    <th>حالاتها</th>
                    <th>أوقات الإضافة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows2 as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['customer_name'] ?? '') ?></td>
                    <td dir="ltr"><?= $r['phone'] ?></td>
                    <td><?= $r['sheet_id'] ?></td>
                    <td style="color:red; font-weight:bold;"><?= $r['cnt'] ?></td>
                    <td style="font-size:11px"><?= $r['ids'] ?></td>
                    <td style="font-size:11px"><?= $r['statuses'] ?></td>
                    <td style="font-size:11px"><?= $r['times'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="?confirm=bug_duplicates" class="btn btn-orange" onclick="return confirm('تأكيد الحذف؟')">حذف التكرار الناتج عن التحديث (<?= $total_to_delete2 ?> أوردر)</a>
        <?php endif; ?>

        <?php if (count($rows1) === 0 && count($rows2) === 0): ?>
        <h2 class="green">لا يوجد أي أوردرات مكررة! الأوردرات الحالية الموجودة صحيحة وطبيعية (من الملف الأصلي).</h2>
        <?php endif; ?>

    </body>
    </html>
    <?php
    exit;
}

$conn->begin_transaction();
try {
    $deleted = 0;
    if ($_GET['confirm'] === 'row_index') {
        $conn->query("
            DELETE o1 FROM support_orders o1
            INNER JOIN support_orders o2
                ON  o1.phone = o2.phone
                AND o1.sheet_id = o2.sheet_id
                AND o1.sheet_row_index = o2.sheet_row_index
                AND o1.support_id = o2.support_id
                AND o1.id < o2.id
            WHERE o1.sheet_id > 0 AND o1.sheet_row_index >= 0 AND o1.phone != ''
        ");
        $deleted = $conn->affected_rows;
    } elseif ($_GET['confirm'] === 'bug_duplicates') {
        $conn->query("
            DELETE o2 FROM support_orders o1
            INNER JOIN support_orders o2
                ON  o1.phone = o2.phone
                AND o1.sheet_id = o2.sheet_id
                AND o1.support_id = o2.support_id
                AND o1.id < o2.id
                AND TIMESTAMPDIFF(MINUTE, o1.created_at, o2.created_at) > 2
                AND o2.order_status = 'pending'
            WHERE o1.sheet_id > 0 AND o1.phone != ''
        ");
        $deleted = $conn->affected_rows;
    }
    $conn->commit();
    echo "<h2 style='color:green; padding: 20px;'>تم حذف $deleted أوردر بنجاح! <a href='delete_duplicates.php'>الرجوع</a></h2>";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
