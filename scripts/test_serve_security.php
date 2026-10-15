<?php
/**
 * Test Suite for serve.php Access Control & Security
 */

$facultyBase = "http://127.0.0.1:9100";
$studentBase = "http://127.0.0.1:9000";
$adminBase = "http://127.0.0.1:8000";

$facultyCookie = tempnam(sys_get_temp_dir(), 'test_fac_cookie_');
$studentCookie = tempnam(sys_get_temp_dir(), 'test_stu_cookie_');
$unauthCookie = tempnam(sys_get_temp_dir(), 'test_anon_cookie_');

function login($base, $cookieFile, $username, $password) {
    @unlink($cookieFile);
    // GET login page for CSRF
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $html = curl_exec($ch);
    curl_close($ch);
    
    preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m);
    $token = $m[1] ?? '';
    
    // POST credentials
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'login' => '1',
        'csrf_token' => $token,
        'login_id' => $username,
        'password' => $password
    ]));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    return ($code === 200 && strpos($url, 'login.php') === false);
}

function req($url, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($cookieFile && file_exists($cookieFile)) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($res, 0, $headerSize);
    $body = substr($res, $headerSize);
    curl_close($ch);
    
    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

echo "============================================================\n";
echo " TESTING SERVE.PHP ACCESS CONTROL, TRAVERSAL & HEADERS\n";
echo "============================================================\n";

// Authenticate Faculty and Student
$facOk = login($facultyBase, $facultyCookie, 'FAC-FCAIT-001', 'faculty123');
echo "Faculty Auth: " . ($facOk ? "PASS" : "FAIL") . "\n";

$stuOk = login($studentBase, $studentCookie, '2021IMSIT0309', 'student123');
echo "Student Auth: " . ($stuOk ? "PASS" : "FAIL") . "\n\n";

$tests = [];

// Test 1: Unauthenticated request to faculty serve.php
$r1 = req("$facultyBase/serve.php?file=uploads/materials/unit_notes_1.pdf", $unauthCookie);
$tests[] = [
    'name' => '1. Unauthenticated Material Access Blocked',
    'pass' => ($r1['code'] === 403),
    'detail' => "HTTP {$r1['code']} (Expected: 403 Forbidden)"
];

// Test 2: Authenticated Faculty viewing material
$r2 = req("$facultyBase/serve.php?file=uploads/materials/unit_notes_1.pdf&action=view", $facultyCookie);
$hasPdfHeader = stripos($r2['headers'], 'Content-Type: application/pdf') !== false;
$hasInline = stripos($r2['headers'], 'Content-Disposition: inline') !== false;
$hasPdfMagic = str_starts_with($r2['body'], '%PDF');
$tests[] = [
    'name' => '2. Authenticated Faculty View Material (Inline PDF)',
    'pass' => ($r2['code'] === 200 && $hasPdfHeader && $hasInline && $hasPdfMagic),
    'detail' => "HTTP {$r2['code']}, Content-Type: " . ($hasPdfHeader ? 'PDF' : 'Unknown') . ", Magic: " . ($hasPdfMagic ? 'Valid PDF' : 'Invalid')
];

// Test 3: Authenticated Faculty downloading material
$r3 = req("$facultyBase/serve.php?file=uploads/materials/unit_notes_1.pdf&action=download", $facultyCookie);
$hasAttachment = stripos($r3['headers'], 'attachment; filename="unit_notes_1.pdf"') !== false;
$tests[] = [
    'name' => '3. Authenticated Faculty Download Material (Attachment Header)',
    'pass' => ($r3['code'] === 200 && $hasAttachment),
    'detail' => "HTTP {$r3['code']}, Attachment Header: " . ($hasAttachment ? 'Present' : 'Missing')
];

// Test 4: Path traversal attempt
$r4 = req("$facultyBase/serve.php?file=../../config/database.php", $facultyCookie);
$tests[] = [
    'name' => '4. Directory Traversal Attack Blocked',
    'pass' => ($r4['code'] === 403),
    'detail' => "HTTP {$r4['code']} (Expected: 403 Forbidden)"
];

// Test 5: Missing / non-existent file
$r5 = req("$facultyBase/serve.php?file=uploads/nonexistent_document_99999.pdf", $facultyCookie);
$tests[] = [
    'name' => '5. Non-existent Resource Handling',
    'pass' => ($r5['code'] === 404),
    'detail' => "HTTP {$r5['code']} (Expected: 404 Not Found)"
];

// Test 6: Cross-role isolation: Student 309 trying to access Student 1's submission
$r6 = req("$studentBase/serve.php?file=uploads/assignments/submission_1.pdf", $studentCookie);
$tests[] = [
    'name' => '6. Student Cross-Student Submission Isolation',
    'pass' => ($r6['code'] === 403),
    'detail' => "HTTP {$r6['code']} (Expected: 403 Forbidden for another student's work)"
];

// Test 7: Student viewing course study material
$r7 = req("$studentBase/serve.php?file=uploads/materials/unit_notes_1.pdf", $studentCookie);
$tests[] = [
    'name' => '7. Authenticated Student View Course Material',
    'pass' => ($r7['code'] === 200 && stripos($r7['headers'], 'application/pdf') !== false),
    'detail' => "HTTP {$r7['code']} (Course material accessible to student)"
];

// Test 8: Nonexistent material ID handling
$r8 = req("$facultyBase/serve.php?material_id=999999", $facultyCookie);
$tests[] = [
    'name' => '8. Nonexistent Material ID Returns 404',
    'pass' => ($r8['code'] === 404),
    'detail' => "HTTP {$r8['code']} (Expected: 404 Not Found)"
];

// Test 9: Colon / Windows drive injection attempt
$r9 = req("$facultyBase/serve.php?file=C:/Windows/win.ini", $facultyCookie);
$tests[] = [
    'name' => '9. Drive Letter / Colon Injection Blocked',
    'pass' => ($r9['code'] === 403),
    'detail' => "HTTP {$r9['code']} (Expected: 403 Forbidden)"
];

// Test 10: Admin portal access to study material
$adminCookie = tempnam(sys_get_temp_dir(), 'test_adm_cookie_');
login($adminBase, $adminCookie, 'GLS_ADMIN', 'admin123');
$r10 = req("$adminBase/serve.php?file=uploads/materials/unit_notes_1.pdf", $adminCookie);
$tests[] = [
    'name' => '10. Authenticated Admin Access to Material',
    'pass' => ($r10['code'] === 200 && stripos($r10['headers'], 'application/pdf') !== false),
    'detail' => "HTTP {$r10['code']} (Expected: 200 OK for Admin)"
];

// Test 11: Direct request for outside script file rejected
$r11 = req("$facultyBase/serve.php?file=api/index.php", $facultyCookie);
$tests[] = [
    'name' => '11. Out-of-Jail Filesystem Access Blocked',
    'pass' => ($r11['code'] === 404 || $r11['code'] === 403),
    'detail' => "HTTP {$r11['code']} (Expected: 403/404 Outside Jail)"
];

$passCount = 0;
foreach ($tests as $t) {
    if ($t['pass']) $passCount++;
    $status = $t['pass'] ? '[PASS]' : '[FAIL]';
    echo sprintf("%-6s %-50s : %s\n", $status, $t['name'], $t['detail']);
}

echo "\n============================================================\n";
echo " SERVE.PHP SECURITY RESULTS: $passCount / " . count($tests) . " PASSED\n";
echo "============================================================\n";

@unlink($facultyCookie);
@unlink($studentCookie);
@unlink($adminCookie);
@unlink($unauthCookie);

