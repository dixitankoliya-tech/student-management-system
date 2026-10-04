<?php
if (php_sapi_name() !== 'cli') die("Must run from CLI\n");

require_once __DIR__ . '/../config/database.php';
set_time_limit(0);

echo "========================================\n";
echo "FCAIT TOTAL RESET & RESEED\n";
echo "========================================\n\n";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $tablesToTruncate = [
        'academic_calendar', 'academic_calendars', 'academic_performance', 'achievements',
        'activity_logs', 'announcements', 'assignment_submissions', 'assignments', 'attendance',
        'audit_logs', 'courses', 'departments', 'divisions', 'examinations', 'faculty',
        'faculty_subjects', 'fee_payments', 'fee_structures', 'marks', 'notifications',
        'profile_change_requests', 'promotion_history', 'student_academic_enrollments',
        'student_fees', 'student_marks', 'student_subject_enrollments', 'students',
        'study_materials', 'subjects', 'timetables', 'sessions'
    ];
    
    echo "[1/8] Truncating all academic tables...\n";
    foreach ($tablesToTruncate as $t) {
        // Suppress errors for tables that might not exist in some versions of the schema
        try {
            $pdo->exec("TRUNCATE TABLE `$t`");
        } catch (Exception $e) {}
    }
    
    // Clear non-admin users
    $pdo->exec("DELETE FROM users WHERE role != 'admin'");
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "      Done.\n\n";
    
    echo "[2/8] Creating Department & Courses...\n";
    $pdo->exec("INSERT INTO departments (id, department_code, department_name, status) VALUES (1, 'FCAIT', 'Faculty of Computer Applications & Information Technology', 'Active')");
    
    $coursesData = [
        ['B.Sc. IT (Dual)', 'B.Sc. Information Technology (Dual)', 3, 6, 'UG'],
        ['BCA (Dual)', 'Bachelor of Computer Applications (Dual)', 3, 6, 'UG'],
        ['BCA', 'Bachelor of Computer Applications', 3, 6, 'UG'],
        ['I.M.Sc. IT', 'Integrated M.Sc. IT', 5, 10, 'PG'],
        ['M.Sc. IT', 'Master of Science in IT', 2, 4, 'PG']
    ];
    
    $insertCourse = $pdo->prepare("INSERT INTO courses (course_code, course_name, duration_years, total_semesters, degree_type, department_id, admission_status, status) VALUES (?, ?, ?, ?, ?, 1, 'Open', 'Active')");
    
    foreach ($coursesData as $c) {
        $insertCourse->execute($c);
    }
    echo "      Created FCAIT and 5 courses.\n\n";
    
    echo "[3/8] Creating Subjects...\n";
    $subjectDefs = [];
    $insertSubject = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, course, semester, credits, max_internal, max_external, subject_type, department, status) VALUES (?, ?, ?, ?, 4, 30.00, 70.00, 'Theory', 'FCAIT', 'Active')");
    
    foreach ($coursesData as $c) {
        $cName = $c[0];
        $sems = $c[3];
        $cShort = preg_replace('/[^A-Z]/', '', $cName);
        for ($sem = 1; $sem <= $sems; $sem++) {
            for ($s = 1; $s <= 4; $s++) {
                $code = $cShort . sprintf('%02d', $sem) . '0' . $s;
                $name = "$cName Subject $sem-$s";
                $insertSubject->execute([$code, $name, $cName, $sem]);
            }
        }
    }
    echo "      Created subjects for all semesters.\n\n";
    
    echo "[4/8] Generating 45 FCAIT Faculty (Including 1 HOD)...\n";
    $facCount = 45;
    $insertFac = $pdo->prepare("INSERT INTO faculty (faculty_id, first_name, last_name, email, phone, department, designation, joining_date, status) VALUES (?, ?, ?, ?, ?, 'FCAIT', ?, '2020-01-01', 'Active')");
    $insertUser = $pdo->prepare("INSERT INTO users (faculty_id, username, email, password_hash, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'faculty', 1, 1)");
    
    $pdo->beginTransaction();
    $facultyIds = [];
    for ($i=1; $i<=$facCount; $i++) {
        $fac_id = 'FAC-FCAIT-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        $fn = "FacFirstName" . $i;
        $ln = "FacLastName" . $i;
        $email = "fac{$i}.fcait@glsuniversity.ac.in";
        $phone = "98" . str_pad($i, 8, '0', STR_PAD_LEFT);
        $designation = ($i == 1) ? 'HOD' : 'Assistant Professor';
        
        $insertFac->execute([$fac_id, $fn, $ln, $email, $phone, $designation]);
        $fid = $pdo->lastInsertId();
        $facultyIds[] = $fid;
        
        $passwordHash = password_hash('faculty123', PASSWORD_DEFAULT);
        $fullName = "$fn $ln";
        $insertUser->execute([$fid, $fac_id, $email, $passwordHash, $fullName]);
    }
    $pdo->commit();
    echo "      Created $facCount faculty.\n\n";
    
    echo "[5/8] Generating 500 Students (100 per course)...\n";
    $insertStudent = $pdo->prepare("INSERT INTO students (enrollment_no, first_name, last_name, gender, dob, email, phone, address, city, course, specialization, semester, division, admission_year) VALUES (?, ?, ?, ?, '2005-01-01', ?, ?, 'Test Addr', 'Ahmedabad', ?, 'General', ?, ?, ?)");
    $insertUserStu = $pdo->prepare("INSERT INTO users (student_id, username, email, password_hash, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");
    $insertAcadEnr = $pdo->prepare("INSERT INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status) VALUES (?, ?, '2025-2026', ?, ?, 'Active')");
    
    $pdo->beginTransaction();
    $studentIds = []; // [id, course, sem]
    $i = 0;
    foreach ($coursesData as $c) {
        $cName = $c[0];
        $sems = $c[3];
        $cShort = preg_replace('/[^A-Z]/', '', $cName);
        for ($j=1; $j<=100; $j++) {
            $i++;
            $sem = (($j - 1) % $sems) + 1;
            $div = ($j % 2 == 0) ? 'B' : 'A';
            $fn = "StuFirst" . $i;
            $ln = "StuLast" . $i;
            
            $admYear = 2026 - ceil($sem/2);
            if ($admYear > 2026) $admYear = 2026;
            
            $enrollment = $admYear . $cShort . str_pad($i, 4, '0', STR_PAD_LEFT);
            $email = "stu{$i}@gls.edu";
            $phone = "90" . str_pad($i, 8, '0', STR_PAD_LEFT);
            
            $insertStudent->execute([$enrollment, $fn, $ln, 'Male', $email, $phone, $cName, $sem, $div, $admYear]);
            $stuId = $pdo->lastInsertId();
            $studentIds[] = ['id' => $stuId, 'course' => $cName, 'sem' => $sem];
            
            $passwordHash = password_hash('student123', PASSWORD_DEFAULT);
            $insertUserStu->execute([$stuId, $enrollment, $email, $passwordHash, "$fn $ln"]);
            
            $insertAcadEnr->execute([$stuId, $cName, $sem, $div]);
            
            if ($i % 50 == 0) echo "      Generated $i students...\n";
        }
    }
    $pdo->commit();
    echo "      Created 500 students.\n\n";
    
    echo "[6/8] Assigning Faculty to Subjects & Student Enrollments...\n";
    $insertFacSubj = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id, course, semester, division) VALUES (?, ?, ?, ?, ?)");
    $insertStuSubj = $pdo->prepare("INSERT INTO student_subject_enrollments (student_id, faculty_subject_id, status) VALUES (?, ?, 'Enrolled')");
    
    $subjects = $pdo->query("SELECT id, course, semester FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
    $fac_idx = 0;
    $pdo->beginTransaction();
    foreach ($subjects as $s) {
        $sId = $s['id'];
        $c = $s['course'];
        $sem = $s['semester'];
        
        $fIdA = $facultyIds[$fac_idx % count($facultyIds)];
        $fac_idx++;
        $insertFacSubj->execute([$fIdA, $sId, $c, $sem, 'A']);
        $fsIdA = $pdo->lastInsertId();
        
        $fIdB = $facultyIds[$fac_idx % count($facultyIds)];
        $fac_idx++;
        $insertFacSubj->execute([$fIdB, $sId, $c, $sem, 'B']);
        $fsIdB = $pdo->lastInsertId();
        
        // Enroll students in these subjects based on division
        $stuInSem = $pdo->query("SELECT id, division FROM students WHERE course='$c' AND semester=$sem")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($stuInSem as $stu) {
            $fsId = ($stu['division'] == 'A') ? $fsIdA : $fsIdB;
            $insertStuSubj->execute([$stu['id'], $fsId]);
        }
    }
    $pdo->commit();
    echo "      Mappings completed.\n\n";
    
    echo "[7/8] Generating Marks Data...\n";
    // For marks, we need exam_id. We'll just create a dummy exam.
    $pdo->exec("INSERT INTO examinations (exam_name, course, semester, academic_year, status) VALUES ('MidSem', 'BCA', 1, '2025-2026', 'Published')");
    $examId = $pdo->lastInsertId();
    
    $insertMark = $pdo->prepare("INSERT INTO marks (exam_id, student_id, subject_id, faculty_id, marks_obtained, max_marks, status) VALUES (?, ?, ?, ?, ?, 70, 'Submitted')");
    $pdo->beginTransaction();
    $fsMap = $pdo->query("SELECT id, faculty_id, subject_id FROM faculty_subjects")->fetchAll(PDO::FETCH_ASSOC);
    $fsLookup = [];
    foreach($fsMap as $fsm) { $fsLookup[$fsm['id']] = $fsm; }
    
    $enrolls = $pdo->query("SELECT student_id, faculty_subject_id FROM student_subject_enrollments")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($enrolls as $en) {
        $fs = $fsLookup[$en['faculty_subject_id']];
        $insertMark->execute([$examId, $en['student_id'], $fs['subject_id'], $fs['faculty_id'], mt_rand(20, 65)]);
    }
    $pdo->commit();
    echo "      Generated marks.\n\n";

    echo "[8/8] Validating database state...\n";
    
    $dept = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $crs = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $stu = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $fac = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
    $hod = $pdo->query("SELECT COUNT(*) FROM faculty WHERE designation = 'HOD'")->fetchColumn();
    
    echo "DEPARTMENTS: $dept\n";
    echo "COURSES: $crs\n";
    echo "STUDENTS: $stu\n";
    echo "FACULTY: $fac\n";
    echo "HOD: $hod\n\n";
    
    echo "Students by course:\n";
    foreach ($coursesData as $c) {
        $cName = $c[0];
        $cnt = $pdo->query("SELECT COUNT(*) FROM students WHERE course='$cName'")->fetchColumn();
        echo "$cName: $cnt\n";
    }
    
    echo "\nOrphan checks:\n";
    $orpStu = $pdo->query("SELECT COUNT(*) FROM student_academic_enrollments WHERE student_id NOT IN (SELECT id FROM students)")->fetchColumn();
    echo "student_academic_enrollments orphans: $orpStu\n";
    $orpM = $pdo->query("SELECT COUNT(*) FROM marks WHERE student_id NOT IN (SELECT id FROM students)")->fetchColumn();
    echo "marks student orphans: $orpM\n";

} catch (Exception $e) {
    die("FATAL ERROR: " . $e->getMessage() . "\n");
}
