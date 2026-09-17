<?php
$dir = 'c:\\xampp\\htdocs\\System';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$phpFiles = [];
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getPathname(), 'vendor') === false && strpos($file->getPathname(), 'scratch') === false) {
        $phpFiles[] = $file->getPathname();
    }
}

$report = [
    'sqli' => [],
    'xss' => [],
    'csrf' => [],
    'auth_missing' => []
];

foreach ($phpFiles as $filepath) {
    $content = file_get_contents($filepath);
    $lines = explode("\n", $content);
    $filename = basename($filepath);
    
    // Auth Check
    if (strpos($content, '<?php') !== false && strpos($content, 'admin_panel.php') === false && strpos($content, 'login.php') === false && strpos($content, '$_SESSION[\'admin_id\']') === false && strpos($content, '$_SESSION[\'user_id\']') === false && strpos($content, '$_SESSION[\'admin_role\']') === false && strpos($content, 'session_start()') === false && strpos($content, 'config.php') === false) {
    }
    
    $in_form = false;
    $has_csrf = false;
    
    foreach ($lines as $i => $line) {
        $lineNum = $i + 1;
        
        // SQLi (very basic naive regex)
        if (preg_match('/\$conn->query\([^)]*\$\w+/', $line) && strpos($line, 'real_escape_string') === false) {
            $report['sqli'][] = "$filename:$lineNum -> " . trim($line);
        }
        
        // XSS
        if (preg_match('/echo\s+.*\$_(GET|POST|REQUEST|SERVER)/', $line) && strpos($line, 'htmlspecialchars') === false) {
            $report['xss'][] = "$filename:$lineNum -> " . trim($line);
        }
        
        // CSRF
        if (stripos($line, '<form ') !== false && stripos($line, 'method="POST"') !== false) {
            $in_form = true;
            $has_csrf = false;
        }
        if ($in_form) {
            if (stripos($line, 'csrf_token') !== false || stripos($line, 'name="csrf_token"') !== false) {
                $has_csrf = true;
            }
            if (stripos($line, '</form>') !== false) {
                if (!$has_csrf) {
                    $report['csrf'][] = "$filename:$lineNum -> Missing CSRF in POST form";
                }
                $in_form = false;
            }
        }
    }
}

echo json_encode($report, JSON_PRETTY_PRINT);
