<?php
/**
 * SMS 2.0: Topper Board
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

// Fetch top 3 students per course based on academic performance
$toppers_by_course = [];
$topper_rows = $pdo->query("
    SELECT s.id, s.enrollment_no, s.first_name, s.last_name, s.course,
           ROUND(AVG(ap.performance_score), 2) as cgpa,
           NULL as profile_image
    FROM students s
    JOIN academic_performance ap ON s.id = ap.student_id
    WHERE ap.performance_score > 0
    GROUP BY s.id, s.enrollment_no, s.first_name, s.last_name, s.course
    ORDER BY s.course ASC, cgpa DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($topper_rows as $row) {
    $course = $row['course'];
    if (!isset($toppers_by_course[$course])) {
        $toppers_by_course[$course] = [];
    }
    if (count($toppers_by_course[$course]) < 3) {
        $toppers_by_course[$course][] = $row;
    }
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<?php include '../includes/admin_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Topper Board</h2>
        <p class="text-muted mb-0">Highest ranking students by CGPA across courses</p>
    </div>
</header>

<?php if (empty($toppers_by_course)): ?>
    <div class="alert alert-info">No academic data available for the topper board yet.</div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($toppers_by_course as $course => $toppers): ?>
        <div class="col-12">
            <h5 class="fw-bold text-primary mb-3 border-bottom pb-2"><?= htmlspecialchars($course) ?></h5>
            <div class="row g-3">
                <?php foreach ($toppers as $index => $topper): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                        <?php if ($index == 0): ?>
                            <div class="position-absolute top-0 end-0 bg-warning text-dark px-3 py-1 rounded-bl-3 fw-bold shadow-sm" style="border-bottom-left-radius: 10px;">
                                <i class="fas fa-crown me-1"></i> Rank 1
                            </div>
                        <?php elseif ($index == 1): ?>
                            <div class="position-absolute top-0 end-0 bg-secondary text-white px-3 py-1 rounded-bl-3 fw-bold shadow-sm" style="border-bottom-left-radius: 10px;">
                                Rank 2
                            </div>
                        <?php else: ?>
                            <div class="position-absolute top-0 end-0 bg-light text-dark px-3 py-1 rounded-bl-3 fw-bold shadow-sm border border-secondary" style="border-bottom-left-radius: 10px; color:#cd7f32 !important; border-color: #cd7f32 !important;">
                                Rank 3
                            </div>
                        <?php endif; ?>
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <?php if (!empty($topper['profile_image'])): ?>
                                    <img src="../<?= htmlspecialchars($topper['profile_image']) ?>" class="rounded-circle shadow-sm" width="80" height="80" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center fw-bold fs-3" style="width:80px; height:80px;">
                                        <?= strtoupper(substr($topper['first_name'], 0, 1) . substr($topper['last_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($topper['first_name'] . ' ' . $topper['last_name']) ?></h5>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($topper['enrollment_no']) ?></p>
                            <div class="d-inline-block bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill border border-success-subtle">
                                CGPA: <?= number_format((float)$topper['cgpa'], 2) ?>
                            </div>
                            <div class="mt-3">
                                <a href="view_student.php?id=<?= $topper['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">View Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
