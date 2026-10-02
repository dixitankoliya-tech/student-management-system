<?php
/**
 * SMS 2.0: Faculty - Study Materials Management
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();
$faculty_id = $_SESSION['faculty_db_id'];
$success = '';
$error = '';

// Setup upload directory
$upload_dir = '../uploads/study_materials/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 1. Fetch assigned subjects for this faculty
try {
    $stmt = $pdo->prepare("
        SELECT fs.*, s.subject_name, s.subject_code 
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.id
        WHERE fs.faculty_id = ?
        ORDER BY fs.course, fs.semester, s.subject_name
    ");
    $stmt->execute([$faculty_id]);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $assignments = [];
}

// 2. Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    // Add Material
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $assignment_id = (int)$_POST['assignment_id'];
        $visibility = $_POST['visibility'] ?? 'Visible';
        $link_url = trim($_POST['link_url']);
        
        // Find assignment details
        $assignment = null;
        foreach ($assignments as $a) {
            if ($a['id'] == $assignment_id) {
                $assignment = $a;
                break;
            }
        }
        
        if (!$assignment) {
            $error = "Invalid assignment selected.";
        } else if (empty($title)) {
            $error = "Title is required.";
        } else {
            $file_path = null;
            $file_type = null;
            
            // Handle file upload
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['file']['tmp_name'];
                $name = basename($_FILES['file']['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'png', 'jpg', 'jpeg'];
                
                if (!in_array($ext, $allowed)) {
                    $error = "Invalid file type. Allowed: " . implode(', ', $allowed);
                } else {
                    $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\.\-_]/", "", $name);
                    if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                        $file_path = 'uploads/study_materials/' . $file_name;
                        $file_type = $_FILES['file']['type'];
                    } else {
                        $error = "Failed to upload file.";
                    }
                }
            }
            
            if (empty($error)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO study_materials (title, description, subject_id, faculty_id, course, semester, division, file_path, file_type, link_url, visibility) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title, $description, $assignment['subject_id'], $faculty_id, 
                        $assignment['course'], $assignment['semester'], $assignment['division'], 
                        $file_path, $file_type, $link_url, $visibility
                    ]);
                    $success = "Study material added successfully.";
                    logActivity($pdo, 'ADD_MATERIAL', null, "Uploaded material: $title");

                    // Notify target students if published
                    if ($visibility === 'Visible') {
                        $notification_title = "New Study Material: " . substr($title, 0, 50);
                        $q = $pdo->prepare("SELECT id FROM students WHERE course = ? AND semester = ? AND division = ?");
                        $q->execute([$assignment['course'], $assignment['semester'], $assignment['division']]);
                        $students = $q->fetchAll();
                        foreach($students as $s) {
                            send_notification($pdo, 'student', $s['id'], $notification_title, "Faculty uploaded new study material.", 'academic', 'study_materials.php');
                        }
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
    
    // Delete Material
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $checkStmt = $pdo->prepare("SELECT * FROM study_materials WHERE id = ? AND faculty_id = ?");
            $checkStmt->execute([$id, $faculty_id]);
            $material = $checkStmt->fetch();
            
            if ($material) {
                if ($material['file_path'] && file_exists('../' . $material['file_path'])) {
                    unlink('../' . $material['file_path']);
                }
                
                $delStmt = $pdo->prepare("DELETE FROM study_materials WHERE id = ?");
                $delStmt->execute([$id]);
                $success = "Study material deleted successfully.";
                logActivity($pdo, 'DELETE_MATERIAL', null, "Deleted material ID: $id");
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// 3. Fetch materials
$materials = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, s.subject_name 
        FROM study_materials m
        JOIN subjects s ON m.subject_id = s.id
        WHERE m.faculty_id = ?
        ORDER BY m.upload_date DESC
    ");
    $stmt->execute([$faculty_id]);
    $materials = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore
}

include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-gray-800 fw-bold">Study Materials</h2>
                    <p class="text-muted mb-0">Upload and manage course materials</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                    <i class="fas fa-plus-circle me-2"></i> Upload Material
                </button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="fw-bold text-uppercase">Title</th>
                                    <th class="fw-bold text-uppercase">Course / Subject</th>
                                    <th class="fw-bold text-uppercase">File / Link</th>
                                    <th class="fw-bold text-uppercase">Date</th>
                                    <th class="fw-bold text-uppercase">Visibility</th>
                                    <th class="fw-bold text-uppercase text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($materials) > 0): ?>
                                    <?php foreach($materials as $m): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['title']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars(substr($m['description'], 0, 50)) . '...'; ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?php echo htmlspecialchars($m['subject_name']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($m['course']); ?> Sem <?php echo $m['semester']; ?> (<?php echo htmlspecialchars($m['division']); ?>)</div>
                                        </td>
                                        <td>
                                            <?php if ($m['file_path']): ?>
                                                <a href="../<?php echo $m['file_path']; ?>" target="_blank" class="badge bg-primary text-decoration-none p-2"><i class="fas fa-download me-1"></i> File</a>
                                            <?php endif; ?>
                                            <?php if ($m['link_url']): ?>
                                                <a href="<?php echo htmlspecialchars($m['link_url']); ?>" target="_blank" class="badge bg-info text-decoration-none p-2"><i class="fas fa-link me-1"></i> Link</a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($m['upload_date'])); ?></td>
                                        <td>
                                            <?php if ($m['visibility'] == 'Visible'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-2">Visible</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill px-3 py-2">Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this material?');">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fs-3 mb-3 opacity-25"></i>
                                            <h5>No materials uploaded</h5>
                                            <p class="small">Upload your first study material to share with students.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

<!-- Add Material Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST" enctype="multipart/form-data">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Upload Study Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted fw-bold small text-uppercase">Target Subject & Class</label>
                            <select name="assignment_id" class="form-select" required>
                                <option value="">Select class assignment...</option>
                                <?php foreach($assignments as $a): ?>
                                    <option value="<?php echo $a['id']; ?>">
                                        <?php echo htmlspecialchars($a['course'] . ' Sem ' . $a['semester'] . ' (' . $a['division'] . ') - ' . $a['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label text-muted fw-bold small text-uppercase">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted fw-bold small text-uppercase">Visibility</label>
                            <select name="visibility" class="form-select">
                                <option value="Visible">Visible to Students</option>
                                <option value="Hidden">Hidden (Draft)</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted fw-bold small text-uppercase">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold small text-uppercase">Upload File (Optional)</label>
                            <input type="file" name="file" class="form-control">
                            <small class="text-muted">Allowed: PDF, DOC, PPT, XLS, ZIP, Images</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold small text-uppercase">External Link (Optional)</label>
                            <input type="url" name="link_url" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-upload me-2"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
