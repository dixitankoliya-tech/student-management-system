<?php
require_once __DIR__ . '/../config/database.php';

echo "====================================================================\n";
echo " GLS FCAIT STUDENT MANAGEMENT SYSTEM -- COMPLETE SYSTEM INSPECTION\n";
echo "====================================================================\n\n";

// 1. All Database Tables & Row Counts
echo "--- 1. DATABASE TABLES & RECORD COUNTS ---\n";
$tablesStmt = $pdo->query("SHOW TABLES");
$allTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
$tableCounts = [];
foreach ($allTables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $tableCounts[$table] = $count;
        echo sprintf("%-35s : %d records\n", $table, $count);
    } catch (Exception $e) {
        echo sprintf("%-35s : Error - %s\n", $table, $e->getMessage());
    }
}
echo "\nTotal tables: " . count($allTables) . "\n\n";

// 2. Academic Programs & Courses
echo "--- 2. ACADEMIC PROGRAMS & COURSES ---\n";
$coursesStmt = $pdo->query("SELECT id, course_code, course_name, duration_years, total_semesters, description FROM courses ORDER BY id");
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($courses as $c) {
    // Note: subjects and students store course name as string or code
    $subCount = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE course = ? OR course = ?");
    $subCount->execute([$c['course_name'], $c['course_code']]);
    $nSubs = $subCount->fetchColumn();

    $stuCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE course = ? OR course = ?");
    $stuCount->execute([$c['course_name'], $c['course_code']]);
    $nStus = $stuCount->fetchColumn();

    echo sprintf("ID: %d | Code: %-12s | Name: %-25s | Duration: %d yrs | Sems: %d | Subjects: %d | Students: %d\n",
        $c['id'], $c['course_code'], $c['course_name'], $c['duration_years'], $c['total_semesters'], $nSubs, $nStus
    );
}
echo "\n";

// 3. Faculty & Leadership Hierarchy
echo "--- 3. FACULTY & LEADERSHIP HIERARCHY ---\n";
$facultyStats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN role = 'dean' THEN 1 ELSE 0 END) as deans,
    SUM(CASE WHEN role = 'coordinator' THEN 1 ELSE 0 END) as coordinators,
    SUM(CASE WHEN role = 'faculty' THEN 1 ELSE 0 END) as regular_faculty
FROM faculty")->fetch(PDO::FETCH_ASSOC);

echo "Total Faculty: {$facultyStats['total']} | Dean: {$facultyStats['deans']} | Coordinators: {$facultyStats['coordinators']} | Regular: {$facultyStats['regular_faculty']}\n\n";

