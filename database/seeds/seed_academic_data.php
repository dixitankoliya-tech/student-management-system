<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting Academic Dataset Seed...\n";

try {
    $pdo->beginTransaction();

    // 1. ADD EXAMINATIONS
    $exams = ['Mid Semester', 'End Semester'];
    $courses = ['BSc IT', 'BCA', 'MBA', 'BBA', 'MCA'];
    
    $checkExam = $pdo->prepare("SELECT id FROM examinations WHERE exam_name = ? AND course = ?");
    $insertExam = $pdo->prepare("INSERT INTO examinations (exam_name, course, academic_year, semester, start_date, end_date, status) VALUES (?, ?, '2026-2027', 1, '2026-10-01', '2026-10-15', 'Completed')");
    
    foreach ($courses as $c) {
        foreach ($exams as $e) {
            $checkExam->execute([$e, $c]);
            if ($checkExam->rowCount() == 0) {
                $insertExam->execute([$e, $c]);
            }
        }
    }
    echo "Examinations generated.\n";

    // 2. ADD MARKS FOR STUDENTS
    $allStudents = $pdo->query("SELECT id, course, semester FROM students")->fetchAll(PDO::FETCH_ASSOC);
    
    $getExams = $pdo->prepare("SELECT id FROM examinations WHERE course = ?");
    $getSubs = $pdo->prepare("SELECT id FROM subjects WHERE course = ?");
    $getFac = $pdo->prepare("SELECT faculty_id FROM faculty_subjects WHERE subject_id = ? LIMIT 1");
    
    $checkMark = $pdo->prepare("SELECT id FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ?");
    $insertMark = $pdo->prepare("INSERT INTO marks (exam_id, student_id, subject_id, faculty_id, marks_obtained, max_marks, status) VALUES (?, ?, ?, ?, ?, ?, 'Locked')");

    $marksAdded = 0;
    foreach ($allStudents as $stu) {
        $getExams->execute([$stu['course']]);
        $stuExams = $getExams->fetchAll(PDO::FETCH_COLUMN);
        
        $getSubs->execute([$stu['course']]);
        $stuSubs = $getSubs->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($stuExams as $exId) {
            foreach ($stuSubs as $subId) {
                $checkMark->execute([$exId, $stu['id'], $subId]);
                if ($checkMark->rowCount() == 0) {
                    $getFac->execute([$subId]);
                    $facId = $getFac->fetchColumn();
                    if (!$facId) $facId = 1; // Fallback to a valid faculty
                    
                    $marksObt = rand(40, 95); // Realistic passing range
                    $insertMark->execute([$exId, $stu['id'], $subId, $facId, $marksObt, 100]);
                    $marksAdded++;
                }
            }
        }
    }
    echo "Marks generated. Added: $marksAdded.\n";

    // 3. ADD ASSIGNMENTS
    $checkAssign = $pdo->prepare("SELECT id FROM assignments WHERE title = ? AND subject_id = ?");
    $insertAssign = $pdo->prepare("INSERT INTO assignments (title, description, subject_id, faculty_id, course, semester, division, due_date, max_marks) VALUES (?, 'Complete the assigned task', ?, ?, ?, 1, 'A', '2026-11-01', 50)");
    
    $allSubs = $pdo->query("SELECT id, course FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
    $assignAdded = 0;
    foreach ($allSubs as $sub) {
        $getFac->execute([$sub['id']]);
        $facId = $getFac->fetchColumn();
        if (!$facId) $facId = 1;
        
        for ($i=1; $i<=2; $i++) {
            $title = "Assignment $i";
            $checkAssign->execute([$title, $sub['id']]);
            if ($checkAssign->rowCount() == 0) {
                $insertAssign->execute([$title, $sub['id'], $facId, $sub['course']]);
                $assignAdded++;
            }
        }
    }
    echo "Assignments generated. Added: $assignAdded.\n";

    // 4. TIMETABLE
    // Skipping complex timetable generation, just basic inserts if necessary.
    
    $pdo->commit();
    echo "Academic Seed completed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}
