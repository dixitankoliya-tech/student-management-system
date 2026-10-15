<?php
require 'config/database.php';

echo "=== fee_payments columns ===\n";
$stmt = $pdo->query("DESCRIBE fee_payments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

$orphans = $pdo->query("SELECT COUNT(*) FROM fee_payments WHERE student_fee_id NOT IN (SELECT id FROM student_fees)")->fetchColumn();
echo "\nOrphan fee payments without student_fees: $orphans\n";
