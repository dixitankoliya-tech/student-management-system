<?php
require 'config/database.php';
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute(['2e22ab44148822e7e55989c833b9641e']);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if ($session) {
    echo "Found session in DB! Data: " . $session['data'] . "\n";
} else {
    echo "Session NOT found in DB!\n";
}
