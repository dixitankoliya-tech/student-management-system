<?php
/**
 * SMS 2.0: Dean - Department Reports
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';
require_once '../includes/faculty_role_helper.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id']; 

if (!isDean($pdo, $faculty_id)) {
    http_response_code(403);
    die('<div style="text-align:center;padding:60px;"><h1>403 Access Denied</h1></div>');
}

// Analytics Queries
// A. Student Statistics
$total_students = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$alumni_students = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Alumni'")->fetchColumn();

// Course-wise students
$course_students = $pdo->query("
    SELECT course, COUNT(*) as count 
    FROM students WHERE status = 'Active' 
    GROUP BY course ORDER BY course
")->fetchAll(PDO::FETCH_ASSOC);

// B. Academic Performance
// We query the promotion_history for pass/fail/ATKT
$pass_count = $pdo->query("SELECT COUNT(*) FROM promotion_history WHERE promotion_status = 'Promoted'")->fetchColumn();
$atkt_count = $pdo->query("SELECT COUNT(*) FROM promotion_history WHERE promotion_status = 'Promoted with ATKT'")->fetchColumn();
$fail_count = $pdo->query("SELECT COUNT(*) FROM promotion_history WHERE promotion_status = 'Not Promoted'")->fetchColumn();
$total_eval = $pass_count + $atkt_count + $fail_count;
$pass_pct = $total_eval > 0 ? round(($pass_count / $total_eval) * 100, 1) : 0;
$atkt_pct = $total_eval > 0 ? round(($atkt_count / $total_eval) * 100, 1) : 0;
$fail_pct = $total_eval > 0 ? round(($fail_count / $total_eval) * 100, 1) : 0;

$avg_sgpa = $pdo->query("SELECT AVG(sgpa) FROM student_academic_enrollments WHERE sgpa > 0")->fetchColumn();
$avg_cgpa = $pdo->query("SELECT AVG(cgpa) FROM students WHERE cgpa > 0")->fetchColumn();

// C. Attendance
$attendance = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status='Present' THEN 1 END) as p_count,
        COUNT(*) as total_count
    FROM attendance
")->fetch(PDO::FETCH_ASSOC);
$overall_attendance = $attendance['total_count'] > 0 ? round(($attendance['p_count'] / $attendance['total_count']) * 100, 1) : 0;

// D. Examination
$upcoming_exams = $pdo->query("SELECT COUNT(*) FROM examinations WHERE status = 'Scheduled' OR status = 'Active'")->fetchColumn();
$completed_exams = $pdo->query("SELECT COUNT(*) FROM examinations WHERE status IN ('Completed', 'Results Published')")->fetchColumn();

// E. Faculty
$total_faculty = $pdo->query("SELECT COUNT(*) FROM faculty WHERE status = 'Active'")->fetchColumn();
$faculty_workload = $pdo->query("
    SELECT f.first_name, f.last_name, COUNT(fs.id) as assigned_classes 
    FROM faculty f 
    LEFT JOIN faculty_subjects fs ON f.id = fs.faculty_id 
    WHERE f.status = 'Active' 
    GROUP BY f.id 
    ORDER BY assigned_classes DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// F. Fees
$fees = $pdo->query("
    SELECT 
        SUM(total_amount) as assigned, 
        SUM(paid_amount) as paid, 
        SUM(pending_amount) as pending 
    FROM fee_invoices
")->fetch(PDO::FETCH_ASSOC);

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Department Reports</h2>
        <p class="text-muted mb-0">FCAIT Analytics & Reporting</p>
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
                <p class="mb-0">Active Faculty</p>
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

    <!-- Course Distribution -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-users text-primary me-2"></i>Student Distribution</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Course</th><th class="text-end">Students</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_students as $cs): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($cs['course']) ?></td>
                                <td class="text-end"><?= $cs['count'] ?></td>
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
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-rupee-sign text-success me-2"></i>Fee Collection</h5>
            </div>
            <div class="card-body p-4">
                <div class="row text-center g-3">
                    <div class="col-6">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
                            <div class="text-muted small mb-1 text-dark">Total Paid</div>
                            <div class="fw-bold fs-5">₹<?= number_format((float)$fees['paid']) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3">
                            <div class="text-muted small mb-1 text-dark">Total Pending</div>
                            <div class="fw-bold fs-5">₹<?= number_format((float)$fees['pending']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Exams & Notifications -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-file-alt text-info me-2"></i>Examinations</h5>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Upcoming / Active Exams
                        <span class="badge bg-primary rounded-pill"><?= $upcoming_exams ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Completed / Published
                        <span class="badge bg-success rounded-pill"><?= $completed_exams ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
