<?php
/**
 * تقديم لوجو النظام مباشرة من القرص (يتجنب 404 لو المسار النسبي غلط)
 * brand_logo.php  |  brand_logo.php?f=favicon
 */
error_reporting(0);

$files = [
    'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'logo.png',
    'favicon' => __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'favicon.png',
];
if (!is_readable($files['favicon'])) {
    $files['favicon'] = __DIR__ . DIRECTORY_SEPARATOR . 'favicon.png';
}

$key = strtolower(trim((string) ($_GET['f'] ?? 'logo')));
if (in_array($key, ['favicon.png', 'icon'], true)) {
    $key = 'favicon';
}
if ($key === 'logo.png') {
    $key = 'logo';
}
if (!isset($files[$key])) {
    $key = 'logo';
}

$file = $files[$key];
if (!is_readable($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Logo not found';
    exit;
}

$mtime = @filemtime($file) ?: time();
header('Content-Type: image/png');
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: public, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
readfile($file);
exit;
