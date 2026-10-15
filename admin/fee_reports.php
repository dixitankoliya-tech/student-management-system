<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$courseFilter = isset($_GET['course']) ? trim($_GET['course']) : '';

$where = "WHERE 1=1";
$params = [];

if ($courseFilter) {
    $where .= " AND s.course = ?";
    $params[] = $courseFilter;
}

$report = $pdo->prepare("
    SELECT s.course, 
           COUNT(sf.id) as total_students,
           SUM(sf.total_amount) as total_expected,
           SUM(sf.paid_amount) as total_collected,
           SUM(sf.pending_amount) as total_pending
    FROM student_fees sf
    JOIN students s ON sf.student_id = s.id
    $where
    GROUP BY s.course
    ORDER BY s.course ASC
");
$report->execute($params);
$data = $report->fetchAll();

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Fee_Collection_Report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Course', 'Students Assigned', 'Total Expected (INR)', 'Total Collected (INR)', 'Total Pending (INR)', 'Collection Percentage']);
    
    $csv_students = 0; $csv_expected = 0; $csv_collected = 0; $csv_pending = 0;
    foreach ($data as $r) {
        $csv_students += $r['total_students'];
        $csv_expected += $r['total_expected'];
        $csv_collected += $r['total_collected'];
        $csv_pending += $r['total_pending'];
        $pct = ($r['total_expected'] > 0) ? round(($r['total_collected'] / $r['total_expected']) * 100, 1) : 0;
        fputcsv($output, [
            $r['course'],
            $r['total_students'],
            number_format($r['total_expected'], 2, '.', ''),
            number_format($r['total_collected'], 2, '.', ''),
            number_format($r['total_pending'], 2, '.', ''),
            $pct . '%'
        ]);
    }
    // Grand Total Row
    $total_pct = ($csv_expected > 0) ? round(($csv_collected / $csv_expected) * 100, 1) : 0;
    fputcsv($output, [
        'GRAND TOTAL',
        $csv_students,
        number_format($csv_expected, 2, '.', ''),
        number_format($csv_collected, 2, '.', ''),
        number_format($csv_pending, 2, '.', ''),
        $total_pct . '%'
    ]);
    
    fclose($output);
    exit();
}

// Fetch Courses for Dropdown Filter
$courses = [];
try {
    $courses = $pdo->query("SELECT course_code, course_name FROM courses WHERE status = 'Active' ORDER BY course_name ASC")->fetchAll();
} catch (PDOException $e) {
    $courses = [];
}

// Compute Totals
$t_students = 0; 
$t_expected = 0; 
$t_collected = 0; 
$t_pending = 0;
foreach ($data as $row) {
    $t_students += (int)$row['total_students'];
    $t_expected += (float)$row['total_expected'];
    $t_collected += (float)$row['total_collected'];
    $t_pending += (float)$row['total_pending'];
}
$t_rate = ($t_expected > 0) ? round(($t_collected / $t_expected) * 100, 1) : 0;

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="fee_payments.php">Finance</a></li>
        <li class="breadcrumb-item active">Fee Reports</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 mb-0 text-dark fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>Fee Reports</h2>
        <p class="text-muted mb-0">Course-wise fee collection summary and financial analytics</p>
    </div>
    <div class="d-flex gap-2">
        <a href="fee_reports.php?export=csv<?php echo $courseFilter ? '&course=' . urlencode($courseFilter) : ''; ?>" class="btn btn-outline-success fw-bold shadow-sm rounded-pill px-3">
            <i class="fas fa-file-csv me-2"></i> Export CSV
        </a>
    </div>
</div>

<!-- Financial Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary me-3">
                        <i class="fas fa-users fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Assigned Students</div>
                        <div class="fs-4 fw-bold text-dark"><?php echo number_format($t_students); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info me-3">
                        <i class="fas fa-file-invoice-dollar fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Total Expected</div>
                        <div class="fs-4 fw-bold text-dark">&#8377;<?php echo number_format($t_expected, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success me-3">
                        <i class="fas fa-check-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Total Collected</div>
                        <div class="fs-4 fw-bold text-success">&#8377;<?php echo number_format($t_collected, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger me-3">
                        <i class="fas fa-exclamation-triangle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Total Pending</div>
                        <div class="fs-4 fw-bold text-danger">&#8377;<?php echo number_format($t_pending, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 bg-light rounded-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Filter by Program</label>
                <select name="course" class="form-select">
                    <option value="">All Programs</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['course_code']); ?>" <?php echo $courseFilter === $c['course_code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold flex-grow-1"><i class="fas fa-filter me-1"></i> Apply Filter</button>
                <?php if ($courseFilter): ?>
                    <a href="fee_reports.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="text-uppercase fw-bold">Course / Program</th>
                        <th class="text-uppercase fw-bold text-center">Students Assigned</th>
                        <th class="text-uppercase fw-bold text-end">Total Expected</th>
                        <th class="text-uppercase fw-bold text-end">Total Collected</th>
                        <th class="text-uppercase fw-bold text-end">Total Pending</th>
                        <th class="text-uppercase fw-bold text-end">Collection %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($data) > 0): ?>
                        <?php foreach ($data as $row): 
                            $pct = ($row['total_expected'] > 0) ? round(($row['total_collected'] / $row['total_expected']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['course']); ?></td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?php echo (int)$row['total_students']; ?></span></td>
                            <td class="text-end fw-bold">&#8377;<?php echo number_format($row['total_expected'], 2); ?></td>
                            <td class="text-end fw-bold text-success">&#8377;<?php echo number_format($row['total_collected'], 2); ?></td>
                            <td class="text-end fw-bold text-danger">&#8377;<?php echo number_format($row['total_pending'], 2); ?></td>
                            <td class="text-end">
                                <div class="progress" style="height: 6px; width: 60px; display: inline-flex; margin-right: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, $pct); ?>%"></div>
                                </div>
                                <span class="fw-semibold" style="font-size: 0.85rem;"><?php echo $pct; ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-light border-top border-2">
                            <td class="fw-bold text-dark">GRAND TOTAL</td>
                            <td class="text-center fw-bold text-dark"><?php echo number_format($t_students); ?></td>
                            <td class="text-end fw-bold text-dark">&#8377;<?php echo number_format($t_expected, 2); ?></td>
                            <td class="text-end fw-bold text-success">&#8377;<?php echo number_format($t_collected, 2); ?></td>
                            <td class="text-end fw-bold text-danger">&#8377;<?php echo number_format($t_pending, 2); ?></td>
                            <td class="text-end fw-bold text-dark">
                                <span class="badge bg-primary px-2 py-1"><?php echo $t_rate; ?>%</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                                <h5>No fee data available</h5>
                                <p class="small mb-0">No records found for the selected course filter.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
