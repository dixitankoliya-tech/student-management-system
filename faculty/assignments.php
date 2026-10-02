<?php
/**
 * SMS 2.0: Faculty - Assignments
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id'];
$error = '';
$success = '';

// Fetch assigned subjects
try {
    $stmt = $pdo->prepare("
        SELECT fs.*, s.subject_name, s.subject_code 
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.id
        WHERE fs.faculty_id = ?
        ORDER BY fs.course, fs.semester, s.subject_name
    ");
    $stmt->execute([$faculty_id]);
    $assignmentsList = $stmt->fetchAll();
} catch (PDOException $e) {
    $assignmentsList = [];
}

// Handle Add Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    requireValidCsrf();
    
    $assignment_id = (int)$_POST['assignment_id']; // This is actually the faculty_subjects.id
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'];
    $max_marks = (float)($_POST['max_marks'] ?? 10.0);
    
    // Validate ownership
    $target = null;
    foreach ($assignmentsList as $a) {
        if ($a['id'] == $assignment_id) {
            $target = $a;
            break;
        }
    }
    
    if (!$target) {
        $error = "Unauthorized subject selection.";
    } elseif (empty($title) || empty($due_date)) {
        $error = "Title and Due Date are required.";
    } else {
        $attachment_path = null;
        
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/assignments/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_info = pathinfo($_FILES['attachment']['name']);
            $ext = strtolower($file_info['extension']);
            $allowed_exts = ['pdf', 'doc', 'docx', 'zip'];
            
            // 5MB max
            if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                $error = "File size exceeds 5MB limit.";
            } else if (in_array($ext, $allowed_exts)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['attachment']['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = [
                    'application/pdf', 
                    'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip',
                    'application/x-zip-compressed'
                ];
                
                if (in_array($mime, $allowed_mimes)) {
                    $new_filename = uniqid('assg_') . '.' . $ext;
                    $target_file = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                        $attachment_path = 'uploads/assignments/' . $new_filename;
                    } else {
                        $error = "Failed to move uploaded file.";
                    }
                } else {
                    $error = "Invalid file type detected (MIME mismatch).";
                }
            } else {
                $error = "Invalid file extension. Allowed: PDF, DOC, DOCX, ZIP.";
            }
        }
        
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assignments (title, description, subject_id, faculty_id, course, semester, division, due_date, max_marks, attachment_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $description, $target['subject_id'], $faculty_id,
                    $target['course'], $target['semester'], $target['division'],
                    $due_date, $max_marks, $attachment_path
                ]);
                logActivity($pdo, 'CREATE_ASSIGNMENT', null, "Created assignment: $title");
                $success = "Assignment created successfully.";

                // Notify target students
                $notification_title = "New Assignment: " . substr($title, 0, 50);
                $q = $pdo->prepare("SELECT id FROM students WHERE course = ? AND semester = ? AND division = ?");
                $q->execute([$target['course'], $target['semester'], $target['division']]);
                $students = $q->fetchAll();
                foreach($students as $s) {
                    send_notification($pdo, 'student', $s['id'], $notification_title, "A new assignment is due on " . date('M j, Y', strtotime($due_date)), 'academic', 'assignments.php');
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    requireValidCsrf();
    $assg_id = (int)$_POST['assignment_id_del'];
    
    $stmt = $pdo->prepare("SELECT attachment_path FROM assignments WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$assg_id, $faculty_id]);
    $assg = $stmt->fetch();
    
    if ($assg) {
        try {
            $pdo->prepare("DELETE FROM assignments WHERE id = ?")->execute([$assg_id]);
            if ($assg['attachment_path'] && file_exists('../' . $assg['attachment_path'])) {
                unlink('../' . $assg['attachment_path']);
            }
            $success = "Assignment deleted.";
        } catch (PDOException $e) {
            $error = "Error deleting assignment.";
        }
    }
}

// Fetch all assignments created by this faculty
try {
    $stmt = $pdo->prepare("
        SELECT a.*, s.subject_name,
        (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) as submission_count
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.faculty_id = ?
        ORDER BY a.due_date DESC
    ");
    $stmt->execute([$faculty_id]);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $assignments = [];
}
?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h3 text-gray-800 fw-bold mb-1">Assignments</h1>
        <p class="text-muted small mb-0">Manage Assignments in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Create Assignment
            </button>
    </div>
</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Class/Subject</th>
                            <th>Due Date</th>
                            <th>Max Marks</th>
                            <th>Submissions</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $a): 
                            $is_past = strtotime($a['due_date']) < time();
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($a['title']) ?></strong>
                                <?php if ($a['attachment_path']): ?>
                                    <span class="badge bg-secondary ms-1"><i class="fas fa-paperclip"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($a['subject_name']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($a['course']) ?> (Sem <?= $a['semester'] ?>, Div <?= htmlspecialchars($a['division']) ?>)</small>
                            </td>
                            <td>
                                <?php if ($is_past): ?>
                                    <span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i><?= date('d M Y h:i A', strtotime($a['due_date'])) ?></span>
                                <?php else: ?>
                                    <span class="text-success"><?= date('d M Y h:i A', strtotime($a['due_date'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= $a['max_marks'] ?></td>
                            <td>
                                <span class="badge <?= $a['submission_count'] > 0 ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                    <?= $a['submission_count'] ?> received
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="view_submissions.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-info" title="View Submissions"><i class="fas fa-eye"></i></a>
                                <?php if ($a['attachment_path']): ?>
                                    <a href="serve.php?file=<?= urlencode($a['attachment_path']) ?>&action=view" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="View File"><i class="fas fa-file-alt"></i></a>
                                    <a href="serve.php?file=<?= urlencode($a['attachment_path']) ?>&action=download" class="btn btn-sm btn-primary" title="Download File"><i class="fas fa-download"></i></a>
                                <?php endif; ?>
                                <form method="POST" class="d-inline">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="assignment_id_del" value="<?= $a['id'] ?>">
                                    <button type="submit" name="delete_assignment" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure? This will delete all submissions too.');"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assignments)): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No assignments created yet</h5><p>There are currently no records available in this section.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Create Assignment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-semibold">Target Class</label>
                            <select name="assignment_id" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($assignmentsList as $a): ?>
                                    <option value="<?= $a['id'] ?>">
                                        <?= htmlspecialchars($a['subject_name']) ?> - <?= htmlspecialchars($a['course']) ?> Sem <?= $a['semester'] ?> Div <?= htmlspecialchars($a['division']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-semibold">Assignment Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. DBMS Homework 1">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-semibold">Description / Instructions</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-semibold">Due Date & Time</label>
                            <input type="datetime-local" name="due_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-semibold">Max Marks</label>
                            <input type="number" step="0.5" name="max_marks" class="form-control" value="10.0" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-semibold">Attachment (Optional, Max 5MB)</label>
                            <input type="file" name="attachment" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_assignment" class="btn btn-primary px-4">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>


