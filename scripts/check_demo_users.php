<?php
require 'config/database.php';

echo "--- CHECKING FACULTY DEMO ACCOUNTS ---\n";
$f1 = $pdo->query("SELECT id, username, email, role, is_active FROM users WHERE username IN ('EMP0001', 'FAC-FCAIT-001')")->fetchAll(PDO::FETCH_ASSOC);
print_r($f1);

echo "--- CHECKING STUDENT DEMO ACCOUNTS ---\n";
$s1 = $pdo->query("SELECT id, username, email, role, is_active FROM users WHERE username IN ('BCA2024001', '2021IMSIT0309', '2024BCA001', '2025BSITD0001')")->fetchAll(PDO::FETCH_ASSOC);
print_r($s1);
