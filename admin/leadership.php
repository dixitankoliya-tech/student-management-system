<?php
/**
 * SMS 2.0: Admin - Leadership Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();
requireRole('admin');
$user = getCurrentUser();

$message = '';
$error = '';

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = $_POST['action'] ?? '';
    $assigned_by = (int)($_SESSION['user_id'] ?? 1);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'assign_dean') {
            $dept_id = (int)($_POST['department_id'] ?? 0);
            $faculty_id = (int)($_POST['faculty_id'] ?? 0);
            
            if ($dept_id <= 0 || $faculty_id <= 0) {
                throw new Exception("Please select a valid department and faculty member.");
            }
            
            // 1. Check current dean
            $old_dean = $pdo->prepare("SELECT faculty_id FROM department_heads WHERE department_id = ? AND status = 'Active'");
            $old_dean->execute([$dept_id]);
            $old_fid = $old_dean->fetchColumn();
            
            if ($old_fid == $faculty_id) {
                throw new Exception("The selected faculty member is already the active Dean for this department.");
            }
            
            if ($old_fid) {
                // Deactivate old dean record
                $pdo->prepare("UPDATE department_heads SET status = 'Inactive', end_date = CURDATE() WHERE department_id = ? AND status = 'Active'")->execute([$dept_id]);
                
                // Set old dean to regular faculty role if not dean of another department
                $checkOtherDean = $pdo->prepare("SELECT 1 FROM department_heads WHERE faculty_id = ? AND status = 'Active'");
                $checkOtherDean->execute([$old_fid]);
                if (!$checkOtherDean->fetchColumn()) {
                    $pdo->prepare("UPDATE faculty SET role = 'faculty' WHERE id = ? AND role = 'dean'")->execute([$old_fid]);
                }
            }
            
            // 2. Set new dean with assigned_date (correct column name)
            $pdo->prepare("INSERT INTO department_heads (department_id, faculty_id, assigned_date, status, assigned_by) VALUES (?, ?, CURDATE(), 'Active', ?)")->execute([$dept_id, $faculty_id, $assigned_by]);
            $pdo->prepare("UPDATE faculty SET role = 'dean' WHERE id = ?")->execute([$faculty_id]);
            
            // 3. Log audit activity
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$assigned_by, 'Assign Dean', "Department ID $dept_id", "Assigned Faculty ID $faculty_id as Dean"]);
            
            $pdo->commit();
            header("Location: leadership.php?msg=" . urlencode("Dean assigned successfully."));
            exit();
        } 
        elseif ($action === 'assign_coordinator') {
            $course_id = (int)($_POST['course_id'] ?? 0);
            $faculty_id = (int)($_POST['faculty_id'] ?? 0);
            
            if ($course_id <= 0 || $faculty_id <= 0) {
                throw new Exception("Please select a valid course and faculty member.");
            }
            
            // 1. Check current coordinator
            $old_coord = $pdo->prepare("SELECT faculty_id FROM course_coordinators WHERE course_id = ? AND status = 'Active'");
            $old_coord->execute([$course_id]);
            $old_fid = $old_coord->fetchColumn();
            
            if ($old_fid == $faculty_id) {
                throw new Exception("The selected faculty member is already the active Coordinator for this course.");
            }
            
            if ($old_fid) {
                // Deactivate old coordinator
                $pdo->prepare("UPDATE course_coordinators SET status = 'Inactive', end_date = CURDATE() WHERE course_id = ? AND status = 'Active'")->execute([$course_id]);
                
                // Only downgrade if not dean and not coordinating something else
                $pdo->prepare("
                    UPDATE faculty SET role = 'faculty' 
                    WHERE id = ? AND role = 'coordinator' 
                    AND NOT EXISTS (SELECT 1 FROM course_coordinators WHERE faculty_id = ? AND status = 'Active')
                    AND NOT EXISTS (SELECT 1 FROM department_heads WHERE faculty_id = ? AND status = 'Active')
                ")->execute([$old_fid, $old_fid, $old_fid]);
            }
            
            // 2. Set new coordinator with assigned_date (correct column name)
            $pdo->prepare("INSERT INTO course_coordinators (course_id, faculty_id, assigned_date, status, assigned_by) VALUES (?, ?, CURDATE(), 'Active', ?)")->execute([$course_id, $faculty_id, $assigned_by]);
            $pdo->prepare("UPDATE faculty SET role = 'coordinator' WHERE id = ? AND role = 'faculty'")->execute([$faculty_id]); // Don't downgrade dean
            
            // 3. Log audit activity
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$assigned_by, 'Assign Coordinator', "Course ID $course_id", "Assigned Faculty ID $faculty_id as Course Coordinator"]);
            
            $pdo->commit();
            header("Location: leadership.php?msg=" . urlencode("Course Coordinator assigned successfully."));
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
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
include '../includes/admin_navbar.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Leadership</li>
    </ol>
</nav>

<!-- MAIN CONTENT -->
<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0"><i class="fas fa-user-shield me-2 text-primary"></i>Leadership Assignment</h2>
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
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Department</th><th>Current Dean</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($dept['department_name']) ?></td>
                                <td>
                                    <?php if ($dept['dean_first']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 rounded-pill"><i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($dept['dean_first'] . ' ' . $dept['dean_last']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#deanModal<?= $dept['id'] ?>">
                                        <i class="fas fa-user-edit me-1"></i>Assign
                                    </button>
                                </td>
                            </tr>
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
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Course</th><th>Current Coordinator</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($course['course_code']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($course['course_name']) ?></div>
                                </td>
                                <td>
                                    <?php if ($course['coord_first']): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning-subtle px-3 py-2 rounded-pill"><i class="fas fa-user-tie me-1"></i> <?= htmlspecialchars($course['coord_first'] . ' ' . $course['coord_last']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#coordModal<?= $course['id'] ?>">
                                        <i class="fas fa-user-edit me-1"></i>Assign
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dean Assignment Modals -->
<?php foreach ($departments as $dept): ?>
<div class="modal fade" id="deanModal<?= $dept['id'] ?>" tabindex="-1" aria-labelledby="deanModalLabel<?= $dept['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4 border-0 shadow">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="assign_dean">
            <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="deanModalLabel<?= $dept['id'] ?>"><i class="fas fa-user-shield text-primary me-2"></i>Assign Dean - <?= htmlspecialchars($dept['department_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Select Faculty Member</label>
                    <select name="faculty_id" class="form-select form-select-lg" required>
                        <option value="">-- Choose Faculty --</option>
                        <?php foreach ($faculty as $fac): ?>
                            <option value="<?= $fac['id'] ?>" <?= ($dept['dean_fid'] == $fac['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['fac_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Assigning a new Dean will automatically conclude the previous Dean's term.</div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Course Coordinator Assignment Modals -->
<?php foreach ($courses as $course): ?>
<div class="modal fade" id="coordModal<?= $course['id'] ?>" tabindex="-1" aria-labelledby="coordModalLabel<?= $course['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4 border-0 shadow">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="assign_coordinator">
            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="coordModalLabel<?= $course['id'] ?>"><i class="fas fa-user-tie text-warning me-2"></i>Assign Coordinator - <?= htmlspecialchars($course['course_code']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Select Faculty Member</label>
                    <select name="faculty_id" class="form-select form-select-lg" required>
                        <option value="">-- Choose Faculty --</option>
                        <?php foreach ($faculty as $fac): ?>
                            <option value="<?= $fac['id'] ?>" <?= ($course['coord_fid'] == $fac['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name'] . ' (' . $fac['fac_code'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Assigning a new Coordinator will automatically update previous assignments.</div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>
