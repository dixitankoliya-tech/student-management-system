<?php
$dirs = ['admin', 'faculty', 'student', 'config', 'includes', 'tests'];
$errors = 0;
foreach ($dirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/../$dir"));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $cmd = "C:\\xampp\\php\\php.exe -l " . escapeshellarg($file->getPathname()) . " 2>&1";
            exec($cmd, $output, $return_var);
            if ($return_var !== 0) {
                echo implode("\n", $output) . "\n";
                $errors++;
            }
        }
    }
}
echo "Total syntax errors: $errors\n";
