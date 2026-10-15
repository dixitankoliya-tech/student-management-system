<?php
require_once __DIR__ . '/../config/database.php';

echo "=== AUDITING fee_payments.php QUERY & SCHEMA ===\n";

// 1. Check indexes on fee_payments, student_fees, students, fee_structures
$tables = ['fee_payments', 'student_fees', 'students', 'fee_structures'];
foreach ($tables as $t) {
    echo "\nIndexes on $t:\n";
    $idx = $pdo->query("SHOW INDEX FROM $t")->fetchAll();
    foreach ($idx as $i) {
        echo "  - {$i['Key_name']} on ({$i['Column_name']})\n";
    }
}

// 2. Total record counts
$totalPayments = $pdo->query("SELECT COUNT(*) FROM fee_payments")->fetchColumn();
echo "\nTotal fee_payments rows: $totalPayments\n";

// 3. Check for any duplicate rows in the join
$joinedCount = $pdo->query("
    SELECT COUNT(*) 
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
")->fetchColumn();
echo "Joined query count: $joinedCount (Matches fee_payments count? " . ($totalPayments == $joinedCount ? "YES - 1:1 join" : "NO - discrepancy!") . ")\n";

// 4. Run EXPLAIN on the main query
echo "\nEXPLAIN of main query:\n";
$explain = $pdo->query("
    EXPLAIN SELECT fp.*, s.first_name, s.last_name, s.enrollment_no, fs.fee_name
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    ORDER BY fp.created_at DESC
")->fetchAll();

foreach ($explain as $row) {
    echo "  " . json_encode($row) . "\n";
}

// 5. Measure query execution time
$start = microtime(true);
$res = $pdo->query("
    SELECT fp.*, s.first_name, s.last_name, s.enrollment_no, fs.fee_name
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    ORDER BY fp.created_at DESC
")->fetchAll();
$time = (microtime(true) - $start) * 1000;
echo "\nQuery execution time: " . round($time, 2) . " ms (returned " . count($res) . " rows)\n";

$pendingCount = $pdo->query("SELECT COUNT(*) FROM student_fees WHERE pending_amount > 0")->fetchColumn();
echo "Pending fees in dropdown: $pendingCount\n";

$totals = $pdo->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM fee_payments")->fetch();
echo "Total transactions: {$totals['cnt']}, Total collected: INR " . number_format($totals['total'], 2) . "\n";


