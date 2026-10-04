<?php
require_once __DIR__ . '/../config/database.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLE COUNTS:\n";
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "$t: $count\n";
}
echo "\nDEPARTMENTS IN STUDENTS:\n";
// students table doesn't have department, it has course. Let's group by course.
$courses = $pdo->query("SELECT course, COUNT(*) as c FROM students GROUP BY course")->fetchAll(PDO::FETCH_ASSOC);
foreach ($courses as $c) {
    echo $c['course'] . ": " . $c['c'] . "\n";
}

echo "\nDEPARTMENTS IN FACULTY:\n";
$fac = $pdo->query("SELECT department, COUNT(*) as c FROM faculty GROUP BY department")->fetchAll(PDO::FETCH_ASSOC);
foreach ($fac as $f) {
    echo $f['department'] . ": " . $f['c'] . "\n";
}

echo "\nUSERS BY ROLE:\n";
$roles = $pdo->query("SELECT role, COUNT(*) as c FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $r) {
    echo $r['role'] . ": " . $r['c'] . "\n";
}
