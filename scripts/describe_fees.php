<?php
require 'config/database.php';

echo "=== fee_structures ===\n";
$stmt = $pdo->query("DESCRIBE fee_structures");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== student_fees ===\n";
$stmt = $pdo->query("DESCRIBE student_fees");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== fee_payments ===\n";
$stmt = $pdo->query("DESCRIBE fee_payments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
