<?php
$file = 'admin/process_promotion.php';
$content = file_get_contents($file);

$search = '$updateStudent = $pdo->prepare("UPDATE students SET semester = ? WHERE id = ?");
    $insertHistory = $pdo->prepare("INSERT INTO promotion_history (student_id, from_semester, to_semester, academic_year, status) VALUES (?, ?, ?, \'2025-2026\', \'Promoted\')");
    $insertEnrollment = $pdo->prepare("INSERT INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status) SELECT id, course, \'2025-2026\', ?, division, \'Active\' FROM students WHERE id = ?");
    
    foreach ($student_ids as $sid) {
        // 1. Update Student Current Sem
        $updateStudent->execute([$to_semester, $sid]);
        // 2. Log History
        $insertHistory->execute([$sid, $from_semester, $to_semester]);
        // 3. New Enrollment Record
        $insertEnrollment->execute([$to_semester, $sid]);
    }';

$replace = '
    // Fetch course max semesters
    $stmtC = $pdo->prepare("SELECT duration_years FROM courses WHERE course_name = ?");
    $stmtC->execute([$course]);
    $courseObj = $stmtC->fetch();
    $max_semesters = $courseObj ? ($courseObj[\'duration_years\'] * 2) : 6;
    
    $is_graduating = ($from_semester == $max_semesters);
    
    $updateStudent = $pdo->prepare("UPDATE students SET semester = ?, status = ? WHERE id = ?");
    $insertHistory = $pdo->prepare("INSERT INTO promotion_history (student_id, from_semester, to_semester, academic_year, status) VALUES (?, ?, ?, \'2025-2026\', ?)");
    $insertEnrollment = $pdo->prepare("INSERT INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status) SELECT id, course, \'2025-2026\', ?, division, ? FROM students WHERE id = ?");
    
    foreach ($student_ids as $sid) {
        if ($is_graduating) {
            // Mark as Alumni
            $updateStudent->execute([$from_semester, \'Alumni\', $sid]);
            $insertHistory->execute([$sid, $from_semester, $from_semester, \'Graduated\']);
            // No new enrollment for next sem, update current enrollment? Or just add a dummy Alumni record?
            // Usually we just mark student status as Alumni.
        } else {
            // 1. Update Student Current Sem
            $updateStudent->execute([$to_semester, \'Active\', $sid]);
            // 2. Log History
            $insertHistory->execute([$sid, $from_semester, $to_semester, \'Promoted\']);
            // 3. New Enrollment Record
            $insertEnrollment->execute([$to_semester, \'Active\', $sid]);
        }
    }';

// Handle \r\n differences
$content = str_replace(str_replace("\n", "\r\n", $search), $replace, $content);
$content = str_replace($search, $replace, $content);

file_put_contents($file, $content);
echo "Updated process_promotion.php\n";
?>
