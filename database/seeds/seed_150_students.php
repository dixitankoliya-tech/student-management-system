<?php
/**
 * ==========================================================
 * SMS 2.0: COMPLETE DEMO DATA SEED — 150 STUDENTS
 * ==========================================================
 * 
 * This script:
 * 1. Removes ALL existing student-related demo data
 * 2. Creates subjects for BSc IT, BCA, MBA (6 per year per course)
 * 3. Creates exactly 150 students with sequential enrollment
 * 4. Creates 150 student user accounts (hashed passwords)
 * 5. Creates marks for every student-subject combination
 * 6. Creates attendance records
 * 7. Creates achievements for a subset of students
 * 8. Populates academic_performance
 * 
 * SAFETY: Protected against accidental re-execution.
 * Does NOT touch the admin account or system settings.
 */

// ===== SAFETY CHECK =====
if (php_sapi_name() !== 'cli') {
    die("ERROR: This script must be run from the command line.\n");
}

echo "========================================\n";
echo "SMS 2.0 DEMO DATA SEED\n";
echo "========================================\n\n";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/enrollment_helper.php';

// ===== CONFIRM =====
echo "WARNING: This will DELETE all existing student data and create 150 new demo students.\n";
echo "The admin account (GLS_ADMIN) will NOT be affected.\n";
echo "Type 'YES' to confirm: ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
if ($line !== 'YES') {
    die("Aborted.\n");
}
fclose($handle);

echo "\n[1/9] Cleaning existing student data...\n";

try {
    // Disable FK checks for clean deletion
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Delete in dependency order
    $pdo->exec("DELETE FROM activity_logs WHERE student_id IS NOT NULL");
    $pdo->exec("DELETE FROM academic_performance");
    $pdo->exec("DELETE FROM achievements");
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM student_marks");
    $pdo->exec("DELETE FROM users WHERE role = 'student'");
    $pdo->exec("DELETE FROM students");
    $pdo->exec("DELETE FROM subjects");
    
    // Reset auto-increment
    $pdo->exec("ALTER TABLE students AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE users AUTO_INCREMENT = 2"); // keep admin at 1
    $pdo->exec("ALTER TABLE subjects AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE student_marks AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE attendance AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE achievements AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE academic_performance AUTO_INCREMENT = 1");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "   Cleaned.\n";
} catch (Exception $e) {
    die("FATAL: Cleanup failed: " . $e->getMessage() . "\n");
}

// ===== ADD course COLUMN TO subjects IF MISSING =====
try {
    $cols = $pdo->query("SHOW COLUMNS FROM subjects LIKE 'course'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE subjects ADD COLUMN course VARCHAR(50) NULL AFTER subject_name");
        echo "   Added 'course' column to subjects table.\n";
    }
} catch (Exception $e) {
    echo "   Note: course column check: " . $e->getMessage() . "\n";
}

// ===================================================================
// SUBJECT DEFINITIONS — 6 subjects per year per course = 54 total
// ===================================================================
echo "\n[2/9] Creating subjects...\n";

