<?php
// Test the API to see if reasons are now included
header('Content-Type: application/json; charset=UTF-8');

// Simulate calling get_orders_simple.php
$api_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/get_orders_simple.php';
$response = file_get_contents($api_url);
$data = json_decode($response, true);

echo "<h2>API Response Test - Cancellation/Return Reasons</h2>";

if ($data && $data['success'] && !empty($data['orders'])) {
    echo "<h3>✅ API Working - Sample Orders:</h3>";
    
    $count = 0;
    foreach ($data['orders'] as $order) {
        if ($count >= 3) break; // Show only first 3 orders
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Order #{$order['id']}</strong> - Status: {$order['status']}<br>";
        
        // Check if reason fields are present
        echo "Cancellation Reason: " . (isset($order['cancellation_reason']) ? 
            ($order['cancellation_reason'] ?: 'NULL/Empty') : 'NOT FOUND') . "<br>";
        echo "Return Reason: " . (isset($order['return_reason']) ? 
            ($order['return_reason'] ?: 'NULL/Empty') : 'NOT FOUND') . "<br>";
        echo "Reason Type: " . (isset($order['reason_type']) ? 
            ($order['reason_type'] ?: 'NULL/Empty') : 'NOT FOUND') . "<br>";
        
        echo "</div>";
        $count++;
    }
    
    echo "<h3>📊 Statistics:</h3>";
    echo "Total Orders: " . count($data['orders']) . "<br>";
    
    $cancelled = array_filter($data['orders'], function($o) { 
        return in_array($o['status'], ['ملغي', 'مرفوض']); 
    });
    $returned = array_filter($data['orders'], function($o) { 
        return $o['status'] === 'مرتجع'; 
    });
    
    echo "Cancelled/Rejected Orders: " . count($cancelled) . "<br>";
    echo "Returned Orders: " . count($returned) . "<br>";
    
} else {
    echo "<h3>❌ API Error:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
}

echo "<br><a href='orders.html'>Test in orders.html</a> | <a href='test_reasons.php'>Check Database</a>";
?>
