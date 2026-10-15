<?php
require_once __DIR__ . '/../config/database.php';

echo "=== ADDING HIGH-IMPACT DATABASE INDEXES ===\n";

$indexesToAdd = [
    'students' => [
        'name' => 'idx_students_course_sem',
        'sql' => "ALTER TABLE students ADD INDEX idx_students_course_sem (course, semester)"
    ],
    'subjects' => [
        'name' => 'idx_subjects_course_sem',
        'sql' => "ALTER TABLE subjects ADD INDEX idx_subjects_course_sem (course, semester)"
    ],
    'examinations' => [
        'name' => 'idx_exams_course_sem_status',
        'sql' => "ALTER TABLE examinations ADD INDEX idx_exams_course_sem_status (course, semester, status)"
    ],
    'student_academic_enrollments' => [
        'name' => 'idx_sae_course_sem_status',
        'sql' => "ALTER TABLE student_academic_enrollments ADD INDEX idx_sae_course_sem_status (course, semester, status)"
    ],
    'timetables' => [
        'name' => 'idx_tt_lookup',
        'sql' => "ALTER TABLE timetables ADD INDEX idx_tt_lookup (course, semester, division, day_of_week)"
    ],
    'attendance' => [
        'name' => 'idx_att_subj_date',
        'sql' => "ALTER TABLE attendance ADD INDEX idx_att_subj_date (subject_id, attendance_date)"
    ],
    'fee_structures' => [
        'name' => 'idx_feestruct_lookup',
        'sql' => "ALTER TABLE fee_structures ADD INDEX idx_feestruct_lookup (course, semester, academic_year)"
    ]
];

foreach ($indexesToAdd as $table => $info) {
    try {
        $idxName = $info['name'];
        // Check if index already exists
        $check = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = '$idxName'")->fetch();
        if ($check) {
            echo "[-] Index $idxName on table $table already exists. Skipping.\n";
        } else {
            $t = microtime(true);
            $pdo->exec($info['sql']);
            $ms = round((microtime(true) - $t) * 1000, 1);
            echo "[+] Added index $idxName on table $table ($ms ms)\n";
        }
    } catch (Exception $e) {
        echo "[!] Error adding index {$info['name']} on table $table: " . $e->getMessage() . "\n";
    }
}

echo "=== INDEX ADDITION COMPLETED ===\n";
