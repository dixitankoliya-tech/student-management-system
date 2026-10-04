<?php
$dirs = ['admin', 'student', 'faculty', 'includes', 'config'];

$issues = [];

function checkFile($path) {
    global $issues;
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    
    // Check for hardcoded localhost/demo
    $patterns = [
        'localhost' => '/localhost|127\.0\.0\.1|8000|9000/',
        'hardcoded_sgpa' => '/sgpa\s*=\s*[0-9\.]+;/',
        'hardcoded_student_count' => '/500|10500/',
        'TODO' => '/TODO|FIXME/i',
        'mock' => '/fake|mock|dummy|placeholder/i'
    ];
    
    foreach ($lines as $i => $line) {
        $lineNum = $i + 1;
        foreach ($patterns as $type => $regex) {
            if (preg_match($regex, $line)) {
                $issues[$type][] = "$path:$lineNum -> " . trim($line);
            }
        }
    }
}

function scanDirRecursive($dir) {
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            scanDirRecursive($file);
        } else if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            checkFile($file);
        }
    }
}

foreach ($dirs as $dir) {
    scanDirRecursive(__DIR__ . '/../' . $dir);
}

// Check missing files linked in code
$all_content = '';
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../' . $dir));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $all_content .= file_get_contents($file->getPathname());
        }
    }
}

preg_match_all('/href="([^"]+\.php)(?:\?[^"]*)?"/i', $all_content, $matches);
$links = array_unique($matches[1]);
$missing_links = [];
foreach ($links as $link) {
    if (strpos($link, 'http') === 0 || strpos($link, '#') === 0 || strpos($link, 'mailto:') === 0) continue;
    
    $link_base = basename($link);
    // Find if any file exists with this basename
    $found = false;
    foreach ($dirs as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../' . $dir));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getFilename() === $link_base) {
                $found = true;
                break 2;
            }
        }
    }
    if (!$found && !file_exists(__DIR__ . '/../' . $link_base)) {
        $missing_links[] = $link;
    }
}
$issues['missing_links'] = $missing_links;

file_put_contents('PHASE3_CODE_AUDIT.json', json_encode($issues, JSON_PRETTY_PRINT));
echo "Audit complete. Results in PHASE3_CODE_AUDIT.json\n";
?>
