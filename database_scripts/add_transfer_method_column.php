<?php
// Script to add transfer_method column to withdrawals table
include(__DIR__ . '/core/config.php");

// Check if column exists
$check_column = $conn->query("SHOW COLUMNS FROM withdrawals LIKE 'transfer_method'");

if ($check_column && $check_column->num_rows == 0) {
    // Add the column
    $alter_table = $conn->query("
        ALTER TABLE withdrawals 
        ADD COLUMN transfer_method VARCHAR(100) DEFAULT NULL 
        AFTER processed_at
    ");
    
    if ($alter_table) {
        echo "تم إضافة عمود transfer_method بنجاح إلى جدول withdrawals\n";
    } else {
        echo "فشل في إضافة عمود transfer_method: " . $conn->error . "\n";
    }
} else {
    echo "عمود transfer_method موجود بالفعل في جدول withdrawals\n";
}

// Update existing completed withdrawals to have default transfer method
$update_existing = $conn->query("
    UPDATE withdrawals 
    SET transfer_method = 'تحويل بنكي' 
    WHERE status = 'مكتمل' AND transfer_method IS NULL
");

if ($update_existing) {
    echo "تم تحديث طلبات السحب المكتملة بطريقة تحويل افتراضية\n";
} else {
    echo "لم يتم تحديث الطلبات المكتملة: " . $conn->error . "\n";
}
?>
