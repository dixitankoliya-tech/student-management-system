<?php
require_once __DIR__ . '/../config/database.php';

echo "=== SEMESTERS MASTER USAGE COUNTS ===\n";
$stmt = $pdo->query("
    SELECT sm.id, sm.semester_number, sm.description, sm.status,
    (SELECT COUNT(DISTINCT s.id) FROM subjects s WHERE s.semester = sm.semester_number) as subject_count,
    (SELECT COUNT(DISTINCT st.id) FROM students st WHERE st.semester = sm.semester_number) as student_count,
    (SELECT COUNT(*) FROM faculty_subjects fs WHERE fs.semester = sm.semester_number) as offering_count
    FROM semesters_master sm
    ORDER BY sm.semester_number ASC
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("Sem %2d (%-14s): Subjects=%3d | Students=%3d | Offerings=%3d | Status=%s\n",
        $r['semester_number'], $r['description'], $r['subject_count'], $r['student_count'], $r['offering_count'], $r['status']);
}

echo "\n=== STUDENTS BY SEMESTER & COURSE ===\n";
$stmt = $pdo->query("SELECT semester, course, COUNT(*) as cnt FROM students GROUP BY semester, course ORDER BY semester, course");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("Semester %2d | %-20s : %d students\n", $r['semester'], $r['course'], $r['cnt']);
}

echo "\n=== SUBJECTS BY SEMESTER & COURSE ===\n";
$stmt = $pdo->query("SELECT semester, course, COUNT(*) as cnt FROM subjects GROUP BY semester, course ORDER BY semester, course");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("Semester %2d | %-20s : %d subjects\n", $r['semester'], $r['course'], $r['cnt']);
}
