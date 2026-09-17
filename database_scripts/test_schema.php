<?php
include __DIR__ . '/core/config.php';
/** @var mysqli $conn */
$res = $conn->query('DESCRIBE support_orders');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " ";
}
echo "\n====\n";
$res2 = $conn->query('DESCRIBE orders');
while($row = $res2->fetch_assoc()) {
    echo $row['Field'] . " ";
}
?>
