<?php
require_once '../config/database.php';
require_once '../includes/student_auth.php';

requireRole('student');
$student_id = $_SESSION['student_id'] ?? $_SESSION['user_id'];

// Fetch fee structures assigned to this student
$feesStmt = $pdo->prepare("
    SELECT sf.*, fs.fee_name, fs.amount as fee_structure_amount, fs.academic_year, fs.semester, fs.due_date 
    FROM student_fees sf
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    WHERE sf.student_id = ?
    ORDER BY fs.due_date DESC
");
$feesStmt->execute([$student_id]);
$my_fees = $feesStmt->fetchAll();

// Fetch payment history
$payStmt = $pdo->prepare("
    SELECT fp.*, fs.fee_name 
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    WHERE sf.student_id = ?
    ORDER BY fp.created_at DESC
");
$payStmt->execute([$student_id]);
$payment_history = $payStmt->fetchAll();

include '../includes/student_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/student_navbar.php'; ?>
        
        <div class="container-fluid py-4 px-4">
            <h2 class="h3 mb-0 text-dark fw-bold">My Fees</h2>
            <p class="text-muted mb-4">View your fee details and payment history.</p>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Current Fees -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                            <h5 class="fw-bold mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Fee Structures</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (count($my_fees) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light text-muted" style="font-size: 0.85rem;">
                                            <tr>
                                                <th>Fee Name</th>
                                                <th>Total</th>
                                                <th>Paid</th>
                                                <th>Pending</th>
                                                <th>Status</th>
                                                <th>Due Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($my_fees as $fee): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                                    <td>₹<?php echo number_format($fee['total_amount'], 2); ?></td>
                                                    <td class="text-success fw-bold">₹<?php echo number_format($fee['paid_amount'], 2); ?></td>
                                                    <td class="text-danger fw-bold">₹<?php echo number_format($fee['pending_amount'], 2); ?></td>
                                                    <td>
                                                        <?php if ($fee['status'] === 'Paid'): ?>
                                                            <span class="badge bg-success rounded-pill px-3">Paid</span>
                                                        <?php elseif ($fee['status'] === 'Partially Paid'): ?>
                                                            <span class="badge bg-warning text-dark rounded-pill px-3">Partial</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger rounded-pill px-3">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $fee['due_date'] ? date('d M, Y', strtotime($fee['due_date'])) : 'N/A'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fs-1 text-success mb-2"></i>
                                    <p>No fee structures assigned yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Payment History -->
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                            <h5 class="fw-bold mb-0"><i class="fas fa-history text-secondary me-2"></i>Payment History</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (count($payment_history) > 0): ?>
                                <div class="list-group list-group-flush border-0">
                                    <?php foreach ($payment_history as $pay): ?>
                                        <div class="list-group-item px-0 py-3 border-bottom">
                                            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold text-dark">₹<?php echo number_format($pay['amount'], 2); ?></h6>
                                                <small class="text-muted"><?php echo date('d M Y', strtotime($pay['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted small">
                                                For: <?php echo htmlspecialchars($pay['fee_name']); ?><br>
                                                Receipt No: <strong><?php echo htmlspecialchars($pay['receipt_no']); ?></strong>
                                            </p>
                                            <a href="print_receipt.php?id=<?php echo $pay['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-1">
                                                <i class="fas fa-download me-1"></i> Download Receipt
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No payment history found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
