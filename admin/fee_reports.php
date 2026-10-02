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
");
$report->execute($params);
$data = $report->fetchAll();

$courses = $pdo->query("SELECT course_code, course_name FROM courses WHERE status = 'Active'")->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Fee Reports</h2>
                    <p class="text-muted mb-0">Course-wise fee collection summary</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Filter by Course</label>
                            <select name="course" class="form-select">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>" <?php echo $courseFilter === $c['course_code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">Generate</button>
                        </div>
                        <?php if ($courseFilter): ?>
                        <div class="col-md-2">
                            <a href="fee_reports.php" class="btn btn-light border w-100">Clear</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="text-uppercase fw-bold">Course</th>
                                    <th class="text-uppercase fw-bold text-center">Students Assigned</th>
                                    <th class="text-uppercase fw-bold text-end">Total Expected</th>
                                    <th class="text-uppercase fw-bold text-end">Total Collected</th>
                                    <th class="text-uppercase fw-bold text-end">Total Pending</th>
                                    <th class="text-uppercase fw-bold text-end">Collection %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $t_students = 0; $t_expected = 0; $t_collected = 0; $t_pending = 0;
                                if (count($data) > 0): 
                                    foreach ($data as $row): 
                                        $t_students += $row['total_students'];
                                        $t_expected += $row['total_expected'];
                                        $t_collected += $row['total_collected'];
                                        $t_pending += $row['total_pending'];
                                        $pct = ($row['total_expected'] > 0) ? round(($row['total_collected'] / $row['total_expected']) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['course']); ?></td>
                                        <td class="text-center"><?php echo $row['total_students']; ?></td>
                                        <td class="text-end fw-bold">&#8377;<?php echo number_format($row['total_expected'], 2); ?></td>
                                        <td class="text-end fw-bold text-success">&#8377;<?php echo number_format($row['total_collected'], 2); ?></td>
                                        <td class="text-end fw-bold text-danger">&#8377;<?php echo number_format($row['total_pending'], 2); ?></td>
                                        <td class="text-end">
                                            <div class="progress" style="height: 6px; width: 60px; display: inline-flex; margin-right: 10px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                            <span style="font-size: 0.85rem;"><?php echo $pct; ?>%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td class="fw-bold text-dark">GRAND TOTAL</td>
                                        <td class="text-center fw-bold text-dark"><?php echo $t_students; ?></td>
                                        <td class="text-end fw-bold text-dark">&#8377;<?php echo number_format($t_expected, 2); ?></td>
                                        <td class="text-end fw-bold text-success">&#8377;<?php echo number_format($t_collected, 2); ?></td>
                                        <td class="text-end fw-bold text-danger">&#8377;<?php echo number_format($t_pending, 2); ?></td>
                                        <td class="text-end fw-bold text-dark">
                                            <?php echo ($t_expected > 0) ? round(($t_collected / $t_expected) * 100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No fee data available for the selected filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

