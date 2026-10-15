<?php
require_once __DIR__ . '/../config/database.php';

$count = $pdo->query("SELECT COUNT(*) FROM academic_calendar")->fetchColumn();
if ($count > 0) {
    echo "academic_calendar already has $count events.\n";
    exit;
}

$events = [
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Semester Start',
        'event_name' => 'Commencement of Odd Semesters (Sem 1, 3, 5)',
        'start_date' => '2026-06-15',
        'end_date' => '2026-06-15',
        'semester' => null,
        'course' => null,
        'description' => 'Academic session begins for BCA and B.Sc. IT (Dual Specialization).'
    ],
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Registration',
        'event_name' => 'Subject Enrollment & Academic Registration',
        'start_date' => '2026-06-16',
        'end_date' => '2026-06-25',
        'semester' => null,
        'course' => null,
        'description' => 'Online portal course registration for all students.'
    ],
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Holiday',
        'event_name' => 'Independence Day Holiday',
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
        'semester' => null,
        'course' => null,
        'description' => 'National holiday - Campus closed.'
    ],
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Exam Period',
        'event_name' => 'Continuous Evaluation & Mid-Term Examinations',
        'start_date' => '2026-09-14',
        'end_date' => '2026-09-22',
        'semester' => 5,
        'course' => 'B.Sc. IT (Dual)',
        'description' => 'Mid-Semester practical & theory assessment.'
    ],
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Holiday',
        'event_name' => 'Diwali Vacation',
        'start_date' => '2026-10-25',
        'end_date' => '2026-11-08',
        'semester' => null,
        'course' => null,
        'description' => 'Diwali break for all faculty and students.'
    ],
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Exam Period',
        'event_name' => 'End Semester University Examinations',
        'start_date' => '2026-11-20',
        'end_date' => '2026-12-05',
        'semester' => null,
        'course' => null,
        'description' => 'GLS University terminal theory & practical exams.'
    ],
    [
        'academic_session' => '2026-2027',
        'event_type' => 'Result Publication',
        'event_name' => 'Declaration of Semester Examination Results',
        'start_date' => '2026-12-28',
        'end_date' => '2026-12-28',
        'semester' => null,
        'course' => null,
        'description' => 'Online results published on Student Portal.'
    ]
];

$stmt = $pdo->prepare("INSERT INTO academic_calendar (academic_session, event_type, event_name, start_date, end_date, semester, course, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($events as $e) {
    $stmt->execute([
        $e['academic_session'],
        $e['event_type'],
        $e['event_name'],
        $e['start_date'],
        $e['end_date'],
        $e['semester'],
        $e['course'],
        $e['description']
    ]);
}

echo "Successfully seeded " . count($events) . " calendar events for academic_calendar.\n";
