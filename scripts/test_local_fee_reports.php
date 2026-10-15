<?php
$cookieFile = __DIR__ . '/admin_test_cookie.txt';

$url = 'http://127.0.0.1:8000/fee_reports.php';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
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
echo "Contains 'Fee Reports': " . (strpos($body, 'Fee Reports') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Assigned Students': " . (strpos($body, 'Assigned Students') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Export CSV': " . (strpos($body, 'Export CSV') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'GRAND TOTAL': " . (strpos($body, 'GRAND TOTAL') !== false ? 'YES' : 'NO') . "\n";

// Test CSV Export
$csvUrl = 'http://127.0.0.1:8000/fee_reports.php?export=csv';
$ch = curl_init($csvUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HEADER => true
]);
$csvResp = curl_exec($ch);
$csvHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$csvHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$csvHeader = substr($csvResp, 0, $csvHeaderSize);
$csvBody = substr($csvResp, $csvHeaderSize);
curl_close($ch);

echo "\n--- LOCAL CSV TEST ---\n";
echo "CSV HTTP: $csvHttp\n";
echo "CSV Header: " . strtok($csvBody, "\r\n") . "\n";
echo "CSV First Row: " . strtok("\r\n") . "\n";
