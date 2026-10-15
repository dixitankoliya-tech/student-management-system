<?php
require_once __DIR__ . '/../config/database.php';

echo "=== CHECKING TABLES EXISTENCE & ROW COUNTS ===\n";
$tables = [
    'semesters_master',
    'attendance',
    'students',
    'student_academic_enrollments',
    'courses',
    'departments',
    'faculty',
    'department_heads',
    'course_coordinators',
    'fee_structures',
    'student_fees',
    'academic_calendar'
];

foreach ($tables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn();
    if ($exists) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo " - Table '$t': EXISTS (Rows: $count)\n";
    } else {
        echo " - Table '$t': MISSING!\n";
    }
}

echo "\n=== CHECKING FACULTY ROLE ENUM ===\n";
$col = $pdo->query("SHOW COLUMNS FROM faculty LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
echo "faculty.role: " . ($col ? $col['Type'] : 'COLUMN NOT FOUND') . "\n";

echo "\n=== CHECKING DEPARTMENT_HEADS SCHEMA ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM department_heads")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo " - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== CHECKING COURSE_COORDINATORS SCHEMA ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM course_coordinators")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo " - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== CHECKING ATTENDANCE STATUS ENUM ===\n";
$col = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
echo "attendance.status: " . ($col ? $col['Type'] : 'COLUMN NOT FOUND') . "\n";

echo "\n=== CHECKING FEE_STRUCTURES SCHEMA ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM fee_structures")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo " - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== CHECKING ACADEMIC_CALENDAR SCHEMA ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM academic_calendar")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo " - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== CHECKING FEE_STRUCTURES COURSES ===\n";
$coursesInFee = $pdo->query('SELECT DISTINCT course FROM fee_structures')->fetchAll(PDO::FETCH_COLUMN);
print_r($coursesInFee);
$coursesInTable = $pdo->query('SELECT course_code, course_name FROM courses')->fetchAll(PDO::FETCH_ASSOC);
print_r($coursesInTable);

$deans = $pdo->query("SELECT dh.*, d.department_name, f.first_name, f.last_name FROM department_heads dh JOIN departments d ON dh.department_id = d.id JOIN faculty f ON dh.faculty_id = f.id WHERE dh.status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
echo "Active Deans: " . count($deans) . "\n";
foreach ($deans as $d) {
    echo " - Dept: {$d['department_name']} -> Dean: {$d['first_name']} {$d['last_name']} (Faculty ID: {$d['faculty_id']})\n";
}

$coords = $pdo->query("SELECT cc.*, c.course_name, f.first_name, f.last_name FROM course_coordinators cc JOIN courses c ON cc.course_id = c.id JOIN faculty f ON cc.faculty_id = f.id WHERE cc.status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
echo "Active Coordinators: " . count($coords) . "\n";
foreach ($coords as $c) {
    echo " - Course: {$c['course_name']} -> Coordinator: {$c['first_name']} {$c['last_name']} (Faculty ID: {$c['faculty_id']})\n";
}
