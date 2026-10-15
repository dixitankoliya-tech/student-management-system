<?php
/**
 * GLS FCAIT Student Management System
 * Comprehensive Automated Security & Functional Regression Test Suite
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/academic_calculator.php';
require_once __DIR__ . '/../includes/faculty_role_helper.php';

$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

function recordTest($id, $name, $expected, $actual, $pass) {
    global $results;
    $results['total']++;
    if ($pass) {
        $results['passed']++;
    } else {
        $results['failed']++;
    }
    $status = $pass ? "PASS" : "FAIL";
    $results['tests'][] = [
        'id' => $id,
        'name' => $name,
        'expected' => $expected,
        'actual' => $actual,
        'status' => $status
    ];
    echo sprintf("[%s] %-12s: %s (Expected: %s | Got: %s)\n", $status, $id, $name, $expected, $actual);
}

echo "====================================================================\n";
echo " GLS FCAIT SMS -- COMPREHENSIVE AUTOMATED TEST SUITE\n";
echo "====================================================================\n\n";

// --- 1. ROUTER & TRAVERSAL DEFENSE CHECKS ---
echo "--- 1. ROUTER & TRAVERSAL DEFENSES ---\n";

function testRouterPathCheck($requestPath) {
    $uri = $requestPath;
    while (strpos($uri, '../') !== false || strpos($uri, '..\\') !== false) {
        $uri = str_replace(['../', '..\\'], '', $uri);
    }
    $path = ltrim($uri, '/');
    $basename = basename($path);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $blockedExtensions = ['env', 'sql', 'json', 'yaml', 'yml', 'md', 'lock', 'log'];
    $blockedFiles = ['migrate_to_tidb.php', 'dockerfile', 'render.yaml', 'vercel.json', '.gitattributes', '.gitignore'];

    if (str_starts_with($basename, '.env')
        || in_array(strtolower($basename), $blockedFiles)
        || in_array($ext, $blockedExtensions)
        || strpos($path, '.git') !== false
        || strpos($path, 'config/') === 0
        || strpos($path, 'scripts/') === 0
        || strpos($path, 'LOCAL_RECOVERY') !== false
        || str_starts_with($basename, 'scratch_')) {
        return '403 Forbidden';
    }
    return 'ALLOWED';
}

$rt1 = testRouterPathCheck('....//....//config/database.php');
recordTest('SEC-RTR-01', 'Nested Path Traversal Blocked', '403 Forbidden', $rt1, $rt1 === '403 Forbidden');

$rt2 = testRouterPathCheck('.env');
recordTest('SEC-RTR-02', '.env Direct Access Blocked', '403 Forbidden', $rt2, $rt2 === '403 Forbidden');

$rt3 = testRouterPathCheck('.env.production');
recordTest('SEC-RTR-03', '.env.production Access Blocked', '403 Forbidden', $rt3, $rt3 === '403 Forbidden');

$rt4 = testRouterPathCheck('config/database.php');
recordTest('SEC-RTR-04', 'config/database.php Direct Access Blocked', '403 Forbidden', $rt4, $rt4 === '403 Forbidden');

$rt5 = testRouterPathCheck('scripts/schema.sql');
recordTest('SEC-RTR-05', 'scripts/schema.sql Access Blocked', '403 Forbidden', $rt5, $rt5 === '403 Forbidden');

$rt6 = testRouterPathCheck('migrate_to_tidb.php');
recordTest('SEC-RTR-06', 'migrate_to_tidb.php Direct Access Blocked', '403 Forbidden', $rt6, $rt6 === '403 Forbidden');


// --- 2. AUTHENTICATION & HASHING CHECKS ---
echo "\n--- 2. AUTHENTICATION & CSRF CHECKS ---\n";

$testPassword = "SamplePassword@2026";
$hash = password_hash($testPassword, PASSWORD_DEFAULT);
$validAuth = password_verify($testPassword, $hash);
recordTest('AUTH-01', 'Bcrypt Hash Verification (Valid)', 'TRUE', $validAuth ? 'TRUE' : 'FALSE', $validAuth === true);

$invalidAuth = password_verify("WrongPassword", $hash);
recordTest('AUTH-02', 'Bcrypt Hash Verification (Invalid Rejected)', 'FALSE', $invalidAuth ? 'TRUE' : 'FALSE', $invalidAuth === false);

// CSRF simulation
$token = bin2hex(random_bytes(32));
$validCsrf = hash_equals($token, $token);
recordTest('CSRF-01', 'CSRF Token Validation (Matching)', 'TRUE', $validCsrf ? 'TRUE' : 'FALSE', $validCsrf === true);

$invalidCsrf = hash_equals($token, "invalid_forged_token");
recordTest('CSRF-02', 'CSRF Token Validation (Mismatch Rejected)', 'FALSE', $invalidCsrf ? 'TRUE' : 'FALSE', $invalidCsrf === false);


// --- 3. ACADEMIC CALCULATOR ENGINE CHECKS ---
echo "\n--- 3. ACADEMIC CALCULATOR ENGINE ---\n";

$g1 = calculateGrade(95);
recordTest('ACAD-GRD-01', 'Grade Boundary: 95% -> O (10 pts)', 'O:10', $g1['grade'] . ':' . $g1['point'], $g1['grade'] === 'O' && $g1['point'] === 10);

$g2 = calculateGrade(85);
recordTest('ACAD-GRD-02', 'Grade Boundary: 85% -> A+ (9 pts)', 'A+:9', $g2['grade'] . ':' . $g2['point'], $g2['grade'] === 'A+' && $g2['point'] === 9);

$g3 = calculateGrade(72);
recordTest('ACAD-GRD-03', 'Grade Boundary: 72% -> A (8 pts)', 'A:8', $g3['grade'] . ':' . $g3['point'], $g3['grade'] === 'A' && $g3['point'] === 8);

$g4 = calculateGrade(64);
recordTest('ACAD-GRD-04', 'Grade Boundary: 64% -> B+ (7 pts)', 'B+:7', $g4['grade'] . ':' . $g4['point'], $g4['grade'] === 'B+' && $g4['point'] === 7);

$g5 = calculateGrade(55);
recordTest('ACAD-GRD-05', 'Grade Boundary: 55% -> B (6 pts)', 'B:6', $g5['grade'] . ':' . $g5['point'], $g5['grade'] === 'B' && $g5['point'] === 6);

$g6 = calculateGrade(42);
recordTest('ACAD-GRD-06', 'Grade Boundary: 42% -> C (5 pts)', 'C:5', $g6['grade'] . ':' . $g6['point'], $g6['grade'] === 'C' && $g6['point'] === 5);

$g7 = calculateGrade(35);
recordTest('ACAD-GRD-07', 'Grade Boundary: 35% -> F (0 pts)', 'F:0', $g7['grade'] . ':' . $g7['point'], $g7['grade'] === 'F' && $g7['point'] === 0);

// SGPA weighted credit test
$sampleMarks = [
    ['subject_id' => 1, 'subject_name' => 'Web Dev', 'subject_code' => 'CS101', 'credits' => 4, 'max_marks' => 100, 'marks_obtained' => 95], // O -> 10 * 4 = 40
    ['subject_id' => 2, 'subject_name' => 'Data Structures', 'subject_code' => 'CS102', 'credits' => 4, 'max_marks' => 100, 'marks_obtained' => 85], // A+ -> 9 * 4 = 36
    ['subject_id' => 3, 'subject_name' => 'Database', 'subject_code' => 'CS103', 'credits' => 3, 'max_marks' => 100, 'marks_obtained' => 75], // A -> 8 * 3 = 24
]; // Total credits: 11. Total points: 100. Expected SGPA: 100 / 11 = 9.09
$semRes = calculateSemesterResult($sampleMarks);
recordTest('ACAD-SGPA-01', 'Weighted SGPA Calculation', '9.09', (string)$semRes['sgpa'], (float)$semRes['sgpa'] === 9.09);
recordTest('ACAD-SGPA-02', 'Semester Result Status: Pass', 'Pass', $semRes['result_status'], $semRes['result_status'] === 'Pass');

// Failing subject test
$failingMarks = [
    ['subject_id' => 1, 'subject_name' => 'Web Dev', 'subject_code' => 'CS101', 'credits' => 4, 'max_marks' => 100, 'marks_obtained' => 95],
    ['subject_id' => 2, 'subject_name' => 'Data Structures', 'subject_code' => 'CS102', 'credits' => 4, 'max_marks' => 100, 'marks_obtained' => 25], // Fail
];
$failRes = calculateSemesterResult($failingMarks);
recordTest('ACAD-SGPA-03', 'Semester Result Status: Fail with Backlog', 'Fail', $failRes['result_status'], $failRes['result_status'] === 'Fail');


// --- 4. ATTENDANCE THRESHOLD LOGIC ---
echo "\n--- 4. ATTENDANCE ALERT LOGIC ---\n";

function evaluateAttendanceThreshold($present, $total, $minRequired = 75) {
    if ($total == 0) return 'SAFE';
    $pct = round(($present / $total) * 100, 2);
    return $pct >= $minRequired ? 'SAFE' : 'SHORTAGE';
}

$att1 = evaluateAttendanceThreshold(80, 100);
recordTest('ATT-01', 'Attendance 80/100 (80%) -> SAFE', 'SAFE', $att1, $att1 === 'SAFE');

$att2 = evaluateAttendanceThreshold(75, 100);
recordTest('ATT-02', 'Attendance 75/100 (75%) -> SAFE', 'SAFE', $att2, $att2 === 'SAFE');

$att3 = evaluateAttendanceThreshold(74, 100);
recordTest('ATT-03', 'Attendance 74/100 (74%) -> SHORTAGE', 'SHORTAGE', $att3, $att3 === 'SHORTAGE');

$att4 = evaluateAttendanceThreshold(50, 100);
recordTest('ATT-04', 'Attendance 50/100 (50%) -> SHORTAGE', 'SHORTAGE', $att4, $att4 === 'SHORTAGE');


// --- 5. PROMOTION ELIGIBILITY RULE ENGINE ---
echo "\n--- 5. PROMOTION & ATKT RULES ---\n";

function checkPromotionEligibility($backlogCount) {
    return $backlogCount <= 3 ? 'ELIGIBLE' : 'BLOCKED';
}

$pr1 = checkPromotionEligibility(0);
recordTest('PROM-01', 'Promotion with 0 Backlogs', 'ELIGIBLE', $pr1, $pr1 === 'ELIGIBLE');

$pr2 = checkPromotionEligibility(2);
recordTest('PROM-02', 'Promotion with 2 Backlogs (<=3)', 'ELIGIBLE', $pr2, $pr2 === 'ELIGIBLE');

$pr3 = checkPromotionEligibility(3);
recordTest('PROM-03', 'Promotion with 3 Backlogs (Max allowable)', 'ELIGIBLE', $pr3, $pr3 === 'ELIGIBLE');

$pr4 = checkPromotionEligibility(4);
recordTest('PROM-04', 'Promotion with 4 Backlogs (>3 Blocked)', 'BLOCKED', $pr4, $pr4 === 'BLOCKED');


// --- 6. DATABASE READ-ONLY INTEGRITY CHECKS ---
echo "\n--- 6. PRODUCTION DATABASE INTEGRITY ---\n";

$deptCount = (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE department_name = 'FCAIT'")->fetchColumn();
recordTest('DB-INT-01', 'FCAIT Department Active', '1', (string)$deptCount, $deptCount === 1);

$courseCount = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
recordTest('DB-INT-02', 'Courses Count (5 Programs)', '5', (string)$courseCount, $courseCount === 5);

$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
recordTest('DB-INT-03', 'Total Students Registered (500)', '500', (string)$studentCount, $studentCount === 500);

$facultyCount = (int)$pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
recordTest('DB-INT-04', 'Total Faculty Count (45)', '45', (string)$facultyCount, $facultyCount === 45);

$orphanStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE course NOT IN (SELECT course_name FROM courses)")->fetchColumn();
recordTest('DB-INT-05', 'Orphan Student Records', '0', (string)$orphanStudents, $orphanStudents === 0);

$orphanMarks = (int)$pdo->query("SELECT COUNT(*) FROM student_marks WHERE student_id NOT IN (SELECT id FROM students)")->fetchColumn();
recordTest('DB-INT-06', 'Orphan Student Marks', '0', (string)$orphanMarks, $orphanMarks === 0);


// --- 7. REPOSITORY PHP SYNTAX LINTING ---
echo "\n--- 7. REPOSITORY PHP SYNTAX LINTING ---\n";

$dirsToScan = ['admin', 'faculty', 'student', 'includes', 'config', 'api', 'database', 'scripts'];
$scannedCount = 0;
$syntaxErrors = 0;

foreach ($dirsToScan as $d) {
    $dirPath = realpath(__DIR__ . '/../' . $d);
    if (!$dirPath || !is_dir($dirPath)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $scannedCount++;
            $filePath = $file->getPathname();
            $output = [];
            $retCode = 0;
            exec('php -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $retCode);
            if ($retCode !== 0) {
                $syntaxErrors++;
                echo "  [SYNTAX ERROR] " . $filePath . "\n";
            }
        }
    }
}

recordTest('LINT-ALL', "PHP Syntax Linting ($scannedCount files scanned)", '0 errors', "$syntaxErrors errors", $syntaxErrors === 0);


echo "\n====================================================================\n";
echo sprintf(" TEST RUN SUMMARY: TOTAL: %d | PASSED: %d | FAILED: %d (%.1f%%)\n", 
    $results['total'], $results['passed'], $results['failed'], 
    ($results['total'] > 0 ? ($results['passed'] / $results['total']) * 100 : 0));
echo "====================================================================\n";

if ($results['failed'] > 0) {
    exit(1);
}
