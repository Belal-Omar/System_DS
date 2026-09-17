<?php
$files = ['admin_your_rank_super.php', 'admin_your_rank_support.php'];
foreach($files as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f);
        $c = str_replace("'confirmed',", "'confirmed', 'order_confirmed',", $c);
        $c = str_replace("'cancelled',", "'cancelled', 'order_cancelled',", $c);
        file_put_contents($f, $c);
        echo "Fixed $f\n";
    }
}
?>
