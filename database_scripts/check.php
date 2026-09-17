<?php
$c = file_get_contents('admin_your_rank_super_restored.php');
if (strpos($c, '??????? ?????') !== false) {
    echo "Successfully restored!";
} else {
    echo "Not found.";
}
?>
