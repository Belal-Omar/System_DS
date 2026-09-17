<?php require 'config.php'; $res = $conn->query("SHOW TABLES LIKE 'system_notifications'"); echo $res ? $res->num_rows : 0; ?>
