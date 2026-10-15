<?php
/**
 * GLS FCAIT SMS -- Comprehensive Full-System Page Loading, Refresh & Performance Benchmark
 * Tests all discovered routes across Admin, Faculty, and Student portals.
 * Fully authenticates with CSRF tokens and role-specific sessions.
 * Measures: HTTP code, Total time (ms), TTFB (ms), Bytes, Redirects, Meaningful Content, PHP errors.
 * Executes 5x consecutive refresh tests on key pages to report Median and Worst-Case times.
 */

set_time_limit(900);

$adminBase = "http://127.0.0.1:8000";
$studentBase = "http://127.0.0.1:9000";
$facultyBase = "http://127.0.0.1:9100";

$adminCookie = tempnam(sys_get_temp_dir(), 'adm_cookie_');
$facultyCookie = tempnam(sys_get_temp_dir(), 'fac_cookie_');
$studentCookie = tempnam(sys_get_temp_dir(), 'stu_cookie_');

function curlReq($url, $cookieFile, $postData = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SMS-PerformanceBenchmark/2.0');
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $duration = (microtime(true) - $start) * 1000;
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    $ttfbSec = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
    $ttfbMs = $ttfbSec ? round($ttfbSec * 1000, 1) : round($duration, 1);
    $error = curl_error($ch);
    curl_close($ch);
    
    $bytes = strlen($response ?: '');
    $hasErrors = false;
    $errorSnippets = [];
    if ($response) {
        if (stripos($response, 'Fatal error') !== false) {
            $hasErrors = true;
            $errorSnippets[] = 'Fatal error';
        }
        if (stripos($response, 'Parse error') !== false) {
            $hasErrors = true;
            $errorSnippets[] = 'Parse error';
        }
        if (stripos($response, 'Uncaught PDOException') !== false) {
            $hasErrors = true;
            $errorSnippets[] = 'PDOException';
        }
        if (stripos($response, 'Warning:') !== false) {
            $hasErrors = true;
            $errorSnippets[] = 'PHP Warning';
        }
    }
    
    $meaningful = false;
    if ($httpCode === 200 && $bytes > 200 && !$hasErrors) {
        if (stripos($response, '<body') !== false || stripos($response, '<html') !== false || stripos($response, 'GLS') !== false || stripos($response, 'FCAIT') !== false) {
            $meaningful = true;
        }
    }
    
    return [
        'code' => $httpCode,
        'time_ms' => round($duration, 1),
        'ttfb_ms' => $ttfbMs,
        'bytes' => $bytes,
        'url' => $finalUrl,
        'redirects' => $redirectCount,
        'meaningful' => $meaningful,
        'has_errors' => $hasErrors,
        'error_notes' => implode(', ', $errorSnippets) ?: ($error ?: 'None'),
        'raw_body' => $response
    ];
}

function loginPortal($base, $cookieFile, $loginId, $password) {
    @unlink($cookieFile);
    // 1. GET login page to obtain CSRF token
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m)) {
        return ['success' => false, 'error' => 'CSRF token missing from login form'];
    }
    $token = $m[1];
    
    // 2. POST credentials
    $start = microtime(true);
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'login' => '1',
        'csrf_token' => $token,
        'login_id' => $loginId,
        'password' => $password
    ]));
    $res = curl_exec($ch);
    $duration = (microtime(true) - $start) * 1000;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $ttfbSec = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
    $ttfbMs = $ttfbSec ? round($ttfbSec * 1000, 1) : round($duration, 1);
    curl_close($ch);
    
    $isSuccess = ($code === 200 && strpos($url, 'login.php') === false);
    return [
        'success' => $isSuccess,
        'code' => $code,
        'url' => $url,
        'time_ms' => round($duration, 1),
        'ttfb_ms' => $ttfbMs,
        'bytes' => strlen($res ?: '')
    ];
}

function calculateStats($arr) {
    if (empty($arr)) return ['min' => 0, 'max' => 0, 'median' => 0, 'mean' => 0];
    sort($arr);
    $count = count($arr);
    $min = $arr[0];
    $max = $arr[$count - 1];
    $mean = round(array_sum($arr) / $count, 1);
    $mid = (int) floor($count / 2);
    $median = ($count % 2 === 0) ? round(($arr[$mid - 1] + $arr[$mid]) / 2, 1) : $arr[$mid];
    return ['min' => $min, 'max' => $max, 'median' => $median, 'mean' => $mean];
}

