<?php
require 'config.php';
$conn->query("ALTER TABLE shipping_inventory_products ADD COLUMN IF NOT EXISTS stock_quantity INT NOT NULL DEFAULT 0");
echo "DB Updated";
