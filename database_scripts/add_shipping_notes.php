<?php
require __DIR__ . '/db_connect.php';
$conn->query('ALTER TABLE support_orders ADD COLUMN shipping_notes TEXT NULL AFTER snap_dom_shipping');
echo "Done";
?>