echo "====================================================================\n";
echo " STEP 1: AUTHENTICATING TEST SESSIONS ACROSS ALL THREE PORTALS\n";
echo "====================================================================\n";

// 1. Admin login
$admRes = loginPortal($adminBase, $adminCookie, 'GLS_ADMIN', 'admin123');
echo sprintf("Admin Login:   %s | HTTP %d in %6.1f ms (TTFB: %6.1f ms, Target: %s)\n", 
    $admRes['success'] ? 'SUCCESS' : 'FAILED', $admRes['code'], $admRes['time_ms'], $admRes['ttfb_ms'], $admRes['url']);

// 2. Faculty login
$facRes = loginPortal($facultyBase, $facultyCookie, 'FAC-FCAIT-001', 'faculty123');
echo sprintf("Faculty Login: %s | HTTP %d in %6.1f ms (TTFB: %6.1f ms, Target: %s)\n", 
    $facRes['success'] ? 'SUCCESS' : 'FAILED', $facRes['code'], $facRes['time_ms'], $facRes['ttfb_ms'], $facRes['url']);

// 3. Student login
$stuRes = loginPortal($studentBase, $studentCookie, '2021IMSIT0309', 'student123');
echo sprintf("Student Login: %s | HTTP %d in %6.1f ms (TTFB: %6.1f ms, Target: %s)\n\n", 
    $stuRes['success'] ? 'SUCCESS' : 'FAILED', $stuRes['code'], $stuRes['time_ms'], $stuRes['ttfb_ms'], $stuRes['url']);

if (!$admRes['success'] || !$facRes['success'] || !$stuRes['success']) {
    die("FATAL: Portal authentication failed. Aborting benchmark.\n");
}

$portals = [
    'Admin' => [
        'base' => $adminBase,
        'cookie' => $adminCookie,
        'pages' => [
            'dashboard.php',
            'students.php',
            'view_student.php?id=309',
            'add_student.php',
            'edit_student.php?id=309',
            'faculty.php',
            'faculty_assignments.php',
            'courses.php',
            'subjects.php',
            'attendance.php',
            'attendance_reports.php',
            'manage_marks.php',
            'report_cards.php',
            'report_card.php?id=309',
            'examinations.php',
            'exam_schedules.php',
            'promote_students.php',
            'fee_structures.php',
            'fee_payments.php',
            'fee_reports.php',
            'student_fees.php',
            'timetable.php',
            'leadership.php',
            'departments.php',
            'divisions.php',
            'semesters.php',
            'academic_years.php',
            'academic_calendar.php',
            'academic_reports.php',
            'assignments.php',
            'announcements.php',
            'activity_logs.php',
            'audit_logs.php',
            'notifications.php',
            'settings.php',
            'users.php',
            'performance_ai.php',
            'topper_board.php',
            'certificate.php',
            'print_certificate.php?student_id=309',
            'print_marksheet.php?student_id=309&semester=1',
            'print_receipt.php?payment_id=1',
            'print_halltickets.php?exam_id=90012'
        ],
        'deep_refresh_pages' => [
            'dashboard.php',
            'students.php',
            'attendance_reports.php',
            'manage_marks.php',
            'report_cards.php',
            'fee_reports.php',
            'activity_logs.php',
            'topper_board.php'
        ]
    ],
    'Faculty' => [
        'base' => $facultyBase,
        'cookie' => $facultyCookie,
        'pages' => [
            'dashboard.php',
            'my_subjects.php',
            'my_students.php',
            'attendance.php',
            'marks.php',
            'assignments.php',
            'view_submissions.php?assignment_id=1',
            'study_materials.php',
            'timetable.php',
            'dept_reports.php',
            'course_reports.php',
            'course_faculty.php',
            'course_students.php',
            'course_overview.php',
            'coordinators.php',
            'all_faculty.php',
            'all_students.php',
            'org_chart.php',
            'profile.php',
            'notifications.php',
            'announcements.php',
            'student_detail.php?id=309'
        ],
        'deep_refresh_pages' => [
            'dashboard.php',
            'my_students.php',
            'attendance.php',
            'marks.php',
            'dept_reports.php',
            'course_reports.php'
        ]
    ],
    'Student' => [
        'base' => $studentBase,
        'cookie' => $studentCookie,
        'pages' => [
            'dashboard.php',
            'profile.php',
            'subjects.php',
            'attendance.php',
            'marks.php',
            'results.php',
            'report_card.php',
            'examinations.php',
            'timetable.php',
            'assignments.php',
            'submit_assignment.php?id=1',
            'study_material.php',
            'fees.php',
            'print_receipt.php?fee_id=1',
            'notifications.php',
            'announcements.php',
            'achievements.php'
        ],
        'deep_refresh_pages' => [
            'dashboard.php',
            'attendance.php',
            'marks.php',
            'results.php',
            'fees.php',
            'report_card.php'
        ]
    ]
];

