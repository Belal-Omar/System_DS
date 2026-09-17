<?php
$conn = new mysqli('localhost', 'root', '', 'system_db'); // Default xampp

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$queries = [
    "CREATE TABLE IF NOT EXISTS currency_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rate DECIMAL(10,4) NOT NULL,
        admin_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS currency_exchange_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        amount_lyd DECIMAL(12,2) NOT NULL,
        amount_egp DECIMAL(12,2) NOT NULL,
        rate_used DECIMAL(10,4) NOT NULL,
        note VARCHAR(255) NULL,
        admin_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error executing query: " . $conn->error . "\n";
    }
}
