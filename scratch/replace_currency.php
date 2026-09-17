<?php
$files = [
    'admin_accounts.php',
    'admin_accounts_post_early.php',
    'admin_marketing_main.php',
    'admin_marketing_manager.php',
    'admin_products.php',
    'admin_shipping_reps.php',
    'admin_shipping_storage.php',
    'admin_support_orders.php',
    'admin_your_rank_marketing.php',
    'admin_your_rank_super.php',
    'admin_your_rank_super_restored.php',
    'admin_your_rank_support.php',
    'helpers.php',
    'product_details.php',
    'withdrawals.php',
    'admin_withdrawals.php',
    'admin_debts.php',
    'admin_storage.php',
    'admin_product_reports.php',
    'admin_reports.php',
    'admin_reports_simple.php',
    'admin_shipping_cities_statistics.php',
    'admin_marketer_reports.php',
    'admin_accounts.php'
];

$replacements = [
    'EGP' => 'دل',
    'ج م' => 'دل',
    'ج.م' => 'دل',
    'جنيه' => 'دل' // We might need to be careful with 'جنيه' if it's used in sentences
];

foreach ($files as $f) {
    $path = "c:/xampp/htdocs/System/$f";
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        $changed = false;
        foreach ($replacements as $search => $replace) {
            // ONLY if it's NOT a bonus file, wait, NONE of these are bonus files!
            if (mb_strpos($content, $search) !== false) {
                // Be careful with جنيه. Let's do a regex for standalone or attached to numbers.
                if ($search == 'جنيه') {
                     $content = preg_replace('/\bجنيه\b/u', 'دل', $content);
                     $content = preg_replace('/\bجنيها\b/u', 'دل', $content);
                } else {
                     $content = str_replace($search, $replace, $content);
                }
                $changed = true;
            }
        }
        
        if ($changed) {
            file_put_contents($path, $content);
            echo "Updated $f\n";
        }
    }
}
