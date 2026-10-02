<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/academic_rules.php';

$academicEngine = new AcademicEngine($pdo);
// Faculty 1 now has 5 subjects (4 updated to 2024-25 + 1 inserted earlier)
$faculty_id = 1;
$academic_year = '2024-25';
$semester_type = 'Odd'; 
$canAssign = $academicEngine->canAssignFacultySubject($faculty_id, $academic_year, $semester_type);
echo "Can Assign (expect false): " . ($canAssign ? 'Yes' : 'No') . "\n";

$stmt = $pdo->query("SELECT * FROM faculty_subjects WHERE faculty_id=1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
