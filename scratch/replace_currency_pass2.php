<?php
$files = glob("c:/xampp/htdocs/System/admin_*.php");
$files[] = "c:/xampp/htdocs/System/withdrawals.php";
$files[] = "c:/xampp/htdocs/System/helpers.php";
$files[] = "c:/xampp/htdocs/System/withdrawing1.php";
$files[] = "c:/xampp/htdocs/System/product_details.php";

$skip = ['admin_bonus_super.php', 'admin_bonus_support.php', 'admin_bonus_marketing.php', 'admin_bonus_shipping.php', 'admin_bonus_super_new.php'];

$replacements = [
    'EGP' => 'دل',
    'ج م' => 'دل',
    'ج.م' => 'دل'
];

foreach ($files as $path) {
    if (in_array(basename($path), $skip)) continue;
    
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        $changed = false;
        foreach ($replacements as $search => $replace) {
            if (mb_strpos($content, $search) !== false) {
                $content = str_replace($search, $replace, $content);
                $changed = true;
            }
        }
        
        // Handle جنيه separately
        if (preg_match('/\bجنيه\b/u', $content) || preg_match('/\bجنيها\b/u', $content)) {
            $content = preg_replace('/\bجنيه\b/u', 'دل', $content);
            $content = preg_replace('/\bجنيها\b/u', 'دل', $content);
            $changed = true;
        }
        
        if ($changed) {
            file_put_contents($path, $content);
            echo "Updated " . basename($path) . "\n";
        }
    }
}
