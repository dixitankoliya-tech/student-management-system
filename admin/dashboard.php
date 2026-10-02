<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

// Fetch REAL statistics
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeStudents = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM student_academic_enrollments WHERE status = 'Active'")->fetchColumn();
$totalFaculty = $pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalDepts = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Academic Overview
$activeExams = $pdo->query("SELECT COUNT(*) FROM examinations WHERE status IN ('Upcoming', 'Ongoing')")->fetchColumn();
$publishedResults = $pdo->query("SELECT COUNT(*) FROM examinations WHERE status = 'Results Published'")->fetchColumn();

// Fetch today's attendance stats
$today = date('Y-m-d');
$attendanceStatsStmt = $pdo->prepare("SELECT 
    COUNT(id) as total_marked,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count
    FROM attendance WHERE attendance_date = ?");
$attendanceStatsStmt->execute([$today]);
$attendanceStats = $attendanceStatsStmt->fetch(PDO::FETCH_ASSOC);

$attendancePercent = ($attendanceStats['total_marked'] > 0) ? round(($attendanceStats['present_count'] / $attendanceStats['total_marked']) * 100, 1) : 0;

// Financial Overview
$totalFees = $pdo->query("SELECT SUM(total_amount) FROM student_fees")->fetchColumn() ?: 0;
$feesCollected = $pdo->query("SELECT SUM(paid_amount) FROM student_fees")->fetchColumn() ?: 0;
$feesPending = $pdo->query("SELECT SUM(pending_amount) FROM student_fees")->fetchColumn() ?: 0;
$collectionPercent = ($totalFees > 0) ? round(($feesCollected / $totalFees) * 100, 1) : 0;

// Recent Fee Collections (last 7 days) for chart
$chartDataQuery = $pdo->query("
    SELECT DATE(payment_date) as pay_date, SUM(amount) as daily_total 
    FROM fee_payments 
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(payment_date)
    ORDER BY pay_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$chartValues = [];
// Initialize last 7 days with 0
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime("-$i days"));
    $chartValues[$date] = 0;
}
foreach ($chartDataQuery as $row) {
    if (isset($chartValues[$row['pay_date']])) {
        $chartValues[$row['pay_date']] = (float)$row['daily_total'];
    }
}
$chartValuesArray = array_values($chartValues);

// Fetch Recent Activity (Audit logs)
$recentLogs = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 6")->fetchAll();

// Fetch Unread Notifications count for dashboard card
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$_SESSION['user_id']]);
$unreadAlerts = $unread_stmt->fetchColumn();

