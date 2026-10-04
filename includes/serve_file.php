<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/file_storage.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Basic Authorization - User must be logged in
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$is_faculty = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'faculty';
$is_student = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';

if (!$is_admin && !$is_faculty && !$is_student) {
    http_response_code(403);
    die("<h2>Unauthorized Access</h2><p>You must be logged in to access this file.</p>");
}

// 2. Validate input
$file = $_GET['file'] ?? '';
$action = $_GET['action'] ?? 'view'; // 'view' or 'download'

if (empty($file)) {
    http_response_code(400);
    die("<h2>Bad Request</h2><p>No file specified.</p>");
}

// 3. Security check path
if (strpos($file, '..') !== false || strpos($file, './') !== false || strpos($file, '\\') !== false || strpos($file, '/') === 0) {
    http_response_code(403);
    die("<h2>Forbidden</h2><p>Invalid file path.</p>");
}

// Ensure the path starts with 'uploads/' because that's how it is saved in DB
if (strpos($file, 'uploads/') !== 0) {
    $file = 'uploads/' . ltrim($file, '/');
}

// 4. Advanced Authorization (Database Checks)
if ($is_student) {
    $student_id = $_SESSION['student_id'];
    
    // Check if it's an assignment submission
    if (strpos($file, 'uploads/submissions/') === 0) {
        $stmt = $pdo->prepare("SELECT student_id FROM assignment_submissions WHERE attachment_path = ?");
        $stmt->execute([$file]);
        $submission = $stmt->fetch();
        
        if ($submission && $submission['student_id'] != $student_id) {
            http_response_code(403);
            die("<h2>Forbidden</h2><p>You are not authorized to view another student's submission.</p>");
        }
    }
}

// 5. Fetch from Database
$fileData = get_file_from_db($file, $pdo);

if (!$fileData) {
    http_response_code(404);
    die("<h2>File Not Found</h2><p>The requested file does not exist in the database.</p>");
}

$mime_type = $fileData['mime_type'];
$file_size = $fileData['file_size'];
$filename = $fileData['file_name'];
$file_content = $fileData['file_content'];

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Fallback previewable MIME types
$previewable_mimes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'txt' => 'text/plain',
    'csv' => 'text/csv'
];

$is_previewable = false;
if (isset($previewable_mimes[$extension])) {
    $mime_type = $previewable_mimes[$extension];
    $is_previewable = true;
}

if (!$mime_type || $mime_type === 'application/octet-stream' || $mime_type === 'inode/x-empty') {
    $mime_type = 'application/octet-stream';
}

// 6. Output headers
header("Content-Type: $mime_type");
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

if ($action === 'download' || !$is_previewable) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

// 7. Output file content
echo $file_content;
exit;
