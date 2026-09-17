<?php
require_once 'config.php';
/** @var mysqli $conn */
if (!$conn) die('DB Connection failed');

try {
    // Add marketer_code to admins if not exists
    $check_mc = $conn->query("SHOW COLUMNS FROM admins LIKE 'marketer_code'");
    if ($check_mc && $check_mc->num_rows == 0) {
        $conn->query("ALTER TABLE admins ADD COLUMN marketer_code VARCHAR(100) NULL AFTER role");
        echo "marketer_code column added to admins.<br>";
    } else {
        echo "marketer_code column already exists.<br>";
    }

    // Backfill marketer_code for all marketing admins
    $res = $conn->query("SELECT id, fullname, marketer_code FROM admins WHERE role IN ('marketing', 'marketing_main')");
    $seq = 1;
    while($row = $res->fetch_assoc()) {
        if (empty($row['marketer_code'])) {
            $fullname = trim($row['fullname']);
            $name_parts = explode(' ', $fullname);
            $first_initial = isset($name_parts[0]) ? mb_substr($name_parts[0], 0, 1, 'UTF-8') : 'M';
            $second_initial = isset($name_parts[1]) ? mb_substr($name_parts[1], 0, 1, 'UTF-8') : 'K';
            $code = strtoupper($first_initial . $second_initial . str_pad($seq, 2, '0', STR_PAD_LEFT));
            
            $stmt = $conn->prepare("UPDATE admins SET marketer_code = ? WHERE id = ?");
            $stmt->bind_param("si", $code, $row['id']);
            $stmt->execute();
        }
        $seq++;
    }
    echo "Marketer codes backfilled for admins.<br>";

    // Add category to products if not exists
    $check_cat = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");
    if ($check_cat && $check_cat->num_rows == 0) {
        $conn->query("ALTER TABLE products ADD COLUMN category VARCHAR(100) NULL AFTER name");
        echo "category column added to products.<br>";
    } else {
        echo "category column already exists in products.<br>";
    }
    
    // Add creative_link to products if not exists
    $check_cl = $conn->query("SHOW COLUMNS FROM products LIKE 'creative_link'");
    if ($check_cl && $check_cl->num_rows == 0) {
        $conn->query("ALTER TABLE products ADD COLUMN creative_link TEXT NULL");
        echo "creative_link column added to products.<br>";
    } else {
        echo "creative_link column already exists in products.<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
