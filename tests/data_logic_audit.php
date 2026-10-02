<?php
require_once __DIR__ . '/../config/database.php';

$output = "DATABASE LIFECYCLE AUDIT\n========================\n\n";

// 1. Future Marks for Current Students
// Students table has 'semester'. Marks table has 'semester' via subject? Or 'student_marks' has semester.
// Wait, do we have 'marks' or 'student_marks'?
try {
    $stmt = $pdo->query("
        SELECT 
            s.id, s.first_name, s.course, s.semester as current_sem, sub.semester as mark_sem 
        FROM students s
        JOIN marks m ON s.id = m.student_id
        JOIN subjects sub ON m.subject_id = sub.id
        WHERE sub.semester > s.semester
    ");
    $futureMarks = $stmt->fetchAll();
    $output .= "FUTURE MARKS (marks.semester > students.semester): " . count($futureMarks) . "\n";
    if (count($futureMarks) > 0) {
        $output .= "Example: Student " . $futureMarks[0]['first_name'] . " (Current Sem: " . $futureMarks[0]['current_sem'] . ") has marks for Sem " . $futureMarks[0]['mark_sem'] . "\n";
    }
} catch (Exception $e) {
    $output .= "Error checking future marks: " . $e->getMessage() . "\n";
}

// 2. Future Attendance
try {
    $stmt = $pdo->query("
        SELECT a.id, s.first_name, s.semester as current_sem, sub.semester as subject_sem
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN subjects sub ON a.subject_id = sub.id
        WHERE sub.semester > s.semester
    ");
    $futureAtt = $stmt->fetchAll();
    $output .= "FUTURE ATTENDANCE (subject.semester > student.semester): " . count($futureAtt) . "\n";
} catch (Exception $e) {}

// 3. Duplicate enrollments
try {
    $stmt = $pdo->query("
        SELECT student_id, academic_year, semester, COUNT(*) as c
        FROM student_academic_enrollments
        GROUP BY student_id, academic_year, semester
        HAVING c > 1
    ");
    $dupEnr = $stmt->fetchAll();
    $output .= "DUPLICATE SEMESTER ENROLLMENTS: " . count($dupEnr) . "\n";
} catch (Exception $e) {}

echo $output;
