<?php
/**
 * SMS 2.0: Academic Calculator Helper (Master Engine)
 */

function calculateGrade($percentage) {
    if ($percentage >= 90) return ['grade' => 'O', 'point' => 10];
    if ($percentage >= 80) return ['grade' => 'A+', 'point' => 9];
    if ($percentage >= 70) return ['grade' => 'A', 'point' => 8];
    if ($percentage >= 60) return ['grade' => 'B+', 'point' => 7];
    if ($percentage >= 50) return ['grade' => 'B', 'point' => 6];
    if ($percentage >= 40) return ['grade' => 'C', 'point' => 5];
    return ['grade' => 'F', 'point' => 0];
}

function calculateSemesterResult($marks_array) {
    $total_max = 0;
    $total_obt = 0;
    $total_credits = 0;
    $earned_points = 0;
    $has_failed = false;
    $subject_results = [];
    
    // Group marks by subject (if multiple exams per subject, sum them up)
    $subject_aggregates = [];
    foreach ($marks_array as $m) {
        $sub_id = $m['subject_id'];
        if (!isset($subject_aggregates[$sub_id])) {
            $subject_aggregates[$sub_id] = [
                'subject_name' => $m['subject_name'],
                'subject_code' => $m['subject_code'],
                'credits' => $m['credits'] ?? 3, // fallback to 3 credits
                'max_marks' => 0,
                'marks_obtained' => 0,
                'internal_marks' => 0,
                'external_marks' => 0
            ];
        }
        $subject_aggregates[$sub_id]['max_marks'] += $m['max_marks'];
        $subject_aggregates[$sub_id]['marks_obtained'] += $m['marks_obtained'];
        if (isset($m['internal_marks'])) {
            $subject_aggregates[$sub_id]['internal_marks'] += $m['internal_marks'];
        }
        if (isset($m['external_marks'])) {
            $subject_aggregates[$sub_id]['external_marks'] += $m['external_marks'];
        }
    }
    
    foreach ($subject_aggregates as $sub_id => $agg) {
        $pct = $agg['max_marks'] > 0 ? ($agg['marks_obtained'] / $agg['max_marks']) * 100 : 0;
        $grade_info = calculateGrade($pct);
        
        $is_pass = $grade_info['grade'] !== 'F';
        if (!$is_pass) $has_failed = true;
        
        $subject_results[] = [
            'subject_name' => $agg['subject_name'],
            'subject_code' => $agg['subject_code'],
            'credits' => $agg['credits'],
            'max_marks' => $agg['max_marks'],
            'marks_obtained' => $agg['marks_obtained'],
            'internal_marks' => $agg['internal_marks'],
            'external_marks' => $agg['external_marks'],
            'percentage' => round($pct, 2),
            'grade' => $grade_info['grade'],
            'grade_point' => $grade_info['point'],
            'pass_fail' => $is_pass ? 'Pass' : 'Fail'
        ];
        
        $total_max += $agg['max_marks'];
        $total_obt += $agg['marks_obtained'];
        $total_credits += $agg['credits'];
        $earned_points += ($grade_info['point'] * $agg['credits']);
    }
    
    $overall_pct = $total_max > 0 ? ($total_obt / $total_max) * 100 : 0;
    $sgpa = $total_credits > 0 ? ($earned_points / $total_credits) : 0;
    
    return [
        'subjects' => $subject_results,
        'total_max' => $total_max,
        'total_obtained' => $total_obt,
        'overall_percentage' => round($overall_pct, 2),
        'total_credits' => $total_credits,
        'sgpa' => round($sgpa, 2),
        'result_status' => $has_failed ? 'Fail' : 'Pass'
    ];
}

function calculateStudentCGPA($pdo, $student_id) {
    // Fetch all Locked/Published marks
    $marksStmt = $pdo->prepare("
        SELECT 
            m.subject_id,
            s.subject_name,
            s.subject_code,
            s.credits,
            s.semester,
            m.max_marks,
            m.marks_obtained,
            sm.internal_marks,
            sm.external_marks
        FROM marks m
        JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN student_marks sm ON sm.student_id = m.student_id AND sm.subject_id = m.subject_id
        WHERE m.student_id = ? AND m.status = 'Locked'
        ORDER BY s.semester DESC, s.subject_name ASC
    ");
    $marksStmt->execute([$student_id]);
    $all_marks = $marksStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group marks by semester
    $semester_marks = [];
    foreach ($all_marks as $m) {
        $sem = $m['semester'];
        if (!isset($semester_marks[$sem])) {
            $semester_marks[$sem] = [];
        }
        $semester_marks[$sem][] = $m;
    }

    $total_credits_all = 0;
    $total_points_all = 0;
    $semester_results = [];

    foreach ($semester_marks as $sem => $marks) {
        $res = calculateSemesterResult($marks);
        $semester_results[$sem] = $res;
        $total_credits_all += $res['total_credits'];
        $total_points_all += ($res['sgpa'] * $res['total_credits']);
    }

    $cgpa = $total_credits_all > 0 ? round($total_points_all / $total_credits_all, 2) : 0;
    
    return [
        'cgpa' => $cgpa,
        'total_credits' => $total_credits_all,
        'semester_results' => $semester_results
    ];
}

function calculateAttendancePercentage($pdo, $student_id, $semester = null, $min_required = 75) {
    $sql = "SELECT COUNT(*) as total_lectures, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_lectures 
            FROM attendance WHERE student_id = ?";
    $params = [$student_id];
    
    if ($semester) {
        $sql .= " AND subject_id IN (SELECT id FROM subjects WHERE semester = ?)";
        $params[] = $semester;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)$result['total_lectures'];
    $present = (int)$result['present_lectures'];
    
    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;
    
    return [
        'total_lectures' => $total,
        'present_lectures' => $present,
        'absent_lectures' => $total - $present,
        'percentage' => $percentage,
        'status' => $percentage >= $min_required || $total == 0 ? 'SAFE' : 'SHORTAGE'
    ];
}

function calculateSubjectAttendancePercentage($pdo, $student_id, $subject_id, $min_required = 75) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_lectures, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_lectures FROM attendance WHERE student_id = ? AND subject_id = ?");
    $stmt->execute([$student_id, $subject_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)$result['total_lectures'];
    $present = (int)$result['present_lectures'];
    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;
    
    return [
        'total_lectures' => $total,
        'present_lectures' => $present,
        'absent_lectures' => $total - $present,
        'percentage' => $percentage,
        'status' => $percentage >= $min_required || $total == 0 ? 'SAFE' : 'SHORTAGE'
    ];
}
function calculateAggregateAttendance($pdo, $date = null, $course = null, $semester = null) {
    $sql = "SELECT COUNT(*) as total_lectures, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_lectures FROM attendance WHERE 1=1";
    $params = [];
    
    if ($date) {
        $sql .= " AND attendance_date = ?";
        $params[] = $date;
    }
    
    if ($course || $semester) {
        $sql .= " AND student_id IN (SELECT student_id FROM student_academic_enrollments WHERE status = 'Active'";
        if ($course) {
            $sql .= " AND course = ?";
            $params[] = $course;
        }
        if ($semester) {
            $sql .= " AND semester = ?";
            $params[] = $semester;
        }
        $sql .= ")";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)$result['total_lectures'];
    $present = (int)$result['present_lectures'];
    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;
    
    return [
        'total_lectures' => $total,
        'present_lectures' => $present,
        'absent_lectures' => $total - $present,
        'percentage' => $percentage
    ];
}
?>
