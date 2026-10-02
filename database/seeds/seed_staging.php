<?php
require_once __DIR__ . '/../config/database.php';

// Configurable target
$TARGET_STUDENTS = isset($argv[1]) ? (int)$argv[1] : 1000;
if (!in_array($TARGET_STUDENTS, [500, 1000, 5000, 10000])) {
    $TARGET_STUDENTS = 1000;
}

echo "Starting Staging Seed for $TARGET_STUDENTS students...\n";

try {
    $pdo->beginTransaction();

    $fnames = ['Amit', 'Sunil', 'Neha', 'Pooja', 'Rahul', 'Vikas', 'Priya', 'Anjali', 'Karan', 'Suresh', 'Anita', 'Ravi', 'Gita', 'Vikram'];
    $lnames = ['Sharma', 'Verma', 'Patel', 'Singh', 'Kumar', 'Joshi', 'Gupta', 'Mehta', 'Rathore', 'Shah', 'Nair', 'Reddy'];
    $courses_info = [
        'BSc IT' => ['code' => '0161901'],
        'BCA' => ['code' => '0251701'],
        'MBA' => ['code' => '0751301'],
        'BBA' => ['code' => '0351501'],
        'MCA' => ['code' => '0851401'],
    ];

    $checkStu = $pdo->prepare("SELECT COUNT(*) FROM students");
    $currentTotal = (int)$checkStu->fetchColumn();

    if ($currentTotal >= $TARGET_STUDENTS) {
        echo "Database already has $currentTotal students. Target $TARGET_STUDENTS satisfied.\n";
        $pdo->rollBack();
        exit;
    }

    $toAdd = $TARGET_STUDENTS - $currentTotal;
    $perCourseYear = ceil($toAdd / (5 * 3)); // 5 courses, 3 years (2024, 2025, 2026)

    $getMaxSeq = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(enrollment_no, 12, 4) AS UNSIGNED)) FROM students WHERE course = ? AND admission_year = ?");
    $insertStu = $pdo->prepare("INSERT INTO students (enrollment_no, first_name, last_name, email, phone, gender, course, semester, admission_year, division) VALUES (?, ?, ?, ?, ?, 'Male', ?, ?, ?, 'A')");
    $insertUserStu = $pdo->prepare("INSERT INTO users (student_id, username, password_hash, email, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");
    $stuPass = password_hash('password123', PASSWORD_BCRYPT);
    
    $totalAdded = 0;
    
    foreach ($courses_info as $cname => $cinfo) {
        foreach ([2026, 2025, 2024] as $yr) {
            $getMaxSeq->execute([$cname, $yr]);
            $maxSeq = (int)$getMaxSeq->fetchColumn();
            if ($maxSeq == 0) $maxSeq = 0;
            
            $sem = ($yr == 2026) ? 1 : (($yr == 2025) ? 3 : 5);
            
            for ($i=0; $i<$perCourseYear; $i++) {
                if ($totalAdded >= $toAdd) break 3; // Break all loops if target met
                
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
                
                $totalAdded++;
            }
        }
    }
    
    $pdo->commit();
    echo "Staging Seed completed. Added: $totalAdded students.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}
