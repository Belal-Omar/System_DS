<?php
$dir = new RecursiveDirectoryIterator('c:\xampp\htdocs\System');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$keywords = ['ج.م', 'ج م', 'جنيه', 'جنيها', 'EGP'];

$matches = [];
foreach($files as $file) {
    $path = $file[0];
    if (strpos($path, 'scratch') !== false) continue; // skip scratch
    $content = file_get_contents($path);
    $found = [];
    foreach ($keywords as $kw) {
        if (mb_strpos($content, $kw) !== false) {
            $found[] = $kw;
        }
    }
    if (!empty($found)) {
        $matches[basename($path)] = $found;
    }
}

foreach($matches as $file => $kws) {
    echo $file . " => " . implode(', ', $kws) . "\n";
}
