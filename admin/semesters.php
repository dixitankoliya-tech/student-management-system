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
                $success = 'Semester added successfully.';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
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
            $success = 'Semester status updated.';
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
?>
<?php
include '../includes/admin_header.php';
?>
    <div class="d-flex" id="wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <div id="page-content-wrapper" class="w-100">
            <?php include '../includes/admin_navbar.php'; ?>
            <div class="container-fluid px-4 py-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Semesters</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0"><i class="fas fa-clock me-2 text-primary"></i>Semester Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus me-2"></i>Add Semester
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Semester</th>
                                        <th>Description</th>
                                        <th>Subjects</th>
                                        <th>Students</th>
                                        <th>Offerings</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semesters as $s): ?>
                                    <tr>
                                        <td><strong>Semester <?= $s['semester_number'] ?></strong></td>
                                        <td><?= htmlspecialchars($s['description']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= $s['subject_count'] ?></span></td>
                                        <td><span class="badge bg-primary"><?= $s['student_count'] ?></span></td>
                                        <td><span class="badge bg-secondary"><?= $s['offering_count'] ?></span></td>
                                        <td>
                                            <?php if ($s['status'] === 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="semester_id" value="<?= $s['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $s['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?= $s['status'] === 'Active' ? 'warning' : 'success' ?>">
                                                    <?= $s['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($semesters)): ?>
                                        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No semesters configured</h5><p>There are currently no records available in this section.</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Add Semester</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Semester Number</label>
                        <input type="number" name="semester_number" class="form-control" required min="1" max="12">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" required placeholder="e.g., Semester 9">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_semester" class="btn btn-primary px-4">Add Semester</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

