<?php
/**
 * SMS 2.0: Advanced Configurable Reports
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

$courses = $pdo->query("SELECT * FROM courses WHERE status = 'Active' ORDER BY course_code")->fetchAll();

$selected_course = $_GET['course'] ?? '';
$selected_semester = $_GET['semester'] ?? '';
$export_csv = isset($_GET['export']) && $_GET['export'] == 'csv';

$reportData = [];
if ($selected_course && $selected_semester) {
    // We aggregate all students in this course and semester
    $stmt = $pdo->prepare("
        SELECT s.enrollment_no, s.first_name, s.last_name, s.division, 
               SUM(sm.total_marks) as total_obtained, 
               COUNT(sm.id) * 100 as total_max 
        FROM students s
        LEFT JOIN student_marks sm ON s.id = sm.student_id AND sm.semester = s.semester
        WHERE s.course = ? AND s.semester = ? AND s.status = 'Active'
        GROUP BY s.id
        ORDER BY total_obtained DESC
    ");
    $stmt->execute([$selected_course, $selected_semester]);
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($export_csv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="academic_report_' . $selected_course . '_sem' . $selected_semester . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Enrollment No', 'First Name', 'Last Name', 'Division', 'Total Obtained', 'Max Marks', 'Percentage']);
        foreach ($reportData as $row) {
            $percentage = $row['total_max'] > 0 ? round(($row['total_obtained'] / $row['total_max']) * 100, 2) : 0;
            fputcsv($output, [
                $row['enrollment_no'],
                $row['first_name'],
                $row['last_name'],
                $row['division'],
                $row['total_obtained'] ?? 0,
                $row['total_max'] ?? 0,
                $percentage . '%'
            ]);
        }
        fclose($output);
        exit();
    }
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 text-gray-800"><i class="fas fa-chart-line text-primary me-2"></i>Advanced Academic Reports</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Select Course</label>
                <select name="course" class="form-select" required>
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['course_name']); ?>" <?php echo $selected_course === $c['course_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Semester</label>
                <select name="semester" class="form-select" required>
                    <option value="">-- Semester --</option>
                    <?php for($i=1; $i<=10; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $selected_semester == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Generate Report</button>
                <?php if ($selected_course && $selected_semester): ?>
                    <button type="submit" name="export" value="csv" class="btn btn-success"><i class="fas fa-file-csv me-1"></i> Export to CSV</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_course && $selected_semester): ?>
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-bold text-primary">Report Data: <?php echo htmlspecialchars($selected_course); ?> - Semester <?php echo htmlspecialchars($selected_semester); ?></h6>
    </div>
    <div class="card-body">
        <?php if (count($reportData) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Enrollment No</th>
                            <th>Student Name</th>
                            <th>Division</th>
                            <th>Total Obtained</th>
                            <th>Max Marks</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): 
                            $percentage = $row['total_max'] > 0 ? round(($row['total_obtained'] / $row['total_max']) * 100, 2) : 0;
                        ?>
                        <tr>
                            <td class="font-monospace fw-bold"><?php echo htmlspecialchars($row['enrollment_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><span class="badge bg-secondary">Div <?php echo htmlspecialchars($row['division']); ?></span></td>
                            <td><strong><?php echo $row['total_obtained'] ?? 0; ?></strong></td>
                            <td><?php echo $row['total_max'] ?? 0; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2"><?php echo $percentage; ?>%</span>
                                    <div class="progress" style="width: 50px; height: 5px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info border-0 shadow-sm text-center py-4 mb-0">
                <i class="fas fa-info-circle fs-4 mb-2 d-block text-info"></i>
                No data available for this criteria.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
