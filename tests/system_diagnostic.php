<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
try {
require_once __DIR__ . '/../config/database.php';

$report = [];

// 1. Capacity Validation
$stmt = $pdo->query("SELECT course, academic_year, semester, division, COUNT(*) as c FROM student_academic_enrollments WHERE status='Active' GROUP BY course, academic_year, semester, division HAVING c > 100");
$overloaded_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$report['overloaded_classes'] = $overloaded_classes;

// 2. Faculty Workload Validation
$stmt = $pdo->query("SELECT faculty_id, academic_year, semester, COUNT(DISTINCT subject_id) as c FROM faculty_subjects GROUP BY faculty_id, academic_year, semester HAVING c > 4");
$overloaded_faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
$report['overloaded_faculty'] = $overloaded_faculty;

// 3. Duplicate assignments for same faculty in same semester
$stmt = $pdo->query("SELECT faculty_id, subject_id, academic_year, semester, COUNT(*) as c FROM faculty_subjects GROUP BY faculty_id, subject_id, academic_year, semester, division HAVING c > 1");
$duplicate_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$report['duplicate_assignments'] = $duplicate_assignments;

// 4. Orphan checks
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE id NOT IN (SELECT student_id FROM users WHERE role='student' AND student_id IS NOT NULL)");
$report['orphan_students'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM faculty WHERE id NOT IN (SELECT faculty_id FROM users WHERE role='faculty' AND faculty_id IS NOT NULL)");
$report['orphan_faculty'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id NOT IN (SELECT id FROM faculty) OR subject_id NOT IN (SELECT id FROM subjects)");
$report['orphan_faculty_subjects'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id NOT IN (SELECT id FROM students)");
$report['orphan_attendance'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM marks WHERE student_id NOT IN (SELECT id FROM students) OR exam_id NOT IN (SELECT id FROM examinations)");
$report['orphan_marks'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id NOT IN (SELECT id FROM assignments) OR student_id NOT IN (SELECT id FROM students)");
$report['orphan_submissions'] = $stmt->fetchColumn();

// 5. Unused Notification User ID
$stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id NOT IN (SELECT id FROM users)");
$report['orphan_notifications'] = $stmt->fetchColumn();

// 6. Data Integrity across portals
// Are assignments referencing subject_id that isn't mapped to the faculty?
// Wait, assignments refer to faculty_id, subject_id, division...
// Wait, `assignments` table structure:
// Let's get table schema details
$report['tables'] = [];
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $t) {
    $report['tables'][$t] = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
}

    $json = json_encode($report, JSON_PRETTY_PRINT);
    file_put_contents(__DIR__ . '/diagnostic_out.json', $json);
    echo $json;
} catch (Throwable $e) {
    $err = json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_PRETTY_PRINT);
    file_put_contents(__DIR__ . '/diagnostic_out.json', $err);
    echo $err;
}
