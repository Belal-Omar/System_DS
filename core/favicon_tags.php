<?php
/**
 * وسوم favicon — تُضمَّن داخل <head>
 * المسار يُحسب تلقائياً حسب مكان الصفحة (مثلاً /System/...)
 */
$__brand_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($__brand_dir === '/' || $__brand_dir === '.' || $__brand_dir === '') {
    $__brand_prefix = '';
} else {
    $__brand_prefix = rtrim($__brand_dir, '/');
}
$__brand_url = $__brand_prefix . '/brand_logo.php';
$__brand_ver = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png') ?: time();
$__brand_logo = htmlspecialchars($__brand_url . '?f=logo&v=' . $__brand_ver, ENT_QUOTES, 'UTF-8');
$__brand_ico = htmlspecialchars($__brand_url . '?f=favicon&v=' . $__brand_ver, ENT_QUOTES, 'UTF-8');
?>
<link rel="icon" type="image/png" href="<?= $__brand_ico ?>">
<link rel="shortcut icon" type="image/png" href="<?= $__brand_ico ?>">
<link rel="apple-touch-icon" href="<?= $__brand_logo ?>">
