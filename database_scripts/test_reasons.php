<?php
include(__DIR__ . '/core/config.php');

echo "<h2>Testing Cancellation/Return Reasons Display</h2>";

// Test orders with reasons
$result = $conn->query('SELECT id, status, cancellation_reason, return_reason FROM orders WHERE (cancellation_reason IS NOT NULL AND cancellation_reason != "") OR (return_reason IS NOT NULL AND return_reason != "") LIMIT 5');

if ($result && $result->num_rows > 0) {
    echo "<h3>Found orders with reasons:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Order #{$row['id']}</strong> - Status: {$row['status']}<br>";
        
        if ($row['status'] === 'ملغي' || $row['status'] === 'مرفوض') {
            echo "<span style='color: #dc2626; font-size: 12px;'>";
            echo "<i class='fa fa-times-circle' style='margin-left: 4px;'></i>";
            echo "سبب الإلغاء: " . htmlspecialchars($row['cancellation_reason'] ?: 'غير محدد');
            echo "</span><br>";
        } elseif ($row['status'] === 'مرتجع') {
            echo "<span style='color: #f59e0b; font-size: 12px;'>";
            echo "<i class='fa fa-undo' style='margin-left: 4px;'></i>";
            echo "سبب الإرجاع: " . htmlspecialchars($row['return_reason'] ?: 'غير محدد');
            echo "</span><br>";
        }
        
        echo "</div>";
    }
} else {
    echo "<p>No orders with cancellation/return reasons found in database.</p>";
    echo "<p>You can test by updating an order status in admin panel to 'ملغي' or 'مرتجع' with a reason.</p>";
}

// Test all orders structure
echo "<h3>Database Structure Test:</h3>";
$structure = $conn->query('DESCRIBE orders');
while ($col = $structure->fetch_assoc()) {
    if (strpos($col['Field'], 'reason') !== false) {
        echo "<strong>{$col['Field']}</strong> - {$col['Type']} - {$col['Null']}<br>";
    }
}
?>
