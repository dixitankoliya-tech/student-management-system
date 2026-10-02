<?php
/**
 * SMS 2.0: Faculty - Student Detail
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    header("Location: my_students.php");
    exit();
}

// Ensure the student belongs to a course the faculty teaches
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT st.*, sae.division as active_division, sae.academic_year
        FROM students st
        JOIN student_academic_enrollments sae ON st.id = sae.student_id AND sae.status = 'Active'
        JOIN faculty_subjects fs ON sae.course = fs.course AND sae.semester = fs.semester AND sae.division = fs.division AND sae.academic_year = fs.academic_year
        WHERE fs.faculty_id = ? AND st.id = ?
    ");
    $stmt->execute([$faculty_id, $student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        header("Location: my_students.php?error=" . urlencode("Unauthorized access or student not found."));
        exit();
    }

    // Get Attendance Stats (Scoped to Faculty's subjects)
    $attStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN a.status='Present' THEN 1 END) as present_count,
            COUNT(a.id) as total_days
        FROM attendance a
        JOIN faculty_subjects fs ON a.subject_id = fs.subject_id AND fs.faculty_id = ?
        WHERE a.student_id = ?
    ");
    $attStmt->execute([$faculty_id, $student_id]);
    $attendance = $attStmt->fetch();
    $overall_attendance_pct = $attendance['total_days'] > 0 ? round(($attendance['present_count'] / $attendance['total_days']) * 100, 2) : 'N/A';

} catch (PDOException $e) {
    die("Database Error.");
}

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
        <div class="mb-3 mb-md-0">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="my_students.php" class="text-decoration-none">My Students</a></li>
                    <li class="breadcrumb-item active">Student Profile</li>
                </ol>
            </nav>
            <h1 class="h3 text-gray-800 fw-bold mb-1">Student Profile</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="my_students.php" class="btn btn-outline-secondary shadow-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <!-- Student Details -->
    <div class="row">
        <!-- Basic Info -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body text-center py-5">
                    <div class="avatar-circle bg-primary text-white mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; line-height: 80px;">
                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h5>
                    <p class="text-muted mb-3"><?= htmlspecialchars($student['enrollment_no']) ?></p>
                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill border border-info">
                        <?= htmlspecialchars($student['course']) ?> | Sem <?= htmlspecialchars($student['semester']) ?> (Div <?= htmlspecialchars($student['division']) ?>)
                    </span>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0 pb-4 px-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-envelope text-muted me-3" style="width: 20px;"></i>
                        <span class="text-dark"><?= htmlspecialchars($student['email']) ?></span>
                    </div>
                    <?php if (!empty($student['phone'])): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-phone text-muted me-3" style="width: 20px;"></i>
                        <span class="text-dark"><?= htmlspecialchars($student['phone']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-venus-mars text-muted me-3" style="width: 20px;"></i>
                        <span class="text-dark"><?= htmlspecialchars($student['gender']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Info -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-graduation-cap me-2"></i> Academic Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="p-4 bg-light rounded-3 border h-100">
                                <h6 class="text-muted mb-2">Overall Attendance</h6>
                                <h2 class="fw-bold mb-0 text-<?= ($overall_attendance_pct !== 'N/A' && $overall_attendance_pct < 75) ? 'danger' : 'success' ?>">
                                    <?= $overall_attendance_pct !== 'N/A' ? $overall_attendance_pct . '%' : 'N/A' ?>
                                </h2>
                                <div class="small text-muted mt-2"><?= $attendance['present_count'] ?> / <?= $attendance['total_days'] ?> Days Present</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 bg-light rounded-3 border h-100">
                                <h6 class="text-muted mb-2">Admission Year</h6>
                                <h2 class="fw-bold mb-0 text-primary">
                                    <?= htmlspecialchars($student['admission_year']) ?>
                                </h2>
                                <div class="small text-muted mt-2">Specialization: <?= htmlspecialchars($student['specialization'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>
