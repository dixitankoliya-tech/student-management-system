<?php
/**
 * SMS 2.0: Student Submit Assignment
 */
require_once '../config/database.php';
require_once '../includes/file_storage.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student || !$assignment_id) {
    header("Location: assignments.php");
    exit();
}

// Fetch assignment details ensuring it belongs to the student's class
$assgStmt = $pdo->prepare("
    SELECT a.*, s.subject_name, f.first_name as fac_first_name, f.last_name as fac_last_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN faculty f ON a.faculty_id = f.id
    WHERE a.id = ? AND a.course = ? AND a.semester = ? AND a.division = ?
");
$assgStmt->execute([$assignment_id, $student['course'], $student['semester'], $student['division']]);
$assignment = $assgStmt->fetch();

if (!$assignment) {
    header("Location: assignments.php");
    exit();
}

// Fetch existing submission
$subStmt = $pdo->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
$subStmt->execute([$assignment_id, $student_id]);
$submission = $subStmt->fetch();

// Check if past deadline
$is_past_due = time() > strtotime($assignment['due_date']);
// Prevent altering if past deadline and already submitted, or if graded
$can_submit = true;
if ($submission && $submission['marks_obtained'] !== null) {
    $can_submit = false; // Graded
} elseif ($submission && $is_past_due) {
    $can_submit = false; // Submitted and now past deadline
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    requireValidCsrf();
    
    if (!$can_submit) {
        $error = "You cannot modify this submission anymore.";
    } else {
        $file_path = $submission['attachment_path'] ?? null;
        
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/submissions/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_info = pathinfo($_FILES['submission_file']['name']);
            $ext = strtolower($file_info['extension']);
            $allowed_exts = ['pdf', 'doc', 'docx', 'zip', 'png', 'jpg'];
            
            if ($_FILES['submission_file']['size'] > 5 * 1024 * 1024) { // 5MB max
                $error = "File size exceeds 5MB limit.";
            } else if (in_array($ext, $allowed_exts)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['submission_file']['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = [
                    'application/pdf', 
                    'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                    'application/x-zip-compressed',
                    'image/jpeg',
                    'image/png'
                ];
                
                if (in_array($mime, $allowed_mimes)) {
                    $new_filename = 'sub_' . $assignment_id . '_' . $student_id . '_' . time() . '.' . $ext;
                    $file_path_new = 'uploads/submissions/' . $new_filename;
                    
                    if (store_file_in_db($_FILES['submission_file']['tmp_name'], $file_path_new, $_FILES['submission_file']['name'], $mime, $_FILES['submission_file']['size'], $pdo)) {
                        // DB storage handles overwrites naturally via delete_file_from_db inside store_file_in_db
                        $file_path = $file_path_new;
                    } else {
                        $error = "Failed to move uploaded file.";
                    }
                } else {
                    $error = "Invalid file type detected (MIME mismatch).";
                }
            } else {
                $error = "Invalid file extension. Allowed: PDF, DOC, DOCX, ZIP, PNG, JPG.";
            }
        } elseif (!$file_path) {
            $error = "You must upload a file to submit the assignment.";
        }
        
        if (empty($error)) {
            try {
                if ($submission) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE assignment_submissions SET attachment_path = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$file_path, $submission['id']]);
                    $success = "Assignment submission updated successfully.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, attachment_path) VALUES (?, ?, ?)");
                    $stmt->execute([$assignment_id, $student_id, $file_path]);
                    $success = "Assignment submitted successfully.";
                }
                
                // Refresh submission data
                $subStmt->execute([$assignment_id, $student_id]);
                $submission = $subStmt->fetch();
                
            } catch (PDOException $e) {
                error_log("Submit Assignment DB Error: " . $e->getMessage());
                $error = APP_DEBUG ? "Database error: " . $e->getMessage() : "A database error occurred while submitting. Please try again.";
            }
        }
    }
}

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">Assignment Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="assignments.php">Assignments</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($assignment['title']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="row">
    <!-- Assignment Info -->
    <div class="col-lg-8 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h4>
                        <div class="text-muted">
                            <?php echo htmlspecialchars($assignment['subject_name']); ?> &bull; Prof. <?php echo htmlspecialchars($assignment['fac_first_name'] . ' ' . $assignment['fac_last_name']); ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-xs fw-bold text-muted text-uppercase">Due Date</div>
                        <div class="<?php echo ($is_past_due && !$submission) ? 'text-danger fw-bold' : 'text-dark fw-bold'; ?>">
                            <?php echo date('D, M d, Y h:i A', strtotime($assignment['due_date'])); ?>
                        </div>
                    </div>
                </div>
                
                <h6 class="fw-bold text-dark mb-2 border-bottom pb-2">Instructions</h6>
                <div class="mb-4 text-muted">
                    <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                </div>
                
                <?php if ($assignment['attachment_path']): ?>
                    <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <i class="fas fa-paperclip text-primary me-2"></i>
                            <strong>Reference Material</strong>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="serve.php?file=<?php echo urlencode($assignment['attachment_path']); ?>&action=view" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary rounded-pill px-3">View</a>
                            <a href="serve.php?file=<?php echo urlencode($assignment['attachment_path']); ?>&action=download" class="btn btn-sm btn-primary rounded-pill px-3">Download</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="row border-top pt-3 mt-auto">
                    <div class="col-6">
                        <div class="text-xs fw-bold text-muted text-uppercase">Maximum Marks</div>
                        <div class="fw-bold text-dark fs-5"><?php echo $assignment['max_marks']; ?></div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="text-xs fw-bold text-muted text-uppercase">Created On</div>
                        <div class="text-dark"><?php echo date('M d, Y', strtotime($assignment['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Submission Panel -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark">Your Submission</h6>
                <?php if ($submission && $submission['marks_obtained'] !== null): ?>
                    <span class="badge bg-success">Graded</span>
                <?php elseif ($submission): ?>
                    <span class="badge bg-primary">Submitted</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Not Submitted</span>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4">
                <?php if ($submission): ?>
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle fs-1 text-success mb-3"></i>
                        <h6>Submitted on <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></h6>
                        <?php if (strtotime($submission['submitted_at']) > strtotime($assignment['due_date'])): ?>
                            <span class="badge bg-warning text-dark mb-2">Turned in late</span>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2 mt-2">
                            <a href="serve.php?file=<?php echo urlencode($submission['attachment_path']); ?>&action=view" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light border w-50">
                                <i class="fas fa-eye me-1"></i> View
                            </a>
                            <a href="serve.php?file=<?php echo urlencode($submission['attachment_path']); ?>&action=download" class="btn btn-sm btn-light border w-50">
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($submission['marks_obtained'] !== null): ?>
                        <div class="alert alert-success p-3 rounded-3 mb-0 text-center">
                            <div class="text-uppercase fw-bold text-success mb-1" style="font-size: 0.75rem;">Marks Awarded</div>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo $submission['marks_obtained']; ?> / <?php echo $assignment['max_marks']; ?></h3>
                            <?php if ($submission['feedback']): ?>
                                <hr>
                                <div class="text-start text-dark small">
                                    <strong>Feedback:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($can_submit): ?>
                    <hr>
                    <form method="POST" enctype="multipart/form-data">
                        <?php csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted">Upload Submission File</label>
                            <input type="file" name="submission_file" class="form-control" <?php echo $submission ? '' : 'required'; ?>>
                            <div class="form-text">PDF, DOC, DOCX, ZIP, PNG, JPG (Max 5MB)</div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="submit_assignment" class="btn btn-primary rounded-pill">
                                <?php echo $submission ? 'Update Submission' : 'Turn In Assignment'; ?>
                            </button>
                        </div>
                    </form>
                <?php elseif (!$submission): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-times-circle mb-2 fa-2x"></i>
                        <p class="mb-0">The deadline for this assignment has passed. You cannot submit it anymore.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
