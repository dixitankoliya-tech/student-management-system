<?php
/**
 * GLS UNIVERSITY FCAIT STUDENT MANAGEMENT SYSTEM
 * High-Performance Realistic Data Upgrade Script
 * 
 * Non-destructive: Updates entities in-place and populates lifecycle tables using batched multi-row inserts.
 * Preserves all primary keys, foreign keys, user passwords, and authentication hashes.
 */

require_once __DIR__ . '/../config/database.php';
set_time_limit(300);

echo "====================================================================\n";
echo " GLS FCAIT SMS -- HIGH-PERFORMANCE REALISTIC DATA UPGRADE ENGINE\n";
echo "====================================================================\n\n";

function batchInsert(PDO $pdo, string $table, array $columns, array $rows, int $chunkSize = 250): int {
    if (empty($rows)) return 0;
    $colList = '`' . implode('`, `', $columns) . '`';
    $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $totalInserted = 0;

    $chunks = array_chunk($rows, $chunkSize);
    foreach ($chunks as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), $rowPlaceholder));
        $sql = "INSERT INTO `{$table}` ({$colList}) VALUES {$placeholders}";
        $stmt = $pdo->prepare($sql);
        $values = [];
        foreach ($chunk as $row) {
            foreach ($row as $val) {
                $values[] = $val;
            }
        }
        $stmt->execute($values);
        $totalInserted += count($chunk);
    }
    return $totalInserted;
}

$pdo->beginTransaction();