echo "====================================================================\n";
echo " STEP 2: BENCHMARKING REAL PAGE ROUTES WITH REPEAT REFRESH CYCLES\n";
echo "====================================================================\n";

$allResults = [];
$totalTested = 0;
$totalPassed = 0;

foreach ($portals as $portalName => $cfg) {
    echo "\n>>> PORTAL: $portalName ({$cfg['base']}) <<<\n";
    printf("%-34s | HTTP | Init(ms) | TTFB(ms) | 5x Median | Worst(ms) | Bytes | Content | Errors\n", "Page Route");
    echo str_repeat("-", 105) . "\n";
    
    foreach ($cfg['pages'] as $page) {
        $totalTested++;
        $url = "{$cfg['base']}/$page";
        $isDeep = in_array($page, $cfg['deep_refresh_pages']);
        $refreshCount = $isDeep ? 5 : 2;
        
        $runs = [];
        $firstRun = null;
        
        for ($i = 0; $i < $refreshCount; $i++) {
            $res = curlReq($url, $cfg['cookie']);
            if ($i === 0) {
                $firstRun = $res;
            }
            $runs[] = $res['time_ms'];
            usleep(25000); // 25ms delay
        }
        
        $stats = calculateStats($runs);
        
        $isAuthProtected = (strpos($firstRun['url'], 'login.php') === false);
        $statusStr = ($firstRun['code'] === 200 && $isAuthProtected && $firstRun['meaningful']) ? 'PASS' : 'WARN';
        if ($statusStr === 'PASS') {
            $totalPassed++;
        }
        
        $contentStr = $firstRun['meaningful'] ? 'Valid' : ($isAuthProtected ? 'Sparse' : 'LoginRedirect');
        
        printf("%-34s | %4d | %8.1f | %8.1f | %9.1f | %9.1f | %5d | %-7s | %s\n",
            $page,
            $firstRun['code'],
            $firstRun['time_ms'],
            $firstRun['ttfb_ms'],
            $stats['median'],
            $stats['max'],
            $firstRun['bytes'],
            $contentStr,
            $firstRun['has_errors'] ? $firstRun['error_notes'] : 'Clean'
        );
        
        $allResults[] = [
            'portal' => $portalName,
            'page' => $page,
            'url' => $url,
            'code' => $firstRun['code'],
            'initial_ms' => $firstRun['time_ms'],
            'ttfb_ms' => $firstRun['ttfb_ms'],
            'median_ms' => $stats['median'],
            'worst_ms' => $stats['max'],
            'refresh_runs' => $runs,
            'bytes' => $firstRun['bytes'],
            'redirects' => $firstRun['redirects'],
            'is_auth_protected' => $isAuthProtected,
            'meaningful' => $firstRun['meaningful'],
            'has_errors' => $firstRun['has_errors'],
            'error_notes' => $firstRun['error_notes'],
            'deep_tested' => $isDeep
        ];
    }
}

$outputFile = dirname(__DIR__) . '/PROJECT_FINAL_AUDIT/benchmark_results.json';
file_put_contents($outputFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'total_routes' => $totalTested,
    'passed_routes' => $totalPassed,
    'results' => $allResults
], JSON_PRETTY_PRINT));

echo "\n====================================================================\n";
echo " BENCHMARK SUMMARY: TOTAL: $totalTested | PASSED: $totalPassed | FAILED: " . ($totalTested - $totalPassed) . "\n";
echo " Full benchmark saved to PROJECT_FINAL_AUDIT/benchmark_results.json\n";
echo "====================================================================\n";
