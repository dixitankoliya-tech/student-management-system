<?php
require_once __DIR__ . '/../config/database.php';

echo "=== FACULTY COLUMNS ===\n";
$cols = $pdo->query("DESCRIBE faculty")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo sprintf("%-20s %-25s Null:%-4s Key:%-4s Default:%s\n", 
        $c['Field'], $c['Type'], $c['Null'], $c['Key'], var_export($c['Default'], true));
}

echo "\n=== FACULTY COUNT & STATUS ===\n";
$st = $pdo->query("SELECT status, COUNT(*) as cnt FROM faculty GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
print_r($st);

echo "\n=== USERS LINKED TO FACULTY ===\n";
$u = $pdo->query("SELECT role, is_active, COUNT(*) as cnt FROM users WHERE faculty_id IS NOT NULL GROUP BY role, is_active")->fetchAll(PDO::FETCH_ASSOC);
print_r($u);
