<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
require_once '../includes/academic_calculator.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 1; // 1=FY, 2=SY, 3=TY

if (!$student_id) {
    header("Location: students.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: 404.php");
    exit();
}

$academic_data = calculateStudentCGPA($pdo, $student_id);

$sem1 = ($year * 2) - 1;
$sem2 = $year * 2;

$semester_1_data = $academic_data['semester_results'][$sem1] ?? null;
$semester_2_data = $academic_data['semester_results'][$sem2] ?? null;

$yearNames = [1 => 'First Year (F.Y.)', 2 => 'Second Year (S.Y.)', 3 => 'Third Year (T.Y.)', 4 => 'Fourth Year', 5 => 'Fifth Year'];
$yearName = $yearNames[$year] ?? "$year Year";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['enrollment_no']); ?> - <?php echo $yearName; ?> Marksheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; color: #000; font-family: Arial, sans-serif; }
        .marksheet-container { max-width: 1000px; margin: 2rem auto; border: 2px solid #000; padding: 2rem; position: relative; }
        .header-title { font-weight: bold; text-align: center; font-size: 24px; text-transform: uppercase; }
        .sub-header { text-align: center; font-size: 16px; margin-bottom: 2rem; }
        .student-info th { text-align: left; padding: 4px 10px; border: 1px solid #000; }
        .student-info td { padding: 4px 10px; border: 1px solid #000; }
        .marks-table th, .marks-table td { border: 1px solid #000; text-align: center; padding: 6px; }
        .marks-table th { background-color: #f0f0f0; }
        .table-title { font-weight: bold; background: #e0e0e0; text-align: left; padding-left: 10px; }
        .footer-sig { margin-top: 4rem; display: flex; justify-content: space-between; }
        .footer-sig div { border-top: 1px solid #000; width: 25%; text-align: center; padding-top: 5px; }
        @media print {
            .no-print { display: none !important; }
            .marksheet-container { border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-3 text-center mb-3">
    <button onclick="window.print()" class="btn btn-primary">Print Marksheet</button>
</div>

<div class="marksheet-container">
    <div class="header-title">GLS UNIVERSITY</div>
    <div class="sub-header">
        Faculty of Computer Applications & Information Technology (FCAIT)<br>
        <strong>Statement of Marks for <?php echo htmlspecialchars($student['course']); ?> - <?php echo $yearName; ?></strong>
    </div>

    <table class="table table-borderless student-info w-100 mb-4">
        <tr>
            <th style="width: 15%">Name:</th>
            <td style="width: 35%"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
            <th style="width: 15%">Enrollment No:</th>
            <td style="width: 35%"><strong><?php echo htmlspecialchars($student['enrollment_no']); ?></strong></td>
        </tr>
        <tr>
            <th>Course:</th>
            <td><?php echo htmlspecialchars($student['course']); ?></td>
            <th>Academic Year:</th>
            <td>2024-2025</td>
        </tr>
    </table>

    <table class="table marks-table w-100">
        <thead>
            <tr>
                <th rowspan="2" style="width: 10%">Subject Code</th>
                <th rowspan="2" style="width: 35%">Subject Name</th>
                <th rowspan="2" style="width: 10%">Credits</th>
                <th colspan="3">Marks Obtained / Maximum Marks</th>
                <th rowspan="2" style="width: 10%">Grade</th>
                <th rowspan="2" style="width: 10%">Grade Point</th>
            </tr>
            <tr>
                <th>Internal</th>
                <th>External</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <!-- Semester 1 -->
            <tr>
                <td colspan="8" class="table-title">SEMESTER <?php echo $sem1; ?></td>
            </tr>
            <?php if ($semester_1_data && !empty($semester_1_data['subjects'])): ?>
                <?php foreach ($semester_1_data['subjects'] as $sub): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                    <td><?php echo $sub['credits']; ?></td>
                    <td><?php echo $sub['internal_marks'] ?? '-'; ?></td>
                    <td><?php echo $sub['external_marks'] ?? '-'; ?></td>
                    <td><strong><?php echo $sub['marks_obtained']; ?> / <?php echo $sub['max_marks']; ?></strong></td>
                    <td><?php echo $sub['grade']; ?></td>
                    <td><?php echo $sub['grade_point']; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #f9f9f9; font-weight: bold;">
                    <td colspan="5" class="text-end">Semester <?php echo $sem1; ?> Total:</td>
                    <td><?php echo $semester_1_data['total_obtained']; ?> / <?php echo $semester_1_data['total_max']; ?></td>
                    <td colspan="2">SGPA: <?php echo $semester_1_data['sgpa']; ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="8">No data available for Semester <?php echo $sem1; ?></td></tr>
            <?php endif; ?>

            <!-- Semester 2 -->
            <tr>
                <td colspan="8" class="table-title">SEMESTER <?php echo $sem2; ?></td>
            </tr>
            <?php if ($semester_2_data && !empty($semester_2_data['subjects'])): ?>
                <?php foreach ($semester_2_data['subjects'] as $sub): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sub['subject_code']); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($sub['subject_name']); ?></td>
                    <td><?php echo $sub['credits']; ?></td>
                    <td><?php echo $sub['internal_marks'] ?? '-'; ?></td>
                    <td><?php echo $sub['external_marks'] ?? '-'; ?></td>
                    <td><strong><?php echo $sub['marks_obtained']; ?> / <?php echo $sub['max_marks']; ?></strong></td>
                    <td><?php echo $sub['grade']; ?></td>
                    <td><?php echo $sub['grade_point']; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #f9f9f9; font-weight: bold;">
                    <td colspan="5" class="text-end">Semester <?php echo $sem2; ?> Total:</td>
                    <td><?php echo $semester_2_data['total_obtained']; ?> / <?php echo $semester_2_data['total_max']; ?></td>
                    <td colspan="2">SGPA: <?php echo $semester_2_data['sgpa']; ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="8">No data available for Semester <?php echo $sem2; ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="table marks-table w-100 mt-4">
        <tr>
            <th>CGPA (Cumulative)</th>
            <td style="font-size: 1.2rem; font-weight: bold;"><?php echo $academic_data['cgpa']; ?></td>
            <th>Result Status</th>
            <td style="font-size: 1.2rem; font-weight: bold;">
                <?php 
                $status1 = $semester_1_data ? $semester_1_data['result_status'] : 'N/A';
                $status2 = $semester_2_data ? $semester_2_data['result_status'] : 'N/A';
                if ($status1 === 'Fail' || $status2 === 'Fail') echo 'ATKT / FAIL';
                else if ($status1 === 'Pass' && $status2 === 'Pass') echo 'PASS';
                else echo 'INCOMPLETE';
                ?>
            </td>
        </tr>
    </table>

    <div class="footer-sig">
        <div>Prepared By</div>
        <div>Checked By</div>
        <div>Controller of Examinations</div>
    </div>
</div>

</body>
</html>
