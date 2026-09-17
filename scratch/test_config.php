<?php
try {
    require 'c:\xampp\htdocs\System\config.php';
    if ($conn === null) {
        echo "conn is null\n";
    } else {
        echo "conn is ok\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
