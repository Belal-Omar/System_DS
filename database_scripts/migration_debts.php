<?php
require_once 'config.php';
/** @var mysqli $conn */
$check = $conn->query("SHOW COLUMNS FROM support_financial_records LIKE 'shipping_company_id'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE support_financial_records ADD COLUMN shipping_company_id INT NULL AFTER id");
    echo "Added shipping_company_id to support_financial_records.<br>";
} else {
    echo "shipping_company_id already exists in support_financial_records.<br>";
}

$conn->query("ALTER TABLE support_financial_records ADD INDEX (shipping_company_id)");
echo "Done.";