include '../includes/admin_header.php';
?>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Premium ERP Dashboard Styles */
    body { background-color: #f4f6f9; }
    .kpi-card { transition: transform 0.2s, box-shadow 0.2s; border-left: 4px solid transparent; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08)!important; }
    .kpi-card.students { border-left-color: #0d6efd; }
    .kpi-card.faculty { border-left-color: #198754; }
    .kpi-card.attendance { border-left-color: #6f42c1; }
    .kpi-card.finance { border-left-color: #ffc107; }
    
    
    .timeline { position: relative; padding-left: 1.5rem; }
    .timeline::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -1.5rem; top: 0.25rem; width: 12px; height: 12px; border-radius: 50%; background: #0d6efd; border: 2px solid #fff; box-shadow: 0 0 0 2px #e9ecef; transform: translateX(-5px); }
    
    .quick-action-btn { transition: all 0.2s; border-radius: 10px; }
    .quick-action-btn:hover { background-color: #f8f9fa; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    
    .card-header-premium { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.25rem 1.5rem; }
</style>

<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <div id="page-content-wrapper" class="w-100">
        <?php include '../includes/admin_navbar.php'; ?>
        
        <div class="container-fluid py-4 px-4">
            
            <!-- Dashboard Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold" style="letter-spacing: -0.5px;">Institutional Overview</h2>
                    <p class="text-muted mb-0" style="font-size: 0.9rem;">Welcome back. Here is today's summary for GLS University.</p>
                </div>
                <div>
                    <span class="badge bg-white text-dark border py-2 px-3 shadow-sm rounded-pill">
                        <i class="far fa-calendar-alt me-2 text-primary"></i> <?php echo date('l, F j, Y'); ?>
                    </span>
                </div>
            </div>

            <!-- KPI Row 1: Primary Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 kpi-card students">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Students</h6>
                                    <h2 class="mb-0 fw-bolder text-dark"><?php echo number_format($totalStudents); ?></h2>
                                </div>
                                <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-user-graduate fs-5"></i>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                <span class="text-success fw-bold"><i class="fas fa-arrow-up me-1"></i><?php echo number_format($activeStudents); ?> Active</span> out of <?php echo number_format($totalStudents); ?> total across <?php echo $totalCourses; ?> programs
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 kpi-card faculty">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Faculty</h6>
                                    <h2 class="mb-0 fw-bolder text-dark"><?php echo number_format($totalFaculty); ?></h2>
                                </div>
                                <div class="kpi-icon bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-chalkboard-teacher fs-5"></i>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                Assigned across <?php echo $totalDepts; ?> departments
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 kpi-card attendance">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Today's Attendance</h6>
                                    <h2 class="mb-0 fw-bolder text-dark"><?php echo $attendancePercent; ?>%</h2>
                                </div>
                                <div class="kpi-icon bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-calendar-check fs-5"></i>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                <?php if($attendanceStats['total_marked'] > 0): ?>
                                    <span class="text-dark fw-bold"><?php echo $attendanceStats['present_count']; ?></span> present out of <?php echo $attendanceStats['total_marked']; ?> marked
                                <?php else: ?>
                                    No attendance marked today yet.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 kpi-card finance">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Fee Collection</h6>
                                    <h2 class="mb-0 fw-bolder text-dark"><?php echo $collectionPercent; ?>%</h2>
                                </div>
                                <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-rupee-sign fs-5"></i>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                &#8377;<?php echo number_format($feesCollected); ?> collected of &#8377;<?php echo number_format($totalFees); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI Row 2: Secondary / Operations -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-3">
                            <div class="row text-center divide-md">
                                <div class="col-md-2 col-6 border-end py-2">
                                    <h6 class="text-muted mb-1 fw-bold" style="font-size: 0.7rem;">ACTIVE EXAMS</h6>
                                    <h4 class="mb-0 fw-bold text-primary"><?php echo $activeExams; ?></h4>
                                </div>
                                <div class="col-md-2 col-6 border-end py-2">
                                    <h6 class="text-muted mb-1 fw-bold" style="font-size: 0.7rem;">PUBLISHED RESULTS</h6>
                                    <h4 class="mb-0 fw-bold text-success"><?php echo $publishedResults; ?></h4>
                                </div>
                                <div class="col-md-2 col-6 border-end py-2">
                                    <h6 class="text-muted mb-1 fw-bold" style="font-size: 0.7rem;">PROGRAMS</h6>
                                    <h4 class="mb-0 fw-bold text-dark"><?php echo $totalCourses; ?></h4>
                                </div>
                                <div class="col-md-2 col-6 border-end py-2">
                                    <h6 class="text-muted mb-1 fw-bold" style="font-size: 0.7rem;">PENDING FEES</h6>
                                    <h4 class="mb-0 fw-bold text-danger">&#8377;<?php echo number_format($feesPending); ?></h4>
                                </div>
                                <div class="col-md-2 col-6 border-end py-2">
                                    <h6 class="text-muted mb-1 fw-bold" style="font-size: 0.7rem;">UNREAD ALERTS</h6>
                                    <h4 class="mb-0 fw-bold text-warning"><?php echo $unreadAlerts; ?></h4>
                                </div>
                                <div class="col-md-2 col-6 py-2">
                                    <h6 class="text-muted mb-1 fw-bold" style="font-size: 0.7rem;">SYSTEM STATUS</h6>
                                    <h4 class="mb-0 fw-bold text-success"><i class="fas fa-check-circle me-1"></i> OK</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Financial Overview Chart -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header card-header-premium d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line text-primary me-2"></i> Revenue Collection (Last 7 Days)</h5>
                            <a href="fee_reports.php" class="btn btn-sm btn-light border fw-semibold">Detailed Report</a>
                        </div>
                        <div class="card-body p-4">
                            <?php if(array_sum($chartValuesArray) > 0): ?>
                                <canvas id="revenueChart" height="100"></canvas>
                            <?php else: ?>
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
                                    <div class="bg-light rounded-circle p-4 mb-3">
                                        <i class="fas fa-chart-bar fa-2x opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold">No Revenue Data</h6>
                                    <p class="small text-center mb-0">No fee payments were collected in the last 7 days.<br>Once payments are received, the trend chart will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Grid -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header card-header-premium">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-bolt text-warning me-2"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-6">
                                    <a href="add_student.php" class="text-decoration-none text-dark">
                                        <div class="quick-action-btn border p-3 text-center h-100">
                                            <i class="fas fa-user-plus text-primary fa-2x mb-2"></i>
                                            <div class="fw-semibold" style="font-size: 0.85rem;">Admit Student</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="fee_payments.php" class="text-decoration-none text-dark">
                                        <div class="quick-action-btn border p-3 text-center h-100">
                                            <i class="fas fa-hand-holding-usd text-success fa-2x mb-2"></i>
                                            <div class="fw-semibold" style="font-size: 0.85rem;">Collect Fee</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="attendance.php" class="text-decoration-none text-dark">
                                        <div class="quick-action-btn border p-3 text-center h-100">
                                            <i class="fas fa-calendar-check text-purple fa-2x mb-2" style="color: #6f42c1;"></i>
                                            <div class="fw-semibold" style="font-size: 0.85rem;">Mark Attendance</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="announcements.php" class="text-decoration-none text-dark">
                                        <div class="quick-action-btn border p-3 text-center h-100">
                                            <i class="fas fa-bullhorn text-danger fa-2x mb-2"></i>
                                            <div class="fw-semibold" style="font-size: 0.85rem;">Announcement</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12">
                                    <a href="manage_marks.php" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                                        <i class="fas fa-edit me-2"></i> Upload Examination Marks
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Academic Updates -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header card-header-premium d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-university text-primary me-2"></i> Academic Operations</h5>
                        </div>
                        <div class="card-body p-4">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-bottom">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded p-2 me-3 text-primary"><i class="fas fa-file-alt fa-fw"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">Active Examinations</div>
                                            <div class="small text-muted">Currently ongoing or upcoming</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $activeExams; ?></span>
                                </li>
                                <li class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-bottom">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded p-2 me-3 text-success"><i class="fas fa-award fa-fw"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">Results Published</div>
                                            <div class="small text-muted">Finalized exam results</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-success rounded-pill px-3 py-2"><?php echo $publishedResults; ?></span>
                                </li>
                                <li class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded p-2 me-3" style="color: #6f42c1;"><i class="fas fa-calendar-times fa-fw"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">Absentees Today</div>
                                            <div class="small text-muted">Students marked absent across all classes</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo $attendanceStats['absent_count'] ?? 0; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header card-header-premium d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-secondary me-2"></i> System Activity Feed</h5>
                            <a href="audit_logs.php" class="btn btn-sm btn-outline-secondary fw-semibold">View Logs</a>
                        </div>
                        <div class="card-body p-4 pt-4">
                            <?php if (count($recentLogs) > 0): ?>
                                <div class="timeline">
                                    <?php foreach ($recentLogs as $log): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($log['action']); ?></div>
                                            <div class="text-muted small fw-semibold"><?php echo date('h:i A', strtotime($log['created_at'])); ?></div>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($log['description']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-4">
                                    <i class="fas fa-clipboard-list fs-3 mb-3 opacity-25"></i>
                                    <h6 class="fw-bold">No Activity Yet</h6>
                                    <p class="small text-center mb-0">System events, logins, and modifications will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if(array_sum($chartValuesArray) > 0): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Create gradient
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(13, 110, 253, 0.2)');
    gradient.addColorStop(1, 'rgba(13, 110, 253, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Revenue (&#8377;)',
                data: <?php echo json_encode($chartValuesArray); ?>,
                borderColor: '#0d6efd',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#0d6efd',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#fff',
                    titleColor: '#212529',
                    bodyColor: '#212529',
                    borderColor: 'rgba(0,0,0,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed.y !== null) {
                                label += '&#8377;' + new Intl.NumberFormat('en-IN').format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                    ticks: {
                        callback: function(value) {
                            if(value >= 100000) return '&#8377;' + (value/100000).toFixed(1) + 'L';
                            if(value >= 1000) return '&#8377;' + (value/1000).toFixed(1) + 'k';
                            return '&#8377;' + value;
                        },
                        font: { size: 11, family: "'Inter', sans-serif" }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { size: 11, family: "'Inter', sans-serif" } }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
