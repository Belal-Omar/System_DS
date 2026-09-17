<?php
// migrate_db.php - Final Refinement
$conn = @new mysqli('localhost', 'root', 'BelalOmar499881', 'system', 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$queries = [
    "DESCRIBE products",
    "DESCRIBE users",
    "SHOW TABLES LIKE 'product_shippers'",
    "SHOW TABLES LIKE 'shipping_company_cities'"
];

foreach ($queries as $sql) {
    if ($res = $conn->query($sql)) {
        echo "Success for query: $sql\n";
        while ($row = $res->fetch_assoc()) {
            print_r($row);
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
?>
