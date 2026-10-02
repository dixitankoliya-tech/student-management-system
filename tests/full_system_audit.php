<?php
// tests/full_system_audit.php
require_once __DIR__ . '/../config/database.php';

$report = [
    'audit_date' => date('Y-m-d H:i:s'),
    'environment' => 'Windows XAMPP',
    'php_version' => phpversion(),
    'database' => 'MySQL',
    'summary' => [
        'p0' => 0,
        'p1' => 0,
        'p2' => 0,
        'p3' => 0,
        'total' => 0
    ],
    'bugs' => [],
    'runtime_errors' => [],
    'security_findings' => [],
    'database_findings' => [],
    'business_rule_findings' => [],
    'tested_pages' => [],
    'tested_workflows' => []
];

$idCounter = 1;
function addBug(&$report, $severity, $portal, $module, $problem, $expected, $actual, $evidence) {
    global $idCounter;
    $report['bugs'][] = [
        'bug_id' => 'BUG-' . str_pad($idCounter++, 3, '0', STR_PAD_LEFT),
        'severity' => $severity,
        'portal' => $portal,
        'module' => $module,
        'problem' => $problem,
        'expected' => $expected,
        'actual' => $actual,
        'evidence' => $evidence,
        'status' => 'OPEN'
    ];
    $severityLower = strtolower($severity);
    if (isset($report['summary'][$severityLower])) {
        $report['summary'][$severityLower]++;
    }
    $report['summary']['total']++;
}

// 1. Lint PHP Files
$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getPathname(), 'vendor') === false) {
        $phpFiles[] = $file->getPathname();
    }
}

$lintErrors = 0;
foreach ($phpFiles as $file) {
    $output = [];
    $returnVar = 0;
    exec('C:\\xampp\\php\\php.exe -l "' . escapeshellarg($file) . '" 2>&1', $output, $returnVar);
    if ($returnVar !== 0) {
        $lintErrors++;
        addBug($report, 'P0', 'ALL', 'Syntax', 'Parse error in ' . basename($file), 'No syntax errors', implode("\n", $output), $file);
    }
}

// 2. Database Integrity - Orphans
$orphanStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND student_id NOT IN (SELECT id FROM students)")->fetchColumn();
if ($orphanStudents > 0) {
    addBug($report, 'P2', 'DB', 'Users', 'Orphan student users exist', '0 orphans', "$orphanStudents orphans", 'users table');
}

$orphanFaculty = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty' AND faculty_id NOT IN (SELECT id FROM faculty)")->fetchColumn();
if ($orphanFaculty > 0) {
    addBug($report, 'P2', 'DB', 'Users', 'Orphan faculty users exist', '0 orphans', "$orphanFaculty orphans", 'users table');
}

// 3. Business Rule: Max 100 students per class
$capacityViolations = $pdo->query("
    SELECT course, academic_year, semester, division, COUNT(DISTINCT student_id) as c 
    FROM student_academic_enrollments 
    WHERE status='Active' 
    GROUP BY course, academic_year, semester, division 
    HAVING c > 100
")->fetchAll();
if (count($capacityViolations) > 0) {
    addBug($report, 'P1', 'DB', 'Enrollment', 'Class capacity > 100', '<= 100', count($capacityViolations) . ' classes exceeded limit', json_encode($capacityViolations));
}

// 4. Business Rule: Max 4 subjects per faculty
$activePeriod = $pdo->query("SELECT * FROM academic_calendars WHERE status='Active' LIMIT 1")->fetch();
if ($activePeriod) {
    $mod = ($activePeriod['semester_type'] === 'Odd') ? 1 : 0;
    $facultyWorkloadViolations = $pdo->prepare("
        SELECT faculty_id, COUNT(*) as c 
        FROM faculty_subjects 
        WHERE academic_year = ? AND (semester % 2) = ? 
        GROUP BY faculty_id 
        HAVING c > 4
    ");
    $facultyWorkloadViolations->execute([$activePeriod['academic_year'], $mod]);
    $violations = $facultyWorkloadViolations->fetchAll();
    if (count($violations) > 0) {
        addBug($report, 'P1', 'DB', 'Faculty Assignments', 'Faculty teaching > 4 subjects', '<= 4 subjects', count($violations) . ' faculties exceeded limit', json_encode($violations));
    }
} else {
    addBug($report, 'P1', 'DB', 'Calendar', 'No active academic calendar found', '1 active calendar', '0 found', 'academic_calendars');
}

// 5. Duplicates: Faculty Subjects
$duplicateSubjects = $pdo->query("
    SELECT faculty_id, subject_id, academic_year, division, COUNT(*) as c 
    FROM faculty_subjects 
    GROUP BY faculty_id, subject_id, academic_year, division 
    HAVING c > 1
")->fetchAll();
if (count($duplicateSubjects) > 0) {
    addBug($report, 'P2', 'DB', 'Faculty Assignments', 'Duplicate faculty subject assignments exist', '0 duplicates', count($duplicateSubjects) . ' duplicates', json_encode($duplicateSubjects));
}

// HTTP Pages Basic Check
$urlsToTest = [
    'http://localhost:8000/admin/login.php',
    'http://localhost:9100/faculty/login.php',
    'http://localhost:9000/student/login.php',
    'http://localhost:8000/admin/dashboard.php', // should redirect to login, not 500
];

foreach ($urlsToTest as $url) {
    $report['tested_pages'][] = $url;
    $headers = @get_headers($url);
    if (!$headers || strpos($headers[0], '500') !== false || strpos($headers[0], '404') !== false) {
        addBug($report, 'P0', 'ALL', 'HTTP', "Page failed to load or returned error: $url", 'HTTP 200 or 302', $headers ? $headers[0] : 'Connection Failed', $url);
    }
}

file_put_contents(__DIR__ . '/full_system_audit_output.json', json_encode($report, JSON_PRETTY_PRINT));
echo "Audit complete. Output written to full_system_audit_output.json\n";
