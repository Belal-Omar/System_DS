<?php
$c = file_get_contents('admin_your_rank_super.php');
// The file is currently UTF-8 encoded text of ISO-8859-1 mojibake.
// We decode the UTF-8 to ISO-8859-1 to get the raw bytes back.
$restored = utf8_decode($c);

// Except the lines I added in fix_others.php ('?? ????? ?????', etc.)
// Those were valid UTF-8 strings. When utf8_decode processes them, it will mangle them into ?.
// BUT wait, we can just replace them AFTER restoring!
file_put_contents('admin_your_rank_super_restored.php', $restored);
?>
