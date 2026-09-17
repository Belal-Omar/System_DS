<?php
session_start();
include(__DIR__ . '/core/config.php");

if (!isset($_SESSION['user_id'])) {
    echo "Please login first";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<h1>Debug Cities Statistics - User ID: $user_id</h1>";

// Get user info
$user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $user_query ? $user_query->fetch_assoc() : null;

echo "<h2>User Info:</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

// Test 1: Check if user has any orders at all
echo "<h2>Test 1: All user orders</h2>";
$test1 = $conn->query("SELECT COUNT(*) as count FROM orders WHERE user_id = $user_id");
$result1 = $test1 ? $test1->fetch_assoc() : ['count' => 0];
echo "Total orders: " . $result1['count'] . "<br>";

if ($result1['count'] > 0) {
    // Show some orders
    $orders = $conn->query("SELECT id, total, status, shipping_city_id FROM orders WHERE user_id = $user_id LIMIT 5");
    echo "Sample orders:<br>";
    while ($order = $orders->fetch_assoc()) {
        echo "- Order ID: {$order['id']}, Total: {$order['total']}, Status: {$order['status']}, City ID: {$order['shipping_city_id']}<br>";
    }
}

// Test 2: Check shipping cities
echo "<h2>Test 2: Shipping Cities</h2>";
$cities = $conn->query("SELECT COUNT(*) as count FROM shipping_cities WHERE is_active = 1");
$result2 = $cities ? $cities->fetch_assoc() : ['count' => 0];
echo "Active cities: " . $result2['count'] . "<br>";

// Test 3: Check if user orders have valid city IDs
echo "<h2>Test 3: Orders with valid cities</h2>";
$test3 = $conn->query("
    SELECT COUNT(*) as count 
    FROM orders o 
    INNER JOIN shipping_cities sc ON o.shipping_city_id = sc.id 
    WHERE o.user_id = $user_id AND sc.is_active = 1
");
$result3 = $test3 ? $test3->fetch_assoc() : ['count' => 0];
echo "Orders with active cities: " . $result3['count'] . "<br>";

// Test 4: Full query test
echo "<h2>Test 4: Full query test</h2>";
$full_query = $conn->query("
    SELECT 
        sc.city_name,
        sc.shipping_cost,
        COUNT(o.id) as total_orders,
        SUM(o.total) as total_revenue
    FROM shipping_cities sc
    INNER JOIN orders o ON sc.id = o.shipping_city_id
    WHERE sc.is_active = 1 
    AND o.user_id = $user_id
    GROUP BY sc.id, sc.city_name, sc.shipping_cost
    ORDER BY total_orders DESC
");

echo "Results:<br>";
if ($full_query) {
    while ($row = $full_query->fetch_assoc()) {
        echo "- {$row['city_name']}: {$row['total_orders']} orders, {$row['total_revenue']} LYD<br>";
    }
} else {
    echo "Query failed: " . mysqli_error($conn) . "<br>";
}

// Test 5: Check for merchants
echo "<h2>Test 5: Merchant specific test</h2>";
if ($user && ($user['user_type'] == 'merchant' || $user['user_type'] == 'tajar')) {
    echo "User is a merchant<br>";
    
    $merchant_query = $conn->query("
        SELECT 
            sc.city_name,
            COUNT(DISTINCT o.id) as total_orders,
            SUM(o.total) as total_revenue
        FROM shipping_cities sc
        INNER JOIN orders o ON sc.id = o.shipping_city_id
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN products p ON oi.product_id = p.id
        WHERE sc.is_active = 1 
        AND o.user_id = $user_id 
        AND p.user_id = $user_id
        GROUP BY sc.id, sc.city_name
        ORDER BY total_orders DESC
    ");
    
    echo "Merchant results:<br>";
    if ($merchant_query) {
        while ($row = $merchant_query->fetch_assoc()) {
            echo "- {$row['city_name']}: {$row['total_orders']} orders, {$row['total_revenue']} LYD<br>";
        }
    } else {
        echo "Merchant query failed: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "User is not a merchant<br>";
}
?>
