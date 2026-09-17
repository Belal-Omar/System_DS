<?php
// Debugging: Check sheet and orders relationship
require_once 'config.php';

// Get all sheets
$sheets = $conn->query("SELECT id, support_id, sheet_name, created_at FROM support_sheets ORDER BY id DESC LIMIT 20");
echo "<h2>All Sheets</h2><table border='1'><tr><th>Sheet ID</th><th>Support ID</th><th>Name</th><th>Created</th></tr>";
while ($r = $sheets->fetch_assoc()) {
    echo "<tr><td>{$r['id']}</td><td>{$r['support_id']}</td><td>{$r['sheet_name']}</td><td>{$r['created_at']}</td></tr>";
}
echo "</table>";

// Get recent orders with their sheet_id
$orders = $conn->query("SELECT id, sheet_id, support_id, phone, order_status, created_at FROM support_orders ORDER BY id DESC LIMIT 20");
echo "<h2>Recent Orders</h2><table border='1'><tr><th>Order ID</th><th>Sheet ID</th><th>Support ID</th><th>Phone</th><th>Status</th><th>Created</th></tr>";
while ($r = $orders->fetch_assoc()) {
    echo "<tr><td>{$r['id']}</td><td>{$r['sheet_id']}</td><td>{$r['support_id']}</td><td>{$r['phone']}</td><td>{$r['order_status']}</td><td>{$r['created_at']}</td></tr>";
}
echo "</table>";

// Check orders for sheet 33
$s33 = $conn->query("SELECT COUNT(*) as cnt FROM support_orders WHERE sheet_id = 33");
$s33r = $s33->fetch_assoc();
echo "<h2>Orders for sheet_id=33: {$s33r['cnt']}</h2>";

// Check sheet 33
$sh33 = $conn->query("SELECT * FROM support_sheets WHERE id = 33");
$sh33r = $sh33->fetch_assoc();
echo "<h2>Sheet 33 info:</h2><pre>" . print_r($sh33r, true) . "</pre>";
