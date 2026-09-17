<?php
include __DIR__ . '/core/config.php';

$queries = [
    "ALTER TABLE products ADD COLUMN special_commission DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE order_items ADD COLUMN special_commission DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE orders ADD COLUMN total_special_commission DECIMAL(10,2) DEFAULT 0.00"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Successfully executd: $sql\n";
    } else {
        echo "Error executing $sql: " . $conn->error . "\n";
    }
}
$conn->close();
?>
