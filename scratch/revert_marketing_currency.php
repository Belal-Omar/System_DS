<?php
$files = [
    'admin_marketing_main.php',
    'admin_marketing_manager.php',
    'admin_your_rank_marketing.php'
];

foreach ($files as $f) {
    $path = "c:/xampp/htdocs/System/$f";
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Since I replaced EGP and ج م with دل previously, I'll replace دل with ج.م
        if (mb_strpos($content, 'دل') !== false) {
            $content = str_replace('دل', 'ج.م', $content);
            file_put_contents($path, $content);
            echo "Updated $f\n";
        }
    }
}
