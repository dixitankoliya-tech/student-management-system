<?php
/**
 * SMS 2.0: Faculty - My Subjects
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();

$faculty_id = $_SESSION['faculty_db_id'];

try {
    $stmt = $pdo->prepare("
        SELECT fs.*, s.subject_code, s.subject_name, s.credits, s.subject_type,
        (SELECT COUNT(*) FROM students st 
         WHERE st.course = fs.course 
         AND st.semester = fs.semester 
         AND st.division = fs.division) as student_count
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.id
        WHERE fs.faculty_id = ?
        ORDER BY fs.academic_year DESC, fs.semester ASC
    ");
    $stmt->execute([$faculty_id]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $subjects = [];
}
?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="fw-bold text-dark mb-0">My Subjects</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active">My Subjects</li>
                    </ol>
                </nav>
            </div>
        </header>

        <div class="row g-4">
            <?php if (empty($subjects)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                        <div class="text-muted mb-3"><i class="fas fa-book-open fs-3"></i></div>
                        <h4 class="text-muted">No Subjects Assigned</h4>
                        <p class="mb-0">You have not been assigned to any subjects yet. Please contact the administrator.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($subjects as $sub): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                        <div class="bg-primary position-absolute top-0 start-0 w-100" style="height: 4px;"></div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill mb-2">
                                        <?= htmlspecialchars($sub['subject_code']) ?>
                                    </span>
                                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($sub['subject_name']) ?></h5>
                                    <small class="text-muted"><?= htmlspecialchars($sub['subject_type']) ?> • <?= $sub['credits'] ?> Credits</small>
                                </div>
                            </div>
                            
                            <div class="bg-light rounded p-3 mb-3">
                                <div class="row text-center g-2">
                                    <div class="col-4 border-end">
                                        <small class="text-muted d-block">Course</small>
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($sub['course']) ?></span>
                                    </div>
                                    <div class="col-4 border-end">
                                        <small class="text-muted d-block">Sem/Year</small>
                                        <span class="fw-semibold text-dark"><?= $sub['semester'] ?> / <?= htmlspecialchars($sub['academic_year']) ?></span>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Division</small>
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($sub['division']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-users me-2 text-primary"></i>
                                    <span class="fw-medium"><?= $sub['student_count'] ?> Students</span>
                                </div>
                                <a href="my_students.php?subject_id=<?= $sub['subject_id'] ?>&division=<?= urlencode($sub['division']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    View Class
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php include '../includes/footer.php'; ?>