$subjectDefs = [
    // BSc IT FY (Sem 1-2)
    ['BSIT101', 'Programming Fundamentals', 'BSc IT', 1],
    ['BSIT102', 'Computer Organization', 'BSc IT', 1],
    ['BSIT103', 'Database Fundamentals', 'BSc IT', 1],
    ['BSIT104', 'Web Development', 'BSc IT', 2],
    ['BSIT105', 'Mathematics for IT', 'BSc IT', 2],
    ['BSIT106', 'Communication Skills', 'BSc IT', 2],
    // BSc IT SY (Sem 3-4)
    ['BSIT201', 'Data Structures', 'BSc IT', 3],
    ['BSIT202', 'Object Oriented Programming', 'BSc IT', 3],
    ['BSIT203', 'Operating Systems', 'BSc IT', 3],
    ['BSIT204', 'Computer Networks', 'BSc IT', 4],
    ['BSIT205', 'Software Engineering', 'BSc IT', 4],
    ['BSIT206', 'Statistics for IT', 'BSc IT', 4],
    // BSc IT TY (Sem 5-6)
    ['BSIT301', 'Artificial Intelligence', 'BSc IT', 5],
    ['BSIT302', 'Machine Learning', 'BSc IT', 5],
    ['BSIT303', 'Cloud Computing', 'BSc IT', 5],
    ['BSIT304', 'Cyber Security', 'BSc IT', 6],
    ['BSIT305', 'Advanced Web Development', 'BSc IT', 6],
    ['BSIT306', 'Project Development', 'BSc IT', 6],
    
    // BCA FY (Sem 1-2)
    ['BCA101', 'Programming in C', 'BCA', 1],
    ['BCA102', 'Computer Fundamentals', 'BCA', 1],
    ['BCA103', 'Database Management Systems', 'BCA', 1],
    ['BCA104', 'Web Technologies', 'BCA', 2],
    ['BCA105', 'Business Communication', 'BCA', 2],
    ['BCA106', 'Mathematics for Computing', 'BCA', 2],
    // BCA SY (Sem 3-4)
    ['BCA201', 'Data Structures', 'BCA', 3],
    ['BCA202', 'Object Oriented Programming', 'BCA', 3],
    ['BCA203', 'Operating Systems', 'BCA', 3],
    ['BCA204', 'Computer Networks', 'BCA', 4],
    ['BCA205', 'Software Engineering', 'BCA', 4],
    ['BCA206', 'Web Application Development', 'BCA', 4],
    // BCA TY (Sem 5-6)
    ['BCA301', 'Java Programming', 'BCA', 5],
    ['BCA302', 'Python Programming', 'BCA', 5],
    ['BCA303', 'Cloud Computing', 'BCA', 5],
    ['BCA304', 'Cyber Security', 'BCA', 6],
    ['BCA305', 'Mobile Application Development', 'BCA', 6],
    ['BCA306', 'Major Project', 'BCA', 6],
    
    // MBA FY (Sem 1-2)
    ['MBA101', 'Principles of Management', 'MBA', 1],
    ['MBA102', 'Managerial Economics', 'MBA', 1],
    ['MBA103', 'Financial Accounting', 'MBA', 1],
    ['MBA104', 'Business Communication', 'MBA', 2],
    ['MBA105', 'Marketing Management', 'MBA', 2],
    ['MBA106', 'Organizational Behaviour', 'MBA', 2],
    // MBA SY (Sem 3-4)
    ['MBA201', 'Human Resource Management', 'MBA', 3],
    ['MBA202', 'Financial Management', 'MBA', 3],
    ['MBA203', 'Operations Management', 'MBA', 3],
    ['MBA204', 'Business Research Methods', 'MBA', 4],
    ['MBA205', 'Strategic Management', 'MBA', 4],
    ['MBA206', 'Management Information Systems', 'MBA', 4],
    // MBA TY (Sem 5-6)
    ['MBA301', 'Business Analytics', 'MBA', 5],
    ['MBA302', 'Digital Marketing', 'MBA', 5],
    ['MBA303', 'Entrepreneurship', 'MBA', 5],
    ['MBA304', 'Project Management', 'MBA', 6],
    ['MBA305', 'Corporate Strategy', 'MBA', 6],
    ['MBA306', 'Industry Project', 'MBA', 6],
];

$subjectMap = []; // subject_code => subject_id
$insertSubject = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, course, semester, credits, max_internal, max_external) VALUES (?, ?, ?, ?, 4, 30.00, 70.00)");
foreach ($subjectDefs as $s) {
    $insertSubject->execute([$s[0], $s[1], $s[2], $s[3]]);
    $subjectMap[$s[0]] = $pdo->lastInsertId();
}
echo "   Created " . count($subjectDefs) . " subjects.\n";

// ===================================================================
// STUDENT NAME POOL — 150 unique fictional Indian names
// ===================================================================
echo "\n[3/9] Generating 150 student records...\n";

