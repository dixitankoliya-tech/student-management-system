<?php
require_once __DIR__ . '/../config/database.php';

echo "Database Counts:\n";
$tables = ['students', 'faculty', 'courses', 'subjects', 'student_subject_enrollments', 'marks', 'student_marks', 'faculty_subjects'];

foreach($tables as $t) {
    try {
        $cnt = $pdo->query("SELECT count(*) FROM $t")->fetchColumn();
        echo str_pad($t, 30) . ": $cnt\n";
    } catch(Exception $e) {
        echo str_pad($t, 30) . ": Error - " . $e->getMessage() . "\n";
    }
}
