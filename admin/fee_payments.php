<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    if (isset($_POST['action']) && $_POST['action'] === 'record_payment') {
        $student_fee_id = (int)$_POST['student_fee_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = trim($_POST['payment_method']);
        $transaction_reference = trim($_POST['transaction_reference']);
        $remarks = trim($_POST['remarks']);
        
        try {
            $pdo->beginTransaction();
            
            // Lock and fetch current fee state
            $feeStmt = $pdo->prepare("SELECT * FROM student_fees WHERE id = ? FOR UPDATE");
            $feeStmt->execute([$student_fee_id]);
            $fee = $feeStmt->fetch();
            
            if ($fee) {
                if ($amount <= 0) {
                    throw new Exception("Amount must be greater than zero.");
                }
                if ($amount > $fee['pending_amount']) {
                    throw new Exception("Payment amount cannot exceed pending amount.");
                }
                
                $attempts = 0;
                $max_attempts = 5;
                $inserted = false;
                
                while ($attempts < $max_attempts && !$inserted) {
                    $receipt_no = 'GLS-FEE-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    try {
                        $payStmt = $pdo->prepare("INSERT INTO fee_payments (student_fee_id, receipt_no, amount, payment_method, transaction_reference, remarks, received_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $payStmt->execute([$student_fee_id, $receipt_no, $amount, $payment_method, $transaction_reference, $remarks, $_SESSION['user_id']]);
                        $inserted = true;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate entry)
                            $attempts++;
                        } else {
                            throw $e;
                        }
                    }
                }
                
                if (!$inserted) {
                    throw new Exception("Failed to generate a unique receipt number after $max_attempts attempts. Please try again.");
                }
                
                $new_paid = $fee['paid_amount'] + $amount;
                $new_pending = $fee['pending_amount'] - $amount;
                
                $new_status = 'Pending';
                if ($new_pending <= 0) {
                    $new_status = 'Paid';
                } elseif ($new_paid > 0) {
                    $new_status = 'Partially Paid';
                }
                
                $updateStmt = $pdo->prepare("UPDATE student_fees SET paid_amount = ?, pending_amount = ?, status = ? WHERE id = ?");
                $updateStmt->execute([$new_paid, $new_pending, $new_status, $student_fee_id]);
                
                // Audit log
                $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'Record Payment', "Receipt $receipt_no", "Recorded payment of â‚¹$amount via $payment_method"]);

                // Send Notification
                $notif_title = "Fee Payment Received";
                $notif_msg = "Your payment of INR " . number_format($amount, 2) . " has been successfully recorded. Receipt No: $receipt_no.";
                send_notification($pdo, 'student', $fee['student_id'], $notif_title, $notif_msg, 'finance', 'dashboard.php');

                
                $pdo->commit();
                $success = "Payment recorded successfully. Receipt No: $receipt_no";
            } else {
                throw new Exception("Fee record not found.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Fetch pending fees for dropdown
$pendingFees = $pdo->query("
    SELECT sf.id, s.first_name, s.last_name, s.enrollment_no, fs.fee_name, sf.pending_amount 
    FROM student_fees sf 
    JOIN students s ON sf.student_id = s.id 
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id 
    WHERE sf.pending_amount > 0 
    ORDER BY s.first_name ASC
")->fetchAll();

// Fetch recent payments
$payments = $pdo->query("
    SELECT fp.*, s.first_name, s.last_name, s.enrollment_no, fs.fee_name
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    ORDER BY fp.created_at DESC
")->fetchAll();

include '../includes/admin_header.php';
?>
<div class="d-flex" id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <div id="page-content-wrapper" class="w-100 bg-light">
        <?php include '../includes/admin_navbar.php'; ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-dark fw-bold">Payments</h2>
                    <p class="text-muted mb-0">Record and track student payments</p>
                </div>
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="fas fa-plus-circle me-2"></i> Record Payment
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
                                    <th class="text-uppercase fw-bold">Receipt No</th>
                                    <th class="text-uppercase fw-bold">Student</th>
                                    <th class="text-uppercase fw-bold">Fee Name</th>
                                    <th class="text-uppercase fw-bold">Amount</th>
                                    <th class="text-uppercase fw-bold">Method</th>
                                    <th class="text-uppercase fw-bold">Date</th>
                                    <th class="text-uppercase fw-bold text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($payments) > 0): ?>
                                    <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($p['receipt_no']); ?></span></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($p['enrollment_no']); ?></div>
                                        </td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['fee_name']); ?></td>
                                        <td class="fw-bold text-success">â‚¹<?php echo number_format($p['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($p['created_at'])); ?></td>
                                        <td class="text-end">
                                            <a href="print_receipt.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7"><div class="empty-state"><i class="fas fa-folder-open"></i><h5>No payments recorded yet</h5><p>There are currently no records available in this section.</p></div></td>
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

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="record_payment">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Select Student & Fee</label>
                        <select name="student_fee_id" class="form-select" required>
                            <option value="">Choose Pending Fee...</option>
                            <?php foreach ($pendingFees as $pf): ?>
                                <option value="<?php echo $pf['id']; ?>">
                                    <?php echo htmlspecialchars($pf['first_name'] . ' ' . $pf['last_name'] . ' (' . $pf['enrollment_no'] . ') - ' . $pf['fee_name'] . ' (Pending: â‚¹' . $pf['pending_amount'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Amount (â‚¹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Payment Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Transaction Ref (Optional)</label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. UTR Number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size: 0.8rem;">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>




