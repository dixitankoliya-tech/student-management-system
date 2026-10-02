<?php
/**
 * SMS 2.0: Student Examinations
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if ($student) {
    $enrStmt = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' ORDER BY academic_year DESC LIMIT 1");
    $enrStmt->execute([$student_id]);
    $student['academic_year'] = $enrStmt->fetchColumn() ?: 'Academic period unavailable';
}
if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch examinations mapped to student's course and semester
$examStmt = $pdo->prepare("
    SELECT * 
    FROM examinations 
    WHERE course = ? AND semester = ?
    ORDER BY start_date DESC
");
$examStmt->execute([$student['course'], $student['semester']]);
$examinations = $examStmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Examinations</h2>
        <p class="text-muted mb-0">Upcoming and past examination schedules</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Examination</th>
                        <th>Academic Year</th>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th class="text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($examinations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-calendar-alt fs-3 mb-3 text-light"></i>
                                <p class="mb-0">No examinations found for your course and semester.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($examinations as $exam): ?>
                            <tr>
                                <td><strong class="text-dark"><?php echo htmlspecialchars($exam['exam_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($exam['academic_year']); ?></td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($exam['exam_type'] ?? 'Standard'); ?></span></td>
                                <td><?php echo $exam['start_date'] ? date('M d, Y', strtotime($exam['start_date'])) : '-'; ?></td>
                                <td><?php echo $exam['end_date'] ? date('M d, Y', strtotime($exam['end_date'])) : '-'; ?></td>
                                <td class="text-end">
                                    <?php if ($exam['status'] === 'Upcoming'): ?>
                                        <span class="badge bg-primary">Upcoming</span>
                                    <?php elseif ($exam['status'] === 'Completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($exam['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
