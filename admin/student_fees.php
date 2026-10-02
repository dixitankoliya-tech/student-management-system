<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    if (isset($_POST['action']) && $_POST['action'] === 'assign_bulk') {
        $fee_id = (int)$_POST['fee_structure_id'];
        
        $feeStmt = $pdo->prepare("SELECT * FROM fee_structures WHERE id = ?");
        $feeStmt->execute([$fee_id]);
        $fee = $feeStmt->fetch();
        
        if ($fee) {
            // Find students matching the course and semester who are active
            $studentsStmt = $pdo->prepare("
                SELECT s.id 
                FROM students s
                JOIN student_academic_enrollments sae ON s.id = sae.student_id
                WHERE sae.course = ? AND sae.semester = ? AND sae.status = 'Active'
            ");
            $studentsStmt->execute([$fee['course'], $fee['semester']]);
            $students = $studentsStmt->fetchAll();
            
            $assignedCount = 0;
            $assignStmt = $pdo->prepare("INSERT INTO student_fees (student_id, fee_structure_id, total_amount, pending_amount, status) VALUES (?, ?, ?, ?, 'Pending') ON DUPLICATE KEY UPDATE id=id");
            
            $check = $pdo->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_structure_id = ?");
            foreach ($students as $stu) {
                // Check if already assigned
                $check->execute([$stu['id'], $fee['id']]);
                if (!$check->fetch()) {
                    $assignStmt->execute([$stu['id'], $fee['id'], $fee['amount'], $fee['amount']]);
                    $assignedCount++;
                }
            }
            
            $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'Assign Fees', "Fee ID {$fee['id']}", "Assigned fee to $assignedCount students"]);
            
            $success = "Successfully assigned fee to $assignedCount student(s).";
        }
    }
}

// Fetch available structures (with graceful fallback if status column doesn't exist)
try {
    $structures = $pdo->query("SELECT * FROM fee_structures WHERE status = 'Active'")->fetchAll();
} catch (PDOException $e) {
    // Fallback if status column doesn't exist
    try {
        $structures = $pdo->query("SELECT * FROM fee_structures")->fetchAll();
    } catch (PDOException $e2) {
        $structures = [];
        $error = "Fee structures table not found. Please run database migrations.";
    }
}

// Fetch student fees summary
try {
    $studentFees = $pdo->query("
        SELECT sf.*, s.first_name, s.last_name, s.enrollment_no, s.course, fs.fee_name, fs.due_date
        FROM student_fees sf
        JOIN students s ON sf.student_id = s.id
        JOIN fee_structures fs ON sf.fee_structure_id = fs.id
        ORDER BY sf.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $studentFees = [];
    error_log("Student fees query error: " . $e->getMessage());
}


include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Student Fees</h2>
                    <p class="text-muted mb-0">Assign and monitor fee statuses</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#assignFeeModal">
                    <i class="fas fa-hand-holding-usd me-2"></i> Assign Fees
                </button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="text-uppercase fw-bold">Student</th>
                                    <th class="text-uppercase fw-bold">Fee Name</th>
                                    <th class="text-uppercase fw-bold">Total</th>
                                    <th class="text-uppercase fw-bold">Paid</th>
                                    <th class="text-uppercase fw-bold">Pending</th>
                                    <th class="text-uppercase fw-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($studentFees) > 0): ?>
                                    <?php foreach ($studentFees as $sf): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($sf['first_name'] . ' ' . $sf['last_name']); ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($sf['enrollment_no']); ?> • <?php echo htmlspecialchars($sf['course']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($sf['fee_name']); ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;">Due: <?php echo date('M d, Y', strtotime($sf['due_date'])); ?></div>
                                        </td>
                                        <td class="fw-bold text-dark">&#8377;<?php echo number_format($sf['total_amount'], 2); ?></td>
                                        <td class="fw-bold text-success">&#8377;<?php echo number_format($sf['paid_amount'], 2); ?></td>
                                        <td class="fw-bold text-danger">&#8377;<?php echo number_format($sf['pending_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = 'bg-secondary';
                                            if ($sf['status'] === 'Paid') $badgeClass = 'bg-success';
                                            elseif ($sf['status'] === 'Partially Paid') $badgeClass = 'bg-warning text-dark';
                                            elseif ($sf['status'] === 'Pending') $badgeClass = 'bg-danger';
                                            elseif ($sf['status'] === 'Overdue') $badgeClass = 'bg-dark';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-2"><?php echo htmlspecialchars($sf['status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No student fees found</h5><p>There are currently no records available in this section.</p></div></td>
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

<!-- Assign Modal -->
<div class="modal fade" id="assignFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="assign_bulk">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Bulk Assign Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Select Fee Structure</label>
                        <select name="fee_structure_id" class="form-select" required>
                            <option value="">Choose Fee...</option>
                            <?php foreach ($structures as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['course'] . ' - ' . $s['fee_name'] . ' (&#8377;' . $s['amount'] . ')', ENT_QUOTES | ENT_HTML5); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text mt-2">
                            This will automatically assign the selected fee to all active students in the matching course and semester.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Assign Fees</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


