<?php
/**
 * ==========================================================
 * SMS 2.0: COMPLETE DEMO DATA SEED — FCAIT (500 STUDENTS)
 * ==========================================================
 */

if (php_sapi_name() !== 'cli') {
    die("ERROR: This script must be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/enrollment_helper.php';
require_once __DIR__ . '/../includes/file_storage.php';

echo "========================================\n";
echo "FCAIT ACADEMIC DATA RESET\n";
echo "========================================\n\n";

try {
    echo "[1/11] Cleaning existing data...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear dependent tables
    $pdo->exec("DELETE FROM activity_logs WHERE student_id IS NOT NULL OR action LIKE '%ASSIGNMENT%' OR action LIKE '%ATTENDANCE%' OR action LIKE '%MARK%'");
    $pdo->exec("DELETE FROM academic_performance");
    $pdo->exec("DELETE FROM achievements");
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM student_marks");
    $pdo->exec("DELETE FROM assignment_submissions");
    $pdo->exec("DELETE FROM assignments");
    $pdo->exec("DELETE FROM study_materials");
    $pdo->exec("DELETE FROM fee_payments");
    $pdo->exec("DELETE FROM fee_structures");
    $pdo->exec("DELETE n FROM notifications n JOIN users u ON n.user_id = u.id WHERE u.role != 'admin'");
    $pdo->exec("DELETE FROM faculty_subjects");
    $pdo->exec("DELETE FROM timetables");
    
    // Clear primary tables
    $pdo->exec("DELETE FROM users WHERE role IN ('student', 'faculty', 'hod')");
    $pdo->exec("DELETE FROM students");
    $pdo->exec("DELETE FROM faculty");
    $pdo->exec("DELETE FROM subjects");
    
    // Reset AI
    $tables = ['students', 'faculty', 'subjects', 'student_marks', 'attendance', 'achievements', 'academic_performance', 'assignments', 'assignment_submissions', 'study_materials', 'fee_structures', 'fee_payments', 'faculty_subjects'];
    foreach ($tables as $t) {
        $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = 1");
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "   Cleaned successfully.\n";

} catch (Exception $e) {
    die("FATAL: Cleanup failed: " . $e->getMessage() . "\n");
}

echo "\n[2/11] Defining Subjects...\n";

// B.Sc. IT (Dual), BCA (Dual), BCA, I.M.Sc. IT, M.Sc. IT
$courses = [
    'B.Sc. IT (Dual)' => 3, // years
    'BCA (Dual)' => 3,
    'BCA' => 3,
    'I.M.Sc. IT' => 5, // integrated masters
    'M.Sc. IT' => 2 // masters
];

$subjectDefs = [];
foreach ($courses as $c => $years) {
    for ($y = 1; $y <= $years; $y++) {
        $sem1 = ($y * 2) - 1;
        $sem2 = $y * 2;
        $cShort = preg_replace('/[^A-Z]/', '', $c);
        
        // 4 subjects per semester
        for ($s = 1; $s <= 4; $s++) {
            $subjectDefs[] = [$cShort . $sem1 . '0' . $s, "$c Subject $sem1-$s", $c, $sem1];
            $subjectDefs[] = [$cShort . $sem2 . '0' . $s, "$c Subject $sem2-$s", $c, $sem2];
        }
    }
}

$subjectMap = []; // code => id
$subjectIdsByCourseSem = []; // "Course|Sem" => [ids]
$insertSubject = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, course, semester, credits, max_internal, max_external) VALUES (?, ?, ?, ?, 4, 30.00, 70.00)");
foreach ($subjectDefs as $s) {
    $insertSubject->execute([$s[0], $s[1], $s[2], $s[3]]);
    $id = $pdo->lastInsertId();
    $subjectMap[$s[0]] = $id;
    $subjectIdsByCourseSem[$s[2].'|'.$s[3]][] = $id;
}
echo "   Created " . count($subjectDefs) . " subjects.\n";

echo "\n[3/11] Generating Faculty...\n";

$firstNamesFac = ['Amit','Rahul','Priya','Sneha','Vikram','Neha','Ramesh','Suresh','Anjali','Pooja','Rohit','Karan','Deepak','Sunil','Sanjay','Kavita','Meena','Rakesh','Rajesh','Gaurav'];
$lastNamesFac = ['Patel','Shah','Mehta','Desai','Joshi','Trivedi','Rao','Sharma','Thakkar','Bhatt','Gandhi','Chauhan','Parmar','Dave','Pandya'];

$faculties = [];
$facCount = 45;
for ($i=0; $i<$facCount; $i++) {
    $fn = $firstNamesFac[$i % count($firstNamesFac)];
    $ln = $lastNamesFac[$i % count($lastNamesFac)];
    if ($i == 0) {
        $faculties[] = ['Prof. '.$fn, $ln, 'hod.fcait@glsuniversity.ac.in', '9800000000', 'FCAIT', 'HOD', 'faculty'];
    } else {
        $faculties[] = ['Dr. '.$fn, $ln, strtolower($fn.'.'.$ln).$i.'@glsuniversity.ac.in', '98000000'.sprintf('%02d', $i), 'FCAIT', 'Assistant Professor', 'faculty'];
    }
}

$insertFac = $pdo->prepare("INSERT INTO faculty (faculty_id, first_name, last_name, email, phone, department, designation, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, '2020-01-01', 'Active')");
$insertUser = $pdo->prepare("INSERT INTO users (faculty_id, username, email, password_hash, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");

$facultyIds = [];
$hodId = null;

$pdo->beginTransaction();
$f_idx = 1;
foreach ($faculties as $f) {
    $fac_id = 'FAC-FCAIT-' . str_pad($f_idx, 3, '0', STR_PAD_LEFT);
    $insertFac->execute([$fac_id, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5]]);
    $fid = $pdo->lastInsertId();
    $facultyIds[] = $fid;
    if ($f[5] == 'HOD') $hodId = $fid;
    
    $passwordHash = password_hash('faculty123', PASSWORD_DEFAULT);
    $fullName = $f[0] . ' ' . $f[1];
    $insertUser->execute([$fid, $fac_id, $f[2], $passwordHash, $fullName, $f[6]]);
    $f_idx++;
}
$pdo->commit();
echo "   Created $facCount FCAIT faculty. HOD ID: $hodId\n";


echo "\n[4/11] Generating 500 Students (100 per course)...\n";
$firstNamesStu = ['Aarav','Vivaan','Aditya','Vihaan','Arjun','Reyansh','Sai','Arnav','Dhruv','Kabir','Ritvik','Darsh','Neel','Ishaan','Karan','Anaya','Diya','Myra','Aanya','Aarohi','Anika','Navya','Kiara','Saanvi','Pari'];
$lastNamesStu = ['Patel','Shah','Mehta','Desai','Joshi','Trivedi','Rao','Sharma','Thakkar','Bhatt','Gandhi','Chauhan','Parmar','Dave','Pandya','Raval','Soni','Modi','Nair','Iyer'];

$studentsToInsert = [];
foreach ($courses as $course => $years) {
    // We want exactly 100 per course. We will distribute across all semesters.
    $sems = $years * 2;
    for ($i=0; $i<100; $i++) {
        $sem = ($i % $sems) + 1; 
        $div = ($i % 2 == 0) ? 'A' : 'B';
        $fn = $firstNamesStu[array_rand($firstNamesStu)];
        $ln = $lastNamesStu[array_rand($lastNamesStu)];
        
        $studentsToInsert[] = [
            'first' => $fn, 'last' => $ln, 'gender' => 'Male', 'course' => $course, 'sem' => $sem, 'div' => $div, 'year' => ceil($sem/2)
        ];
    }
}

$insertStudent = $pdo->prepare("INSERT INTO students (enrollment_no, first_name, last_name, gender, dob, email, phone, address, city, course, specialization, semester, division, admission_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insertUserStu = $pdo->prepare("INSERT INTO users (student_id, username, email, password_hash, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");

$studentIds = []; // internal DB ID
$pdo->beginTransaction();
$i = 0;
foreach ($studentsToInsert as $stuData) {
    $course = $stuData['course'];
    $sem = $stuData['sem'];
    $div = $stuData['div'];
    
    // Enrollment formatting: YEAR + COURSE CODE + ID
    // Keep it simple
    $admYear = 2026 - ceil($sem/2);
    if ($admYear > 2026) $admYear = 2026;
    
    // Generate unique enrollment manually to guarantee uniqueness
    $cShort = preg_replace('/[^A-Z]/', '', $course);
    $enrollment = $admYear . $cShort . str_pad($i, 4, '0', STR_PAD_LEFT);
    
    $email = 'stu' . $enrollment . '@gls.edu';
    $phone = '90' . str_pad($i, 8, '0', STR_PAD_LEFT);
    
    $insertStudent->execute([
        $enrollment, $stuData['first'], $stuData['last'], $stuData['gender'], '2005-01-01',
        $email, $phone, 'Test Addr', 'Ahmedabad', $course, 'General',
        $sem, $div, $admYear
    ]);
    $studentId = $pdo->lastInsertId();
    $studentIds[] = ['id' => $studentId, 'course' => $course, 'sem' => $sem, 'div' => $div];
    
    $passwordHash = password_hash('student123', PASSWORD_DEFAULT);
    $fullName = $stuData['first'] . ' ' . $stuData['last'];
    $insertUserStu->execute([$studentId, $enrollment, $email, $passwordHash, $fullName]);
    $i++;
}
$pdo->commit();
echo "   Created 500 Students.\n";

echo "\n[5/11] Assigning Faculty to Subjects...\n";
$insertFacSubj = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id, course, semester, division) VALUES (?, ?, ?, ?, ?)");
$pdo->beginTransaction();
$f_idx = 0;
foreach ($subjectIdsByCourseSem as $key => $subIds) {
    list($c, $sem) = explode('|', $key);
    foreach ($subIds as $sId) {
        // assign Div A and Div B
        $fId = $facultyIds[$f_idx % count($facultyIds)];
        $insertFacSubj->execute([$fId, $sId, $c, $sem, 'A']);
        $insertFacSubj->execute([$fId, $sId, $c, $sem, 'B']);
        $f_idx++;
    }
}
$pdo->commit();
echo "   Assigned subjects to faculty.\n";

echo "\n[6/11] Generating Marks...\n";
$insertMark = $pdo->prepare("INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '2025-2026')");
$pdo->beginTransaction();
$m_cnt = 0;
foreach ($studentIds as $stu) {
    $key = $stu['course'] . '|' . $stu['sem'];
    if (isset($subjectIdsByCourseSem[$key])) {
        foreach ($subjectIdsByCourseSem[$key] as $sId) {
            $int = mt_rand(15, 30);
            $ext = mt_rand(30, 70);
            $tot = $int + $ext;
            $insertMark->execute([$stu['id'], $sId, $int, $ext, $tot, $tot, 'A', $stu['sem']]);
            $m_cnt++;
        }
    }
}
$pdo->commit();
echo "   Created $m_cnt marks records.\n";

echo "\n[7/11] Generating Attendance...\n";
$insertAtt = $pdo->prepare("INSERT INTO attendance (student_id, attendance_date, status) VALUES (?, '2026-10-01', 'Present')");
$pdo->beginTransaction();
foreach ($studentIds as $stu) {
    $insertAtt->execute([$stu['id']]);
}
$pdo->commit();
echo "   Created attendance records.\n";

echo "\n[8/11] Generating Assignments...\n";
$insertAssg = $pdo->prepare("INSERT INTO assignments (title, subject_id, faculty_id, course, semester, division, due_date, max_marks) VALUES ('Test Assg', ?, ?, ?, ?, ?, '2026-12-01', 10)");
$pdo->beginTransaction();
$facultyAssg = $pdo->query("SELECT * FROM faculty_subjects LIMIT 50")->fetchAll();
foreach ($facultyAssg as $fa) {
    $insertAssg->execute([$fa['subject_id'], $fa['faculty_id'], $fa['course'], $fa['semester'], $fa['division']]);
}
$pdo->commit();
echo "   Created some assignments.\n";


echo "\n[9/11] Running Validation...\n";

function check($name, $query, $expected) {
    global $pdo;
    $val = $pdo->query($query)->fetchColumn();
    $status = ($val == $expected || (is_callable($expected) && $expected($val))) ? 'PASS' : "FAIL (Got $val)";
    echo str_pad($name, 40) . $status . "\n";
}

check("Total Students (500)", "SELECT COUNT(*) FROM students", 500);
check("B.Sc. IT (Dual) (100)", "SELECT COUNT(*) FROM students WHERE course='B.Sc. IT (Dual)'", 100);
check("BCA (Dual) (100)", "SELECT COUNT(*) FROM students WHERE course='BCA (Dual)'", 100);
check("BCA (100)", "SELECT COUNT(*) FROM students WHERE course='BCA'", 100);
check("I.M.Sc. IT (100)", "SELECT COUNT(*) FROM students WHERE course='I.M.Sc. IT'", 100);
check("M.Sc. IT (100)", "SELECT COUNT(*) FROM students WHERE course='M.Sc. IT'", 100);

check("Faculty (<50)", "SELECT COUNT(*) FROM faculty", function($v){return $v < 50 && $v > 0;});
check("HOD exactly 1", "SELECT COUNT(*) FROM faculty WHERE designation='HOD'", 1);
check("Duplicate Students", "SELECT COUNT(*) FROM (SELECT enrollment_no FROM students GROUP BY enrollment_no HAVING COUNT(*) > 1) t", 0);
check("Duplicate Emails", "SELECT COUNT(*) FROM (SELECT email FROM students GROUP BY email HAVING COUNT(*) > 1) t", 0);

echo "\n[10/11] ACADEMIC DATA RESET: PASS\n";
