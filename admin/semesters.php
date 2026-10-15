<?php
/**
 * SMS 2.0: Admin Semesters Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();
requireRole('admin');

$error = '';
$success = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = 'Semester added successfully.';
    elseif ($_GET['msg'] === 'status_updated') $success = 'Semester status updated.';
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_semester'])) {
        $semester_number = (int)($_POST['semester_number'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if ($semester_number < 1 || $semester_number > 12) {
            $error = 'Semester number must be between 1 and 12.';
        } elseif (empty($description)) {
            $error = 'Description is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO semesters_master (semester_number, description) VALUES (?, ?)");
                $stmt->execute([$semester_number, $description]);
                logActivity($pdo, 'ADD_SEMESTER', null, "Added semester: $description");
                header("Location: semesters.php?msg=added");
                exit();
            } catch (PDOException $e) {
                if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                    $error = 'Semester number already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $sem_id = (int)$_POST['semester_id'];
        $new_status = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
        try {
            $stmt = $pdo->prepare("UPDATE semesters_master SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $sem_id]);
            logActivity($pdo, 'UPDATE_SEMESTER', null, "Toggled semester $sem_id status to $new_status");
            header("Location: semesters.php?msg=status_updated");
            exit();
        } catch (PDOException $e) {
            $error = 'Error updating status.';
        }
    }
}

// Fetch semesters with usage counts
try {
    $semesters = $pdo->query("
        SELECT sm.*,
        (SELECT COUNT(DISTINCT s.id) FROM subjects s WHERE s.semester = sm.semester_number) as subject_count,
        (SELECT COUNT(DISTINCT st.id) FROM students st WHERE st.semester = sm.semester_number) as student_count,
        (SELECT COUNT(*) FROM faculty_subjects fs WHERE fs.semester = sm.semester_number) as offering_count
        FROM semesters_master sm
        ORDER BY sm.semester_number ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $semesters = [];
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Semesters</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold mb-0 text-dark"><i class="fas fa-clock me-2 text-primary"></i>Semester Management</h2>
        <p class="text-muted mb-0">Configure academic semesters and operational statuses</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus me-2"></i>Add Semester
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="px-4 text-uppercase fw-bold">Semester</th>
                        <th class="text-uppercase fw-bold">Description</th>
                        <th class="text-uppercase fw-bold text-center">Subjects</th>
                        <th class="text-uppercase fw-bold text-center">Students</th>
                        <th class="text-uppercase fw-bold text-center">Offerings</th>
                        <th class="text-uppercase fw-bold">Status</th>
                        <th class="px-4 text-uppercase fw-bold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semesters as $s): ?>
                    <tr>
                        <td class="px-4"><strong>Semester <?= (int)$s['semester_number'] ?></strong></td>
                        <td><?= htmlspecialchars($s['description']) ?></td>
                        <td class="text-center"><span class="badge bg-info text-dark rounded-pill px-3"><?= (int)$s['subject_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-primary rounded-pill px-3"><?= (int)$s['student_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-secondary rounded-pill px-3"><?= (int)$s['offering_count'] ?></span></td>
                        <td>
                            <?php if ($s['status'] === 'Active'): ?>
                                <span class="badge bg-success rounded-pill px-3">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 text-end">
                            <form method="POST" class="d-inline m-0">
                                <?php csrfField(); ?>
                                <input type="hidden" name="semester_id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $s['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?= $s['status'] === 'Active' ? 'warning' : 'success' ?> rounded-pill px-3">
                                    <i class="fas fa-power-off me-1"></i><?= $s['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($semesters)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-folder-open fs-2 mb-3 opacity-50"></i><h5>No semesters configured</h5><p class="small mb-0">There are currently no records available in this section.</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4 border-0 shadow">
            <?php csrfField(); ?>
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="addModalLabel"><i class="fas fa-plus-circle me-2"></i>Add Semester</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Semester Number (1-12)</label>
                    <input type="number" name="semester_number" class="form-control" required min="1" max="12" placeholder="e.g. 9">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control" required placeholder="e.g., Semester 9">
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_semester" class="btn btn-primary rounded-pill px-4">Add Semester</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


