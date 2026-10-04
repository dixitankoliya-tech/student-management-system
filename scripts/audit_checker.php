<?php
require_once __DIR__ . '/../config/database.php';
$out = [];

// 1. Data Quality
$tables = ['students', 'faculty', 'courses', 'departments', 'subjects', 'users'];
foreach($tables as $t) {
    try {
        $cnt = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        $out[] = "TABLE $t: $cnt";
    } catch(Exception $e) {}
}

// 2. Orphan checks
$out[] = "Orphan student_academic_enrollments: " . $pdo->query("SELECT COUNT(*) FROM student_academic_enrollments WHERE student_id NOT IN (SELECT id FROM students)")->fetchColumn();
$out[] = "Orphan users (student): " . $pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND student_id NOT IN (SELECT id FROM students)")->fetchColumn();
$out[] = "Orphan users (faculty): " . $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty' AND faculty_id NOT IN (SELECT id FROM faculty)")->fetchColumn();

// 3. Faculty without subjects
$out[] = "Faculty without subjects: " . $pdo->query("SELECT COUNT(*) FROM faculty WHERE id NOT IN (SELECT faculty_id FROM faculty_subjects)")->fetchColumn();

// 4. Students without enrollments
$out[] = "Students without academic enrollments: " . $pdo->query("SELECT COUNT(*) FROM students WHERE id NOT IN (SELECT student_id FROM student_academic_enrollments)")->fetchColumn();

// 5. Subjects without faculty
$out[] = "Subjects without assigned faculty: " . $pdo->query("SELECT COUNT(*) FROM subjects WHERE id NOT IN (SELECT subject_id FROM faculty_subjects)")->fetchColumn();

echo implode("\n", $out);
