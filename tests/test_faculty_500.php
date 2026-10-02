<?php
require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'faculty' AND is_active = 1 LIMIT 1");
$stmt->execute();
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    die("No faculty found\n");
}

echo "Found faculty: {$faculty['username']}\n";

// Now log in and fetch dashboard
$ch = curl_init('http://localhost:9100/faculty/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
$cookies = array();
foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
}
$cookieStr = '';
foreach ($cookies as $k => $v) {
    $cookieStr .= "$k=$v; ";
}

$csrf = '';
if (preg_match('/name="csrf_token" value="(.*?)"/', $response, $m)) {
    $csrf = $m[1];
}

$ch = curl_init('http://localhost:9100/faculty/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $faculty['username'], 'password' => 'password123', 'csrf_token' => $csrf]));
curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);

$ch = curl_init('http://localhost:9100/faculty/dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
curl_setopt($ch, CURLOPT_HEADER, true);
$dashboardResponse = curl_exec($ch);

echo "\nDashboard Response Headers:\n";
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($dashboardResponse, 0, $header_size);
$body = substr($dashboardResponse, $header_size);
echo $header;
echo "\nBody Snippet:\n";
echo substr($body, 0, 500);

