<?php
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

echo "<h2>Test Shipping Company Integration</h2>";

// Check if shipping_companies table exists and has data
echo "<h3>1. Shipping Companies Table:</h3>";
$companies_query = $conn->query("SELECT * FROM shipping_companies LIMIT 5");
if ($companies_query && $companies_query->num_rows > 0) {
    while ($company = $companies_query->fetch_assoc()) {
        echo "ID: {$company['id']}, Name: {$company['name']}<br>";
    }
} else {
    echo "No shipping companies found or table doesn't exist<br>";
}

// Check if products have shipping companies
echo "<h3>2. Products with Shipping Companies:</h3>";
$products_query = $conn->query("SELECT id, name, shipping_company FROM products WHERE shipping_company IS NOT NULL AND shipping_company != '' LIMIT 5");
if ($products_query && $products_query->num_rows > 0) {
    while ($product = $products_query->fetch_assoc()) {
        echo "Product ID: {$product['id']}, Name: {$product['name']}, Shipping Company: {$product['shipping_company']}<br>";
    }
} else {
    echo "No products with shipping companies found<br>";
}

// Check if orders have shipping companies
echo "<h3>3. Orders with Shipping Companies:</h3>";
$orders_query = $conn->query("SELECT id, customer_name, shipping_company FROM orders WHERE shipping_company IS NOT NULL AND shipping_company != '' LIMIT 5");
if ($orders_query && $orders_query->num_rows > 0) {
    while ($order = $orders_query->fetch_assoc()) {
        echo "Order ID: {$order['id']}, Customer: {$order['customer_name']}, Shipping Company: {$order['shipping_company']}<br>";
    }
} else {
    echo "No orders with shipping companies found<br>";
}

// Test statistics function
echo "<h3>4. Test Statistics Function:</h3>";
if (isset($_GET['company_id']) && !empty($_GET['company_id'])) {
    $company_id = intval($_GET['company_id']);
    $stats = get_shipping_company_stats($conn, $company_id);
    echo "Stats for company ID $company_id:<br>";
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
} else {
    echo "Add ?company_id=1 to URL to test statistics for company ID 1<br>";
}

// Check table structures
echo "<h3>5. Table Structures:</h3>";
echo "<strong>Products table columns:</strong><br>";
$products_columns = $conn->query("DESCRIBE products");
while ($column = $products_columns->fetch_assoc()) {
    if (strpos($column['Field'], 'shipping') !== false) {
        echo "- {$column['Field']}: {$column['Type']}<br>";
    }
}

echo "<strong>Orders table columns:</strong><br>";
$orders_columns = $conn->query("DESCRIBE orders");
while ($column = $orders_columns->fetch_assoc()) {
    if (strpos($column['Field'], 'shipping') !== false) {
        echo "- {$column['Field']}: {$column['Type']}<br>";
    }
}
?>
