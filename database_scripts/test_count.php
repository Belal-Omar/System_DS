<?php require_once "config.php"; $res = $conn->query("SELECT support_id, COUNT(*) FROM support_orders GROUP BY support_id"); while($row=$res->fetch_assoc()) { print_r($row); } ?>
