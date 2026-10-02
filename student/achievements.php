<?php
/**
 * SMS 2.0: Student Achievements View
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];
if (!$student_id) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM achievements WHERE student_id = ? ORDER BY awarded_date DESC");
$stmt->execute([$student_id]);
$achievements = $stmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">My Achievements</li>
            </ol>
        </nav>
        <h2 class="h3 mb-0 text-gray-800">My Achievements</h2>
    </div>
</div>

<div class="row">
    <?php if (count($achievements) > 0): ?>
        <?php foreach($achievements as $ach): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-award fs-3 text-warning"></i>
                        </div>
                        <h5 class="card-title font-weight-bold"><?php echo htmlspecialchars($ach['title']); ?></h5>
                        <p class="card-text text-muted small mb-3"><?php echo htmlspecialchars($ach['description']); ?></p>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span><i class="fas fa-calendar-alt me-1"></i> <?php echo date('d M Y', strtotime($ach['awarded_date'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-medal fs-3 mb-3 text-light"></i>
                    <p class="mb-0">No achievements recorded yet. Keep working hard!</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
