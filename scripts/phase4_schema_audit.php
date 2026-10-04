<?php
require 'config/database.php';
ob_start();

echo "=== FACULTY SAMPLE ===\n";
$sample = $pdo->query("SELECT id, faculty_id, first_name, last_name, designation, department, status FROM faculty LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sample as $s) {
    echo "  ID={$s['id']} FID={$s['faculty_id']} {$s['first_name']} {$s['last_name']} | {$s['designation']} | {$s['department']} | {$s['status']}\n";
}

echo "\n=== FACULTY DESIGNATIONS ===\n";
$desigs = $pdo->query("SELECT designation, COUNT(*) as cnt FROM faculty GROUP BY designation ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($desigs as $d) echo "  {$d['designation']}: {$d['cnt']}\n";

echo "\n=== FACULTY DEPARTMENTS ===\n";
$depts = $pdo->query("SELECT department, COUNT(*) as cnt FROM faculty GROUP BY department ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($depts as $d) echo "  {$d['department']}: {$d['cnt']}\n";

echo "\n=== HOD/DEAN ===\n";
$hods = $pdo->query("SELECT id, faculty_id, first_name, last_name, designation FROM faculty WHERE designation LIKE '%HOD%' OR designation LIKE '%Dean%' OR designation LIKE '%Head%' OR designation LIKE '%Director%'")->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($hods) . "\n";
foreach ($hods as $h) echo "  ID={$h['id']} {$h['first_name']} {$h['last_name']} - {$h['designation']}\n";

echo "\n=== USERS TABLE ===\n";
try {
    $ucols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ucols as $c) echo "  " . str_pad($c['Field'], 25) . str_pad($c['Type'], 30) . $c['Default'] . "\n";
    echo "\n  Users sample:\n";
    $usample = $pdo->query("SELECT id, username, role FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($usample as $u) echo "    ID={$u['id']} user={$u['username']} role={$u['role']}\n";
    echo "\n  Role counts:\n";
    $rcounts = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rcounts as $r) echo "    {$r['role']}: {$r['cnt']}\n";
} catch (Exception $e) { echo "  Error: " . $e->getMessage() . "\n"; }

echo "\n=== FACULTY_SUBJECTS ===\n";
try {
    $fscols = $pdo->query("DESCRIBE faculty_subjects")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fscols as $c) echo "  " . str_pad($c['Field'], 25) . $c['Type'] . "\n";
    $fscnt = $pdo->query("SELECT COUNT(*) FROM faculty_subjects")->fetchColumn();
    echo "  Total: $fscnt\n";
} catch (Exception $e) { echo "  Not found\n"; }

echo "\n=== DEPARTMENTS ===\n";
$dept = $pdo->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dept as $d) echo "  ID={$d['id']} name={$d['department_name']} code={$d['department_code']}\n";

echo "\n=== COURSES ===\n";
$courses = $pdo->query("SELECT * FROM courses")->fetchAll(PDO::FETCH_ASSOC);
foreach ($courses as $c) {
    $fields = [];
    foreach ($c as $k => $v) $fields[] = "$k=$v";
    echo "  " . implode(" | ", $fields) . "\n";
}

echo "\n=== ALL TABLES WITH COUNTS ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "  " . str_pad($t, 40) . $cnt . "\n";
}

$output = ob_get_clean();
file_put_contents('scripts/phase4_audit_output.txt', $output);
echo $output;
