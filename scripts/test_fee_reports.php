<?php
require_once __DIR__ . '/test_live_all_5_pages.php';

echo "\n--- TESTING LIVE fee_reports.php ---\n";
$res = httpLiveReq('https://gls-sms-admin.vercel.app/fee_reports.php');
echo "Live fee_reports.php HTTP: " . $res['code'] . "\n";
echo "Content length: " . strlen($res['body']) . "\n";
echo "Contains 'Fee Reports': " . (strpos($res['body'], 'Fee Reports') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'GRAND TOTAL': " . (strpos($res['body'], 'GRAND TOTAL') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'BCA': " . (strpos($res['body'], 'BCA') !== false ? 'YES' : 'NO') . "\n";

// Test course filter
$filterRes = httpLiveReq('https://gls-sms-admin.vercel.app/fee_reports.php?course=BCA');
echo "\n--- TESTING FILTERED fee_reports.php?course=BCA ---\n";
echo "Filter HTTP: " . $filterRes['code'] . "\n";
echo "Contains 'BCA': " . (strpos($filterRes['body'], 'BCA') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'M.Sc. IT' in table: " . (strpos($filterRes['body'], '<td>M.Sc. IT</td>') !== false ? 'YES (UNEXPECTED)' : 'NO (CORRECT FILTER)') . "\n";
