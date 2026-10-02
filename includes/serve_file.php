<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session_handler.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Basic Authorization - User must be logged in
$is_admin = isset($_SESSION['admin_id']);
$is_faculty = isset($_SESSION['faculty_id']);
$is_student = isset($_SESSION['student_id']);

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

// 3. Security: Prevent directory traversal and restrict to uploads directory
if (strpos($file, '..') !== false || strpos($file, './') !== false || strpos($file, '\\') !== false || strpos($file, '/') === 0) {
    http_response_code(403);
    die("<h2>Forbidden</h2><p>Invalid file path.</p>");
}

// Normalize the file path
// Assuming the file is passed like "uploads/assignments/file.pdf"
// If it starts with '../', we already blocked it above.
$file = ltrim($file, '/');

// Strip any leading "uploads/" if it was passed, so we can reliably prepend it.
if (strpos($file, 'uploads/') === 0) {
    $file = substr($file, 8);
}

$base_dir = realpath(__DIR__ . '/../uploads');
if (!$base_dir) {
    // If uploads directory doesn't exist yet
    http_response_code(404);
    die("<h2>Not Found</h2><p>Uploads directory not found.</p>");
}

$file_path = realpath($base_dir . '/' . $file);

// Ensure the resolved path actually exists and is inside the uploads directory
if (!$file_path || strpos($file_path, $base_dir) !== 0 || !file_exists($file_path)) {
    http_response_code(404);
    die("<h2>File Not Found</h2><p>The requested file does not exist or has been removed.</p>");
}

// 4. Advanced Authorization (Database Checks)
// E.g., Student must not access another student's submission.
$db_path = 'uploads/' . $file;

if ($is_student) {
    $student_id = $_SESSION['student_id'];
    
    // Check if it's an assignment submission
    if (strpos($file, 'assignment_submissions/') === 0) {
        $stmt = $pdo->prepare("SELECT student_id FROM assignment_submissions WHERE attachment_path = ?");
        $stmt->execute([$db_path]);
        $submission = $stmt->fetch();
        
        if ($submission && $submission['student_id'] != $student_id) {
            http_response_code(403);
            die("<h2>Forbidden</h2><p>You are not authorized to view another student's submission.</p>");
        }
    }
    
    // Additional checks could be added here for assignments or study materials
    // to ensure the student is enrolled in the relevant course.
    // For simplicity, we assume they can view public course materials.
}

if ($is_faculty) {
    $faculty_id = $_SESSION['faculty_id'];
    // Faculty can generally view submissions for their assignments.
    // We can add strict checks here if needed, but usually faculty have broad access to their students.
}

// 5. Determine MIME Type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

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

// If we still have an empty or generic octet-stream, and it's not previewable, stick to octet-stream
if (!$mime_type || $mime_type === 'application/octet-stream' || $mime_type === 'inode/x-empty') {
    $mime_type = 'application/octet-stream';
}

$filename = basename($file_path);

// 6. Output headers
header("Content-Type: $mime_type");
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

if ($action === 'download' || !$is_previewable) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

// 7. Output file
readfile($file_path);
exit;
