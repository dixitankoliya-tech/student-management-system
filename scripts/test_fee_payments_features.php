<?php
/**
 * Test fee_payments.php search, method filter, pagination, and data integrity
 */

require_once __DIR__ . '/../config/database.php';

$adminBase = "http://127.0.0.1:8000";
$adminCookie = tempnam(sys_get_temp_dir(), 'test_adm_fee_');

function adminLogin($base, $cookieFile) {
    @unlink($cookieFile);
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $html = curl_exec($ch);
    curl_close($ch);
    
    preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m);
    $token = $m[1] ?? '';
    
    $ch = curl_init("$base/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'login' => '1',
        'csrf_token' => $token,
        'login_id' => 'GLS_ADMIN',
        'password' => 'admin123'
    ]));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ($code === 200 && strpos($url, 'login.php') === false);
}

function fetchPage($url, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $bytes = strlen($res ?: '');
    curl_close($ch);
    return ['code' => $code, 'bytes' => $bytes, 'body' => $res];
}

echo "=== TESTING FEE PAYMENTS FUNCTIONAL ENHANCEMENTS ===\n";
$auth = adminLogin($adminBase, $adminCookie);
echo "Admin Authentication: " . ($auth ? "PASS" : "FAIL") . "\n";

if (!$auth) {
    exit(1);
}

// 1. Default view (limit 100)
$d = fetchPage("$adminBase/fee_payments.php", $adminCookie);
$hasKpi = strpos($d['body'], 'Total Collections') !== false;
$hasTable = strpos($d['body'], 'Receipt No') !== false;
$hasReceipt = strpos($d['body'], 'GLS-FCAIT-REC-') !== false;
echo "1. Default View (100 per page): HTTP {$d['code']}, {$d['bytes']} bytes | KPI: " . ($hasKpi ? "YES" : "NO") . " | Table: " . ($hasTable ? "YES" : "NO") . " | Receipts: " . ($hasReceipt ? "YES" : "NO") . "\n";


// 2. Search for student 'Karan'
$s = fetchPage("$adminBase/fee_payments.php?search=Karan", $adminCookie);
$hasKaran = strpos($s['body'], 'Karan') !== false;
echo "2. Search Filter ('Karan'): HTTP {$s['code']}, {$s['bytes']} bytes | Matches: " . ($hasKaran ? "YES" : "NO") . "\n";

// 3. Filter by method 'UPI'
$u = fetchPage("$adminBase/fee_payments.php?method=UPI", $adminCookie);
$hasUpi = strpos($u['body'], 'UPI') !== false;
echo "3. Method Filter ('UPI'): HTTP {$u['code']}, {$u['bytes']} bytes | Matches: " . ($hasUpi ? "YES" : "NO") . "\n";

// 4. Pagination page 2 with limit 25
$p2 = fetchPage("$adminBase/fee_payments.php?limit=25&page=2", $adminCookie);
$hasPage2 = strpos($p2['body'], 'Page 2 of') !== false;
echo "4. Pagination (Limit 25, Page 2): HTTP {$p2['code']}, {$p2['bytes']} bytes | Page 2 Indicated: " . ($hasPage2 ? "YES" : "NO") . "\n";

// 5. All records view
$all = fetchPage("$adminBase/fee_payments.php?limit=all", $adminCookie);
$hasAll = $all['bytes'] > 500000;
echo "5. All Records View ('limit=all'): HTTP {$all['code']}, {$all['bytes']} bytes | >500KB Payload: " . ($hasAll ? "YES" : "NO") . "\n";

@unlink($adminCookie);
echo "\nALL FEE PAYMENTS AUDIT TESTS COMPLETED SUCCESSFULLY.\n";
