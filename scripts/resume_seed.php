<?php
if (php_sapi_name() !== 'cli') die("Must run from CLI\n");

require_once __DIR__ . '/../config/database.php';
set_time_limit(0);

echo "========================================\n";
echo "RESUMING SEED FROM STEP 6 (BULK MODE)\n";
echo "========================================\n\n";

try {
    echo "[6/8] Assigning Faculty to Subjects & Student Enrollments...\n";
    
    // Clear out anything that might have been partially written
    $pdo->exec("TRUNCATE TABLE faculty_subjects");
    $pdo->exec("TRUNCATE TABLE student_subject_enrollments");
    $pdo->exec("TRUNCATE TABLE marks");
    $pdo->exec("TRUNCATE TABLE student_marks");
    
    // Get all faculties
    $facultyIds = $pdo->query("SELECT id FROM faculty")->fetchAll(PDO::FETCH_COLUMN);
    $subjects = $pdo->query("SELECT id, course, semester FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
    
    $pdo->beginTransaction();
    
    $fac_idx = 0;
    $student_enrollments_data = [];
    
    $insertFacSubj = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id, course, semester, division, academic_year) VALUES (?, ?, ?, ?, ?, '2024-2025')");
    
    foreach ($subjects as $s) {
        $sId = $s['id'];
        $c = $s['course'];
        $sem = $s['semester'];
        
        $fIdA = $facultyIds[$fac_idx % count($facultyIds)];
        $fac_idx++;
        $insertFacSubj->execute([$fIdA, $sId, $c, $sem, 'A']);
        $fsIdA = $pdo->lastInsertId();
        
        $fIdB = $facultyIds[$fac_idx % count($facultyIds)];
        $fac_idx++;
        $insertFacSubj->execute([$fIdB, $sId, $c, $sem, 'B']);
        $fsIdB = $pdo->lastInsertId();
        
        // Enroll students in these subjects based on division
        $stuInSem = $pdo->query("SELECT id, division FROM students WHERE course='$c' AND semester=$sem")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($stuInSem as $stu) {
            $fsId = ($stu['division'] == 'A') ? $fsIdA : $fsIdB;
            $student_enrollments_data[] = "({$stu['id']}, {$fsId}, 'Enrolled')";
        }
    }
    
    // Bulk insert enrollments
    if (!empty($student_enrollments_data)) {
        $chunks = array_chunk($student_enrollments_data, 500);
        foreach($chunks as $chunk) {
            $pdo->exec("INSERT INTO student_subject_enrollments (student_id, faculty_subject_id, status) VALUES " . implode(',', $chunk));
        }
    }
    
    $pdo->commit();
    echo "      Mappings completed.\n\n";
    
    echo "[7/8] Generating Marks Data...\n";
    // For marks, we need exam_id. We'll just create a dummy exam.
    $pdo->exec("INSERT INTO examinations (exam_name, course, semester, academic_year, status) VALUES ('MidSem', 'BCA', 1, '2025-2026', 'Results Published')");
    $examId = $pdo->lastInsertId();
    
    $pdo->beginTransaction();
    $fsMap = $pdo->query("SELECT id, faculty_id, subject_id, semester FROM faculty_subjects")->fetchAll(PDO::FETCH_ASSOC);
    $fsLookup = [];
    foreach($fsMap as $fsm) { $fsLookup[$fsm['id']] = $fsm; }
    
    $enrolls = $pdo->query("SELECT student_id, faculty_subject_id FROM student_subject_enrollments")->fetchAll(PDO::FETCH_ASSOC);
    
    $marks_data = [];
    $student_marks_data = [];
    
    foreach ($enrolls as $en) {
        $fs = $fsLookup[$en['faculty_subject_id']];
        $intMarks = mt_rand(15, 30);
        $extMarks = mt_rand(30, 70);
        $totMarks = $intMarks + $extMarks;
        
        $marks_data[] = "($examId, {$en['student_id']}, {$fs['subject_id']}, {$fs['faculty_id']}, $totMarks, 70, 'Submitted')";
        $student_marks_data[] = "({$en['student_id']}, {$fs['subject_id']}, {$fs['semester']}, $intMarks, $extMarks, $totMarks, 'B')";
    }
    
    if (!empty($marks_data)) {
        $chunks = array_chunk($marks_data, 500);
        foreach($chunks as $chunk) {
            $pdo->exec("INSERT INTO marks (exam_id, student_id, subject_id, faculty_id, marks_obtained, max_marks, status) VALUES " . implode(',', $chunk));
        }
    }
    
    if (!empty($student_marks_data)) {
        $chunks = array_chunk($student_marks_data, 500);
        foreach($chunks as $chunk) {
            $pdo->exec("INSERT INTO student_marks (student_id, subject_id, semester, internal_marks, external_marks, total_marks, grade) VALUES " . implode(',', $chunk));
        }
    }
    
    $pdo->commit();
    echo "      Generated marks.\n\n";

    echo "[8/8] Validating database state...\n";
    
    $dept = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $crs = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $stu = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $fac = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
    
    echo "DEPARTMENTS: $dept\n";
    echo "COURSES: $crs\n";
    echo "STUDENTS: $stu\n";
    echo "FACULTY: $fac\n";
    
    echo "\nOrphan checks:\n";
    $orpStu = $pdo->query("SELECT COUNT(*) FROM student_academic_enrollments WHERE student_id NOT IN (SELECT id FROM students)")->fetchColumn();
    echo "student_academic_enrollments orphans: $orpStu\n";
    $orpM = $pdo->query("SELECT COUNT(*) FROM marks WHERE student_id NOT IN (SELECT id FROM students)")->fetchColumn();
    echo "marks student orphans: $orpM\n";

} catch (Exception $e) {
    die("FATAL ERROR: " . $e->getMessage() . "\n");
}
