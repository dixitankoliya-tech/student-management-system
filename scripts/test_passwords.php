<?php
require_once __DIR__ . '/../config/database.php';

$stmt = $pdo->query("SELECT id, username, password_hash, role FROM users WHERE role = 'admin' LIMIT 1");
$user = $stmt->fetch();
$candidates = ['admin123', 'Admin@123', 'admin', 'password', 'gls@2026', 'Admin@2026'];
foreach ($candidates as $c) {
    if (password_verify($c, $user['password_hash'])) {
        echo "Admin password matches: $c\n";
    }
}

$stmt2 = $pdo->query("SELECT id, username, password_hash, role FROM users WHERE role = 'faculty' LIMIT 1");
$user2 = $stmt2->fetch();
$candidates2 = ['faculty123', 'Faculty@123', 'password'];
foreach ($candidates2 as $c) {
    if (password_verify($c, $user2['password_hash'])) {
        echo "Faculty password matches: $c\n";
    }
}

$stmt3 = $pdo->query("SELECT id, username, password_hash, role FROM users WHERE role = 'student' LIMIT 1");
$user3 = $stmt3->fetch();
$candidates3 = ['student123', 'Student@123', 'password'];
foreach ($candidates3 as $c) {
    if (password_verify($c, $user3['password_hash'])) {
        echo "Student password matches: $c\n";
    }
}
