<?php
/**
 * SMS 2.0: Student Attendance
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch subject-wise attendance for the student
$attStmt = $pdo->prepare("
    SELECT 
        s.id,
        s.subject_name,
        s.subject_code,
        COUNT(a.id) as total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_count
    FROM attendance a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.student_id = ? AND s.course = ? AND s.semester = ? AND a.division = ?
    GROUP BY s.id, s.subject_name, s.subject_code
    ORDER BY s.subject_name
");
$attStmt->execute([$student_id, $student['course'], $student['semester'], $student['division']]);
$subject_attendance = $attStmt->fetchAll();

require_once '../includes/academic_calculator.php';
$attendance_data = calculateAttendancePercentage($pdo, $student_id);

$overall_total = $attendance_data['total_lectures'];
$overall_present = $attendance_data['present_lectures'];
$overall_absent = $attendance_data['absent_lectures'];
$overall_leave = $overall_total - $overall_present - $overall_absent; // Approximation or adjust if 'leave' is stored.
$overall_pct = $attendance_data['percentage'];
$attendance_status = $attendance_data['status'];

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Attendance</h2>
        <p class="text-muted mb-0">Track your overall and subject-wise attendance</p>
    </div>
</div>

<?php if ($overall_total > 0 && $attendance_status === 'SHORTAGE'): ?>
    <div class="alert alert-danger d-flex align-items-center py-3 rounded-3 shadow-sm border-0 mb-4">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div>
            <h5 class="alert-heading mb-1 fw-bold">Academic Alert</h5>
            <p class="mb-0">Your overall attendance (<?php echo $overall_pct; ?>%) is below the required threshold. Please meet with your faculty advisor.</p>
        </div>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-primary text-white">
            <div class="card-body p-4 text-center">
                <h6 class="text-white-50 text-uppercase fw-bold mb-2">Overall Attendance</h6>
                <h1 class="display-5 fw-bold mb-0"><?php echo $overall_pct; ?>%</h1>
                <div class="mt-3">
                    <span class="badge bg-white text-primary"><?php echo $overall_present; ?> / <?php echo $overall_total; ?> Classes</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-9 col-md-6">
        <div class="row h-100">
            <div class="col-sm-4 mb-3 mb-sm-0">
                <div class="card border-0 shadow-sm h-100 py-3 rounded-4">
                    <div class="card-body text-center">
                        <div class="text-success mb-2"><i class="fas fa-check-circle fa-2x"></i></div>
                        <h5 class="fw-bold text-dark mb-0"><?php echo $overall_present; ?></h5>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Total Present</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 mb-3 mb-sm-0">
                <div class="card border-0 shadow-sm h-100 py-3 rounded-4">
                    <div class="card-body text-center">
                        <div class="text-danger mb-2"><i class="fas fa-times-circle fa-2x"></i></div>
                        <h5 class="fw-bold text-dark mb-0"><?php echo $overall_absent; ?></h5>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Total Absent</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card border-0 shadow-sm h-100 py-3 rounded-4">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2"><i class="fas fa-minus-circle fa-2x"></i></div>
                        <h5 class="fw-bold text-dark mb-0"><?php echo $overall_leave; ?></h5>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Total Leave</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 border-bottom border-light">
        <h6 class="m-0 fw-bold text-dark">Subject-wise Breakdown</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted">
                    <tr>
                        <th class="ps-4">Subject</th>
                        <th class="text-center">Total Classes</th>
                        <th class="text-center text-success">Present</th>
                        <th class="text-center text-danger">Absent</th>
                        <th class="text-center text-warning">Leave</th>
                        <th class="text-end pe-4">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subject_attendance)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-calendar-times fs-3 mb-3 text-light"></i>
                                <p class="mb-0">No attendance records found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subject_attendance as $row): 
                            $pctData = calculateSubjectAttendancePercentage($pdo, $student_id, $row['id'] ?? 0);
                            $pct = $pctData['percentage'];
                            $pctClass = 'success';
                            if ($pctData['status'] === 'SHORTAGE') $pctClass = 'danger';
                            elseif ($pct < 85) $pctClass = 'warning';
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <strong class="text-dark d-block"><?php echo htmlspecialchars($row['subject_name']); ?></strong>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['subject_code']); ?></small>
                                </td>
                                <td class="text-center fw-semibold text-secondary"><?php echo $row['total_classes']; ?></td>
                                <td class="text-center fw-semibold text-success"><?php echo $row['present_count']; ?></td>
                                <td class="text-center fw-semibold text-danger"><?php echo $row['absent_count']; ?></td>
                                <td class="text-center fw-semibold text-warning"><?php echo $row['leave_count']; ?></td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-<?php echo $pctClass; ?>-subtle text-<?php echo $pctClass; ?> fs-6 rounded-pill px-3 py-2">
                                        <?php echo $pct; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
