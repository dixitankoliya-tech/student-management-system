<?php
/**
 * SMS 2.0: Admin - Leadership Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();
$user = getCurrentUser();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'assign_dean') {
            $dept_id = $_POST['department_id'];
            $faculty_id = $_POST['faculty_id'];
            
            // 1. Deactivate old dean
            $old_dean = $pdo->prepare("SELECT faculty_id FROM department_heads WHERE department_id = ? AND status = 'Active'");
            $old_dean->execute([$dept_id]);
            $old_fid = $old_dean->fetchColumn();
            
            if ($old_fid) {
                // Set old dean to regular faculty role
                $pdo->prepare("UPDATE faculty SET role = 'faculty' WHERE id = ? AND role = 'dean'")->execute([$old_fid]);
                $pdo->prepare("UPDATE department_heads SET status = 'Inactive', end_date = CURDATE() WHERE department_id = ? AND status = 'Active'")->execute([$dept_id]);
            }
            
            // 2. Set new dean
            $pdo->prepare("INSERT INTO department_heads (department_id, faculty_id, start_date, status) VALUES (?, ?, CURDATE(), 'Active')")->execute([$dept_id, $faculty_id]);
            $pdo->prepare("UPDATE faculty SET role = 'dean' WHERE id = ?")->execute([$faculty_id]);
            
            $message = "Dean assigned successfully.";
        } 
        elseif ($action === 'assign_coordinator') {
            $course_id = $_POST['course_id'];
            $faculty_id = $_POST['faculty_id'];
            
            // Validate course belongs to same department (if needed, but FCAIT is the only one)
            
            // 1. Deactivate old coordinator
            $old_coord = $pdo->prepare("SELECT faculty_id FROM course_coordinators WHERE course_id = ? AND status = 'Active'");
            $old_coord->execute([$course_id]);
            $old_fid = $old_coord->fetchColumn();
            
            if ($old_fid) {
                // If old coordinator is no longer coordinating any other course (though they shouldn't by constraint)
                $pdo->prepare("UPDATE course_coordinators SET status = 'Inactive', end_date = CURDATE() WHERE course_id = ? AND status = 'Active'")->execute([$course_id]);
                
                // Only downgrade if not dean and not coordinating something else
                $pdo->prepare("
                    UPDATE faculty SET role = 'faculty' 
                    WHERE id = ? AND role = 'coordinator' 
                    AND NOT EXISTS (SELECT 1 FROM course_coordinators WHERE faculty_id = ? AND status = 'Active')
                ")->execute([$old_fid, $old_fid]);
            }
            
            // 2. Set new coordinator
            $pdo->prepare("INSERT INTO course_coordinators (course_id, faculty_id, start_date, status) VALUES (?, ?, CURDATE(), 'Active')")->execute([$course_id, $faculty_id]);
            $pdo->prepare("UPDATE faculty SET role = 'coordinator' WHERE id = ? AND role = 'faculty'")->execute([$faculty_id]); // Don't downgrade dean
            
            $message = "Course Coordinator assigned successfully.";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating assignment: " . $e->getMessage();
    }
}

// Fetch data
$departments = $pdo->query("
    SELECT d.*, 
        (SELECT f.first_name FROM department_heads dh JOIN faculty f ON dh.faculty_id = f.id WHERE dh.department_id = d.id AND dh.status = 'Active' LIMIT 1) as dean_first,
        (SELECT f.last_name FROM department_heads dh JOIN faculty f ON dh.faculty_id = f.id WHERE dh.department_id = d.id AND dh.status = 'Active' LIMIT 1) as dean_last,
        (SELECT dh.faculty_id FROM department_heads dh WHERE dh.department_id = d.id AND dh.status = 'Active' LIMIT 1) as dean_fid
    FROM departments d WHERE d.status = 'Active'
")->fetchAll(PDO::FETCH_ASSOC);

$courses = $pdo->query("
    SELECT c.*, d.department_name,
        (SELECT f.first_name FROM course_coordinators cc JOIN faculty f ON cc.faculty_id = f.id WHERE cc.course_id = c.id AND cc.status = 'Active' LIMIT 1) as coord_first,
        (SELECT f.last_name FROM course_coordinators cc JOIN faculty f ON cc.faculty_id = f.id WHERE cc.course_id = c.id AND cc.status = 'Active' LIMIT 1) as coord_last,
        (SELECT cc.faculty_id FROM course_coordinators cc WHERE cc.course_id = c.id AND cc.status = 'Active' LIMIT 1) as coord_fid
    FROM courses c JOIN departments d ON c.department_id = d.id WHERE c.status = 'Active' ORDER BY d.department_name, c.course_name
")->fetchAll(PDO::FETCH_ASSOC);

$faculty = $pdo->query("SELECT id, first_name, last_name, faculty_id as fac_code, role FROM faculty WHERE status = 'Active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<?php include '../includes/admin_navbar.php'; ?>

<!-- MAIN CONTENT -->
<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Leadership Assignment</h2>
        <p class="text-muted mb-0">Manage Deans and Course Coordinators</p>
    </div>
</header>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- DEAN ASSIGNMENT -->
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-user-shield text-primary me-2"></i>Department Heads (Deans)</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Department</th><th>Current Dean</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?= htmlspecialchars($dept['department_name']) ?></td>
                                <td>
                                    <?php if ($dept['dean_first']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1"><i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($dept['dean_first'] . ' ' . $dept['dean_last']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#deanModal<?= $dept['id'] ?>">Assign</button>
                                </td>
                            </tr>
                            
                            <!-- Dean Modal -->
                            <div class="modal fade" id="deanModal<?= $dept['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                        <input type="hidden" name="action" value="assign_dean">
                                        <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Assign Dean - <?= htmlspecialchars($dept['department_name']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Select Faculty</label>
                                                <select name="faculty_id" class="form-select" required>
                                                    <option value="">-- Select Faculty --</option>
                                                    <?php foreach ($faculty as $fac): ?>
                                                        <option value="<?= $fac['id'] ?>" <?= ($dept['dean_fid'] == $fac['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['fac_code'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text">This will deactivate the current Dean if assigned.</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Assign Dean</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- COORDINATOR ASSIGNMENT -->
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-user-tie text-warning me-2"></i>Course Coordinators</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Course</th><th>Current Coordinator</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($course['course_code']) ?></div>
                                </td>
                                <td>
                                    <?php if ($course['coord_first']): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning-subtle px-2 py-1"><i class="fas fa-user-tie me-1"></i> <?= htmlspecialchars($course['coord_first'] . ' ' . $course['coord_last']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#coordModal<?= $course['id'] ?>">Assign</button>
                                </td>
                            </tr>
                            
                            <!-- Coordinator Modal -->
                            <div class="modal fade" id="coordModal<?= $course['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST" class="modal-content">
                                        <input type="hidden" name="action" value="assign_coordinator">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Assign Coordinator - <?= htmlspecialchars($course['course_name']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Select Faculty</label>
                                                <select name="faculty_id" class="form-select" required>
                                                    <option value="">-- Select Faculty --</option>
                                                    <?php foreach ($faculty as $fac): ?>
                                                        <option value="<?= $fac['id'] ?>" <?= ($course['coord_fid'] == $fac['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['fac_code'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text">This will deactivate the current Coordinator if assigned.</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-warning">Assign Coordinator</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
