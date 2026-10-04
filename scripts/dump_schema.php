<?php
require_once __DIR__ . '/../config/database.php';
$tables = ['students', 'courses', 'departments', 'subjects', 'marks', 'student_academic_enrollments', 'student_subject_enrollments', 'faculty'];
foreach($tables as $t) {
    echo "=== $t ===\n";
    $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo "{$c['Field']} ({$c['Type']})\n";
    }
}
