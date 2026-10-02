<?php
/**
 * SMS 2.0: Student Assignments
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch active enrollment to determine academic year
$enrStmt = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' ORDER BY academic_year DESC LIMIT 1");
$enrStmt->execute([$student_id]);
$student['academic_year'] = $enrStmt->fetchColumn() ?: 'Academic period unavailable';

// Fetch assignments
$assgStmt = $pdo->prepare("
    SELECT DISTINCT
        a.id as assignment_id,
        a.title,
        a.description,
        a.due_date,
        a.max_marks,
        a.created_at,
        s.subject_name,
        f.first_name as fac_first_name,
        f.last_name as fac_last_name,
        sub.id as submission_id,
        sub.submitted_at,
        sub.marks_obtained
    FROM assignments a
    JOIN faculty_subjects fs ON a.faculty_id = fs.faculty_id AND a.subject_id = fs.subject_id AND a.course = fs.course AND a.semester = fs.semester AND a.division = fs.division
    JOIN subjects s ON a.subject_id = s.id
    JOIN faculty f ON a.faculty_id = f.id
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
    WHERE a.course = ? AND a.semester = ? AND a.division = ? AND fs.academic_year = ?
    ORDER BY a.due_date DESC
");
$assgStmt->execute([$student_id, $student['course'], $student['semester'], $student['division'], $student['academic_year']]);
$assignments = $assgStmt->fetchAll();

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Assignments</h2>
        <p class="text-muted mb-0">Track and submit your coursework</p>
    </div>
</div>

<div class="row">
    <?php if (empty($assignments)): ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-tasks fs-1 text-light mb-3"></i>
            <h5 class="fw-bold text-muted">No Assignments</h5>
            <p class="text-muted">You have no assignments at the moment.</p>
        </div>
    <?php else: ?>
        <?php foreach ($assignments as $a): 
            $now = time();
            $due = strtotime($a['due_date']);
            $is_past_due = $now > $due;
            
            $status = 'Not Submitted';
            $status_class = 'secondary';
            $icon = 'fa-times-circle';
            
            if ($a['submission_id']) {
                $sub_time = strtotime($a['submitted_at']);
                if ($a['marks_obtained'] !== null) {
                    $status = 'Graded';
                    $status_class = 'success';
                    $icon = 'fa-check-double';
                } elseif ($sub_time > $due) {
                    $status = 'Submitted Late';
                    $status_class = 'warning';
                    $icon = 'fa-exclamation-triangle';
                } else {
                    $status = 'Submitted';
                    $status_class = 'primary';
                    $icon = 'fa-check-circle';
                }
            } elseif ($is_past_due) {
                $status = 'Missing';
                $status_class = 'danger';
                $icon = 'fa-times-circle';
            }
        ?>
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100 rounded-4 border-top border-<?php echo $status_class; ?> border-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($a['title']); ?></h5>
                                <div class="text-muted small">
                                    <?php echo htmlspecialchars($a['subject_name']); ?> &bull; Prof. <?php echo htmlspecialchars($a['fac_last_name']); ?>
                                </div>
                            </div>
                            <span class="badge bg-<?php echo $status_class; ?>-subtle text-<?php echo $status_class; ?> fs-6 rounded-pill px-3 py-1">
                                <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo $status; ?>
                            </span>
                        </div>
                        
                        <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($a['description']); ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-end mt-4">
                            <div>
                                <div class="text-xs fw-bold text-muted text-uppercase">Due Date</div>
                                <div class="<?php echo ($is_past_due && !$a['submission_id']) ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                    <?php echo date('D, M d, Y h:i A', $due); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ($a['marks_obtained'] !== null): ?>
                                    <div class="text-xs fw-bold text-success text-uppercase">Marks</div>
                                    <div class="fw-bold text-dark fs-5"><?php echo $a['marks_obtained']; ?> / <?php echo $a['max_marks']; ?></div>
                                <?php else: ?>
                                    <div class="text-xs fw-bold text-muted text-uppercase">Max Marks</div>
                                    <div class="fw-bold text-dark fs-5"><?php echo $a['max_marks']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-light border-top-0 rounded-bottom-4 py-3">
                        <div class="d-grid">
                            <a href="submit_assignment.php?id=<?php echo $a['assignment_id']; ?>" class="btn btn-outline-<?php echo $status_class; ?> rounded-pill">
                                <?php echo $a['submission_id'] ? 'View Submission' : 'Submit Assignment'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
