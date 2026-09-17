<?php
session_start();
include(__DIR__ . '/core/config.php");

echo "<h1>Debug Commission System</h1>";

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];
echo "User ID: $user_id<br>";

// 1. Check User Type
$u = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
echo "User Name: " . $u['fullname'] . "<br>";
echo "User Type: " . $u['user_type'] . "<br>";

echo "<hr>";

// 2. Check Orders for this User
echo "<h2>Orders Analysis</h2>";
$orders = $conn->query("SELECT id, status, commission_total, created_at, user_id FROM orders WHERE user_id = $user_id");

if ($orders->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Status</th><th>Commission</th><th>User ID (FK)</th><th>Status Length</th><th>Hex Status</th></tr>";
    while ($row = $orders->fetch_assoc()) {
        $status_hex = bin2hex($row['status']);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>'{$row['status']}'</td>";
        echo "<td>{$row['commission_total']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>" . strlen($row['status']) . "</td>";
        echo "<td>{$status_hex}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No orders found for this user.<br>";
}

echo "<hr>";

// 3. Test Balance Calculation Query Manually
echo "<h2>Manual Calculation Test</h2>";
$statuses = "'تم التوصيل', 'محصل', 'مكتمل'";
$sql = "SELECT COALESCE(SUM(commission_total), 0) as total FROM orders WHERE user_id = $user_id AND status IN ($statuses)";
echo "SQL: $sql<br>";
$res = $conn->query($sql);
$row = $res->fetch_assoc();
echo "Calculated Earnings: " . $row['total'] . "<br>";

echo "<hr>";

// 4. Test BalanceSystem Class
echo "<h2>BalanceSystem Class Test</h2>";
if (file_exists("balance_system.php")) {
    include(__DIR__ . '/core/balance_system.php");
    $bs = new BalanceSystem($conn);
    $balance = $bs->refreshUserBalance($user_id);
    echo "<pre>";
    print_r($balance);
    echo "</pre>";
} else {
    echo "balance_system.php not found!";
}
?>
