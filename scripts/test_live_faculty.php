<?php
$url = 'https://gls-sms-admin.vercel.app/faculty.php';
$cookieFile = __DIR__ . '/admin_live_test_cookie.txt';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HEADER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Body length: " . strlen($body) . "\n";
echo "Contains 'Faculty Management': " . (strpos($body, 'Faculty Management') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Export CSV': " . (strpos($body, 'Export CSV') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Breadcrumb 'Faculty Management': " . (strpos($body, '<li class="breadcrumb-item active">Faculty Management</li>') !== false ? 'YES' : 'NO') . "\n";

// Test Live CSV export
$csvUrl = 'https://gls-sms-admin.vercel.app/faculty.php?export=csv';
$ch = curl_init($csvUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HEADER => true
]);
$csvResp = curl_exec($ch);
$csvHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$csvHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$csvHeader = substr($csvResp, 0, $csvHeaderSize);
$csvBody = substr($csvResp, $csvHeaderSize);
curl_close($ch);

echo "\n--- LIVE CSV EXPORT TEST ---\n";
echo "CSV HTTP Code: $csvHttp\n";
echo "Content-Disposition Header: " . (preg_match('/content-disposition:\s*(.+)/i', $csvHeader, $m) ? trim($m[1]) : 'NOT FOUND') . "\n";
echo "CSV Header Row: " . strtok($csvBody, "\r\n") . "\n";
echo "CSV First Data Row: " . strtok("\r\n") . "\n";
