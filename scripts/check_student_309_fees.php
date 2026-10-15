<?php
require_once __DIR__ . '/../config/database.php';

$s309 = $pdo->query('SELECT * FROM student_fees WHERE student_id = 309')->fetchAll(PDO::FETCH_ASSOC);
echo "Student 309 fees count: " . count($s309) . "\n";
print_r($s309);

$p309 = $pdo->query('SELECT fp.* FROM fee_payments fp JOIN student_fees sf ON fp.student_fee_id = sf.id WHERE sf.student_id = 309')->fetchAll(PDO::FETCH_ASSOC);
echo "Student 309 payments count: " . count($p309) . "\n";
print_r($p309);

$allPayments = $pdo->query('SELECT fp.id, fp.receipt_no, sf.student_id FROM fee_payments fp JOIN student_fees sf ON fp.student_fee_id = sf.id LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
echo "Sample all payments:\n";
print_r($allPayments);
