<?php
require_once __DIR__ . '/../config/database.php';

echo "=== STUDY MATERIALS SAMPLE ===\n";
$stmt = $pdo->query("SELECT id, title, subject_id, faculty_id, course, semester, file_path, file_type, link_url, visibility FROM study_materials LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

echo "=== ASSIGNMENTS SAMPLE ===\n";
$stmt = $pdo->query("SELECT id, title, subject_id, faculty_id, course, semester, attachment_path FROM assignments LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

echo "=== ASSIGNMENT SUBMISSIONS SAMPLE ===\n";
$stmt = $pdo->query("SELECT id, assignment_id, student_id, attachment_path FROM assignment_submissions LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}

echo "=== FILE STORAGE COUNT ===\n";
try {
    $c = $pdo->query("SELECT COUNT(*) FROM file_storage")->fetchColumn();
    echo "file_storage records: $c\n";
    if ($c > 0) {
        $files = $pdo->query("SELECT id, file_path, file_name, mime_type, file_size FROM file_storage LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        print_r($files);
    }
} catch (Exception $e) {
    echo "file_storage table error: " . $e->getMessage() . "\n";
}
