<?php
$url = "https://gls-sms-admin.vercel.app/login.php";
$headers = get_headers($url, 1);
print_r($headers);
