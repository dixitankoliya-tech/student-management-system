<?php
require 'config/database.php';
require 'includes/admin_auth.php';

echo "Session ID: " . session_id() . "\n";
echo "CSRF Token: " . generateCsrfToken() . "\n";

// check if session is stored
$stmt = $pdo->query("SELECT * FROM sessions");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Sessions in DB: " . count($sessions) . "\n";
foreach($sessions as $s) {
    echo $s['id'] . " => " . $s['data'] . "\n";
}
