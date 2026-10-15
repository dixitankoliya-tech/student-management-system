<?php
$url = 'https://gls-sms-admin.vercel.app/fee_reports.php';
$cookieFile = __DIR__ . '/admin_live_test_cookie.txt';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HEADER => true
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($resp, 0, $headerSize);
$body = substr($resp, $headerSize);
curl_close($ch);

echo "Live Fee Reports HTTP Code: $httpCode\n";
echo "Contains 'Fee Reports': " . (strpos($body, 'Fee Reports') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Assigned Students': " . (strpos($body, 'Assigned Students') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Export CSV': " . (strpos($body, 'Export CSV') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'GRAND TOTAL': " . (strpos($body, 'GRAND TOTAL') !== false ? 'YES' : 'NO') . "\n";
echo "Contains Breadcrumb 'Fee Reports': " . (strpos($body, 'breadcrumb-item active">Fee Reports</li>') !== false ? 'YES' : 'NO') . "\n";

// Live CSV Export Test
$csvUrl = 'https://gls-sms-admin.vercel.app/fee_reports.php?export=csv';
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
echo "CSV Header: " . strtok($csvBody, "\r\n") . "\n";
echo "CSV First Row: " . strtok("\r\n") . "\n";
echo "CSV Grand Total Row:\n";
while (($line = strtok("\r\n")) !== false) {
    if (strpos($line, 'GRAND TOTAL') !== false) {
        echo "  " . $line . "\n";
    }
}
