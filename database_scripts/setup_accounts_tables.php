<?php
include('c:\xampp\htdocs\System\config.php');

$sql1 = "CREATE TABLE IF NOT EXISTS support_monthly_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month VARCHAR(7) NOT NULL UNIQUE, -- e.g. '2023-10'
    product_cost DECIMAL(10,2) DEFAULT 0,
    intl_shipping DECIMAL(10,2) DEFAULT 0,
    dom_shipping DECIMAL(10,2) DEFAULT 0,
    ops_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$sql2 = "CREATE TABLE IF NOT EXISTS support_daily_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE, -- e.g. '2023-10-01'
    lead_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if($conn->query($sql1) && $conn->query($sql2)) {
    echo "Tables created successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>
