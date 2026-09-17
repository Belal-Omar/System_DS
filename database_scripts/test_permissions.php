<?php
require_once 'config.php';
require_once 'helpers.php';

$all = array_keys(get_admin_manageable_pages());
echo "All manageable pages: \n";
print_r($all);

$effective = get_admin_effective_pages('super_admin');
echo "\nEffective pages for super_admin: \n";
print_r($effective);

$can_accounts = admin_can_access_page('accounts', 'super_admin');
echo "\nCan super_admin access accounts? " . ($can_accounts ? 'Yes' : 'No');
