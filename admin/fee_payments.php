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

// Overall aggregate statistics
$summary = $pdo->query("SELECT COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount FROM fee_payments")->fetch();
$total_all_records = (int)$summary['total_count'];
$total_all_amount = (float)$summary['total_amount'];

// Pagination & Filter Parameters
$search = trim($_GET['search'] ?? '');
$filter_method = trim($_GET['method'] ?? '');
$raw_limit = trim($_GET['limit'] ?? '');
$limit = ($raw_limit === 'all') ? 0 : (($raw_limit !== '' && is_numeric($raw_limit)) ? max(10, (int)$raw_limit) : 100);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($limit > 0) ? ($page - 1) * $limit : 0;

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(fp.receipt_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.enrollment_no LIKE ? OR fs.fee_name LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($filter_method !== '' && $filter_method !== 'All') {
    $whereClauses[] = "fp.payment_method = ?";
    $params[] = $filter_method;
}

$whereSql = count($whereClauses) > 0 ? ("WHERE " . implode(" AND ", $whereClauses)) : "";

// Count total matching records for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    $whereSql
");
$countStmt->execute($params);
$total_filtered = (int)$countStmt->fetchColumn();
$total_pages = ($limit > 0) ? (int)ceil($total_filtered / $limit) : 1;
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($limit > 0) ? ($page - 1) * $limit : 0;
}

// Fetch payments with bounded retrieval
$limitSql = ($limit > 0) ? "LIMIT $limit OFFSET $offset" : "";
$stmt = $pdo->prepare("
    SELECT fp.*, s.first_name, s.last_name, s.enrollment_no, fs.fee_name
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    $whereSql
    ORDER BY fp.created_at DESC
    $limitSql
");
$stmt->execute($params);
$payments = $stmt->fetchAll();


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

            <!-- Summary KPI Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-4 bg-success bg-opacity-10 p-3 text-success me-3">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Total Collections</div>
                                <h4 class="mb-0 fw-bold text-dark">₹<?php echo number_format($total_all_amount, 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-4 bg-primary bg-opacity-10 p-3 text-primary me-3">
                                <i class="fas fa-receipt fa-2x"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Total Receipts</div>
                                <h4 class="mb-0 fw-bold text-dark"><?php echo number_format($total_all_records); ?> Receipts</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-4 bg-warning bg-opacity-10 p-3 text-warning me-3">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Pending Accounts</div>
                                <h4 class="mb-0 fw-bold text-dark"><?php echo count($pendingFees); ?> Accounts</h4>
                            </div>
                        </div>
                    </div>
                </div>
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

            <!-- Search, Filter & Bounded Control Bar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-12 col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search receipt, student, enrollment..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select name="method" class="form-select">
                                <option value="">All Payment Methods</option>
                                <option value="Cash" <?php echo ($filter_method === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="UPI" <?php echo ($filter_method === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                                <option value="Bank Transfer" <?php echo ($filter_method === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="Card" <?php echo ($filter_method === 'Card') ? 'selected' : ''; ?>>Card</option>
                                <option value="Other" <?php echo ($filter_method === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="limit" class="form-select">
                                <option value="25" <?php echo ($limit === 25) ? 'selected' : ''; ?>>25 per page</option>
                                <option value="50" <?php echo ($limit === 50) ? 'selected' : ''; ?>>50 per page</option>
                                <option value="100" <?php echo ($limit === 100) ? 'selected' : ''; ?>>100 per page</option>
                                <option value="all" <?php echo ($raw_limit === 'all' || $limit === 0) ? 'selected' : ''; ?>>All (450)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary fw-bold w-100">
                                <i class="fas fa-filter me-1"></i> Apply Filter
                            </button>
                            <?php if ($search !== '' || $filter_method !== '' || $raw_limit !== ''): ?>
                                <a href="fee_payments.php" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted small">
                            Showing <strong><?php echo count($payments); ?></strong> of <strong><?php echo number_format($total_filtered); ?></strong> payment records
                        </div>
                    </div>
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
                                        <td class="fw-bold text-success">₹<?php echo number_format($p['amount'], 2); ?></td>
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
                                        <td colspan="7"><div class="empty-state text-center py-5"><i class="fas fa-folder-open fa-3x text-muted mb-3"></i><h5>No payments recorded yet</h5><p class="text-muted">There are currently no records matching the specified criteria.</p></div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <div class="text-muted small">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&method=<?php echo urlencode($filter_method); ?>&limit=<?php echo $limit; ?>">Previous</a>
                                </li>
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&method=<?php echo urlencode($filter_method); ?>&limit=<?php echo $limit; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&method=<?php echo urlencode($filter_method); ?>&limit=<?php echo $limit; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
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




