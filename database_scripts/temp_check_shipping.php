<?php
require 'config.php';
global $conn;
$res = $conn->query("SELECT id, username, role, shipping_company_id FROM admins WHERE shipping_company_id IS NOT NULL");
while($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']}, Username: {$r['username']}, Role: {$r['role']}, CompanyID: {$r['shipping_company_id']}\n";
}
echo "----\n";
$res2 = $conn->query("SELECT id, name FROM shipping_accounts");
while($r = $res2->fetch_assoc()) {
    echo "CompID: {$r['id']}, Name: {$r['name']}\n";
}
