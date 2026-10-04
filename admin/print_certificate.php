<?php
/**
 * SMS 2.0: Print Bonafide Certificate
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) die("Student not found.");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bonafide Certificate</title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 40px; text-align: center; }
        .certificate-container { border: 10px solid #787878; padding: 40px; margin: 0 auto; max-width: 800px; position: relative; }
        .header { font-size: 32px; font-weight: bold; margin-bottom: 20px; }
        .sub-header { font-size: 24px; font-weight: bold; text-decoration: underline; margin-bottom: 40px; }
        .content { font-size: 20px; line-height: 2; text-align: justify; margin-bottom: 60px; }
        .signature { display: flex; justify-content: space-between; margin-top: 60px; font-weight: bold; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 20px;"><button onclick="window.print()">Print</button></div>
    <div class="certificate-container">
        <div class="header">GLS UNIVERSITY</div>
        <div class="sub-header">BONAFIDE CERTIFICATE</div>
        <div class="content">
            This is to certify that Mr./Ms. <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong> 
            (Enrollment No. <strong><?= htmlspecialchars($student['enrollment_no']) ?></strong>) is a bonafide student of this institution 
            studying in the <strong><?= htmlspecialchars($student['course']) ?></strong> program during the academic year 2026-2027.
            <br><br>
            To the best of our knowledge, their character and conduct are satisfactory.
        </div>
        <div class="signature">
            <div>Date: <?= date('d M, Y') ?></div>
            <div>Head of Department<br>FCAIT</div>
        </div>
    </div>
</body>
</html>
