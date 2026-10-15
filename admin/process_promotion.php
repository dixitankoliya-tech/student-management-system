<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: promote_students.php");
    exit();
}
requireValidCsrf();

$course = $_POST['course'] ?? '';
$from_semester = (int)($_POST['from_semester'] ?? 0);
$to_semester = (int)($_POST['to_semester'] ?? 0);
$student_ids = $_POST['student_ids'] ?? [];

if (empty($course) || empty($from_semester) || empty($to_semester) || empty($student_ids)) {
    header("Location: promote_students.php?error=" . urlencode("Missing required promotion parameters."));
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Fetch course max semesters
    $stmtC = $pdo->prepare("SELECT duration_years FROM courses WHERE course_name = ?");
    $stmtC->execute([$course]);
    $courseObj = $stmtC->fetch();
    $max_semesters = $courseObj ? ($courseObj['duration_years'] * 2) : 6;
    
    $is_graduating = ($from_semester == $max_semesters);
    
    // Prepare statements for verification and promotion
    $verifyStmt = $pdo->prepare("
        SELECT id, 
               (SELECT COUNT(*) FROM student_marks WHERE student_id = students.id AND grade = 'F') as backlogs
        FROM students 
        WHERE id = ? AND course = ? AND semester = ? AND status = 'Active'
    ");

    $updateStudent = $pdo->prepare("UPDATE students SET semester = ?, status = ? WHERE id = ?");
    $insertHistory = $pdo->prepare("INSERT INTO promotion_history (student_id, from_semester, to_semester, academic_year, status) VALUES (?, ?, ?, '2025-2026', ?)");
    $insertEnrollment = $pdo->prepare("INSERT INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status) SELECT id, course, '2025-2026', ?, division, ? FROM students WHERE id = ?");
    
    $promoted_count = 0;
    foreach ($student_ids as $sid) {
        $sid = (int)$sid;
        $verifyStmt->execute([$sid, $course, $from_semester]);
        $stu = $verifyStmt->fetch();

        // Enforce server-side rules: student must belong to cohort and have <= 3 backlogs
        if (!$stu || (int)$stu['backlogs'] > 3) {
            continue; // Skip ineligible or invalid students
        }

        if ($is_graduating) {
            // Mark as Alumni
            $updateStudent->execute([$from_semester, 'Alumni', $sid]);
            $insertHistory->execute([$sid, $from_semester, $from_semester, 'Graduated']);
        } else {
            // 1. Update Student Current Sem
            $updateStudent->execute([$to_semester, 'Active', $sid]);
            // 2. Log History
            $insertHistory->execute([$sid, $from_semester, $to_semester, 'Promoted']);
            // 3. New Enrollment Record
            $insertEnrollment->execute([$to_semester, 'Active', $sid]);
        }
        $promoted_count++;
    }
    
    $pdo->commit();
    logActivity($pdo, 'PROMOTE_STUDENTS', null, "Promoted $promoted_count students from $course Sem $from_semester to Sem $to_semester");
    header("Location: promote_students.php?course=".urlencode($course)."&semester={$from_semester}&success=".urlencode("$promoted_count students successfully processed for promotion."));
    exit();
    
} catch(Exception $e) {
    $pdo->rollBack();
    error_log("Promotion Error: " . $e->getMessage());
    $errMsg = APP_DEBUG ? "Failed to promote students: " . $e->getMessage() : "An error occurred while processing promotions. Please try again.";
    header("Location: promote_students.php?course=".urlencode($course)."&semester={$from_semester}&error=".urlencode($errMsg));
    exit();
}
