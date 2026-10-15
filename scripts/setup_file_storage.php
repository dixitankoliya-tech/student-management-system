<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/file_storage.php';

init_file_storage($pdo);
echo "file_storage table verified/created in database.\n";

// Ensure upload directories exist
$dirs = [
    __DIR__ . '/../uploads',
    __DIR__ . '/../uploads/materials',
    __DIR__ . '/../uploads/assignments',
    __DIR__ . '/../uploads/study_materials'
];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0777, true);
        echo "Created directory: $d\n";
    }
}

// Generate valid sample PDF helper
function createSamplePdf($path, $title) {
    if (file_exists($path) && filesize($path) > 100) return;
    
    $content = "%PDF-1.4\n";
    $content .= "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $content .= "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
    $content .= "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n";
    $stream = "BT /F1 18 Tf 50 720 Td (GLS University FCAIT) Tj ET\n";
    $stream .= "BT /F1 14 Tf 50 690 Td (" . addcslashes($title, '()\\') . ") Tj ET\n";
    $stream .= "BT /F1 11 Tf 50 660 Td (Academic Course Study Material & Reference Guide) Tj ET\n";
    $stream .= "BT /F1 10 Tf 50 630 Td (Verified by Faculty Coordinator - Session 2025-2026) Tj ET\n";
    $len = strlen($stream);
    $content .= "4 0 obj << /Length $len >> stream\n" . $stream . "endstream\nendobj\n";
    $content .= "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
    $content .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000266 00000 n \n" . sprintf("%010d 00000 n \n", 266 + 40 + $len) . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n" . (350 + $len) . "\n%%EOF\n";
    
    file_put_contents($path, $content);
}

// Ensure unit_notes PDFs exist for study materials in database
$mats = $pdo->query("SELECT id, title, file_path FROM study_materials")->fetchAll(PDO::FETCH_ASSOC);
$createdCount = 0;
foreach ($mats as $m) {
    if (!empty($m['file_path'])) {
        $fullPath = __DIR__ . '/../' . ltrim($m['file_path'], '/');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        createSamplePdf($fullPath, $m['title']);
        $createdCount++;
    }
}
echo "Ensured $createdCount sample study material PDFs exist on disk.\n";

// Ensure submission PDFs exist for assignment submissions in database
$subs = $pdo->query("SELECT id, attachment_path FROM assignment_submissions LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach ($subs as $s) {
    if (!empty($s['attachment_path'])) {
        $fullPath = __DIR__ . '/../' . ltrim($s['attachment_path'], '/');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        createSamplePdf($fullPath, "Assignment Submission #" . $s['id']);
    }
}
echo "Ensured sample assignment submission PDFs exist on disk.\n";