$firstNamesMale = [
    'Aarav','Vivaan','Aditya','Vihaan','Arjun','Reyansh','Sai','Arnav','Dhruv','Kabir',
    'Ritvik','Darsh','Neel','Ishaan','Karan','Yash','Dev','Rohan','Parth','Mihir',
    'Rudra','Pranav','Harsh','Sahil','Ansh','Kunal','Rishi','Siddharth','Nakul','Tejas',
    'Aman','Jay','Vatsal','Chirag','Manan','Dhrumil','Het','Jeet','Krish','Laksh',
    'Nirav','Ojas','Pranjal','Rajat','Tanay','Uday','Viraj','Wrik','Atharv','Bhavin',
    'Chetan','Devansh','Eshaan','Farhan','Gaurav','Hemang','Ishan','Jai','Kartik','Laxman',
    'Manav','Nikhil','Om','Prateek','Rahul','Shaurya','Tushar','Utkarsh','Ved','Yug'
];

$firstNamesFemale = [
    'Anaya','Diya','Myra','Aanya','Aarohi','Anika','Navya','Kiara','Saanvi','Pari',
    'Riya','Shreya','Tanvi','Isha','Meera','Nisha','Pooja','Kavya','Priya','Sanya',
    'Tara','Aditi','Bhavna','Charvi','Dhara','Ekta','Falguni','Gauri','Hetal','Ira',
    'Janvi','Krisha','Lavanya','Mahi','Neha','Oviya','Payal','Radhika','Sakshi','Trisha',
    'Uma','Vaani','Wani','Yashvi','Zara','Aishwarya','Bhumika','Chetna','Devika','Esha',
    'Falak','Gargi','Harini','Iti','Jiya','Keya','Lata','Mansi','Nandini','Ojal',
    'Pallavi','Rhea','Suhani','Tanya','Urvi','Veda','Wisha','Yamini','Zeel','Swara'
];

$lastNames = [
    'Patel','Shah','Mehta','Desai','Joshi','Trivedi','Rao','Sharma','Thakkar','Bhatt',
    'Gandhi','Chauhan','Parmar','Dave','Pandya','Raval','Soni','Modi','Nair','Iyer',
    'Malhotra','Kapoor','Verma','Gupta','Singh','Kumar','Reddy','Pillai','Menon','Hegde',
    'Kulkarni','Chavan','Patil','More','Deshpande','Jain','Agarwal','Bhatia','Chopra','Taneja',
    'Saxena','Mishra','Tiwari','Dubey','Yadav','Rathod','Solanki','Gohel','Rajput','Parekh'
];

// Generate 150 unique names
$names = [];
$usedNames = [];
$genderPool = [];
$maleIdx = 0;
$femaleIdx = 0;
$lastIdx = 0;

for ($i = 0; $i < 150; $i++) {
    // Alternate male/female roughly 50/50
    $isMale = ($i % 2 === 0);
    $attempts = 0;
    do {
        if ($isMale) {
            $fn = $firstNamesMale[$maleIdx % count($firstNamesMale)];
            $maleIdx++;
        } else {
            $fn = $firstNamesFemale[$femaleIdx % count($firstNamesFemale)];
            $femaleIdx++;
        }
        $ln = $lastNames[$lastIdx % count($lastNames)];
        $lastIdx++;
        $fullName = $fn . ' ' . $ln;
        $attempts++;
        if ($attempts > 300) break; // safety
    } while (isset($usedNames[$fullName]));
    
    $usedNames[$fullName] = true;
    $names[] = ['first' => $fn, 'last' => $ln, 'gender' => $isMale ? 'Male' : 'Female'];
    $genderPool[] = $isMale ? 'Male' : 'Female';
}

// Sort names alphabetically first, to satisfy alphabetical ordering within each group.
// But wait, it's easier to just pick the names, assign them to buckets, sort buckets, then assign sequence.
$buckets = [
    'BSc IT' => ['FY' => 20, 'SY' => 20, 'TY' => 10],
    'BCA'    => ['FY' => 20, 'SY' => 20, 'TY' => 10],
    'MBA'    => ['FY' => 20, 'SY' => 20, 'TY' => 10],
];

$studentsToInsert = [];
$nameIdx = 0;

