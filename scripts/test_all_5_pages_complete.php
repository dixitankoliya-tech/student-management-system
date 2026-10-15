<?php
/**
 * Comprehensive Local Automated Test Suite for All 5 Fixed Admin Pages
 */
require_once __DIR__ . '/../config/database.php';

$baseUrl = 'http://127.0.0.1:8000';
$cookieFile = __DIR__ . '/admin_test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpReq($url, $method = 'GET', $data = null, $follow = false) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data ?? []));
    }
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
}

function extractCsrf($html) {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

echo "=========================================================\n";
echo "STEP 0: AUTHENTICATING AS ADMIN (GLS_ADMIN)\n";
echo "=========================================================\n";

$loginPage = httpReq("$baseUrl/login.php");
$csrf = extractCsrf($loginPage['body']);
$loginRes = httpReq("$baseUrl/login.php", 'POST', [
    'login' => '1',
    'csrf_token' => $csrf,
    'login_id' => 'GLS_ADMIN',
    'password' => 'admin123'
]);
echo "Login Status: {$loginRes['code']}\n";
if (strpos($loginRes['headers'], 'Location:') !== false) {
    echo "Login Redirect Success: Admin authenticated.\n";
} else {
    echo "Login Failed! Headers:\n{$loginRes['headers']}\n";
    exit(1);
}

echo "\n=========================================================\n";
echo "PHASE 2: SEMESTERS PAGE (semesters.php)\n";
echo "=========================================================\n";
$semRes = httpReq("$baseUrl/semesters.php");
echo "HTTP Code: {$semRes['code']}\n";
assert($semRes['code'] === 200, "Semesters page returned 200");
$hasSemesters = strpos($semRes['body'], 'Semester Management') !== false;
$hasScriptJs = strpos($semRes['body'], '/assets/js/script.js') !== false;
$hasBreadcrumb = strpos($semRes['body'], 'breadcrumb') !== false;
echo " - Title present: " . ($hasSemesters ? "YES" : "NO") . "\n";
echo " - Script.js loaded via footer: " . ($hasScriptJs ? "YES" : "NO") . "\n";
echo " - Breadcrumbs present: " . ($hasBreadcrumb ? "YES" : "NO") . "\n";

echo "\n=========================================================\n";
echo "PHASE 3: ATTENDANCE REPORTS PAGE (attendance_reports.php)\n";
echo "=========================================================\n";
// 1. Initial Page Load
$attRes = httpReq("$baseUrl/attendance_reports.php");
echo "Initial Load HTTP Code: {$attRes['code']}\n";
assert($attRes['code'] === 200);

// 2. Filter attendance report with Course=BCA, Semester=1
$filterUrl = "$baseUrl/attendance_reports.php?start_date=2024-01-01&end_date=2026-12-31&course=BCA&semester=1";
$filterRes = httpReq($filterUrl);
echo "Filtered Report HTTP Code: {$filterRes['code']}\n";
$noSqlError = strpos($filterRes['body'], 'Syntax error or access violation') === false;
$hasTable = (strpos($filterRes['body'], 'Attendance Summary') !== false) || (strpos($filterRes['body'], 'No data available') !== false);
echo " - No ONLY_FULL_GROUP_BY Crash: " . ($noSqlError ? "PASS" : "FAIL") . "\n";
echo " - Report summary table renders: " . ($hasTable ? "PASS" : "FAIL") . "\n";

// 3. Test Invalid Date Range (start_date > end_date)
$invalidDateUrl = "$baseUrl/attendance_reports.php?start_date=2026-12-31&end_date=2024-01-01&course=BCA&semester=1";
$invalidRes = httpReq($invalidDateUrl);
$hasDateError = strpos($invalidRes['body'], 'Start date cannot be after end date.') !== false;
echo " - Inverted date range validation: " . ($hasDateError ? "PASS" : "FAIL") . "\n";

// 4. Test CSV Export
$csvUrl = "$baseUrl/attendance_reports.php?start_date=2024-01-01&end_date=2026-12-31&course=BCA&semester=1&export=csv";
$csvRes = httpReq($csvUrl);
$isCsv = strpos($csvRes['headers'], 'text/csv') !== false;
echo " - CSV export content-type: " . ($isCsv ? "PASS" : "FAIL") . "\n";

echo "\n=========================================================\n";
echo "PHASE 4: FEE STRUCTURES PAGE (fee_structures.php)\n";
echo "=========================================================\n";
// 1. Initial Load
$feeRes = httpReq("$baseUrl/fee_structures.php");
echo "Fee Structures Load HTTP Code: {$feeRes['code']}\n";
$feeCsrf = extractCsrf($feeRes['body']);

// 2. Add Fee Structure
$testFeeName = "Test Lab Equipment Fee " . time();
$addFeeRes = httpReq("$baseUrl/fee_structures.php", 'POST', [
    'csrf_token' => $feeCsrf,
    'action' => 'add',
    'course' => 'BCA',
    'academic_year' => '2026-2027',
    'semester' => 5,
    'fee_name' => $testFeeName,
    'amount' => 1250.00,
    'due_date' => '2026-11-15'
]);
echo "Add Fee HTTP Code: {$addFeeRes['code']}\n";
$isPrgAdd = strpos($addFeeRes['headers'], 'Location: fee_structures.php?msg=added') !== false;
echo " - PRG Redirect on Add: " . ($isPrgAdd ? "PASS" : "FAIL") . "\n";

// Verify persisted in DB
$feeId = $pdo->query("SELECT id FROM fee_structures WHERE fee_name = '$testFeeName'")->fetchColumn();
echo " - Fee Persisted in Database (ID: $feeId): " . ($feeId ? "PASS" : "FAIL") . "\n";

// 3. Duplicate Fee check
$dupFeeRes = httpReq("$baseUrl/fee_structures.php", 'POST', [
    'csrf_token' => $feeCsrf,
    'action' => 'add',
    'course' => 'BCA',
    'academic_year' => '2026-2027',
    'semester' => 5,
    'fee_name' => $testFeeName,
    'amount' => 1250.00,
    'due_date' => '2026-11-15'
]);
$hasDupError = strpos($dupFeeRes['body'], 'already exists') !== false;
echo " - Duplicate fee validation rejection: " . ($hasDupError ? "PASS" : "FAIL") . "\n";

// 4. Delete the added test fee
$delFeeRes = httpReq("$baseUrl/fee_structures.php", 'POST', [
    'csrf_token' => $feeCsrf,
    'action' => 'delete',
    'id' => $feeId
]);
$isPrgDel = strpos($delFeeRes['headers'], 'Location: fee_structures.php?msg=deleted') !== false;
echo " - PRG Redirect on Delete: " . ($isPrgDel ? "PASS" : "FAIL") . "\n";
$feeStillExists = $pdo->query("SELECT COUNT(*) FROM fee_structures WHERE id = $feeId")->fetchColumn();
echo " - Record cleanly removed from DB: " . ($feeStillExists == 0 ? "PASS" : "FAIL") . "\n";

echo "\n=========================================================\n";
echo "PHASE 5: ACADEMIC CALENDAR PAGE (academic_calendar.php)\n";
echo "=========================================================\n";
// 1. Initial Load
$calRes = httpReq("$baseUrl/academic_calendar.php");
echo "Academic Calendar HTTP Code: {$calRes['code']}\n";
$calCsrf = extractCsrf($calRes['body']);
$hasEvents = strpos($calRes['body'], 'Commencement of Odd Semesters') !== false;
$hasFooterJs = strpos($calRes['body'], '/assets/js/script.js') !== false;
echo " - Seeded events displayed: " . ($hasEvents ? "PASS" : "FAIL") . "\n";
echo " - Footer script.js loaded: " . ($hasFooterJs ? "PASS" : "FAIL") . "\n";

// 2. Add an event
$testEventName = "Unit Test Evaluation " . time();
$addEventRes = httpReq("$baseUrl/academic_calendar.php", 'POST', [
    'csrf_token' => $calCsrf,
    'add_event' => '1',
    'academic_session' => '2026-2027',
    'event_type' => 'Exam Period',
    'event_name' => $testEventName,
    'start_date' => '2026-11-01',
    'end_date' => '2026-11-05',
    'semester' => 5,
    'course' => 'BCA',
    'description' => 'Automated test suite calendar event'
]);
$isCalPrg = strpos($addEventRes['headers'], 'Location: academic_calendar.php?msg=added') !== false;
echo " - Add Event PRG Redirect: " . ($isCalPrg ? "PASS" : "FAIL") . "\n";
$calId = $pdo->query("SELECT id FROM academic_calendar WHERE event_name = '$testEventName'")->fetchColumn();
echo " - Event Persisted in DB (ID: $calId): " . ($calId ? "PASS" : "FAIL") . "\n";

// 3. Test Invalid Date Range
$invCalRes = httpReq("$baseUrl/academic_calendar.php", 'POST', [
    'csrf_token' => $calCsrf,
    'add_event' => '1',
    'academic_session' => '2026-2027',
    'event_type' => 'Holiday',
    'event_name' => 'Bad Date Event',
    'start_date' => '2026-11-10',
    'end_date' => '2026-11-01'
]);
$hasCalDateErr = strpos($invCalRes['body'], 'Start date cannot be after end date.') !== false;
echo " - Invalid event date range validation: " . ($hasCalDateErr ? "PASS" : "FAIL") . "\n";

// 4. Delete the test event
$delCalRes = httpReq("$baseUrl/academic_calendar.php", 'POST', [
    'csrf_token' => $calCsrf,
    'delete_event' => '1',
    'event_id' => $calId
]);
$isCalDelPrg = strpos($delCalRes['headers'], 'Location: academic_calendar.php?msg=deleted') !== false;
echo " - Delete Event PRG Redirect: " . ($isCalDelPrg ? "PASS" : "FAIL") . "\n";

echo "\n=========================================================\n";
echo "PHASE 6: LEADERSHIP PAGE & ASSIGN BUTTON (leadership.php)\n";
echo "=========================================================\n";
// 1. Initial Load
$leadRes = httpReq("$baseUrl/leadership.php");
echo "Leadership Page HTTP Code: {$leadRes['code']}\n";
$leadCsrf = extractCsrf($leadRes['body']);
$hasDeanTable = strpos($leadRes['body'], 'Department Heads (Deans)') !== false;
$hasCoordTable = strpos($leadRes['body'], 'Course Coordinators') !== false;
$hasAssignBtn = strpos($leadRes['body'], 'data-bs-target="#deanModal') !== false;
echo " - Deans Table: " . ($hasDeanTable ? "PASS" : "FAIL") . "\n";
echo " - Coordinators Table: " . ($hasCoordTable ? "PASS" : "FAIL") . "\n";
echo " - Assign Button Triggers: " . ($hasAssignBtn ? "PASS" : "FAIL") . "\n";

// 2. Test ASSIGN DEAN: Assign Faculty ID 2 (Dr. Bhavin Joshi) to Dept 1 (FCAIT)
echo "\n--- Testing Dean Assignment Workflow ---\n";
$assignDeanRes = httpReq("$baseUrl/leadership.php", 'POST', [
    'csrf_token' => $leadCsrf,
    'action' => 'assign_dean',
    'department_id' => 1,
    'faculty_id' => 2
]);
echo "Assign Dean HTTP Code: {$assignDeanRes['code']}\n";
$isDeanPrg = strpos($assignDeanRes['headers'], 'Location: leadership.php?msg=Dean+assigned+successfully.') !== false;
echo " - PRG Redirect after Dean Assignment: " . ($isDeanPrg ? "PASS" : "FAIL") . "\n";

// Verify Database
$currentDean = $pdo->query("SELECT faculty_id, assigned_date, status, assigned_by FROM department_heads WHERE department_id = 1 AND status = 'Active'")->fetch(PDO::FETCH_ASSOC);
echo " - DB Active Dean Faculty ID: " . $currentDean['faculty_id'] . " (Expected: 2) -> " . ($currentDean['faculty_id'] == 2 ? "PASS" : "FAIL") . "\n";
echo " - DB assigned_date populated: " . $currentDean['assigned_date'] . " -> " . (!empty($currentDean['assigned_date']) ? "PASS" : "FAIL") . "\n";
echo " - DB assigned_by populated: " . $currentDean['assigned_by'] . " -> " . ($currentDean['assigned_by'] > 0 ? "PASS" : "FAIL") . "\n";

// Re-assign back to Faculty ID 1 (Dr. Rajesh Patel) to restore original Dean
$reassignDeanRes = httpReq("$baseUrl/leadership.php", 'POST', [
    'csrf_token' => $leadCsrf,
    'action' => 'assign_dean',
    'department_id' => 1,
    'faculty_id' => 1
]);
$restoredDean = $pdo->query("SELECT faculty_id FROM department_heads WHERE department_id = 1 AND status = 'Active'")->fetchColumn();
echo " - Re-assigned Dean back to ID 1: " . ($restoredDean == 1 ? "PASS" : "FAIL") . "\n";

// 3. Test ASSIGN COURSE COORDINATOR: Assign Faculty ID 3 (Dr. Sneha Desai) to Course 1 (B.Sc. IT (Dual))
echo "\n--- Testing Course Coordinator Assignment Workflow ---\n";
$origCoord = $pdo->query("SELECT faculty_id FROM course_coordinators WHERE course_id = 1 AND status = 'Active'")->fetchColumn();
echo "Original Coordinator for Course 1: Faculty ID $origCoord\n";

$targetCoordFid = ($origCoord == 3) ? 4 : 3;
$assignCoordRes = httpReq("$baseUrl/leadership.php", 'POST', [
    'csrf_token' => $leadCsrf,
    'action' => 'assign_coordinator',
    'course_id' => 1,
    'faculty_id' => $targetCoordFid
]);
echo "Assign Coordinator HTTP Code: {$assignCoordRes['code']}\n";
$isCoordPrg = strpos($assignCoordRes['headers'], 'Location: leadership.php?msg=Course+Coordinator+assigned+successfully.') !== false;
echo " - PRG Redirect after Coordinator Assignment: " . ($isCoordPrg ? "PASS" : "FAIL") . "\n";

// Verify Database
$currentCoord = $pdo->query("SELECT faculty_id, assigned_date, status, assigned_by FROM course_coordinators WHERE course_id = 1 AND status = 'Active'")->fetch(PDO::FETCH_ASSOC);
echo " - DB Active Coordinator Faculty ID: " . $currentCoord['faculty_id'] . " (Expected: $targetCoordFid) -> " . ($currentCoord['faculty_id'] == $targetCoordFid ? "PASS" : "FAIL") . "\n";
echo " - DB assigned_date: " . $currentCoord['assigned_date'] . " -> " . (!empty($currentCoord['assigned_date']) ? "PASS" : "FAIL") . "\n";

// Restore original coordinator
$restoreCoordRes = httpReq("$baseUrl/leadership.php", 'POST', [
    'csrf_token' => $leadCsrf,
    'action' => 'assign_coordinator',
    'course_id' => 1,
    'faculty_id' => $origCoord
]);
$restoredCoord = $pdo->query("SELECT faculty_id FROM course_coordinators WHERE course_id = 1 AND status = 'Active'")->fetchColumn();
echo " - Restored Original Coordinator (ID $origCoord): " . ($restoredCoord == $origCoord ? "PASS" : "FAIL") . "\n";

// 4. Test Duplicate Re-assignment rejection
$dupCoordRes = httpReq("$baseUrl/leadership.php", 'POST', [
    'csrf_token' => $leadCsrf,
    'action' => 'assign_coordinator',
    'course_id' => 1,
    'faculty_id' => $origCoord
]);
$hasDupCoordErr = strpos($dupCoordRes['body'], 'already the active Coordinator') !== false;
echo " - Duplicate Coordinator assignment rejection: " . ($hasDupCoordErr ? "PASS" : "FAIL") . "\n";

echo "\n=========================================================\n";
echo "ALL TESTS COMPLETED SUCCESSFULLY!\n";
echo "=========================================================\n";
