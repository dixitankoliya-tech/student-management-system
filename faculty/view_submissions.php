<?php
/**
 * SMS 2.0: Faculty - View and Grade Submissions
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id'];
$error = '';
$success = '';
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$assignment_id) {
    header("Location: assignments.php");
    exit();
}

// Verify assignment belongs to this faculty
try {
    $stmt = $pdo->prepare("
        SELECT a.*, s.subject_name 
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.id = ? AND a.faculty_id = ?
    ");
    $stmt->execute([$assignment_id, $faculty_id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        header("Location: assignments.php?error=" . urlencode("Unauthorized assignment access."));
        exit();
    }
} catch (PDOException $e) {
    die("Database Error.");
}

// Handle grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    requireValidCsrf();
    
    $submission_id = (int)$_POST['submission_id'];
    $marks = isset($_POST['marks_obtained']) ? (float)$_POST['marks_obtained'] : null;
    $feedback = trim($_POST['feedback'] ?? '');
    
    if ($marks !== null && ($marks < 0 || $marks > $assignment['max_marks'])) {
        $error = "Marks must be between 0 and " . $assignment['max_marks'];
    } else {
        try {
            // Check submission exists and belongs to this assignment
            $checkStmt = $pdo->prepare("SELECT student_id FROM assignment_submissions WHERE id = ? AND assignment_id = ?");
            $checkStmt->execute([$submission_id, $assignment_id]);
            $sub = $checkStmt->fetch();
            
            if ($sub) {
                $updStmt = $pdo->prepare("UPDATE assignment_submissions SET marks_obtained = ?, feedback = ?, status = 'Graded' WHERE id = ?");
                $updStmt->execute([$marks, $feedback, $submission_id]);
                
                // Notify student
                send_notification($pdo, 'student', $sub['student_id'], 'Assignment Graded', "Your submission for {$assignment['title']} has been graded.", 'Academic', 'assignments.php');
                
                $_SESSION['flash_success'] = "Grade saved successfully.";
                header("Location: view_submissions.php?id=" . $assignment_id);
                exit();
            } else {
                $error = "Invalid submission.";
            }
        } catch (PDOException $e) {
            $error = "Failed to save grade.";
        }
    }
}

// Fetch all enrolled students for this assignment's course/semester to see who hasn't submitted yet
try {
    $studentsStmt = $pdo->prepare("
        SELECT id, enrollment_no, first_name, last_name 
        FROM students 
        WHERE course = ? AND semester = ? AND division = ?
        ORDER BY enrollment_no
    ");
    $studentsStmt->execute([$assignment['course'], $assignment['semester'], $assignment['division']]);
    $allStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch submissions
    $subStmt = $pdo->prepare("
        SELECT sub.*, st.enrollment_no, st.first_name, st.last_name 
        FROM assignment_submissions sub
        JOIN students st ON sub.student_id = st.id
        WHERE sub.assignment_id = ?
    ");
    $subStmt->execute([$assignment_id]);
    $submissions = $subStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map submissions by student_id
    $submissionsMap = [];
    foreach ($submissions as $sub) {
        $submissionsMap[$sub['student_id']] = $sub;
    }
} catch (PDOException $e) {
    $allStudents = [];
    $submissionsMap = [];
}
?>
<?php
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
                    <li class="breadcrumb-item"><a href="assignments.php" class="text-decoration-none">Assignments</a></li>
                    <li class="breadcrumb-item active">Submissions</li>
                </ol>
            </nav>
            <h1 class="h3 text-gray-800 fw-bold mb-1">Submissions: <?= htmlspecialchars($assignment['title']) ?></h1>
            <p class="text-muted small mb-0"><?= htmlspecialchars($assignment['subject_name']) ?> | Due: <?= date('d M Y, h:i A', strtotime($assignment['due_date'])) ?> | Max Marks: <?= $assignment['max_marks'] ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="assignments.php" class="btn btn-outline-secondary shadow-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Submissions Table -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-0">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-tasks me-2"></i> Student Submissions</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Attachment</th>
                            <th>Marks</th>
                            <th class="text-center pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allStudents)): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-users-slash"></i><h5>No Students</h5><p>No students enrolled in this course.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($allStudents as $student): ?>
                                <?php 
                                    $sub = $submissionsMap[$student['id']] ?? null; 
                                    $has_submitted = $sub !== null;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-3">
                                                <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($student['enrollment_no']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($has_submitted): ?>
                                            <?php if ($sub['status'] === 'Graded'): ?>
                                                <span class="badge bg-success">Graded</span>
                                            <?php elseif ($sub['status'] === 'Late'): ?>
                                                <span class="badge bg-warning text-dark">Late</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Submitted</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_submitted): ?>
                                            <?= date('d M Y, h:i A', strtotime($sub['submitted_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_submitted && $sub['attachment_path']): ?>
                                            <div class="d-flex gap-1">
                                                <a href="serve.php?file=<?= urlencode($sub['attachment_path']) ?>&action=view" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info rounded-pill"><i class="fas fa-eye"></i> View</a>
                                                <a href="serve.php?file=<?= urlencode($sub['attachment_path']) ?>&action=download" class="btn btn-sm btn-info text-white rounded-pill"><i class="fas fa-download"></i></a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_submitted && $sub['marks_obtained'] !== null): ?>
                                            <strong><?= $sub['marks_obtained'] ?></strong> / <?= $assignment['max_marks'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <?php if ($has_submitted): ?>
                                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#gradeModal<?= $sub['id'] ?>">
                                                <i class="fas fa-check-double me-1"></i> Grade
                                            </button>
                                            
                                            <!-- Grade Modal -->
                                            <div class="modal fade text-start" id="gradeModal<?= $sub['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <?php csrfField(); ?>
                                                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                                            <div class="modal-header border-0 pb-0">
                                                                <h5 class="modal-title fw-bold">Grade Submission</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="mb-3">Student: <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong></p>
                                                                
                                                                <?php if ($sub['submission_text']): ?>
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Student Text Response</label>
                                                                        <div class="p-3 bg-light rounded border">
                                                                            <?= nl2br(htmlspecialchars($sub['submission_text'])) ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Marks (Out of <?= $assignment['max_marks'] ?>) <span class="text-danger">*</span></label>
                                                                    <input type="number" step="0.1" min="0" max="<?= $assignment['max_marks'] ?>" class="form-control" name="marks_obtained" value="<?= $sub['marks_obtained'] ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Feedback / Comments</label>
                                                                    <textarea class="form-control" name="feedback" rows="3" placeholder="Provide feedback to the student..."><?= htmlspecialchars($sub['feedback'] ?? '') ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer border-0 pt-0">
                                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="grade_submission" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Grade</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-light rounded-pill px-3" disabled title="No submission yet">Grade</button>
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