foreach ($buckets as $course => $years) {
    foreach ($years as $year => $count) {
        $groupStudents = [];
        for ($i = 0; $i < $count; $i++) {
            $groupStudents[] = $names[$nameIdx++];
        }
        
        // Sort group alphabetically by first name, then last name
        usort($groupStudents, function($a, $b) {
            $cmp = strcmp($a['first'], $b['first']);
            if ($cmp === 0) {
                return strcmp($a['last'], $b['last']);
            }
            return $cmp;
        });
        
        $semesterMap = ['FY' => 1, 'SY' => 3, 'TY' => 5];
        $sem = $semesterMap[$year];
        
        foreach ($groupStudents as $sIdx => $stu) {
            $studentsToInsert[] = [
                'name' => $stu,
                'course' => $course,
                'year' => $year,
                'sem' => $sem,
                'division' => ($sIdx % 2 === 0) ? 'A' : 'B'
            ];
        }
    }
}

// Generate 150 unique dummy Aadhaar-style values
$aadhaars = [];
mt_srand(42); // deterministic for reproducibility
for ($i = 0; $i < 150; $i++) {
    do {
        $val = '';
        for ($d = 0; $d < 12; $d++) {
            $val .= mt_rand(0, 9);
        }
    } while (in_array($val, $aadhaars));
    $aadhaars[] = $val;
}

