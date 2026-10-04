<?php
require 'config/database.php';

function checkCount($pdo, $query, $desc, $expected = null) {
    $stmt = $pdo->query($query);
    $count = $stmt->fetchColumn();
    $status = ($expected === null || $count == $expected) ? "PASS" : "FAIL (Expected $expected)";
    echo str_pad($desc, 40) . str_pad($count, 10) . $status . "\n";
    return $count;
}

echo "--- DATABASE AUDIT ---\n";
checkCount($pdo, "SELECT COUNT(*) FROM departments", "Departments", 1);
checkCount($pdo, "SELECT COUNT(*) FROM departments WHERE department_name = 'FCAIT'", "FCAIT Department", 1);
checkCount($pdo, "SELECT COUNT(*) FROM courses", "Courses", 5);
checkCount($pdo, "SELECT COUNT(*) FROM students", "Students", 500);

$courses = $pdo->query("SELECT course_name FROM courses")->fetchAll(PDO::FETCH_COLUMN);
foreach ($courses as $c) {
    checkCount($pdo, "SELECT COUNT(*) FROM students WHERE course = '$c'", "Students ($c)", 100);
}

checkCount($pdo, "SELECT COUNT(*) FROM faculty", "Faculty", 45);
checkCount($pdo, "SELECT COUNT(*) FROM student_academic_enrollments", "Enrollments", 500);
checkCount($pdo, "SELECT COUNT(*) FROM subjects", "Subjects");
checkCount($pdo, "SELECT COUNT(*) FROM attendance", "Attendance");
checkCount($pdo, "SELECT COUNT(*) FROM student_marks", "Marks");
checkCount($pdo, "SELECT COUNT(*) FROM examinations", "Examinations");
checkCount($pdo, "SELECT COUNT(*) FROM exam_schedules", "Exam Schedules");

echo "\n--- ORPHAN DATA AUDIT ---\n";
checkCount($pdo, "SELECT COUNT(*) FROM students WHERE course NOT IN (SELECT course_name FROM courses)", "Orphan Students (Course)", 0);
checkCount($pdo, "SELECT COUNT(*) FROM student_academic_enrollments WHERE student_id NOT IN (SELECT id FROM students)", "Orphan Enrollments (Student)", 0);
checkCount($pdo, "SELECT COUNT(*) FROM student_marks WHERE student_id NOT IN (SELECT id FROM students)", "Orphan Marks (Student)", 0);
checkCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id NOT IN (SELECT id FROM students)", "Orphan Attendance (Student)", 0);
checkCount($pdo, "SELECT COUNT(*) FROM assignments WHERE subject_id NOT IN (SELECT id FROM subjects)", "Orphan Assignments (Subject)", 0);
checkCount($pdo, "SELECT COUNT(*) FROM assignment_submissions WHERE student_id NOT IN (SELECT id FROM students)", "Orphan Submissions (Student)", 0);

echo "\n--- DUPLICATE AUDIT ---\n";
checkCount($pdo, "SELECT COUNT(*) FROM (SELECT student_id, academic_year, semester FROM student_academic_enrollments GROUP BY student_id, academic_year, semester HAVING COUNT(*) > 1) as t", "Duplicate Enrollments", 0);
checkCount($pdo, "SELECT COUNT(*) FROM (SELECT student_id, subject_id, attendance_date FROM attendance GROUP BY student_id, subject_id, attendance_date HAVING COUNT(*) > 1) as t", "Duplicate Attendance", 0);
checkCount($pdo, "SELECT COUNT(*) FROM (SELECT student_id, subject_id FROM student_marks GROUP BY student_id, subject_id HAVING COUNT(*) > 1) as t", "Duplicate Marks", 0);

?>
