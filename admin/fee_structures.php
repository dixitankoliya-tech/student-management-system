<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $course = trim($_POST['course']);
        $academic_year = trim($_POST['academic_year']);
        $semester = (int)$_POST['semester'];
        $fee_name = trim($_POST['fee_name']);
        $amount = (float)$_POST['amount'];
        $due_date = trim($_POST['due_date']);
        
        if (empty($course) || empty($academic_year) || empty($fee_name) || empty($due_date) || $amount <= 0) {
            $error = "All fields are required and amount must be greater than zero.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO fee_structures (course, academic_year, semester, fee_name, amount, due_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$course, $academic_year, $semester, $fee_name, $amount, $due_date]);
                
                // Log action
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Create Fee Structure', "Fee Structure for $course", "Created fee $fee_name (Amount: $amount)"]);
                
                $success = "Fee structure added successfully.";
            } catch (PDOException $e) {
                $error = "Database error. Could not add fee structure.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        // Check if there are assignments
        $check = $pdo->prepare("SELECT COUNT(*) FROM student_fees WHERE fee_structure_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $error = "Cannot delete fee structure. It is already assigned to students.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
            $stmt->execute([$id]);
            
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Delete Fee Structure', "Fee Structure ID $id", "Deleted fee structure"]);
            
            $success = "Fee structure deleted successfully.";
        }
    }
}

// Fetch lists for dropdowns
$courses = $pdo->query("SELECT course_code, course_name FROM courses WHERE status = 'Active'")->fetchAll();
$years = $pdo->query("SELECT year_name FROM academic_years WHERE status = 'Active'")->fetchAll();

$structures = $pdo->query("SELECT * FROM fee_structures ORDER BY created_at DESC")->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Fee Structures</h2>
                    <p class="text-muted mb-0">Manage university fee templates</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                    <i class="fas fa-plus-circle me-2"></i> Create Fee Structure
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
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="text-uppercase fw-bold">Course & Sem</th>
                                    <th class="text-uppercase fw-bold">Academic Year</th>
                                    <th class="text-uppercase fw-bold">Fee Name</th>
                                    <th class="text-uppercase fw-bold">Amount</th>
                                    <th class="text-uppercase fw-bold">Due Date</th>
                                    <th class="text-uppercase fw-bold text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($structures) > 0): ?>
                                    <?php foreach ($structures as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['course']); ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;">Semester <?php echo $s['semester']; ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($s['academic_year']); ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($s['fee_name']); ?></td>
                                        <td class="fw-bold text-success">₹<?php echo number_format($s['amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($s['due_date'])); ?></td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this fee structure?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No fee structures found</h5><p>There are currently no records available in this section.</p></div></td>
                                    </tr>
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
<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Create Fee Structure</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Course</label>
                        <select name="course" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Academic Year</label>
                            <select name="academic_year" class="form-select" required>
                                <option value="">Select Year</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo htmlspecialchars($y['year_name']); ?>"><?php echo htmlspecialchars($y['year_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Semester</label>
                            <input type="number" name="semester" class="form-control" min="1" max="10" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Fee Name</label>
                        <input type="text" name="fee_name" class="form-control" placeholder="e.g. Tuition Fee" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Due Date</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Create Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


