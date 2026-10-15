<?php
/**
 * Master Comprehensive System Audit Scanner for GLS SMS 2.0
 * Safely inspects every portal, file, database table, form, button, and security control.
 * Non-destructive: READ-ONLY.
 */
require_once __DIR__ . '/../config/database.php';

$report = [
    'inventory' => [],
    'syntax_check' => [],
    'db_tables' => [],
    'security_scan' => [],
    'form_button_inventory' => [],
    'broken_interactions' => [],
    'role_check_scan' => []
];

echo "=== STEP 1: SCANNING ALL PROJECT FILES & SYNTAX ===\n";
$scanDirs = [
    'admin' => __DIR__ . '/../admin',
    'admin_api' => __DIR__ . '/../admin/api',
    'faculty' => __DIR__ . '/../faculty',
    'student' => __DIR__ . '/../student',
    'includes' => __DIR__ . '/../includes',
    'config' => __DIR__ . '/../config',
    'api' => __DIR__ . '/../api',
    'root' => __DIR__ . '/..'
];

$allPhpFiles = [];
foreach ($scanDirs as $cat => $dir) {
    if (!is_dir($dir)) continue;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || is_dir("$dir/$item")) continue;
        if (substr($item, -4) === '.php') {
            $filePath = realpath("$dir/$item");
            if ($cat === 'root' && in_array($item, ['migrate_to_tidb.php'])) continue; // skip non-app root scripts
            $allPhpFiles[] = [
                'category' => $cat,
                'file' => $item,
                'path' => $filePath,
                'rel_path' => str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', $filePath)
            ];
        }
    }
}

$syntaxErrors = [];
foreach ($allPhpFiles as $f) {
    $out = [];
    $ret = 0;
    exec("php -l " . escapeshellarg($f['path']), $out, $ret);
    if ($ret !== 0) {
        $syntaxErrors[] = ['file' => $f['rel_path'], 'error' => implode(" ", $out)];
    }
}
$report['total_files'] = count($allPhpFiles);
$report['syntax_errors'] = $syntaxErrors;
echo "Discovered {$report['total_files']} application PHP files.\n";
echo "Syntax check: " . (empty($syntaxErrors) ? "ALL PASS (0 errors)" : count($syntaxErrors) . " errors") . "\n";

echo "\n=== STEP 2: DATABASE SCHEMA & DATA INTEGRITY AUDIT ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$dbAudit = [];
foreach ($tables as $t) {
    $rowCount = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    $indexes = $pdo->query("SHOW INDEX FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    $idxNames = array_unique(array_column($indexes, 'Key_name'));
    
    $dbAudit[$t] = [
        'rows' => (int)$rowCount,
        'column_count' => count($cols),
        'columns' => array_column($cols, 'Field'),
        'indexes' => array_values($idxNames)
    ];
}
$report['db_tables'] = $dbAudit;
echo "Audited " . count($tables) . " database tables in TiDB/MySQL.\n";

echo "\n=== STEP 3: SECURITY & AUTHORIZATION STATIC CODE AUDIT ===\n";
// Check role checks in admin, faculty, student
$authIssues = [];
foreach ($allPhpFiles as $f) {
    $content = file_get_contents($f['path']);
    $rel = $f['rel_path'];
    $cat = $f['category'];
    
    // Check pages that should require auth
    if (in_array($cat, ['admin', 'faculty', 'student'])) {
        $isLogin = in_array(basename($f['file']), ['login.php', 'logout.php', 'index.php', 'serve.php']);
        if (!$isLogin) {
            $hasAuthCheck = (strpos($content, 'requireAuth') !== false || strpos($content, 'isLoggedIn') !== false || strpos($content, 'requireRole') !== false);
            if (!$hasAuthCheck) {
                $authIssues[] = [
                    'file' => $rel,
                    'issue' => 'Missing explicit requireAuth() call at top of file',
                    'severity' => 'P1'
                ];
            }
        }
    }
    
    // Check for CSRF enforcement in POST handlers
    if (strpos($content, '$_SERVER[\'REQUEST_METHOD\'] === \'POST\'') !== false || strpos($content, '$_SERVER["REQUEST_METHOD"] === "POST"') !== false || strpos($content, 'if ($_POST)') !== false) {
        $hasCsrfCheck = (strpos($content, 'requireValidCsrf') !== false || strpos($content, 'validateCsrfToken') !== false || strpos($content, 'csrf_token') !== false);
        if (!$hasCsrfCheck && !in_array(basename($f['file']), ['login.php'])) {
            $authIssues[] = [
                'file' => $rel,
                'issue' => 'POST handler without requireValidCsrf() or validateCsrfToken()',
                'severity' => 'P2'
            ];
        }
    }
    
    // Check for raw SQL concatenation risks ($pdo->query("...$var..."))
    if (preg_match('/\$pdo->(?:query|prepare)\s*\(\s*["\'][^"\']*\$(?!this)[a-zA-Z0-9_]+[^"\']*["\']\s*\)/i', $content, $m)) {
        // filter out benign matches (e.g. table name or constant)
        if (strpos($m[0], 'WHERE') !== false && strpos($m[0], '?') === false && strpos($m[0], ':') === false) {
            $authIssues[] = [
                'file' => $rel,
                'issue' => 'Potential SQL parameter concatenation: ' . substr($m[0], 0, 80),
                'severity' => 'P1'
            ];
        }
    }
}
$report['security_scan'] = $authIssues;
echo "Security scan complete: " . count($authIssues) . " items flagged for review.\n";

echo "\n=== STEP 4: INTERACTIVE ELEMENTS (BUTTONS, FORMS, MODALS) INVENTORY ===\n";
$formInventory = [];
foreach ($allPhpFiles as $f) {
    if (!in_array($f['category'], ['admin', 'faculty', 'student'])) continue;
    $content = file_get_contents($f['path']);
    $rel = $f['rel_path'];
    
    // Find forms
    preg_match_all('/<form[^>]*action=["\']?([^"\'>\s]*)["\']?[^>]*method=["\']?([^"\'>\s]*)["\']?[^>]*>/i', $content, $forms, PREG_SET_ORDER);
    
    // Find buttons
    preg_match_all('/<button[^>]*>.*?<\/button>/is', $content, $buttons);
    
    // Find modals
    preg_match_all('/<div[^>]*class=["\'][^"\']*modal\s+fade[^"\']*["\'][^>]*id=["\']([^"\']+)["\']/i', $content, $modals);
    
    // Find inputs
    preg_match_all('/<input[^>]*name=["\']([^"\']+)["\']/i', $content, $inputs);
    
    if (!empty($forms) || !empty($buttons[0]) || !empty($modals[1])) {
        $formInventory[$rel] = [
            'forms_count' => count($forms),
            'buttons_count' => count($buttons[0]),
            'modals' => $modals[1],
            'form_actions' => array_column($forms, 1),
            'input_names' => array_unique($inputs[1])
        ];
    }
}
$report['form_button_inventory'] = $formInventory;
echo "Cataloged interactive elements across " . count($formInventory) . " portal pages.\n";

file_put_contents(__DIR__ . '/master_audit_results.json', json_encode($report, JSON_PRETTY_PRINT));
echo "\nSaved audit findings to scripts/master_audit_results.json\n";
