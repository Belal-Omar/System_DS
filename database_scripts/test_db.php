<?php
include __DIR__ . '/core/config.php';
// Enable raw error reporting for the test
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $product_id = 1;
    echo "Testing get_product_data...\n";
    $product_query = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $product_query->bind_param("i", $product_id);
    $product_query->execute();
    $product = $product_query->get_result()->fetch_assoc();
    
    // Get product shippers
    $shippers_query = $conn->prepare("SELECT shipping_company_id, stock FROM product_shippers WHERE product_id = ?");
    if (!$shippers_query) {
        echo "Error preparing shippers_query: " . $conn->error . "\n";
    } else {
        $shippers_query->bind_param("i", $product_id);
        $shippers_query->execute();
        $shippers_result = $shippers_query->get_result();
        $shippers = [];
        while ($row = $shippers_result->fetch_assoc()) {
            $shippers[] = $row;
        }
        echo "Shippers found: " . count($shippers) . "\n";
    }
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
}
echo "Done.\n";
?>