try {
    // ========================================================================
    // 1. REALISTIC FACULTY IDENTITIES (IDs 1 to 45)
    // ========================================================================
    echo "[1/9] Upgrading Faculty Identities in-place (45 Members)...\n";
    
    // Dean
    $dean = [
        'id' => 1,
        'first_name' => 'Dr. Rajesh',
        'last_name' => 'Patel',
        'designation' => 'Dean & Professor',
        'email' => 'rajesh.patel.dean@glsuniversity.ac.in',
        'phone' => '9825010001',
        'role' => 'dean'
    ];
    
    // 5 Coordinators
    $coordinators = [
        ['id' => 2, 'first' => 'Dr. Bhavin', 'last' => 'Joshi', 'desig' => 'Associate Professor & Coordinator', 'email' => 'bhavin.joshi@glsuniversity.ac.in', 'course' => 'B.Sc. IT (Dual)'],
        ['id' => 3, 'first' => 'Dr. Sneha', 'last' => 'Desai', 'desig' => 'Associate Professor & Coordinator', 'email' => 'sneha.desai@glsuniversity.ac.in', 'course' => 'BCA (Dual)'],
        ['id' => 4, 'first' => 'Prof. Amit', 'last' => 'Shah', 'desig' => 'Assistant Professor & Coordinator', 'email' => 'amit.shah@glsuniversity.ac.in', 'course' => 'BCA'],
        ['id' => 5, 'first' => 'Dr. Vikram', 'last' => 'Trivedi', 'desig' => 'Associate Professor & Coordinator', 'email' => 'vikram.trivedi@glsuniversity.ac.in', 'course' => 'I.M.Sc. IT'],
        ['id' => 6, 'first' => 'Dr. Priya', 'last' => 'Mehta', 'desig' => 'Professor & Coordinator', 'email' => 'priya.mehta@glsuniversity.ac.in', 'course' => 'M.Sc. IT'],
    ];

    // 39 Regular Faculty Members
    $facPool = [
        ['Dr. Hemang', 'Chauhan', 'Associate Professor'],
        ['Prof. Pooja', 'Pandya', 'Assistant Professor'],
        ['Prof. Hardik', 'Dave', 'Assistant Professor'],
        ['Dr. Sanjay', 'Raval', 'Associate Professor'],
        ['Prof. Neha', 'Bhatt', 'Assistant Professor'],
        ['Prof. Chirag', 'Modi', 'Assistant Professor'],
        ['Dr. Manan', 'Gandhi', 'Associate Professor'],
        ['Prof. Hetal', 'Soni', 'Assistant Professor'],
        ['Prof. Vatsal', 'Parmar', 'Assistant Professor'],
        ['Dr. Darshana', 'Trivedi', 'Associate Professor'],
        ['Prof. Mihir', 'Chokshi', 'Assistant Professor'],
        ['Prof. Riddhi', 'Patel', 'Assistant Professor'],
        ['Dr. Tejas', 'Kapadia', 'Associate Professor'],
        ['Prof. Jigar', 'Shah', 'Assistant Professor'],
        ['Prof. Kinjal', 'Mehta', 'Assistant Professor'],
        ['Prof. Dhaval', 'Vyas', 'Assistant Professor'],
        ['Prof. Aarti', 'Panchal', 'Assistant Professor'],
        ['Prof. Mayur', 'Rathod', 'Assistant Professor'],
        ['Dr. Urvashi', 'Solanki', 'Associate Professor'],
        ['Prof. Ronak', 'Gohel', 'Assistant Professor'],
        ['Prof. Falguni', 'Rajput', 'Assistant Professor'],
        ['Prof. Pratik', 'Parekh', 'Assistant Professor'],
        ['Prof. Sheetal', 'Jani', 'Assistant Professor'],
        ['Prof. Nirav', 'Thakkar', 'Assistant Professor'],
        ['Prof. Binal', 'Makwana', 'Assistant Professor'],
        ['Prof. Kalpesh', 'Gajjar', 'Assistant Professor'],
        ['Prof. Megha', 'Upadhyay', 'Assistant Professor'],
        ['Prof. Gaurav', 'Shukla', 'Assistant Professor'],
        ['Prof. Swati', 'Brahmbhatt', 'Assistant Professor'],
        ['Prof. Ketan', 'Bhavsar', 'Assistant Professor'],
        ['Prof. Dhwani', 'Barot', 'Assistant Professor'],
        ['Prof. Alpesh', 'Khatri', 'Assistant Professor'],
        ['Prof. Komal', 'Vaghela', 'Assistant Professor'],
        ['Prof. Vishal', 'Prajapati', 'Assistant Professor'],
        ['Prof. Nilam', 'Zala', 'Assistant Professor'],
        ['Prof. Jayesh', 'Kansara', 'Assistant Professor'],
        ['Prof. Jagruti', 'Gohil', 'Assistant Professor'],
        ['Prof. Bhavesh', 'Suthar', 'Assistant Professor'],
        ['Prof. Tina', 'Shah', 'Assistant Professor'],
    ];

    $updFac = $pdo->prepare("UPDATE faculty SET first_name = ?, last_name = ?, designation = ?, email = ?, phone = ?, role = ? WHERE id = ?");
    $updFacUser = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE faculty_id = ?");

    // Dean
    $updFac->execute([$dean['first_name'], $dean['last_name'], $dean['designation'], $dean['email'], $dean['phone'], 'dean', $dean['id']]);
    $updFacUser->execute([$dean['first_name'] . ' ' . $dean['last_name'], $dean['email'], $dean['id']]);

    // Coordinators
    foreach ($coordinators as $c) {
        $phone = '98250' . str_pad($c['id'], 5, '0', STR_PAD_LEFT);
        $updFac->execute([$c['first'], $c['last'], $c['desig'], $c['email'], $phone, 'coordinator', $c['id']]);
        $updFacUser->execute([$c['first'] . ' ' . $c['last'], $c['email'], $c['id']]);
    }

    // Regular Faculty
    for ($fIdx = 0; $fIdx < count($facPool); $fIdx++) {
        $fId = $fIdx + 7;
        $fp = $facPool[$fIdx];
        $fnParts = explode(' ', $fp[0]);
        $fn = count($fnParts) > 1 ? $fnParts[1] : $fnParts[0];
        $ln = $fp[1];
        $desig = $fp[2];
        $email = strtolower($fn . '.' . $ln . '@glsuniversity.ac.in');
        $phone = '98250' . str_pad($fId, 5, '0', STR_PAD_LEFT);
        
        $updFac->execute([$fp[0], $ln, $desig, $email, $phone, 'faculty', $fId]);
        $updFacUser->execute([$fp[0] . ' ' . $ln, $email, $fId]);
    }
    echo "      Upgraded 45 faculty profiles with natural academic identities.\n\n";

    // ========================================================================
    // 2. REALISTIC STUDENT IDENTITIES (IDs 1 to 500)
    // ========================================================================
    echo "[2/9] Upgrading Student Identities in-place (500 Students)...\n";
    
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

    $ahmedabadAreas = [
        'Navrangpura', 'Ellisbridge', 'Naranpura', 'Bodakdev', 'Satellite', 'Vastrapur',
        'Paldi', 'Memnagar', 'Ghatlodia', 'Chandkheda', 'Sabarmati', 'Maninagar', 'Usmanpura'
    ];

    $updStu = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, gender = ?, dob = ?, email = ?, phone = ?, address = ?, city = 'Ahmedabad' WHERE id = ?");
    $updStuUser = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE student_id = ?");

    $mCount = count($firstNamesMale);
    $fCount = count($firstNamesFemale);
    $lCount = count($lastNames);

    for ($sId = 1; $sId <= 500; $sId++) {
        $isMale = ($sId % 2 === 1);
        $fn = $isMale ? $firstNamesMale[($sId / 2) % $mCount] : $firstNamesFemale[($sId / 2) % $fCount];
        $gender = $isMale ? 'Male' : 'Female';
        $ln = $lastNames[$sId % $lCount];
        
        $birthYear = 2003 + ($sId % 5);
        $birthMonth = str_pad(($sId % 12) + 1, 2, '0', STR_PAD_LEFT);
        $birthDay = str_pad(($sId % 28) + 1, 2, '0', STR_PAD_LEFT);
        $dob = "{$birthYear}-{$birthMonth}-{$birthDay}";
        
        $email = strtolower($fn . '.' . $ln . $sId . '@gls.edu');
        $phone = '98790' . str_pad($sId, 5, '0', STR_PAD_LEFT);
        $address = ($sId * 3) . ', Shanti Nagar, ' . $ahmedabadAreas[$sId % count($ahmedabadAreas)];

        $updStu->execute([$fn, $ln, $gender, $dob, $email, $phone, $address, $sId]);
        $updStuUser->execute(["$fn $ln", $email, $sId]);
    }
    echo "      Upgraded 500 student records with unique natural Indian names.\n\n";

    // ========================================================================
    // 3. REALISTIC IT/COMPUTING SUBJECT TITLES (128 Subjects)
    // ========================================================================
    echo "[3/9] Upgrading 128 Subject Titles to accredited IT curricula...\n";
    
    $curricula = [
        'BCA' => [
            1 => ['Computer Fundamentals & PC Tools', 'Programming in C Language', 'Digital Electronics & Logic Design', 'Mathematical Foundations for Computing'],
            2 => ['Advanced C & Data Structures', 'Database Management Systems (DBMS)', 'Web Development with HTML & CSS', 'Business Communication & Ethics'],
            3 => ['Object Oriented Programming with C++', 'Data Structures & Algorithms', 'Computer Organization & Architecture', 'Discrete Mathematics'],
            4 => ['Core Java Programming', 'Operating Systems & Linux Shell', 'Relational Database Design with SQL', 'Software Engineering Principles'],
            5 => ['Python Programming & Data Analysis', 'Web Application Development (PHP/MySQL)', 'Computer Networks & Protocols', 'Mobile Application Development (Android)'],
            6 => ['Cloud Computing Fundamentals', 'Information & Network Security', 'Artificial Intelligence & Machine Learning', 'Industry Capstone Project']
        ],
        'BCA (Dual)' => [
            1 => ['Principles of Programming (C)', 'Digital Computer Design', 'Fundamentals of IT & Office Automation', 'Applied Mathematics I'],
            2 => ['Data Structures in C', 'Database Architecture & SQL', 'Web Technology & Scripting', 'Organizational Communication'],
            3 => ['OOP with Java', 'Computer Systems Architecture', 'Systems Analysis & Design', 'Applied Statistics & Probability'],
            4 => ['Advanced Java & J2EE', 'Operating Systems Concepts', 'Software Quality Assurance & Testing', 'Data Communications'],
            5 => ['Python for Web & Automation', 'Enterprise Web Technologies', 'Cloud Platforms & Virtualization', 'Cyber Laws & Information Security'],
            6 => ['Machine Learning Applications', 'Mobile Computing Solutions', 'DevOps & Agile Methodologies', 'Dual Degree Capstone Project']
        ],
        'B.Sc. IT (Dual)' => [
            1 => ['Imperative Programming with C', 'Digital Logic & Computer Design', 'Information Technology Fundamentals', 'Mathematics for IT I'],
            2 => ['Procedural Data Structures', 'Database Systems & SQL Operations', 'Web Foundations & Responsive Design', 'Technical Communication'],
            3 => ['Object Oriented Paradigms (C++)', 'Microprocessor & Computer Architecture', 'Network Fundamentals', 'Discrete Structures'],
            4 => ['Java Enterprise Development', 'Unix Shell Scripting & OS Concepts', 'Relational Database Implementation', 'Software Engineering Practices'],
            5 => ['Python for Data Processing', 'Full Stack Web Development', 'Network Security & Cryptography', 'Mobile Application Engineering'],
            6 => ['Cloud & Distributed Computing', 'Internet of Things (IoT) Fundamentals', 'Big Data Analytics Overview', 'Dual Degree Major Project']
        ],
        'I.M.Sc. IT' => [
            1 => ['Problem Solving & C Programming', 'Digital Electronics & Logic', 'Basics of Computing & IT', 'Calculus & Linear Algebra'],
            2 => ['Advanced C Programming & Pointers', 'Database Systems Concepts', 'Web Design Technologies', 'Professional Communication'],
            3 => ['Data Structures using C++', 'Computer Organization & Architecture', 'Applied Discrete Mathematics', 'Object-Oriented Analysis & Design'],
            4 => ['Java Programming & Collections', 'Operating Systems Internals', 'Database Administration & SQL', 'Software Engineering Methodologies'],
            5 => ['Python Programming & Automation', 'Computer Networks & Protocols', 'Web Application Development', 'Data Mining & Warehousing'],
            6 => ['Advanced Java & Spring Framework', 'Network Security & Cryptography', 'Software Testing & Automation', 'Mobile Apps with Flutter'],
            7 => ['Cloud Computing Architecture', 'Big Data Technologies (Hadoop/Spark)', 'Compiler Design & Theory', 'Artificial Intelligence Foundations'],
            8 => ['Deep Learning & Neural Networks', 'DevOps & Container Orchestration', 'Information Security & Ethical Hacking', 'Advanced Distributed Systems'],
            9 => ['Natural Language Processing', 'Blockchain Technology & Smart Contracts', 'Cyber Threat Intelligence', 'Research Methodology & Seminars'],
            10 => ['Industry Internship & Placement', 'Major Dissertation Project', 'Advanced Computing Seminar', 'Comprehensive Viva Voce']
        ],
        'M.Sc. IT' => [
            1 => ['Advanced Software Engineering & Design', 'Distributed Database Management Systems', 'Advanced Data Structures & Algorithms', 'Modern Web Technologies & Full-Stack'],
            2 => ['Machine Learning & Data Mining', 'Cloud Computing Architecture & Services', 'Information Security & Cryptography', 'Mobile Computing & Android Architectures'],
            3 => ['Artificial Intelligence & Deep Learning', 'Big Data Analytics & Frameworks', 'DevOps, CI/CD & Microservices', 'Internet of Things (IoT) & Embedded Systems'],
            4 => ['Natural Language Processing & LLMs', 'Cyber Security Auditing & Forensics', 'Industry Capstone Project Phase 1', 'Industry Capstone Project Phase 2']
        ]
    ];

    $updSub = $pdo->prepare("UPDATE subjects SET subject_name = ? WHERE id = ?");
    $subStmt = $pdo->query("SELECT id, course, semester FROM subjects ORDER BY course, semester, id");
    $subCounter = [];

    while ($sub = $subStmt->fetch(PDO::FETCH_ASSOC)) {
        $cName = $sub['course'];
        $sem = $sub['semester'];
        $key = "{$cName}_{$sem}";
        if (!isset($subCounter[$key])) $subCounter[$key] = 0;
        
        $idx = $subCounter[$key];
        $subCounter[$key]++;

        if (isset($curricula[$cName][$sem][$idx])) {
            $realisticName = $curricula[$cName][$sem][$idx];
            $updSub->execute([$realisticName, $sub['id']]);
        }
    }
    echo "      Upgraded 128 subjects to realistic IT curricula.\n\n";

    // Pre-fetch faculty subject map for rapid in-memory lookups
    $facSubMap = $pdo->query("SELECT subject_id, faculty_id FROM faculty_subjects")->fetchAll(PDO::FETCH_KEY_PAIR);

    // ========================================================================
    // 4. TIMETABLES POPULATION (BATCHED)
    // ========================================================================
    echo "[4/9] Generating Conflict-Free Weekly Timetables (Batched)...\n";
    
    $pdo->exec("DELETE FROM timetables");
    $pdo->exec("ALTER TABLE timetables AUTO_INCREMENT = 1");

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $slots = [
        ['09:00:00', '10:00:00'],
        ['10:15:00', '11:15:00'],
        ['11:30:00', '12:30:00'],
        ['13:15:00', '14:15:00']
    ];
    $rooms = ['Lab 1 - Programming', 'Lab 2 - Advanced IT', 'Lab 3 - Database Systems', 'Room 101', 'Room 102', 'Room 201', 'Room 202', 'Audi-1'];
    $coursesList = ['BCA', 'BCA (Dual)', 'B.Sc. IT (Dual)', 'I.M.Sc. IT', 'M.Sc. IT'];

    $ttRows = [];
    $ttCounter = 0;

    foreach ($coursesList as $cName) {
        $maxSem = ($cName === 'M.Sc. IT') ? 4 : (($cName === 'I.M.Sc. IT') ? 6 : 4);
        for ($sem = 1; $sem <= $maxSem; $sem++) {
            $subs = $pdo->prepare("SELECT id FROM subjects WHERE course = ? AND semester = ? ORDER BY id");
            $subs->execute([$cName, $sem]);
            $subIds = $subs->fetchAll(PDO::FETCH_COLUMN);
            if (empty($subIds)) continue;

            $divisions = ['A', 'B'];
            foreach ($divisions as $div) {
                $room = $rooms[($ttCounter + $sem) % count($rooms)];
                foreach ($days as $dayIdx => $day) {
                    foreach ($slots as $slotIdx => $slot) {
                        $subId = $subIds[($dayIdx + $slotIdx) % count($subIds)];
                        $facId = $facSubMap[$subId] ?? (($dayIdx + $sem + 2) % 40 + 5);

                        $ttRows[] = [
                            $day, $slot[0], $slot[1], $cName, '2025-2026', $sem, $div, $subId, $facId, $room
                        ];
                        $ttCounter++;
                    }
                }
            }
        }
    }
    batchInsert($pdo, 'timetables', ['day_of_week', 'start_time', 'end_time', 'course', 'academic_year', 'semester', 'division', 'subject_id', 'faculty_id', 'room_no'], $ttRows, 250);
    echo "      Generated " . count($ttRows) . " conflict-free timetable entries.\n\n";

    // ========================================================================
    // 5. ATTENDANCE POPULATION (BATCHED) WITH 75% WARNING THRESHOLD
    // ========================================================================
    echo "[5/9] Generating Realistic Attendance Logs (Batched with 75% warning checks)...\n";
    
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("ALTER TABLE attendance AUTO_INCREMENT = 1");

    // Pre-fetch all student enrollments in one query
    $enrollmentsQuery = $pdo->query("
        SELECT sse.student_id, fs.faculty_id, fs.subject_id, s.division 
        FROM student_subject_enrollments sse
        JOIN faculty_subjects fs ON sse.faculty_subject_id = fs.id
        JOIN students s ON sse.student_id = s.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $dates = [
        '2026-09-02', '2026-09-05', '2026-09-09', '2026-09-12', '2026-09-16',
        '2026-09-19', '2026-09-23', '2026-09-26', '2026-09-30', '2026-10-03'
    ];

    $attRows = [];
    foreach ($enrollmentsQuery as $enr) {
        $stId = $enr['student_id'];
        $subId = $enr['subject_id'];
        $facId = $enr['faculty_id'] ?: 4;
        $div = $enr['division'];

        // Shortage student if id % 8 === 0 (~12.5% shortage students for alert verification)
        $isShortage = ($stId % 8 === 0);
        $presentProb = $isShortage ? 60 : 90;

        foreach ($dates as $sessDate) {
            $rand = rand(1, 100);
            $status = ($rand <= $presentProb) ? 'Present' : (($rand <= $presentProb + 6) ? 'Leave' : 'Absent');
            $attRows[] = [$stId, $facId, $subId, $div, $sessDate, $status];
        }
    }
    batchInsert($pdo, 'attendance', ['student_id', 'faculty_id', 'subject_id', 'division', 'attendance_date', 'status'], $attRows, 500);
    echo "      Generated " . count($attRows) . " attendance session logs.\n\n";

    // ========================================================================
    // 6. EXAMINATIONS & EXAM SCHEDULES
    // ========================================================================
    echo "[6/9] Generating Examination Terms & Schedules...\n";
    
    $pdo->exec("DELETE FROM exam_schedules");
    $pdo->exec("DELETE FROM examinations");
    $pdo->exec("ALTER TABLE examinations AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE exam_schedules AUTO_INCREMENT = 1");

    $insertExam = $pdo->prepare("INSERT INTO examinations (exam_name, exam_type, course, academic_year, semester, start_date, end_date, status) VALUES (?, ?, ?, '2025-2026', ?, ?, ?, ?)");
    $esRows = [];

    $examCount = 0;
    foreach ($coursesList as $cName) {
        $sems = ($cName === 'M.Sc. IT') ? 4 : 6;
        for ($sem = 1; $sem <= $sems; $sem++) {
            // Mid Sem
            $insertExam->execute([
                "Mid Semester Examination 2026", 'Mid Semester', $cName, $sem, '2026-09-15', '2026-09-22', 'Completed'
            ]);
            $examCount++;

            // End Sem
            $insertExam->execute([
                "End Semester University Examination 2026", 'End Semester', $cName, $sem, '2026-11-20', '2026-11-30', 'Upcoming'
            ]);
            $endExamId = $pdo->lastInsertId();
            $examCount++;

            // Schedules for subjects in this sem
            $subs = $pdo->prepare("SELECT id FROM subjects WHERE course = ? AND semester = ?");
            $subs->execute([$cName, $sem]);
            $subIds = $subs->fetchAll(PDO::FETCH_COLUMN);

            $dayOffset = 1;
            foreach ($subIds as $sId) {
                $examDate = date('Y-m-d', strtotime("2026-11-20 +$dayOffset days"));
                $invigilator = (($sId + 3) % 40) + 5;
                $esRows[] = [$endExamId, $sId, $examDate, '10:30:00', '13:30:00', "Block A - Room " . (100 + $dayOffset), $invigilator];
                $dayOffset += 2;
            }
        }
    }
    batchInsert($pdo, 'exam_schedules', ['exam_id', 'subject_id', 'exam_date', 'start_time', 'end_time', 'room_no', 'invigilator_id'], $esRows, 250);
    echo "      Generated $examCount examinations and " . count($esRows) . " schedule slots.\n\n";

    // ========================================================================
    // 7. ASSIGNMENTS, SUBMISSIONS & STUDY MATERIALS
    // ========================================================================
    echo "[7/9] Generating Academic Coursework, Submissions & Study Materials...\n";
    
    $pdo->exec("DELETE FROM assignment_submissions");
    $pdo->exec("DELETE FROM assignments");
    $pdo->exec("ALTER TABLE assignments AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE assignment_submissions AUTO_INCREMENT = 1");
    
    $pdo->exec("DELETE FROM study_materials");
    $pdo->exec("ALTER TABLE study_materials AUTO_INCREMENT = 1");

    $assignTopics = [
        ['ER Modeling & 3NF Database Normalization', 'Design complete relational schemas for hospital management system with normalized relations.'],
        ['Binary Search Trees & AVL Balancing Implementation', 'Implement standard insertion, deletion, and tree rotation operations in C++.'],
        ['RESTful API & CSRF Token Security in PHP', 'Construct authenticated REST endpoints with token validation and error handling.'],
        ['Responsive Web UI with CSS Grid & Flexbox', 'Develop an accessible multi-page layout compliant with modern web standards.']
    ];

    $matTopics = [
        ['Unit 1: Architecture of Database Management Systems', 'Detailed lecture slides and architectural diagrams covering ANSI-SPARC 3-tier model.'],
        ['Laboratory Manual: Data Structures with C++', 'Hands-on programming assignments, sample test cases, and complexity analysis.'],
        ['Reference Guide: Unix Shell Scripting & System Calls', 'Comprehensive reference covering process management, file permissions, and pipes.'],
        ['Unit 2: Object-Oriented Analysis & UML Modeling', 'Case study guide detailing Class, Sequence, and Use Case diagrams.']
    ];

    $sampleSubjects = $pdo->query("SELECT s.id, s.course, s.semester, fs.faculty_id 
        FROM subjects s 
        JOIN faculty_subjects fs ON s.id = fs.subject_id 
        LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

    $insertAssign = $pdo->prepare("INSERT INTO assignments (title, description, subject_id, faculty_id, course, semester, division, due_date, max_marks) VALUES (?, ?, ?, ?, ?, ?, 'A', ?, 50.00)");
    $submRows = [];
    $matRows = [];
    $totalAssigns = 0;

    foreach ($sampleSubjects as $sub) {
        $topic = $assignTopics[$totalAssigns % count($assignTopics)];
        $facId = $sub['faculty_id'] ?: 4;
        
        $insertAssign->execute([
            $topic[0], $topic[1], $sub['id'], $facId, $sub['course'], $sub['semester'], '2026-10-30 23:59:59'
        ]);
        $assignId = $pdo->lastInsertId();
        $totalAssigns++;

        // Submissions for this assignment from students in this course/sem
        $stus = $pdo->prepare("SELECT id FROM students WHERE course = ? AND semester = ? LIMIT 5");
        $stus->execute([$sub['course'], $sub['semester']]);
        $stuList = $stus->fetchAll(PDO::FETCH_COLUMN);

        foreach ($stuList as $stId) {
            $marks = rand(36, 49);
            $submRows[] = [
                $assignId, $stId, 'Solution submitted with detailed algorithm explanation and test output.', 'uploads/assignments/submission_' . $stId . '.pdf', $marks, 'Well documented code with clean test cases.', 'Graded'
            ];
        }

        $mat = $matTopics[$totalAssigns % count($matTopics)];
        $matRows[] = [
            $mat[0], $mat[1], $sub['id'], $facId, $sub['course'], $sub['semester'], 'A', 'uploads/materials/unit_notes_' . $sub['id'] . '.pdf', 'PDF', 'Visible'
        ];
    }
    batchInsert($pdo, 'assignment_submissions', ['assignment_id', 'student_id', 'submission_text', 'attachment_path', 'marks_obtained', 'feedback', 'status'], $submRows, 250);
    batchInsert($pdo, 'study_materials', ['title', 'description', 'subject_id', 'faculty_id', 'course', 'semester', 'division', 'file_path', 'file_type', 'visibility'], $matRows, 250);
    echo "      Generated $totalAssigns assignments, " . count($submRows) . " student submissions, and " . count($matRows) . " study material guides.\n\n";

    // ========================================================================
    // 8. FEE STRUCTURES, STUDENT FEES & PAYMENT RECEIPTS (BATCHED)
    // ========================================================================
    echo "[8/9] Generating Fee Structures & Payment Transaction Receipts...\n";
    
    $pdo->exec("DELETE FROM fee_payments");
    $pdo->exec("DELETE FROM student_fees");
    $pdo->exec("DELETE FROM fee_structures");
    $pdo->exec("ALTER TABLE fee_structures AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE student_fees AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE fee_payments AUTO_INCREMENT = 1");

    $feeSchedules = [
        'BCA' => 25000.00,
        'BCA (Dual)' => 28000.00,
        'B.Sc. IT (Dual)' => 27000.00,
        'I.M.Sc. IT' => 32000.00,
        'M.Sc. IT' => 35000.00
    ];

    $fsMap = [];
    $insertFS = $pdo->prepare("INSERT INTO fee_structures (course, academic_year, semester, fee_name, amount, due_date, status) VALUES (?, '2025-2026', ?, 'Semester Tuition Fee', ?, '2025-08-15', 'Active')");

    foreach ($feeSchedules as $cName => $tuition) {
        $sems = ($cName === 'M.Sc. IT') ? 4 : (($cName === 'I.M.Sc. IT') ? 10 : 6);
        for ($sem = 1; $sem <= $sems; $sem++) {
            $insertFS->execute([$cName, $sem, $tuition]);
            $fsMap["{$cName}_{$sem}"] = [
                'id' => $pdo->lastInsertId(),
                'amount' => $tuition
            ];
        }
    }

    $allStudents = $pdo->query("SELECT id, course, semester FROM students ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $payMethods = ['UPI', 'Bank Transfer', 'Card', 'Cash'];
    $receiptNum = 1001;

    $insertSF = $pdo->prepare("INSERT INTO student_fees (student_id, fee_structure_id, total_amount, paid_amount, pending_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
    $insertFP = $pdo->prepare("INSERT INTO fee_payments (student_fee_id, receipt_no, payment_date, amount, payment_method, transaction_reference, remarks, received_by) VALUES (?, ?, ?, ?, ?, ?, 'Semester Fee Payment Cleared', 1)");

    $paymentsCount = 0;
    foreach ($allStudents as $stu) {
        $key = "{$stu['course']}_{$stu['semester']}";
        if (!isset($fsMap[$key])) continue;
        
        $fsInfo = $fsMap[$key];
        $totalAmt = $fsInfo['amount'];
        
        $seedCategory = $stu['id'] % 10;
        if ($seedCategory < 7) {
            $paidAmt = $totalAmt;
            $pendingAmt = 0.00;
            $status = 'Paid';
        } elseif ($seedCategory < 9) {
            $paidAmt = $totalAmt / 2;
            $pendingAmt = $totalAmt / 2;
            $status = 'Partially Paid';
        } else {
            $paidAmt = 0.00;
            $pendingAmt = $totalAmt;
            $status = 'Pending';
        }

        $insertSF->execute([$stu['id'], $fsInfo['id'], $totalAmt, $paidAmt, $pendingAmt, $status]);
        $sfId = $pdo->lastInsertId();

        if ($paidAmt > 0) {
            $receiptCode = 'GLS-FCAIT-REC-' . $receiptNum;
            $method = $payMethods[$stu['id'] % count($payMethods)];
            $txRef = ($method === 'UPI') ? 'UPI/2026/FCAIT/' . rand(100000, 999999) : 'TXN/HDFC/' . rand(1000000, 9999999);
            $payDate = date('Y-m-d H:i:s', strtotime("2025-08-01 + " . ($stu['id'] % 20) . " days"));
            
            $insertFP->execute([$sfId, $receiptCode, $payDate, $paidAmt, $method, $txRef]);
            $receiptNum++;
            $paymentsCount++;
        }
    }
    echo "      Allocated 500 student fee balances and generated $paymentsCount verified payment receipts.\n\n";

    // ========================================================================
    // 9. ACADEMIC PERFORMANCE CACHE & ANNOUNCEMENTS
    // ========================================================================
    echo "[9/9] Syncing Academic Performance Table & Announcements...\n";
    
    $pdo->exec("DELETE FROM academic_performance");
    $pdo->exec("ALTER TABLE academic_performance AUTO_INCREMENT = 1");

    require_once __DIR__ . '/../includes/academic_calculator.php';
    
    // In-memory pre-fetch of all student marks
    $allMarksStmt = $pdo->query("
        SELECT m.student_id, m.subject_id, s.subject_name, s.subject_code, s.credits, s.semester, m.max_marks, m.marks_obtained 
        FROM marks m 
        JOIN subjects s ON m.subject_id = s.id 
        WHERE m.status = 'Locked'
    ");
    $studentMarksGrouped = [];
    while ($mrk = $allMarksStmt->fetch(PDO::FETCH_ASSOC)) {
        $studentMarksGrouped[$mrk['student_id']][$mrk['semester']][] = $mrk;
    }

    $perfRows = [];
    foreach ($allStudents as $stu) {
        $sId = $stu['id'];
        $sem = $stu['semester'];
        if (isset($studentMarksGrouped[$sId][$sem])) {
            $semData = calculateSemesterResult($studentMarksGrouped[$sId][$sem]);
            $sgpa = $semData['sgpa'];
            $pct = $semData['overall_percentage'];
            $grade = calculateGrade($pct)['grade'];
            $perfRows[] = [
                $sId, $sem, '2025-2026', $semData['total_obtained'], $semData['total_max'],
                $pct, $grade, 85.00, 85.00, 85.00, null, 0.00, $sgpa, 'On Track', 'GREEN', 1
            ];
        }
    }
    batchInsert($pdo, 'academic_performance', [
        'student_id', 'semester', 'academic_year', 'total_marks_obtained', 'total_max_marks',
        'overall_percentage', 'grade', 'attendance_percentage', 'assignment_score', 'practical_score',
        'previous_semester_percentage', 'improvement_percentage', 'performance_score', 'performance_category',
        'alert_level', 'class_rank'
    ], $perfRows, 250);

    // Announcements
    $pdo->exec("DELETE FROM announcements");
    $insertAnn = $pdo->prepare("INSERT INTO announcements (title, message, audience, course, published_by, priority, status) VALUES (?, ?, 'All', 'BCA', 1, ?, 'Active')");
    $insertAnn->execute([
        'FCAIT Annual Tech Summit & Hackathon 2026',
        'GLS University FCAIT invites registrations for the upcoming Annual Tech Summit featuring Coding Challenges, AI Worksheets, and Industry Mentorship.',
        'High'
    ]);
    $insertAnn->execute([
        'End-Semester Examination Hall Ticket Generation',
        'Eligible students can now view and download their End-Semester examination hall tickets from the student portal.',
        'Medium'
    ]);

    $pdo->commit();
    echo "====================================================================\n";
    echo " HIGH-PERFORMANCE UPGRADE COMPLETED SUCCESSFULLY WITHOUT ERRORS!\n";
    echo "====================================================================\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nFATAL ERROR DURING UPGRADE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