// Generate 150 unique DOBs (1998-2005 range)
function generateDob($idx) {
    $year = 1998 + ($idx % 8);
    $month = 1 + ($idx % 12);
    $day = 1 + ($idx % 28);
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

// Gujarat cities for address
$cities = ['Ahmedabad','Gandhinagar','Vadodara','Surat','Rajkot','Bhavnagar','Jamnagar','Junagadh','Anand','Nadiad','Mehsana','Bharuch','Navsari','Valsad','Morbi'];

$addresses = ['Satellite Road','SG Highway','CG Road','Navrangpura','Vastrapur','Maninagar','Gota','Bopal','Chandkheda','Nikol','Naroda','Thaltej','Paldi','Shahibaug','Ambawadi'];

// ===================================================================
// INSERT STUDENTS + USERS (in a transaction)
// ===================================================================
$pdo->beginTransaction();
try {
    $insertStudent = $pdo->prepare("INSERT INTO students (enrollment_no, first_name, last_name, gender, dob, email, phone, address, city, course, specialization, semester, division, admission_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $insertUser = $pdo->prepare("INSERT INTO users (student_id, username, email, password_hash, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 'student', 1, 1)");
    
    $studentIds = []; // idx => student_id
    $enrollments = []; // idx => enrollment_no
    
    for ($i = 0; $i < 150; $i++) {
        $stuData = $studentsToInsert[$i];
        $course = $stuData['course'];
        $academicYear = $stuData['year'];
        $semester = $stuData['sem'];
        $division = $stuData['division'];
        $n = $stuData['name'];
        
        $enrollment = generateNextEnrollmentNumber($pdo, $course, $academicYear);
        
        $email = 'student' . str_pad($i + 1, 3, '0', STR_PAD_LEFT) . '@sms-demo.local';
        $phone = '90' . str_pad(10000000 + $i * 67, 8, '0', STR_PAD_LEFT);
        $dob = generateDob($i);
        $city = $cities[$i % count($cities)];
        $address = ($i + 1) . ', ' . $addresses[$i % count($addresses)] . ', ' . $city;
        
        // Specialization
        $specs = [
            'BSc IT' => 'AI/ML',
            'BCA' => 'General',
            'MBA' => 'Finance & Marketing'
        ];
        $spec = $specs[$course];
        
        $prefixData = getEnrollmentPrefixData($course, $academicYear);
        $admYear = (int)$prefixData[0];
        
        $insertStudent->execute([
            $enrollment, $n['first'], $n['last'], $n['gender'], $dob,
            $email, $phone, $address, $city, $course, $spec,
            $semester, $division, $admYear
        ]);
        $studentId = $pdo->lastInsertId();
        $studentIds[$i] = $studentId;
        $enrollments[$i] = $enrollment;
        
        // Create user account — password is hashed Aadhaar
        $passwordHash = password_hash($aadhaars[$i], PASSWORD_DEFAULT);
        $fullName = $n['first'] . ' ' . $n['last'];
        $insertUser->execute([$studentId, $enrollment, $email, $passwordHash, $fullName]);
    }
    
    $pdo->commit();
    echo "   Created 150 students and 150 user accounts.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("FATAL: Student creation failed: " . $e->getMessage() . "\n");
}

// ===================================================================
// MARKS
// ===================================================================
echo "\n[4/9] Creating marks...\n";

// Build subject lookup: course+semester => [subject_ids]
$subjectsByCourseAndSem = [];
foreach ($subjectDefs as $s) {
    $code = $s[0];
    $course = $s[2];
    $sem = $s[3];
    $key = $course . '|' . $sem;
    $subjectsByCourseAndSem[$key][] = $subjectMap[$code];
}

// For each student, assign marks for their current semester's subjects (both semesters in their year)
$insertMark = $pdo->prepare("INSERT INTO student_marks (student_id, subject_id, internal_marks, external_marks, total_marks, percentage, grade, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '2024-2025')");

$totalMarks = 0;
mt_srand(123); // deterministic

$pdo->beginTransaction();
try {
    for ($i = 0; $i < 150; $i++) {
        $stuData = $studentsToInsert[$i];
        $course = $stuData['course'];
        $baseSem = $stuData['sem'];
        $year = $stuData['year'];
        $div = $stuData['division'];
        
        // Students get marks for both semesters of their year
        $semesters = [$baseSem, $baseSem + 1];
        
        foreach ($semesters as $sem) {
            $key = $course . '|' . $sem;
            if (!isset($subjectsByCourseAndSem[$key])) continue;
            
            foreach ($subjectsByCourseAndSem[$key] as $subjectId) {
                // Realistic distribution: ~15% high, ~60% average, ~25% lower
                $band = mt_rand(1, 100);
                if ($band <= 15) {
                    // High performer: internal 25-30, external 56-70
                    $internal = mt_rand(25, 30);
                    $external = mt_rand(56, 70);
                } elseif ($band <= 75) {
                    // Average: internal 18-26, external 40-58
                    $internal = mt_rand(18, 26);
                    $external = mt_rand(40, 58);
                } else {
                    // Lower: internal 12-20, external 28-45
                    $internal = mt_rand(12, 20);
                    $external = mt_rand(28, 45);
                }
                $total = $internal + $external;
                $pct = round($total / 100 * 100, 2); // max=100
                
                // Grade calculation matching academic_helper.php
                if ($pct >= 90) $grade = 'A+';
                elseif ($pct >= 80) $grade = 'A';
                elseif ($pct >= 70) $grade = 'B+';
                elseif ($pct >= 60) $grade = 'B';
                elseif ($pct >= 50) $grade = 'C';
                elseif ($pct >= 40) $grade = 'D';
                else $grade = 'F';
                
                $insertMark->execute([
                    $studentIds[$i], $subjectId,
                    $internal, $external, $total, $pct, $grade,
                    $sem
                ]);
                $totalMarks++;
            }
        }
    }
    $pdo->commit();
    echo "   Created $totalMarks marks records.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("FATAL: Marks creation failed: " . $e->getMessage() . "\n");
}

// ===================================================================
// ATTENDANCE
// ===================================================================
echo "\n[5/9] Creating attendance records...\n";

// Create attendance: ~60 weekdays from Aug-Nov 2024
$attendanceDates = [];
$startDate = new DateTime('2024-08-01');
$endDate = new DateTime('2024-11-30');
$interval = new DateInterval('P1D');
$period = new DatePeriod($startDate, $interval, $endDate);
foreach ($period as $date) {
    $dow = $date->format('N');
    if ($dow <= 5) { // Mon-Fri
        $attendanceDates[] = $date->format('Y-m-d');
    }
}
// Cap at 90 days for a reasonable dataset
$attendanceDates = array_slice($attendanceDates, 0, 90);

$insertAttendance = $pdo->prepare("INSERT INTO attendance (student_id, attendance_date, status) VALUES (?, ?, ?)");

$totalAttendance = 0;
mt_srand(456);

$pdo->beginTransaction();
try {
    for ($i = 0; $i < 150; $i++) {
        // Each student gets a target attendance between 70-98%
        $targetPct = mt_rand(70, 98);
        
        foreach ($attendanceDates as $date) {
            $roll = mt_rand(1, 100);
            if ($roll <= $targetPct) {
                $status = 'Present';
            } elseif ($roll <= $targetPct + 3) {
                $status = 'Leave';
            } else {
                $status = 'Absent';
            }
            $insertAttendance->execute([$studentIds[$i], $date, $status]);
            $totalAttendance++;
        }
    }
    $pdo->commit();
    echo "   Created $totalAttendance attendance records.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("FATAL: Attendance creation failed: " . $e->getMessage() . "\n");
}

// ===================================================================
// ACHIEVEMENTS
// ===================================================================
echo "\n[6/9] Creating achievements...\n";

$achievementPool = [
    ['Hackathon Participation', 'fa-code', 'info', 'Participated in the annual GLS Hackathon and built a working prototype.'],
    ['Coding Competition Winner', 'fa-trophy', 'warning', 'Secured top position in inter-college coding competition.'],
    ['Sports Achievement', 'fa-futbol', 'success', 'Represented university in inter-university sports tournament.'],
    ['Technical Workshop', 'fa-tools', 'primary', 'Completed advanced technical workshop on emerging technologies.'],
    ['Paper Presentation', 'fa-file-alt', 'secondary', 'Presented research paper at national-level seminar.'],
    ['Cultural Event', 'fa-music', 'danger', 'Outstanding performance in annual cultural fest.'],
    ['Academic Excellence', 'fa-star', 'warning', 'Achieved distinction in semester examinations.'],
    ['Project Competition', 'fa-project-diagram', 'info', 'Won Best Project award at departmental exhibition.'],
    ['Community Service', 'fa-hands-helping', 'success', 'Led community service initiatives through NSS.'],
    ['Debate Competition', 'fa-microphone', 'primary', 'Won inter-college debate competition.'],
];

$insertAchievement = $pdo->prepare("INSERT INTO achievements (student_id, title, badge_icon, badge_color, description, awarded_date) VALUES (?, ?, ?, ?, ?, ?)");

$totalAchievements = 0;
mt_srand(789);

$pdo->beginTransaction();
try {
    for ($i = 0; $i < 150; $i++) {
        // ~40% get 0 achievements, ~30% get 1, ~20% get 2, ~10% get 3
        $roll = mt_rand(1, 100);
        if ($roll <= 40) $numAch = 0;
        elseif ($roll <= 70) $numAch = 1;
        elseif ($roll <= 90) $numAch = 2;
        else $numAch = 3;
        
        $usedTitles = [];
        for ($a = 0; $a < $numAch; $a++) {
            $attempts = 0;
            do {
                $achIdx = mt_rand(0, count($achievementPool) - 1);
                $ach = $achievementPool[$achIdx];
                $attempts++;
            } while (in_array($ach[0], $usedTitles) && $attempts < 20);
            
            if (!in_array($ach[0], $usedTitles)) {
                $usedTitles[] = $ach[0];
                $awardMonth = mt_rand(1, 12);
                $awardDay = mt_rand(1, 28);
                $awardDate = sprintf('2024-%02d-%02d', $awardMonth, $awardDay);
                
                $insertAchievement->execute([
                    $studentIds[$i], $ach[0], $ach[1], $ach[2], $ach[3], $awardDate
                ]);
                $totalAchievements++;
            }
        }
    }
    $pdo->commit();
    echo "   Created $totalAchievements achievements.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("FATAL: Achievements creation failed: " . $e->getMessage() . "\n");
}

// ===================================================================
// ACADEMIC PERFORMANCE
// ===================================================================
echo "\n[7/9] Computing academic performance...\n";

$insertPerf = $pdo->prepare("INSERT INTO academic_performance (student_id, semester, academic_year, total_marks_obtained, total_max_marks, overall_percentage, grade, attendance_percentage, assignment_score, practical_score, performance_score, performance_category, alert_level) VALUES (?, ?, '2024-2025', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$totalPerf = 0;

$pdo->beginTransaction();
try {
    for ($i = 0; $i < 150; $i++) {
        $stuData = $studentsToInsert[$i];
        $baseSem = $stuData['sem'];
        $semesters = [$baseSem, $baseSem + 1];
        
        foreach ($semesters as $sem) {
            // Calculate from actual marks
            $markStmt = $pdo->prepare("SELECT SUM(total_marks) as total_obt, COUNT(*) as num_subjects FROM student_marks WHERE student_id = ? AND semester = ?");
            $markStmt->execute([$studentIds[$i], $sem]);
            $markData = $markStmt->fetch();
            
            if (!$markData || $markData['num_subjects'] == 0) continue;
            
            $totalObt = (float)$markData['total_obt'];
            $totalMax = (float)($markData['num_subjects'] * 100); // each subject max=100
            $overallPct = round($totalObt / $totalMax * 100, 2);
            
            // Grade from percentage
            if ($overallPct >= 90) $grade = 'A+';
            elseif ($overallPct >= 80) $grade = 'A';
            elseif ($overallPct >= 70) $grade = 'B+';
            elseif ($overallPct >= 60) $grade = 'B';
            elseif ($overallPct >= 50) $grade = 'C';
            elseif ($overallPct >= 40) $grade = 'D';
            else $grade = 'F';
            
            // Attendance percentage
            $attStmt = $pdo->prepare("SELECT COUNT(CASE WHEN status='Present' THEN 1 END) as present, COUNT(*) as total FROM attendance WHERE student_id = ?");
            $attStmt->execute([$studentIds[$i]]);
            $attData = $attStmt->fetch();
            $attPct = $attData['total'] > 0 ? round($attData['present'] / $attData['total'] * 100, 2) : 80;
            
            // Assignment and practical scores (derived from marks)
            $assignScore = min(100, round($overallPct + mt_rand(-5, 8), 2));
            $practScore = min(100, round($overallPct + mt_rand(-3, 10), 2));
            
            // Performance score (weighted)
            $perfScore = round(
                ($overallPct * 0.60) + ($attPct * 0.15) + ($assignScore * 0.10) + ($practScore * 0.15),
                2
            );
            
            // Category
            if ($perfScore >= 90) $cat = 'Exceptional';
            elseif ($perfScore >= 80) $cat = 'High Performer';
            elseif ($perfScore >= 70) $cat = 'Strong Performer';
            elseif ($perfScore >= 60) $cat = 'On Track';
            elseif ($perfScore >= 50) $cat = 'Needs Improvement';
            else $cat = 'At Risk';
            
            // Alert level
            if ($overallPct >= 75 && $attPct >= 75) $alert = 'GREEN';
            elseif ($overallPct >= 60 && $attPct >= 65) $alert = 'YELLOW';
            elseif ($overallPct >= 45 && $attPct >= 50) $alert = 'ORANGE';
            else $alert = 'RED';
            
            $insertPerf->execute([
                $studentIds[$i], $sem,
                $totalObt, $totalMax, $overallPct, $grade,
                $attPct, $assignScore, $practScore,
                $perfScore, $cat, $alert
            ]);
            $totalPerf++;
        }
    }
    $pdo->commit();
    echo "   Created $totalPerf academic performance records.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    die("FATAL: Academic performance failed: " . $e->getMessage() . "\n");
}

// ===================================================================
// VERIFICATION
// ===================================================================
echo "\n[8/9] Verifying data...\n";

$countStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$countUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$countSubjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$countMarks = $pdo->query("SELECT COUNT(*) FROM student_marks")->fetchColumn();
$countAttendance = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
$countAchievements = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
$countPerf = $pdo->query("SELECT COUNT(*) FROM academic_performance")->fetchColumn();

// Course distribution
$courseDistStmt = $pdo->query("SELECT course, COUNT(*) as cnt FROM students GROUP BY course ORDER BY course");
$courseDist = $courseDistStmt->fetchAll(PDO::FETCH_ASSOC);

// Year distribution (based on semester)
$yearDistStmt = $pdo->query("SELECT CASE WHEN semester IN (1,2) THEN 'FY' WHEN semester IN (3,4) THEN 'SY' WHEN semester IN (5,6) THEN 'TY' END as yr, COUNT(*) as cnt FROM students GROUP BY yr ORDER BY yr");
$yearDist = $yearDistStmt->fetchAll(PDO::FETCH_ASSOC);

// Enrollment range
$enrollRange = $pdo->query("SELECT MIN(enrollment_no) as mn, MAX(enrollment_no) as mx FROM students")->fetch();

// Uniqueness checks
$dupEnrollment = $pdo->query("SELECT enrollment_no, COUNT(*) as cnt FROM students GROUP BY enrollment_no HAVING cnt > 1")->fetchAll();
$dupUsers = $pdo->query("SELECT username, COUNT(*) as cnt FROM users WHERE role='student' GROUP BY username HAVING cnt > 1")->fetchAll();

echo "   Students: $countStudents\n";
echo "   Student Accounts: $countUsers\n";
echo "   Subjects: $countSubjects\n";
echo "   Marks: $countMarks\n";
echo "   Attendance: $countAttendance\n";
echo "   Achievements: $countAchievements\n";
echo "   Performance: $countPerf\n";
echo "   Enrollment Range: {$enrollRange['mn']} - {$enrollRange['mx']}\n";
echo "   Duplicate Enrollments: " . count($dupEnrollment) . "\n";
echo "   Duplicate User Accounts: " . count($dupUsers) . "\n";

foreach ($courseDist as $cd) {
    echo "   {$cd['course']}: {$cd['cnt']}\n";
}
foreach ($yearDist as $yd) {
    echo "   {$yd['yr']}: {$yd['cnt']}\n";
}

// ===================================================================
// FINAL SUMMARY
// ===================================================================
echo "\n[9/9] Done!\n\n";
echo "========================================\n";
echo "SMS 2.0 DEMO DATA SEED COMPLETE\n";
echo "========================================\n\n";
echo "Students:\n$countStudents\n\n";

foreach ($courseDist as $cd) {
    echo "{$cd['course']}:\n{$cd['cnt']}\n";
    // Sub-distribution
    $subStmt = $pdo->prepare("SELECT CASE WHEN semester IN (1,2) THEN 'FY' WHEN semester IN (3,4) THEN 'SY' WHEN semester IN (5,6) THEN 'TY' END as yr, COUNT(*) as cnt FROM students WHERE course = ? GROUP BY yr ORDER BY yr");
    $subStmt->execute([$cd['course']]);
    $subDist = $subStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subDist as $sd) {
        echo "  {$sd['yr']}: {$sd['cnt']}\n";
    }
    echo "\n";
}

echo "Student Accounts:\n$countUsers\n\n";
echo "Enrollment Range:\n{$enrollRange['mn']}\nto\n{$enrollRange['mx']}\n\n";
echo "Subjects:\n$countSubjects\n\n";
echo "Marks:\n$countMarks\n\n";
echo "Attendance:\n$countAttendance\n\n";
echo "Achievements:\n$countAchievements\n\n";
echo "========================================\n";

// Verify one student can authenticate
$testStmt = $pdo->prepare("SELECT u.username, u.password_hash, u.must_change_password, u.student_id, s.enrollment_no, s.first_name, s.last_name, s.course 
    FROM users u 
    JOIN students s ON u.student_id = s.id 
    WHERE u.username = ?");
$testStmt->execute(['202401619010001']);
$testUser = $testStmt->fetch();

if ($testUser) {
    $canAuth = password_verify($aadhaars[0], $testUser['password_hash']);
    echo "\nAuth Test (202401619010001):\n";
    echo "  Name: {$testUser['first_name']} {$testUser['last_name']}\n";
    echo "  Course: {$testUser['course']}\n";
    echo "  Password Verify: " . ($canAuth ? 'PASS' : 'FAIL') . "\n";
    echo "  Must Change Password: " . ($testUser['must_change_password'] ? 'YES' : 'NO') . "\n";
    echo "  Password is Hash (not plaintext): " . (strlen($testUser['password_hash']) > 50 ? 'YES' : 'NO') . "\n";
}

$testStmt->execute(['202401619010150']);
$testUser2 = $testStmt->fetch();
if ($testUser2) {
    $canAuth2 = password_verify($aadhaars[149], $testUser2['password_hash']);
    echo "\nAuth Test (202401619010150):\n";
    echo "  Name: {$testUser2['first_name']} {$testUser2['last_name']}\n";
    echo "  Course: {$testUser2['course']}\n";
    echo "  Password Verify: " . ($canAuth2 ? 'PASS' : 'FAIL') . "\n";
}

echo "\n========================================\n";
echo "SEED COMPLETE. You may now test at:\n";
echo "  Admin:   http://localhost:8000\n";
echo "  Student: http://localhost:9000\n";
echo "========================================\n";
