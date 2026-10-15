<?php
$cookieFile = __DIR__ . '/admin_test_cookie.txt';

// Step 1: Login if cookie not valid
$loginUrl = 'http://127.0.0.1:8000/login.php';
$ch = curl_init($loginUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$html = curl_exec($ch);
curl_close($ch);

// Extract CSRF token
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

if ($csrf) {
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'login' => '1',
            'login_id' => 'GLS_ADMIN',
            'password' => 'admin123',
            'csrf_token' => $csrf
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Step 2: Fetch faculty.php
$url = 'http://127.0.0.1:8000/faculty.php';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HEADER => true
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($resp, 0, $headerSize);
$body = substr($resp, $headerSize);
curl_close($ch);

echo "Local HTTP Code: $httpCode\n";
echo "Body length: " . strlen($body) . "\n";
echo "Contains 'Faculty Management': " . (strpos($body, 'Faculty Management') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Export CSV': " . (strpos($body, 'Export CSV') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'FAC-FCAIT-001': " . (strpos($body, 'FAC-FCAIT-001') !== false ? 'YES' : 'NO') . "\n";

// Step 3: Test CSV export
$csvUrl = 'http://127.0.0.1:8000/faculty.php?export=csv';
$ch = curl_init($csvUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HEADER => true
]);
$csvResp = curl_exec($ch);
$csvHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$csvHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$csvBody = substr($csvResp, $csvHeaderSize);
curl_close($ch);

echo "CSV Export HTTP Code: $csvHttp\n";
echo "CSV First line: " . strtok($csvBody, "\r\n") . "\n";
echo "CSV Second line: " . strtok("\r\n") . "\n";
