<?php
require_once __DIR__ . '/../config/database.php';

$res = $pdo->query("SELECT id, username, email, must_change_password FROM users WHERE role = 'student' AND must_change_password = 0")->fetchAll(PDO::FETCH_ASSOC);
echo "Students with must_change_password=0: " . json_encode($res) . "\n";

$u1 = $pdo->query("SELECT id, username, email, must_change_password FROM users WHERE username = '2025BSITD0001'")->fetch(PDO::FETCH_ASSOC);
echo "User 2025BSITD0001: " . json_encode($u1) . "\n";
