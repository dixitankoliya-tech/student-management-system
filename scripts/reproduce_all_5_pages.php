<?php
/**
 * Reproduction & Audit Test Script for the 5 Audited Admin Pages
 */
require_once __DIR__ . '/../config/database.php';

$cookie = tempnam(sys_get_temp_dir(), 'admin_audit_ck_');
@unlink($cookie);

// 1. GET login.php
$ch = curl_init("http://127.0.0.1:8000/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m);
$token = $m[1] ?? '';
echo "CSRF Token: " . substr($token, 0, 10) . "...\n";

// 2. POST login credentials
$ch = curl_init("http://127.0.0.1:8000/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'login' => '1',
    'csrf_token' => $token,
    'login_id' => 'GLS_ADMIN',
    'password' => 'admin123'
]));
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
echo "Login result: HTTP $code | Target: $url\n";

echo "\n========================================================\n";
echo "AUDITING THE 5 ADMIN PAGES LOCALLY (http://127.0.0.1:8000)\n";
echo "========================================================\n";

$pages = [
    'semesters.php',
    'attendance_reports.php',
    'fee_structures.php',
    'academic_calendar.php',
    'leadership.php'
];

foreach ($pages as $p) {
    echo "\n--- PAGE: $p ---\n";
    $ch = curl_init("http://127.0.0.1:8000/$p");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    $pageHtml = curl_exec($ch);
    $pageCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    
    echo "HTTP Status: $pageCode | Bytes: " . strlen($pageHtml) . ($redirectUrl ? " | Redirect: $redirectUrl" : "") . "\n";
    
    $wrapperCount = substr_count($pageHtml, 'id="wrapper"');
    $contentWrapperCount = substr_count($pageHtml, 'id="page-content-wrapper"');
    $hasFooterScript = strpos($pageHtml, '/assets/js/script.js') !== false;
    
    echo " - id=\"wrapper\" count: $wrapperCount " . ($wrapperCount === 1 ? "[OK]" : "[DEFECT: $wrapperCount instances]") . "\n";
    echo " - id=\"page-content-wrapper\" count: $contentWrapperCount " . ($contentWrapperCount === 1 ? "[OK]" : "[DEFECT: $contentWrapperCount instances]") . "\n";
    echo " - Includes footer script.js: " . ($hasFooterScript ? "[YES]" : "[DEFECT: MISSING FOOTER]") . "\n";
}

echo "\n========================================================\n";
echo "TESTING LEADERSHIP.PHP ASSIGN BUTTON WORKFLOW\n";
echo "========================================================\n";

// GET leadership.php to obtain current CSRF and test modal/form presence
$ch = curl_init("http://127.0.0.1:8000/leadership.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$leadHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $leadHtml, $m);
$leadCsrf = $m[1] ?? '';
echo "Leadership CSRF: " . substr($leadCsrf, 0, 10) . "...\n";

// Check if Assign buttons and modals exist in HTML
$deanAssignBtn = strpos($leadHtml, 'data-bs-target="#deanModal') !== false;
$coordAssignBtn = strpos($leadHtml, 'data-bs-target="#coordModal') !== false;
echo " - Dean Assign Button present in HTML: " . ($deanAssignBtn ? "YES" : "NO") . "\n";
echo " - Coordinator Assign Button present in HTML: " . ($coordAssignBtn ? "YES" : "NO") . "\n";

// POST Assign Dean (Department 1, Faculty 2 - Dr. Bhavin Joshi)
$ch = curl_init("http://127.0.0.1:8000/leadership.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $leadCsrf,
    'action' => 'assign_dean',
    'department_id' => '1',
    'faculty_id' => '2'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$assignRes = curl_exec($ch);
curl_close($ch);

echo "\nAssign Dean Submission Result:\n";
if (strpos($assignRes, 'Error updating assignment:') !== false) {
    preg_match('/Error updating assignment:[^<]+/i', $assignRes, $em);
    echo " --> CONFIRMED DEFECT: " . trim($em[0]) . "\n";
} elseif (strpos($assignRes, 'Dean assigned successfully') !== false) {
    echo " --> Success\n";
} else {
    echo " --> Response did not contain success or expected error\n";
}

// POST Assign Coordinator (Course 1, Faculty 3 - Dr. Sneha Desai)
$ch = curl_init("http://127.0.0.1:8000/leadership.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $leadCsrf,
    'action' => 'assign_coordinator',
    'course_id' => '1',
    'faculty_id' => '3'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$coordRes = curl_exec($ch);
curl_close($ch);

echo "\nAssign Coordinator Submission Result:\n";
if (strpos($coordRes, 'Error updating assignment:') !== false) {
    preg_match('/Error updating assignment:[^<]+/i', $coordRes, $em);
    echo " --> CONFIRMED DEFECT: " . trim($em[0]) . "\n";
} elseif (strpos($coordRes, 'Course Coordinator assigned successfully') !== false) {
    echo " --> Success\n";
} else {
    echo " --> Response did not contain success or expected error\n";
}

@unlink($cookie);
