<?php
require 'config.php';
require 'admin_support_post_early.php';

$p = [
    'order_id' => 0,
    'sheet_row_index' => 2,
    'phone' => '11111111',
    'recipient_name' => '',
];

$existing_probe = support_find_existing_order_id($conn, 1, 33, $p);
echo "Found existing ID: " . $existing_probe . "\n";

$q = $conn->query("SELECT id, phone, sheet_row_index, sheet_id FROM support_orders WHERE phone = '11111111'");
while ($r = $q->fetch_assoc()) {
    print_r($r);
}
