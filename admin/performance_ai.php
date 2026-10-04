<?php
/**
 * SMS 2.0: Performance AI / Analytics
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

// Aggregate performance data
$course_performance = $pdo->query("
    SELECT s.course, 
           COUNT(*) as total_students,
           AVG(s.cgpa) as avg_cgpa,
           SUM(CASE WHEN ph.promotion_status = 'Not Promoted' THEN 1 ELSE 0 END) as fail_count,
           SUM(CASE WHEN ph.promotion_status = 'Promoted with ATKT' THEN 1 ELSE 0 END) as atkt_count
    FROM students s
    LEFT JOIN promotion_history ph ON s.id = ph.student_id
    WHERE s.status = 'Active'
    GROUP BY s.course
")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<?php include '../includes/admin_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Performance Analytics</h2>
        <p class="text-muted mb-0">Aggregate academic performance across all courses</p>
    </div>
</header>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Course Performance Trends</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Course</th>
                                <th>Total Students</th>
                                <th>Average CGPA</th>
                                <th>Failures (Overall)</th>
                                <th>ATKTs (Overall)</th>
                                <th>Status Alert</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_performance as $cp): ?>
                                <?php 
                                    $fail_rate = $cp['total_students'] > 0 ? ($cp['fail_count'] / $cp['total_students']) * 100 : 0;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($cp['course']) ?></td>
                                    <td><?= $cp['total_students'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $cp['avg_cgpa'] >= 7 ? 'success' : ($cp['avg_cgpa'] >= 5 ? 'warning' : 'danger') ?>">
                                            <?= number_format((float)$cp['avg_cgpa'], 2) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$cp['fail_count'] ?></td>
                                    <td><?= (int)$cp['atkt_count'] ?></td>
                                    <td>
                                        <?php if ($fail_rate > 10): ?>
                                            <span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> High Fail Rate</span>
                                        <?php else: ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Stable</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
