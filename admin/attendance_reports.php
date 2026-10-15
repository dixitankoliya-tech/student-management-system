<?php
/**
 * SMS 2.0: Admin Attendance Reports
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();
requireRole('admin');

$course = isset($_GET['course']) ? $_GET['course'] : '';
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Get unique courses
$courses = $pdo->query("SELECT DISTINCT course FROM students ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

$report_data = [];
$error = '';
if ($start_date && $end_date && $start_date > $end_date) {
    $error = 'Start date cannot be after end date.';
} elseif ($course && $semester) {
    // Generate the report: group by student, count total sessions, present, absent, leave, calculate percentage
    $sql = "
        SELECT 
            s.id, s.enrollment_no, s.first_name, s.last_name, sae.division,
            COUNT(a.id) as total_sessions,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_count
        FROM students s
        JOIN student_academic_enrollments sae ON s.id = sae.student_id AND sae.status = 'Active'
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
        WHERE sae.course = ? AND sae.semester = ?
        GROUP BY s.id, s.enrollment_no, s.first_name, s.last_name, sae.division
        ORDER BY s.enrollment_no ASC
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date, $course, $semester]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Failed to generate attendance report. Please check query parameters.';
    }
}

if ($export === 'csv' && count($report_data) > 0 && empty($error)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Attendance_Report_'.str_replace(' ', '_', $course).'_Sem'.$semester.'_'.date('Ymd').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Enrollment No', 'Student Name', 'Division', 'Total Sessions', 'Present', 'Absent', 'Leave', 'Percentage']);
    foreach ($report_data as $row) {
        $percentage = $row['total_sessions'] > 0 ? round(($row['present_count'] / $row['total_sessions']) * 100, 2) : 0;
        fputcsv($output, [
            $row['enrollment_no'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['division'],
            $row['total_sessions'],
            $row['present_count'],
            $row['absent_count'],
            $row['leave_count'],
            $percentage . '%'
        ]);
    }
    fclose($output);
    exit();
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Attendance Reports</li>
    </ol>
</nav>

<div class="mb-4">
    <h2 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>Attendance Reports</h2>
    <p class="text-muted mb-0">Generate aggregate attendance analytics by class</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end" id="reportForm">
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase fw-bold">Course</label>
                            <select name="course" class="form-select" required>
                                <option value="">Select Course...</option>
                                <?php foreach($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $course === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase fw-bold">Semester</label>
                            <select name="semester" class="form-select" required>
                                <option value="">Select Semester...</option>
                                <?php for($i=1; $i<=8; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $semester == $i ? 'selected' : ''; ?>>Semester <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="fas fa-chart-bar me-2"></i>Generate</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($course && $semester): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-table me-2 text-primary"></i> Attendance Summary</h5>
                    <?php if (count($report_data) > 0): ?>
                        <a href="?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&course=<?php echo urlencode($course); ?>&semester=<?php echo $semester; ?>&export=csv" class="btn btn-success btn-sm fw-bold">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="px-4 fw-bold text-uppercase">Enrollment No</th>
                                    <th class="fw-bold text-uppercase">Student Name</th>
                                    <th class="fw-bold text-uppercase">Div</th>
                                    <th class="fw-bold text-uppercase text-center">Total Sessions</th>
                                    <th class="fw-bold text-uppercase text-center text-success">Present</th>
                                    <th class="fw-bold text-uppercase text-center text-danger">Absent</th>
                                    <th class="fw-bold text-uppercase text-center text-warning">Leave</th>
                                    <th class="px-4 fw-bold text-uppercase text-end">Attendance %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($report_data) > 0): ?>
                                    <?php foreach($report_data as $row): 
                                        $percentage = $row['total_sessions'] > 0 ? round(($row['present_count'] / $row['total_sessions']) * 100, 2) : 0;
                                        $badge_color = 'bg-success';
                                        if ($percentage < 75) $badge_color = 'bg-danger';
                                        elseif ($percentage < 85) $badge_color = 'bg-warning text-dark';
                                    ?>
                                        <tr>
                                            <td class="px-4 fw-medium text-dark"><?php echo htmlspecialchars($row['enrollment_no']); ?></td>
                                            <td class="fw-bold text-muted"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['division'] ?? '-'); ?></td>
                                            <td class="text-center fw-medium"><?php echo $row['total_sessions']; ?></td>
                                            <td class="text-center text-success fw-bold"><?php echo $row['present_count']; ?></td>
                                            <td class="text-center text-danger fw-bold"><?php echo $row['absent_count']; ?></td>
                                            <td class="text-center text-warning fw-bold"><?php echo $row['leave_count']; ?></td>
                                            <td class="px-4 text-end">
                                                <span class="badge <?php echo $badge_color; ?> rounded-pill px-3 py-2" style="width: 70px;">
                                                    <?php echo $percentage; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-clipboard-list fs-3 mb-3 opacity-25"></i>
                                            <h5>No data available</h5>
                                            <p class="small">No attendance records found for this criteria.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

<?php include '../includes/footer.php'; ?>
