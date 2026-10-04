<?php
/**
 * SMS 2.0: Coordinator - Course Reports
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../includes/faculty_role_helper.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id']; 

if (!isCoordinator($pdo, $faculty_id)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:60px;"><h1>403 Access Denied</h1></div>');
}

$role_info = getFacultyRoleInfo($pdo, $faculty_id);
if (empty($role_info['coordinated_course_names'])) {
    die("No course assigned.");
}

$course_name = $role_info['coordinated_course_names'][0];

// Analytics Queries Scoped to Course
// A. Student Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE course = ? AND status = 'Active'");
$stmt->execute([$course_name]);
$total_students = $stmt->fetchColumn();

// Semester-wise students
$stmt = $pdo->prepare("
    SELECT semester, COUNT(*) as count 
    FROM students WHERE course = ? AND status = 'Active' 
    GROUP BY semester ORDER BY semester
");
$stmt->execute([$course_name]);
$semester_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// B. Academic Performance
// We query the promotion_history for pass/fail/ATKT
$stmt = $pdo->prepare("
    SELECT promotion_status, COUNT(*) as count 
    FROM promotion_history ph
    JOIN students s ON ph.student_id = s.id
    WHERE s.course = ?
    GROUP BY promotion_status
");
$stmt->execute([$course_name]);
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pass_count = 0; $atkt_count = 0; $fail_count = 0;
foreach ($promotions as $p) {
    if ($p['promotion_status'] == 'Promoted') $pass_count = $p['count'];
    if ($p['promotion_status'] == 'Promoted with ATKT') $atkt_count = $p['count'];
    if ($p['promotion_status'] == 'Not Promoted') $fail_count = $p['count'];
}

$total_eval = $pass_count + $atkt_count + $fail_count;
$pass_pct = $total_eval > 0 ? round(($pass_count / $total_eval) * 100, 1) : 0;
$atkt_pct = $total_eval > 0 ? round(($atkt_count / $total_eval) * 100, 1) : 0;
$fail_pct = $total_eval > 0 ? round(($fail_count / $total_eval) * 100, 1) : 0;

$stmt = $pdo->prepare("
    SELECT AVG(sae.sgpa) 
    FROM student_academic_enrollments sae
    JOIN students s ON sae.student_id = s.id
    WHERE s.course = ? AND sae.sgpa > 0
");
$stmt->execute([$course_name]);
$avg_sgpa = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(cgpa) FROM students WHERE course = ? AND cgpa > 0");
$stmt->execute([$course_name]);
$avg_cgpa = $stmt->fetchColumn();

// C. Attendance
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN a.status='Present' THEN 1 END) as p_count,
        COUNT(*) as total_count
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE s.course = ?
");
$stmt->execute([$course_name]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);
$overall_attendance = $attendance['total_count'] > 0 ? round(($attendance['p_count'] / $attendance['total_count']) * 100, 1) : 0;

// E. Faculty
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT faculty_id) FROM faculty_subjects WHERE course = ?");
$stmt->execute([$course_name]);
$total_faculty = $stmt->fetchColumn();

// F. Fees
$stmt = $pdo->prepare("
    SELECT 
        SUM(fi.total_amount) as assigned, 
        SUM(fi.paid_amount) as paid, 
        SUM(fi.pending_amount) as pending 
    FROM fee_invoices fi
    JOIN students s ON fi.student_id = s.id
    WHERE s.course = ?
");
$stmt->execute([$course_name]);
$fees = $stmt->fetch(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Course Reports: <?= htmlspecialchars($course_name) ?></h2>
        <p class="text-muted mb-0">Detailed Analytics for your assigned course</p>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Report</button>
    </div>
</header>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-primary bg-opacity-10 text-primary">
            <div class="card-body p-4 text-center">
                <i class="fas fa-user-graduate fs-1 mb-3"></i>
                <h3 class="fw-bold mb-0"><?= number_format((float)$total_students) ?></h3>
                <p class="mb-0">Active Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-success bg-opacity-10 text-success">
            <div class="card-body p-4 text-center">
                <i class="fas fa-chalkboard-teacher fs-1 mb-3"></i>
                <h3 class="fw-bold mb-0"><?= number_format((float)$total_faculty) ?></h3>
                <p class="mb-0">Assigned Faculty</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-warning bg-opacity-10 text-warning">
            <div class="card-body p-4 text-center">
                <i class="fas fa-chart-line fs-1 mb-3"></i>
                <h3 class="fw-bold mb-0"><?= number_format((float)$avg_cgpa, 2) ?></h3>
                <p class="mb-0">Average CGPA</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-info bg-opacity-10 text-info">
            <div class="card-body p-4 text-center">
                <i class="fas fa-calendar-check fs-1 mb-3"></i>
                <h3 class="fw-bold mb-0"><?= $overall_attendance ?>%</h3>
                <p class="mb-0">Avg Attendance</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Academic Performance -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-trophy text-warning me-2"></i>Academic Performance</h5>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-2">
                    <span>Promoted / Pass</span>
                    <span class="fw-bold text-success"><?= $pass_pct ?>%</span>
                </div>
                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= $pass_pct ?>%"></div>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span>Promoted with ATKT</span>
                    <span class="fw-bold text-warning"><?= $atkt_pct ?>%</span>
                </div>
                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar bg-warning" style="width: <?= $atkt_pct ?>%"></div>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span>Not Promoted / Fail</span>
                    <span class="fw-bold text-danger"><?= $fail_pct ?>%</span>
                </div>
                <div class="progress mb-4" style="height: 8px;">
                    <div class="progress-bar bg-danger" style="width: <?= $fail_pct ?>%"></div>
                </div>
                
                <div class="row text-center mt-4">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small">Avg SGPA</div>
                            <div class="fw-bold fs-4"><?= number_format((float)$avg_sgpa, 2) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small">Total Evals</div>
                            <div class="fw-bold fs-4"><?= number_format((float)$total_eval) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Semester Distribution -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-layer-group text-primary me-2"></i>Semester Distribution</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Semester</th><th class="text-end">Students</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($semester_students as $sem): ?>
                            <tr>
                                <td class="fw-bold">Semester <?= htmlspecialchars($sem['semester']) ?></td>
                                <td class="text-end"><span class="badge bg-secondary rounded-pill"><?= $sem['count'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Fee Overview -->
    <div class="col-md-12">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-rupee-sign text-success me-2"></i>Fee Collection Overview</h5>
            </div>
            <div class="card-body p-4">
                <div class="row text-center g-3">
                    <div class="col-4">
                        <div class="p-3 bg-light rounded-3">
                            <div class="text-muted small mb-1">Total Assigned</div>
                            <div class="fw-bold fs-5 text-dark">₹<?= number_format((float)$fees['assigned']) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
                            <div class="text-muted small mb-1 text-dark">Total Paid</div>
                            <div class="fw-bold fs-5">₹<?= number_format((float)$fees['paid']) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3">
                            <div class="text-muted small mb-1 text-dark">Total Pending</div>
                            <div class="fw-bold fs-5">₹<?= number_format((float)$fees['pending']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
