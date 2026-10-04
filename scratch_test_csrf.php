<?php
$url = "https://gls-sms-admin.vercel.app/login.php";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($ch);

preg_match('/set-cookie:\s*([^;]*)/mi', $header, $matches);
$cookie = $matches[1] ?? '';

preg_match('/name="csrf_token" value="([^"]+)"/i', $body, $matches);
$csrf = $matches[1] ?? '';

echo "GET Session: $cookie\n";
echo "GET CSRF: $csrf\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Get headers from POST
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'login' => 'login',
    'csrf_token' => $csrf,
    'login_id' => 'GLS_ADMIN',
    'password' => 'password123'
]));
// Try sending standard Cookie header
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: " . $cookie
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$post_header = substr($response, 0, $header_size);
$post_body = substr($response, $header_size);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

preg_match('/set-cookie:\s*([^;]*)/mi', $post_header, $matches);
$post_cookie = $matches[1] ?? '';

echo "POST Code: $http_code\n";
echo "POST Session: $post_cookie\n";
