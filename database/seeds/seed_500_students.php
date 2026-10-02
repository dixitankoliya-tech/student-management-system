<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting Complete University Dataset Seed...\n";

try {
    $pdo->beginTransaction();

    // 1. ADD MISSING COURSES (BBA, MCA)
    $new_courses = [
        ['code' => '0351501', 'name' => 'BBA'],
        ['code' => '0851401', 'name' => 'MCA']
    ];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_name = ?");
    $insertCourse = $pdo->prepare("INSERT INTO courses (course_code, course_name, status) VALUES (?, ?, 'Active')");
    
    foreach ($new_courses as $c) {
        $stmt->execute([$c['name']]);
        if ($stmt->fetchColumn() == 0) {
            $insertCourse->execute([$c['code'], $c['name']]);
            echo "Created course: {$c['name']}\n";
        }
    }

    // 2. ADD SUBJECTS FOR BBA AND MCA
    $bba_subjects = [
        ['code' => 'BBA101', 'name' => 'Principles of Management', 'sem' => 1, 'credits' => 3],
        ['code' => 'BBA102', 'name' => 'Marketing Management', 'sem' => 1, 'credits' => 3],
        ['code' => 'BBA201', 'name' => 'Financial Accounting', 'sem' => 2, 'credits' => 4],
        ['code' => 'BBA202', 'name' => 'Business Economics', 'sem' => 2, 'credits' => 3],
        ['code' => 'BBA301', 'name' => 'Human Resource Management', 'sem' => 3, 'credits' => 3],
        ['code' => 'BBA302', 'name' => 'Business Communication', 'sem' => 3, 'credits' => 2],
        ['code' => 'BBA401', 'name' => 'Entrepreneurship', 'sem' => 4, 'credits' => 3],
    ];
    $mca_subjects = [
        ['code' => 'MCA101', 'name' => 'Advanced Programming', 'sem' => 1, 'credits' => 4],
        ['code' => 'MCA102', 'name' => 'Advanced Database Systems', 'sem' => 1, 'credits' => 4],
        ['code' => 'MCA201', 'name' => 'Web Application Development', 'sem' => 2, 'credits' => 3],
        ['code' => 'MCA202', 'name' => 'Cloud Computing', 'sem' => 2, 'credits' => 3],
        ['code' => 'MCA301', 'name' => 'Software Engineering', 'sem' => 3, 'credits' => 3],
        ['code' => 'MCA302', 'name' => 'Data Science', 'sem' => 3, 'credits' => 4],
        ['code' => 'MCA401', 'name' => 'Machine Learning', 'sem' => 4, 'credits' => 4],
        ['code' => 'MCA402', 'name' => 'Cyber Security', 'sem' => 4, 'credits' => 3],
    ];

    $checkSubject = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = ? AND course = ?");
    $insertSubject = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, course, semester, credits, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    
    foreach ($bba_subjects as $s) {
        $checkSubject->execute([$s['code'], 'BBA']);
        if ($checkSubject->fetchColumn() == 0) {
            $insertSubject->execute([$s['code'], $s['name'], 'BBA', $s['sem'], $s['credits']]);
        }
    }
    foreach ($mca_subjects as $s) {
        $checkSubject->execute([$s['code'], 'MCA']);
        if ($checkSubject->fetchColumn() == 0) {
            $insertSubject->execute([$s['code'], $s['name'], 'MCA', $s['sem'], $s['credits']]);
        }
    }
    echo "Subjects added for BBA and MCA.\n";

    // 3. GENERATE FACULTY TO REACH REQUIRED NUMBERS
    $courses_faculty_target = ['BSc IT' => 10, 'BCA' => 10, 'MBA' => 8, 'BBA' => 8, 'MCA' => 10];
    
    $checkFac = $pdo->prepare("SELECT COUNT(*) FROM faculty WHERE department = ?");
    $insertFac = $pdo->prepare("INSERT INTO faculty (faculty_id, first_name, last_name, email, department, designation, status) VALUES (?, ?, ?, ?, ?, 'Assistant Professor', 'Active')");
    $insertUser = $pdo->prepare("INSERT INTO users (faculty_id, username, password_hash, email, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 'faculty', 1)");
    $defaultPass = password_hash('faculty123', PASSWORD_BCRYPT);

    $facSeq = (int)$pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn() + 1000;

    $fnames = ['Amit', 'Sunil', 'Neha', 'Pooja', 'Rahul', 'Vikas', 'Priya', 'Anjali', 'Karan', 'Suresh', 'Anita', 'Ravi', 'Gita', 'Vikram'];
    $lnames = ['Sharma', 'Verma', 'Patel', 'Singh', 'Kumar', 'Joshi', 'Gupta', 'Mehta', 'Rathore', 'Shah', 'Nair', 'Reddy'];

    foreach ($courses_faculty_target as $course => $target) {
        $checkFac->execute([$course]);
        $current = $checkFac->fetchColumn();
        
        while ($current < $target) {
            $facSeq++;
            $facId = "FAC" . $facSeq;
            $fn = $fnames[array_rand($fnames)];
            $ln = $lnames[array_rand($lnames)];
            $em = strtolower($fn . "." . $ln . $facSeq . "@gls.edu");
            
            $insertFac->execute([$facId, $fn, $ln, $em, $course]);
            $db_id = $pdo->lastInsertId();
            $insertUser->execute([$db_id, $facId, $defaultPass, $em, "$fn $ln"]);
            $current++;
        }
    }
    echo "Faculty generated.\n";

    // 4. MAP FACULTY TO SUBJECTS
    $allFac = $pdo->query("SELECT id, department FROM faculty")->fetchAll(PDO::FETCH_ASSOC);
    $allSub = $pdo->query("SELECT id, course FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
    
    $checkFacSub = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE subject_id = ? AND division = 'A'");
    $insertFacSub = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id, course, academic_year, semester, division) VALUES (?, ?, ?, '2026-2027', 1, 'A')");
    
    foreach ($allSub as $sub) {
        $checkFacSub->execute([$sub['id']]);
        if ($checkFacSub->fetchColumn() == 0) {
            $valid_facs = array_filter($allFac, fn($f) => $f['department'] === $sub['course']);
            if (!empty($valid_facs)) {
                $chosen = $valid_facs[array_rand($valid_facs)];
                try {
                    $insertFacSub->execute([$chosen['id'], $sub['id'], $sub['course']]);
                } catch (Exception $e) {}
            }
        }
    }
    echo "Faculty Subjects mapped.\n";

    // 5. GENERATE STUDENTS
    $courses_info = [
        'BSc IT' => ['code' => '0161901', 'years' => [2026=>34, 2025=>33, 2024=>33]],
        'BCA' => ['code' => '0251701', 'years' => [2026=>34, 2025=>33, 2024=>33]],
        'MBA' => ['code' => '0751301', 'years' => [2026=>34, 2025=>33, 2024=>33]],
        'BBA' => ['code' => '0351501', 'years' => [2026=>34, 2025=>33, 2024=>33]],
        'MCA' => ['code' => '0851401', 'years' => [2026=>34, 2025=>33, 2024=>33]],
    ];

    $checkStu = $pdo->prepare("SELECT COUNT(*) FROM students WHERE course = ? AND admission_year = ?");
    $getMaxSeq = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(enrollment_no, 12, 4) AS UNSIGNED)) FROM students WHERE course = ? AND admission_year = ?");
    $insertStu = $pdo->prepare("INSERT INTO students (enrollment_no, first_name, last_name, email, phone, gender, course, semester, admission_year, division) VALUES (?, ?, ?, ?, ?, 'Male', ?, ?, ?, 'A')");
    $insertUserStu = $pdo->prepare("INSERT INTO users (student_id, username, password_hash, email, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");
    $stuPass = password_hash('password123', PASSWORD_BCRYPT);
    
    $totalAdded = 0;
    foreach ($courses_info as $cname => $cinfo) {
        foreach ($cinfo['years'] as $yr => $target) {
            $checkStu->execute([$cname, $yr]);
            $current = (int)$checkStu->fetchColumn();
            
            $getMaxSeq->execute([$cname, $yr]);
            $maxSeq = (int)$getMaxSeq->fetchColumn();
            if ($maxSeq == 0) $maxSeq = 0;
            
            $sem = ($yr == 2026) ? 1 : (($yr == 2025) ? 3 : 5);
            
            while ($current < $target) {
                $maxSeq++;
                $seqStr = str_pad($maxSeq, 4, '0', STR_PAD_LEFT);
                $enroll = $yr . $cinfo['code'] . $seqStr;
                
                $fn = $fnames[array_rand($fnames)];
                $ln = $lnames[array_rand($lnames)];
                $em = strtolower($fn . "." . $ln . $enroll . "@student.gls.edu");
                $ph = "9" . rand(100000000, 999999999);
                
                $insertStu->execute([$enroll, $fn, $ln, $em, $ph, $cname, $sem, $yr]);
                $db_id = $pdo->lastInsertId();
                $insertUserStu->execute([$db_id, $enroll, $stuPass, $em, "$fn $ln"]);
                
                $current++;
                $totalAdded++;
            }
        }
    }
    echo "Students generated. Added: $totalAdded.\n";

    // Validate 500
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    echo "Total Students in DB: $totalStudents\n";
    if ($totalStudents != 500) {
        throw new Exception("Validation failed: Total students is $totalStudents, expected 500.");
    }

    $pdo->commit();
    echo "Seed completed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}
