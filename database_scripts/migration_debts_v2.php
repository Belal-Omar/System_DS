<?php
$conn = new mysqli("127.0.0.1", "u497700233_medhatomar5555", "BelalOmar49988155$", "u497700233_System");
if ($conn->connect_error) { die("Connection failed"); }
$conn->query("ALTER TABLE support_financial_records ADD COLUMN shipping_company_id INT NULL AFTER id");
$conn->query("ALTER TABLE support_financial_records ADD INDEX (shipping_company_id)");
echo "Done";
