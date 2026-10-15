<?php
require_once __DIR__ . '/../config/database.php';

$tables = [
    'students', 'faculty', 'courses', 'subjects', 'timetables', 
    'attendance', 'examinations', 'exam_schedules', 'marks', 
    'student_marks', 'academic_performance', 'assignments', 
    'assignment_submissions', 'study_materials', 'fee_structures', 
    'student_fees', 'fee_payments', 'users'
];

echo "=== DATABASE INDEX AUDIT ===\n";
foreach ($tables as $t) {
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `$t`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table: $t\n";
        $grouped = [];
        foreach ($indexes as $idx) {
            $grouped[$idx['Key_name']][] = $idx['Column_name'];
        }
        foreach ($grouped as $k => $cols) {
            echo "  - $k (" . implode(', ', $cols) . ")\n";
        }
        echo "\n";
    } catch (Exception $e) {
        echo "Table: $t - Error: " . $e->getMessage() . "\n\n";
    }
}
