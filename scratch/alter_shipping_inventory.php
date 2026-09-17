<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'System');
$conn->query("ALTER TABLE shipping_inventory ADD COLUMN IF NOT EXISTS status ENUM('active', 'disabled') DEFAULT 'active'");
echo "Added status column.";
