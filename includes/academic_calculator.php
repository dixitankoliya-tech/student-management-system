<?php
/**
 * SMS 2.0: Academic Calculator Helper
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
                'marks_obtained' => 0
            ];
        }
        $subject_aggregates[$sub_id]['max_marks'] += $m['max_marks'];
        $subject_aggregates[$sub_id]['marks_obtained'] += $m['marks_obtained'];
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
?>
