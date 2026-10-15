<?php
/**
 * Test authenticating against Admin, Faculty, and Student portals with CSRF & login_id
 */
$cookie = tempnam(sys_get_temp_dir(), 'test_cookie_');

function testLogin($portalName, $base, $loginId, $password) {
    global $cookie;
    @unlink($cookie);
    
    // 1. GET login page to obtain CSRF token and session cookie
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m)) {
        echo "[$portalName] Failed to extract CSRF token!\n";
        return false;
    }
    $token = $m[1];
    echo "[$portalName] CSRF Token: " . substr($token, 0, 10) . "...\n";
    
    // 2. POST login credentials
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'login' => '1',
        'csrf_token' => $token,
        'login_id' => $loginId,
        'password' => $password
    ]));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    echo "[$portalName] Login result: HTTP $code | Target: $url | Body len: " . strlen($res) . "\n";
    
    // 3. Test accessing a protected page (e.g. dashboard.php or students.php)
    $testPage = ($portalName === 'Admin') ? "$base/students.php" : (($portalName === 'Faculty') ? "$base/my_students.php" : "$base/subjects.php");
    $ch = curl_init($testPage);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    $res2 = curl_exec($ch);
    $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url2 = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    echo "[$portalName] Protected page ($testPage): HTTP $code2 | Target: $url2 | Body len: " . strlen($res2) . "\n";
    return true;
}

testLogin('Admin', 'http://127.0.0.1:8000', 'GLS_ADMIN', 'admin123');
testLogin('Faculty', 'http://127.0.0.1:9100', 'FAC-FCAIT-001', 'faculty123');
testLogin('Student', 'http://127.0.0.1:9000', '2021IMSIT0309', 'student123');
