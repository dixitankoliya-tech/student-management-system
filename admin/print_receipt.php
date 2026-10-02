<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireRole('admin');

if (!isset($_GET['id'])) {
    die("Receipt ID not provided.");
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT fp.*, s.first_name, s.last_name, s.enrollment_no, s.course, s.semester as stu_sem, s.admission_year, 
           fs.fee_name, fs.academic_year, sf.total_amount, sf.paid_amount, sf.pending_amount, sf.status
    FROM fee_payments fp
    JOIN student_fees sf ON fp.student_fee_id = sf.id
    JOIN students s ON sf.student_id = s.id
    JOIN fee_structures fs ON sf.fee_structure_id = fs.id
    WHERE fp.id = ?
");
$stmt->execute([$id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Receipt not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt - <?php echo htmlspecialchars($receipt['receipt_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .receipt-container { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
        .receipt-header { border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; text-align: center; }
        @media print {
            body { background-color: #fff; }
            .receipt-container { box-shadow: none; margin: 0; padding: 20px; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="container receipt-container border">
        <div class="text-end mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Receipt</button>
            <a href="fee_payments.php" class="btn btn-secondary">Back</a>
        </div>
        
        <div class="receipt-header">
            <h2 class="fw-bold mb-1">GLS UNIVERSITY</h2>
            <p class="text-muted mb-0">Student Management System</p>
            <h4 class="mt-3 mb-0">FEE RECEIPT</h4>
        </div>
        
        <div class="row mb-4">
            <div class="col-sm-6">
                <table class="table table-sm table-borderless">
                    <tr><th style="width: 120px;">Receipt No:</th><td><strong><?php echo htmlspecialchars($receipt['receipt_no']); ?></strong></td></tr>
                    <tr><th>Payment Date:</th><td><?php echo date('F d, Y h:i A', strtotime($receipt['created_at'])); ?></td></tr>
                    <tr><th>Payment Mode:</th><td><?php echo htmlspecialchars($receipt['payment_method']); ?></td></tr>
                    <?php if ($receipt['transaction_reference']): ?>
                    <tr><th>Ref No:</th><td><?php echo htmlspecialchars($receipt['transaction_reference']); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="col-sm-6">
                <table class="table table-sm table-borderless">
                    <tr><th style="width: 120px;">Student Name:</th><td><strong><?php echo htmlspecialchars($receipt['first_name'] . ' ' . $receipt['last_name']); ?></strong></td></tr>
                    <tr><th>Enrollment No:</th><td><?php echo htmlspecialchars($receipt['enrollment_no']); ?></td></tr>
                    <tr><th>Course:</th><td><?php echo htmlspecialchars($receipt['course']); ?> (Sem <?php echo htmlspecialchars($receipt['stu_sem']); ?>)</td></tr>
                    <tr><th>Academic Year:</th><td><?php echo htmlspecialchars($receipt['academic_year']); ?></td></tr>
                </table>
            </div>
        </div>
        
        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th>S.No.</th>
                    <th>Fee Description</th>
                    <th class="text-end">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?php echo htmlspecialchars($receipt['fee_name']); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format($receipt['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="row mb-5">
            <div class="col-6">
                <p class="mb-1"><strong>Amount in words:</strong></p>
                <p class="text-muted fst-italic">Rupees <?php 
                    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
                    echo ucwords($f->format($receipt['amount']));
                ?> Only</p>
                
                <?php if ($receipt['remarks']): ?>
                <p class="mb-1 mt-3"><strong>Remarks:</strong></p>
                <p class="text-muted"><?php echo htmlspecialchars($receipt['remarks']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <table class="table table-sm table-borderless ms-auto" style="width: 250px;">
                    <tr><th>Total Fee:</th><td class="text-end">₹<?php echo number_format($receipt['total_amount'], 2); ?></td></tr>
                    <tr><th>Total Paid:</th><td class="text-end text-success">₹<?php echo number_format($receipt['paid_amount'], 2); ?></td></tr>
                    <tr><th>Balance Pending:</th><td class="text-end text-danger border-top fw-bold">₹<?php echo number_format($receipt['pending_amount'], 2); ?></td></tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-5 pt-5">
            <div class="col-6 text-center">
                <hr style="width: 200px; margin: 0 auto 10px auto;">
                <p class="mb-0 text-muted">Student Signature</p>
            </div>
            <div class="col-6 text-center">
                <hr style="width: 200px; margin: 0 auto 10px auto;">
                <p class="mb-0 text-muted">Authorized Signatory</p>
            </div>
        </div>
        
        <div class="text-center mt-5 pt-3 border-top">
            <p class="text-muted" style="font-size: 0.8rem;">This is a computer generated receipt and does not require a physical signature.</p>
        </div>
    </div>
</body>
</html>

