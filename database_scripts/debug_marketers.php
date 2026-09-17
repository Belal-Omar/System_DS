<?php
session_start();
include(__DIR__ . '/core/config.php");

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Debug Marketers</h1>";

// 1. List all users and their types
echo "<h2>All Users:</h2>";
$result = $conn->query("SELECT id, fullname, user_type, HEX(user_type) as type_hex FROM users");
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Type</th><th>HEX(Type)</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
    echo "<td>" . htmlspecialchars($row['user_type']) . "</td>";
    echo "<td>{$row['type_hex']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Test the specific query
echo "<h2>Test Report Query:</h2>";
$marketers_query = "
    SELECT 
        u.id, u.fullname, u.email, u.phone,
        COUNT(DISTINCT o.id) as delivered_orders,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN oi.commission * oi.quantity ELSE 0 END), 0) as total_net_earnings,
        COALESCE(SUM(CASE WHEN o.status IN ('تم التوصيل', 'محصل', 'مكتمل') THEN oi.special_commission * oi.quantity ELSE 0 END), 0) as total_deductions
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE u.user_type = 'مسوق'
    GROUP BY u.id
    ORDER BY total_net_earnings DESC
";

$stmt = $conn->prepare($marketers_query);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error;
} else {
    $stmt->execute();
    $res = $stmt->get_result();
    echo "Rows returned: " . $res->num_rows . "<br>";
    if ($res->num_rows == 0) {
        echo "No rows matching 'مسوق'<br>";
    }
    while ($row = $res->fetch_assoc()) {
        print_r($row);
        echo "<br>";
    }
}
?>
