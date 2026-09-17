<?php
$c = "ساعة";
$decoded = mb_convert_encoding($c, 'Windows-1252', 'UTF-8');
echo bin2hex($decoded);
?>
