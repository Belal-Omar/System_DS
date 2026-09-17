<?php
require 'db_connect.php';
$q = $conn->query("SELECT s.*, a.fullname as employee_name, (SELECT COUNT(*) FROM support_orders WHERE sheet_id = s.id) as saved_orders_count FROM support_sheets s JOIN admins a ON s.support_id = a.id ORDER BY s.created_at DESC");
if(!$q) echo "DB Error: " . $conn->error; else echo "Query OK! Rows: " . $q->num_rows;
