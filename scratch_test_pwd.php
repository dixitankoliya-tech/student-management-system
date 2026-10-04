<?php
require 'config/database.php';
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'FAC-FCAIT-001'");
$stmt->execute();
$hash = $stmt->fetchColumn();
echo "Hash: $hash\n";
var_dump(password_verify('password123', $hash));
