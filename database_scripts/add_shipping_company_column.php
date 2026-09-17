<?php
include(__DIR__ . '/core/config.php");

$sql = "ALTER TABLE orders ADD COLUMN shipping_company VARCHAR(100) DEFAULT NULL AFTER shipping_city_name";

if ($conn->query($sql)) {
    echo "Column shipping_company added successfully to orders table";
} else {
    echo "Error adding column: " . $conn->error;
}
?>
