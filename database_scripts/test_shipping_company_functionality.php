<?php
// Test file for shipping company functionality
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

echo "<h1>Test Shipping Company Functionality</h1>";

// Test 1: Get all shipping companies
echo "<h2>1. All Shipping Companies:</h2>";
$companies = get_shipping_companies($conn);
echo "<pre>";
print_r($companies);
echo "</pre>";

// Test 2: Get stats for each company
echo "<h2>2. Shipping Company Stats:</h2>";
foreach ($companies as $company) {
    echo "<h3>Company: " . htmlspecialchars($company['name']) . "</h3>";
    $stats = get_shipping_company_stats($conn, $company['id']);
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
}

// Test 3: Get orders for each company
echo "<h2>3. Orders for each company:</h2>";
foreach ($companies as $company) {
    echo "<h3>Orders for: " . htmlspecialchars($company['name']) . "</h3>";
    $orders = get_shipping_company_orders($conn, $company['id']);
    echo "<pre>";
    print_r($orders);
    echo "</pre>";
}

// Test 4: Get products stats for each company
echo "<h2>4. Products Stats for each company:</h2>";
foreach ($companies as $company) {
    echo "<h3>Products for: " . htmlspecialchars($company['name']) . "</h3>";
    $products_stats = get_shipping_company_products_stats($conn, $company['id']);
    echo "<pre>";
    print_r($products_stats);
    echo "</pre>";
}

// Test 5: Check some product records
echo "<h2>5. Sample Products with shipping companies:</h2>";
$product_query = "SELECT id, name, shipping_company, shipping_company_id FROM products WHERE shipping_company IS NOT NULL LIMIT 5";
$result = $conn->query($product_query);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
echo "<pre>";
print_r($products);
echo "</pre>";

// Test 6: Check some order records
echo "<h2>6. Sample Orders with shipping companies:</h2>";
$order_query = "SELECT id, customer_name, shipping_company, shipping_company_id FROM orders WHERE shipping_company IS NOT NULL LIMIT 5";
$result = $conn->query($order_query);
$orders = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
echo "<pre>";
print_r($orders);
echo "</pre>";

echo "<h2>Test Complete!</h2>";
?>
