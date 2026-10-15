<?php
require_once __DIR__ . '/../config/database.php';

$cookie = tempnam(sys_get_temp_dir(), 'admin_audit_ck2_');
@unlink($cookie);

// Login
$ch = curl_init("http://127.0.0.1:8000/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m);
$token = $m[1] ?? '';

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
curl_exec($ch);
curl_close($ch);

// Test Attendance Reports with valid course & semester
$url = "http://127.0.0.1:8000/attendance_reports.php?start_date=2024-01-01&end_date=2026-12-31&course=BCA&semester=5";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Attendance Report GET ($url): HTTP $code\n";
echo " - Contains 'Attendance Summary': " . (strpos($res, 'Attendance Summary') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Export CSV': " . (strpos($res, 'Export CSV') !== false ? 'YES' : 'NO') . "\n";
echo " - Rows found in table: " . substr_count($res, '<tr>') . "\n";

// Test Attendance Reports with inverted date range (start_date > end_date)
$urlInvalid = "http://127.0.0.1:8000/attendance_reports.php?start_date=2026-12-31&end_date=2024-01-01&course=BCA&semester=5";
$ch = curl_init($urlInvalid);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$resInvalid = curl_exec($ch);
curl_close($ch);
echo "\nInverted Date Range GET:\n";
echo " - Shows error alert: " . (strpos($resInvalid, 'alert-danger') !== false ? 'YES' : 'NO (Silently gives empty table)') . "\n";

// Test CSV Export
$urlCsv = "http://127.0.0.1:8000/attendance_reports.php?start_date=2024-01-01&end_date=2026-12-31&course=BCA&semester=5&export=csv";
$ch = curl_init($urlCsv);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$csv = curl_exec($ch);
$ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);
echo "\nCSV Export:\n";
echo " - Content-Type: $ct\n";
echo " - First line of CSV: " . strtok($csv, "\n") . "\n";
echo " - Total CSV Lines: " . substr_count($csv, "\n") . "\n";

@unlink($cookie);
