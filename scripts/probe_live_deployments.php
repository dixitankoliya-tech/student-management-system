<?php
/**
 * Probe Live Production Deployments
 */

$urls = [
    'Admin Portal'   => 'https://gls-sms-admin.vercel.app',
    'Faculty Portal' => 'https://gls-sms-faculty.vercel.app',
    'Student Portal' => 'https://gls-sms-student.vercel.app',
];

echo "============================================================\n";
echo " PROBING LIVE VERCEL PRODUCTION ENDPOINTS\n";
echo "============================================================\n";

foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $caPaths = ['C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/xampp/php/extras/ssl/cacert.pem', '/etc/ssl/certs/ca-certificates.crt'];
    $caSet = false;
    foreach ($caPaths as $ca) {
        if (file_exists($ca)) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
            $caSet = true;
            break;
        }
    }
    if (!$caSet) {
        // Fallback for local Windows environment lacking curl.cainfo in php.ini
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 400) {
        echo sprintf("[PASS] %-16s: %s -> HTTP %d (URL: %s)\n", $name, $url, $httpCode, $effectiveUrl);
    } else {
        echo sprintf("[FAIL] %-16s: %s -> HTTP %d (Error: %s)\n", $name, $url, $httpCode, $error ?: 'Non-200 status');
    }
}
