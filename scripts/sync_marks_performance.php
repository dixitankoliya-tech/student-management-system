<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/academic_calculator.php';

echo "=== SYNCING MARKS AND ACADEMIC PERFORMANCE ===\n";

try {
    // 1. Fetch Examinations mapping: [course][semester] => id (Mid Semester)
    $stmtExams = $pdo->query("SELECT id, course, semester, exam_name, exam_type FROM examinations WHERE exam_type = 'Mid Semester'");
    $examMap = [];
    while ($row = $stmtExams->fetch(PDO::FETCH_ASSOC)) {
        $examMap[$row['course']][$row['semester']] = $row['id'];
    }
    echo "Loaded " . count($examMap) . " course exam mappings.\n";

    // 2. Fetch subject faculty mappings
    $stmtFac = $pdo->query("SELECT subject_id, faculty_id FROM faculty_subjects");
    $subjectFaculty = [];
    while ($row = $stmtFac->fetch(PDO::FETCH_ASSOC)) {
        $subjectFaculty[$row['subject_id']] = $row['faculty_id'];
    }
    echo "Loaded " . count($subjectFaculty) . " subject faculty assignments.\n";

    // 3. Fetch all 2,000 student_marks
    $stmtSM = $pdo->query("
        SELECT sm.student_id, sm.subject_id, sm.semester, sm.total_marks, s.course 
        FROM student_marks sm 
        JOIN students s ON sm.student_id = s.id
    ");
    $allSM = $stmtSM->fetchAll(PDO::FETCH_ASSOC);
    echo "Loaded " . count($allSM) . " student_marks records.\n";

    // 4. Build batch for marks table
    $marksRows = [];
    foreach ($allSM as $sm) {
        $c = $sm['course'];
        $sem = (int)$sm['semester'];
        $exId = $examMap[$c][$sem] ?? null;
        if (!$exId) {
            // fallback to any exam for this course
            $exId = reset($examMap[$c]) ?: 90001;
        }
        $facId = $subjectFaculty[$sm['subject_id']] ?? 1;
        $obt = round((float)$sm['total_marks'], 2);
        
        $marksRows[] = [
            'exam_id' => $exId,
            'student_id' => $sm['student_id'],
            'subject_id' => $sm['subject_id'],
            'faculty_id' => $facId,
            'marks_obtained' => $obt,
            'max_marks' => 100.00,
            'status' => 'Locked'
        ];
    }

    // Insert into marks in batches of 250
    $pdo->exec("DELETE FROM marks");
    $pdo->exec("ALTER TABLE marks AUTO_INCREMENT = 1");

    $batchSize = 250;
    for ($i = 0; $i < count($marksRows); $i += $batchSize) {
        $chunk = array_slice($marksRows, $i, $batchSize);
        $placeholders = [];
        $values = [];
        foreach ($chunk as $r) {
            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?)";
            $values[] = $r['exam_id'];
            $values[] = $r['student_id'];
            $values[] = $r['subject_id'];
            $values[] = $r['faculty_id'];
            $values[] = $r['marks_obtained'];
            $values[] = $r['max_marks'];
            $values[] = $r['status'];
        }
        $sql = "INSERT INTO marks (exam_id, student_id, subject_id, faculty_id, marks_obtained, max_marks, status) VALUES " . implode(',', $placeholders);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
    $totalMarks = $pdo->query("SELECT COUNT(*) FROM marks")->fetchColumn();
    echo "Successfully populated `marks` table: $totalMarks records.\n";

    // 5. Pre-calculate attendance percentages per student
    $attStmt = $pdo->query("
        SELECT student_id, 
               COUNT(*) as total_sessions, 
               SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_sessions 
        FROM attendance 
        GROUP BY student_id
    ");
    $attMap = [];
    while ($row = $attStmt->fetch(PDO::FETCH_ASSOC)) {
        $tot = (int)$row['total_sessions'];
        $prs = (int)$row['present_sessions'];
        $attMap[$row['student_id']] = ($tot > 0) ? round(($prs / $tot) * 100, 2) : 80.00;
    }

    // 6. Calculate Academic Performance for all students
    $allStudents = $pdo->query("SELECT id, course, semester FROM students")->fetchAll(PDO::FETCH_ASSOC);
    $allMarksStmt = $pdo->query("
        SELECT m.student_id, m.subject_id, s.subject_name, s.subject_code, s.credits, s.semester, m.max_marks, m.marks_obtained 
        FROM marks m 
        JOIN subjects s ON m.subject_id = s.id 
        WHERE m.status = 'Locked'
    ");
    $studentMarksGrouped = [];
    while ($mrk = $allMarksStmt->fetch(PDO::FETCH_ASSOC)) {
        $studentMarksGrouped[$mrk['student_id']][$mrk['semester']][] = $mrk;
    }

    $pdo->exec("DELETE FROM academic_performance");
    $pdo->exec("ALTER TABLE academic_performance AUTO_INCREMENT = 1");

    $perfRows = [];
    foreach ($allStudents as $stu) {
        $sId = $stu['id'];
        $sem = (int)$stu['semester'];
        $attPct = $attMap[$sId] ?? 82.50;

        if (isset($studentMarksGrouped[$sId][$sem])) {
            $semData = calculateSemesterResult($studentMarksGrouped[$sId][$sem]);
            $sgpa = $semData['sgpa'];
            $pct = $semData['overall_percentage'];
            $grade = calculateGrade($pct)['grade'];
            
            // Performance category and alert level
            $alert = ($attPct < 75 || $pct < 40) ? 'RED' : (($attPct < 80 || $pct < 50) ? 'YELLOW' : 'GREEN');
            $category = ($pct >= 75) ? 'High Achiever' : (($pct >= 55) ? 'On Track' : 'Needs Support');

            $perfRows[] = [
                'student_id' => $sId,
                'semester' => $sem,
                'academic_year' => '2025-2026',
                'total_marks_obtained' => $semData['total_obtained'],
                'total_max_marks' => $semData['total_max'],
                'overall_percentage' => $pct,
                'grade' => $grade,
                'attendance_percentage' => $attPct,
                'assignment_score' => round(min(100, $pct * 0.95 + 5), 2),
                'practical_score' => round(min(100, $pct * 0.92 + 8), 2),
                'previous_semester_percentage' => ($sem > 1) ? round(max(35, $pct + rand(-5, 5)), 2) : null,
                'improvement_percentage' => 0.00,
                'performance_score' => $sgpa,
                'performance_category' => $category,
                'alert_level' => $alert,
                'class_rank' => 1
            ];
        }
    }

    for ($i = 0; $i < count($perfRows); $i += $batchSize) {
        $chunk = array_slice($perfRows, $i, $batchSize);
        $placeholders = [];
        $values = [];
        foreach ($chunk as $r) {
            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $values[] = $r['student_id'];
            $values[] = $r['semester'];
            $values[] = $r['academic_year'];
            $values[] = $r['total_marks_obtained'];
            $values[] = $r['total_max_marks'];
            $values[] = $r['overall_percentage'];
            $values[] = $r['grade'];
            $values[] = $r['attendance_percentage'];
            $values[] = $r['assignment_score'];
            $values[] = $r['practical_score'];
            $values[] = $r['previous_semester_percentage'];
            $values[] = $r['improvement_percentage'];
            $values[] = $r['performance_score'];
            $values[] = $r['performance_category'];
            $values[] = $r['alert_level'];
            $values[] = $r['class_rank'];
        }
        $sql = "INSERT INTO academic_performance (
            student_id, semester, academic_year, total_marks_obtained, total_max_marks,
            overall_percentage, grade, attendance_percentage, assignment_score, practical_score,
            previous_semester_percentage, improvement_percentage, performance_score, performance_category,
            alert_level, class_rank
        ) VALUES " . implode(',', $placeholders);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }

    $totalPerf = $pdo->query("SELECT COUNT(*) FROM academic_performance")->fetchColumn();
    echo "Successfully populated `academic_performance`: $totalPerf records.\n";
    echo "=== SYNC COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
