<?php
// admin_your_rank.php
if (!isset($_SESSION['admin_role'])) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>ليس مصرح لك بالدخول</div>";
    return;
}

$role = $_SESSION['admin_role'];

if ($role === 'shipping_company') {
    include 'admin_your_rank_shipping.php';
} elseif ($role === 'support') {
    include 'admin_your_rank_support.php';
} elseif ($role === 'marketing') {
    include 'admin_your_rank_marketing.php';
} else {
    // For super_admin and admin
    include 'admin_your_rank_super.php';
}
?>
