<?php
session_start();
include(__DIR__ . '/core/config.php");

if (!isset($_SESSION['user_id'])) {
    echo "Please login first";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<h1>Debug Cities Statistics</h1>";
echo "<p>User ID: $user_id</p>";

// Get user info
$user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $user_query ? $user_query->fetch_assoc() : null;

echo "<h2>User Info:</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

// Test different queries
echo "<h2>Test 1: All user orders (old way)</h2>";
$test1 = $conn->query("SELECT COUNT(*) as count FROM orders WHERE user_id = $user_id");
$result1 = $test1 ? $test1->fetch_assoc() : ['count' => 0];
echo "All orders: " . $result1['count'] . "<br>";

echo "<h2>Test 2: Orders with products filtering (new way)</h2>";
$test2 = $conn->query("
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = $user_id AND p.status = 'active'
");
$result2 = $test2 ? $test2->fetch_assoc() : ['count' => 0];
echo "Orders with active products: " . $result2['count'] . "<br>";

echo "<h2>Test 3: For merchants - their own products only</h2>";
$test3 = $conn->query("
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = $user_id AND p.user_id = $user_id AND p.status = 'active'
");
$result3 = $test3 ? $test3->fetch_assoc() : ['count' => 0];
echo "Orders with merchant's own products: " . $result3['count'] . "<br>";

echo "<h2>Test 4: Cities test</h2>";
$cities_query = $conn->query("
    SELECT 
        sc.city_name,
        COUNT(DISTINCT o.id) as total_orders
    FROM shipping_cities sc
    LEFT JOIN orders o ON sc.id = o.shipping_city_id AND o.user_id = $user_id
    WHERE sc.is_active = 1
    GROUP BY sc.id, sc.city_name
    HAVING total_orders > 0
    ORDER BY total_orders DESC
    LIMIT 5
");

echo "Cities with all orders:<br>";
if ($cities_query) {
    while ($row = $cities_query->fetch_assoc()) {
        echo "- " . $row['city_name'] . ": " . $row['total_orders'] . " orders<br>";
    }
}

echo "<br>Cities with product filtering:<br>";
$cities_filtered = $conn->query("
    SELECT 
        sc.city_name,
        COUNT(DISTINCT o.id) as total_orders
    FROM shipping_cities sc
    INNER JOIN orders o ON sc.id = o.shipping_city_id
    INNER JOIN order_items oi ON o.id = oi.order_id
    INNER JOIN products p ON oi.product_id = p.id
    WHERE sc.is_active = 1 
    AND o.user_id = $user_id 
    AND p.user_id = $user_id
    AND p.status = 'active'
    GROUP BY sc.id, sc.city_name
    HAVING total_orders > 0
    ORDER BY total_orders DESC
    LIMIT 5
");

if ($cities_filtered) {
    while ($row = $cities_filtered->fetch_assoc()) {
        echo "- " . $row['city_name'] . ": " . $row['total_orders'] . " orders (filtered)<br>";
    }
}
?>