echo "Leadership Assignments:\n";
// Dean
$deanStmt = $pdo->query("SELECT f.id, f.faculty_id, f.first_name, f.last_name, f.designation, f.email, d.department_name
    FROM department_heads dh
    JOIN faculty f ON dh.faculty_id = f.id
    JOIN departments d ON dh.department_id = d.id
    WHERE dh.status = 'Active'");
while ($dean = $deanStmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("DEAN: %s %s (ID: %s, Desig: %s) -> Dept: %s | Email: %s\n",
        $dean['first_name'], $dean['last_name'], $dean['faculty_id'], $dean['designation'], $dean['department_name'], $dean['email']
    );
}

// Coordinators
$coordStmt = $pdo->query("SELECT f.id, f.faculty_id, f.first_name, f.last_name, f.designation, f.email, c.course_name, c.course_code
    FROM course_coordinators cc
    JOIN faculty f ON cc.faculty_id = f.id
    JOIN courses c ON cc.course_id = c.id
    WHERE cc.status = 'Active'
    ORDER BY c.id");
while ($coord = $coordStmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("COORDINATOR: %s %s (ID: %s) -> Course: %s (%s) | Email: %s\n",
        $coord['first_name'], $coord['last_name'], $coord['faculty_id'], $coord['course_name'], $coord['course_code'], $coord['email']
    );
}

// Sample regular faculty
echo "\nSample Faculty Members:\n";
$facSample = $pdo->query("SELECT faculty_id, first_name, last_name, designation, department, email FROM faculty WHERE role = 'faculty' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($facSample as $fs) {
    echo sprintf("ID: %-8s | Name: %-25s | Desig: %-25s | Dept: %s\n", 
        $fs['faculty_id'], $fs['first_name'] . ' ' . $fs['last_name'], $fs['designation'], $fs['department']
    );
}
echo "\n";

// 4. Student Records & Quality
echo "--- 4. STUDENT RECORDS & DATA QUALITY ---\n";
$stuStats = $pdo->query("SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT enrollment_no) as distinct_enrollment,
    COUNT(DISTINCT email) as distinct_email,
    SUM(CASE WHEN CONCAT(first_name, ' ', last_name) REGEXP '^[A-Za-z. ]+$' THEN 1 ELSE 0 END) as valid_names,
    SUM(CASE WHEN CONCAT(first_name, ' ', last_name) LIKE '%Student%' OR CONCAT(first_name, ' ', last_name) LIKE '%Test%' OR CONCAT(first_name, ' ', last_name) LIKE '%Demo%' OR CONCAT(first_name, ' ', last_name) LIKE '%User%' OR CONCAT(first_name, ' ', last_name) LIKE '%ABC%' OR CONCAT(first_name, ' ', last_name) LIKE '%XYZ%' THEN 1 ELSE 0 END) as placeholder_names
FROM students")->fetch(PDO::FETCH_ASSOC);

echo "Total Students: {$stuStats['total']}\n";
echo "Distinct Enrollment Numbers: {$stuStats['distinct_enrollment']}\n";
echo "Valid Natural Names: {$stuStats['valid_names']}\n";
echo "Placeholder Names Detected: {$stuStats['placeholder_names']}\n";

echo "\nStudent Distribution by Course & Semester:\n";
$distStmt = $pdo->query("SELECT course, semester, COUNT(*) as cnt 
    FROM students 
    GROUP BY course, semester 
    ORDER BY course, semester");
while ($d = $distStmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("Course: %-25s | Sem %d: %d students\n", $d['course'], $d['semester'], $d['cnt']);
}

echo "\nSample Student Records:\n";
$stuSample = $pdo->query("SELECT enrollment_no, first_name, last_name, course, semester, email, admission_year 
    FROM students 
    ORDER BY id LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
foreach ($stuSample as $ss) {
    echo sprintf("Enroll: %-14s | Name: %-25s | %-15s Sem %d | Year: %s\n", 
        $ss['enrollment_no'], $ss['first_name'] . ' ' . $ss['last_name'], $ss['course'], $ss['semester'], $ss['admission_year']
    );
}
echo "\n";

// 5. Subjects & Timetables
echo "--- 5. SUBJECTS & TIMETABLES ---\n";
$subStats = $pdo->query("SELECT 
    COUNT(*) as total_subjects,
    COUNT(DISTINCT subject_code) as distinct_codes,
    SUM(CASE WHEN subject_name LIKE '%Dummy%' OR subject_name LIKE '%Sample%' OR subject_name LIKE '%Test%' OR subject_name LIKE '%Subject%' THEN 1 ELSE 0 END) as placeholder_subjects
FROM subjects")->fetch(PDO::FETCH_ASSOC);
echo "Total Subjects: {$subStats['total_subjects']} | Distinct Codes: {$subStats['distinct_codes']} | Placeholders: {$subStats['placeholder_subjects']}\n";

// Sample subjects
echo "\nSample Subjects per Program:\n";
$sampleSubs = $pdo->query("SELECT course, semester, subject_code, subject_name, credits 
    FROM subjects 
    WHERE semester = 1 
    ORDER BY course, subject_code LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sampleSubs as $sub) {
    echo sprintf("Course: %-15s | Sem %d | Code: %-10s | Credits: %d | Name: %s\n",
        $sub['course'], $sub['semester'], $sub['subject_code'], $sub['credits'], $sub['subject_name']
    );
}
echo "\n";

// 6. Attendance & Academic Records
echo "--- 6. ATTENDANCE & MARKS INTEGRITY ---\n";
$marksStats = $pdo->query("SELECT 
    COUNT(*) as total_marks,
    COUNT(DISTINCT student_id) as students_with_marks,
    COUNT(DISTINCT subject_id) as subjects_with_marks,
    AVG(marks_obtained) as avg_marks,
    MIN(marks_obtained) as min_marks,
    MAX(marks_obtained) as max_marks
FROM marks")->fetch(PDO::FETCH_ASSOC);
echo "Marks Rows: {$marksStats['total_marks']} | Students: {$marksStats['students_with_marks']} | Avg Marks: " . round($marksStats['avg_marks'], 2) . " | Range: {$marksStats['min_marks']} - {$marksStats['max_marks']}\n";

// 7. Relational Consistency Checks (Orphans)
echo "\n--- 7. RELATIONAL ORPHAN AUDIT ---\n";
$checks = [
    "Marks with invalid student_id" => "SELECT COUNT(*) FROM marks WHERE student_id NOT IN (SELECT id FROM students)",
    "Marks with invalid subject_id" => "SELECT COUNT(*) FROM marks WHERE subject_id NOT IN (SELECT id FROM subjects)",
    "Student Subject Enrollments invalid student" => "SELECT COUNT(*) FROM student_subject_enrollments WHERE student_id NOT IN (SELECT id FROM students)",
    "Student Academic Enrollments invalid student" => "SELECT COUNT(*) FROM student_academic_enrollments WHERE student_id NOT IN (SELECT id FROM students)"
];

foreach ($checks as $label => $sql) {
    $orphans = $pdo->query($sql)->fetchColumn();
    echo sprintf("%-45s : %d orphans %s\n", $label, $orphans, $orphans == 0 ? "[PASS]" : "[FAIL]");
}

echo "\n====================================================================\n";
echo " INSPECTION COMPLETE\n";
echo "====================================================================\n";
