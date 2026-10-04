<?php
/**
 * SMS 2.0: Print Hall Tickets
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if (!$exam_id) {
    die("Invalid Examination.");
}

// Fetch Examination Details
$stmt = $pdo->prepare("SELECT * FROM examinations WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    die("Examination not found.");
}

// Fetch Schedules
$stmt = $pdo->prepare("
    SELECT es.*, s.subject_code, s.subject_name 
    FROM exam_schedules es
    JOIN subjects s ON es.subject_id = s.id
    WHERE es.exam_id = ?
    ORDER BY es.exam_date ASC, es.start_time ASC
");
$stmt->execute([$exam_id]);
$schedules = $stmt->fetchAll();

if (empty($schedules)) {
    die("No timetable configured for this examination. Please configure the timetable first.");
}

// Fetch active students for this course and semester
$stmt = $pdo->prepare("
    SELECT st.* 
    FROM students st
    JOIN student_academic_enrollments sae ON st.id = sae.student_id AND sae.status = 'Active'
    WHERE sae.course = ? AND sae.semester = ? AND sae.academic_year = ?
    ORDER BY st.enrollment_no ASC
");
$stmt->execute([$exam['course'], $exam['semester'], $exam['academic_year']]);
$students = $stmt->fetchAll();

if (empty($students)) {
    die("No active students found for this course and semester.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hall Tickets - <?= htmlspecialchars($exam['exam_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-size: 14px; }
        .hall-ticket {
            background-color: #fff;
            border: 2px solid #000;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .ht-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .ht-header h2 { margin: 0; font-weight: bold; font-size: 24px; text-transform: uppercase; }
        .ht-header h4 { margin: 5px 0 0; font-size: 18px; }
        .ht-header p { margin: 5px 0 0; font-size: 14px; font-weight: bold; }
        .student-details { margin-bottom: 15px; }
        .student-details th { width: 120px; text-align: left; padding: 4px 0; }
        .student-details td { font-weight: bold; padding: 4px 0; }
        .timetable-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .timetable-table th, .timetable-table td { border: 1px solid #000; padding: 8px; text-align: center; }
        .timetable-table th { background-color: #f2f2f2; font-weight: bold; }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 10px;
        }
        .signatures div {
            border-top: 1px dashed #000;
            padding-top: 5px;
            width: 200px;
            text-align: center;
            font-weight: bold;
        }
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        @media print {
            body { background-color: #fff; }
            .print-btn { display: none; }
            .hall-ticket { border: 1px solid #000; box-shadow: none; margin-bottom: 40px; page-break-after: always; }
        }
    </style>
</head>
<body>
    <button class="btn btn-primary btn-lg print-btn shadow" onclick="window.print()"><i class="fas fa-print me-2"></i> Print Hall Tickets</button>
    
    <div class="container py-4">
        <?php foreach ($students as $student): ?>
        <div class="hall-ticket">
            <div class="ht-header">
                <h2>GLS UNIVERSITY</h2>
                <h4>FCAIT DEPARTMENT</h4>
                <p>HALL TICKET - <?= htmlspecialchars($exam['exam_name']) ?></p>
            </div>
            
            <div class="row student-details">
                <div class="col-8">
                    <table class="table-borderless w-100">
                        <tr>
                            <th>Enrollment No:</th>
                            <td><?= htmlspecialchars($student['enrollment_no']) ?></td>
                        </tr>
                        <tr>
                            <th>Student Name:</th>
                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Program:</th>
                            <td><?= htmlspecialchars($exam['course']) ?> (Sem <?= $exam['semester'] ?>)</td>
                        </tr>
                        <tr>
                            <th>Academic Year:</th>
                            <td><?= htmlspecialchars($exam['academic_year']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-4 text-end">
                    <div style="width: 100px; height: 120px; border: 1px solid #000; display: inline-flex; align-items: center; justify-content: center; background-color: #eee;">
                        <span class="text-muted small">Passport Photo</span>
                    </div>
                </div>
            </div>
            
            <table class="timetable-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Venue/Room</th>
                        <th>Invigilator Sign</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td><?= date('d-m-Y', strtotime($s['exam_date'])) ?></td>
                        <td><?= date('h:i A', strtotime($s['start_time'])) ?> to <?= date('h:i A', strtotime($s['end_time'])) ?></td>
                        <td><?= htmlspecialchars($s['subject_code']) ?></td>
                        <td><?= htmlspecialchars($s['subject_name']) ?></td>
                        <td><?= htmlspecialchars($s['room_no'] ?: 'TBA') ?></td>
                        <td></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="signatures">
                <div>Student Signature</div>
                <div>Controller of Examinations</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
