<?php
require_once "config.php";
require_once "helpers.php";
$sheets = $conn->query("SELECT id, support_id, file_name, names_count FROM support_sheets");
$total_imported = 0;
while($s = $sheets->fetch_assoc()) {
    $sid = $s["id"];
    $c = $conn->query("SELECT COUNT(*) as c FROM support_orders WHERE sheet_id = $sid")->fetch_assoc()["c"];
    if ($c == 0 && $s["names_count"] > 0) {
        $imp = import_support_sheet_orders_to_db($conn, $sid, $s["support_id"], $s["file_name"]);
        $total_imported += $imp;
        echo "Imported $imp orders for sheet $sid\n";
    }
}
echo "Total imported: $total_imported\n";
?>
