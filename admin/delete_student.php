<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && !empty($_POST['id'])) {
    requireValidCsrf();
    $id = (int)$_POST['id'];
    
    try {
        // Check if exists first
        $check_stmt = $pdo->prepare("SELECT id, first_name, last_name, enrollment_no FROM students WHERE id = ?");
        $check_stmt->execute([$id]);
        $student = $check_stmt->fetch();
        
        if (!$student) {
            header("Location: students.php?error=" . urlencode("Student not found for deletion."));
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($pdo, 'DELETE', null, "Deleted student: " . $student['first_name'] . " " . $student['last_name'] . " (" . $student['enrollment_no'] . ")");
        
        header("Location: students.php?msg=deleted");
        exit();
    } catch(PDOException $e) {
        error_log("Delete Error: " . $e->getMessage());
        header("Location: students.php?error=" . urlencode("Error deleting record."));
        exit();
    }
} else {
    // If someone tries to access this directly via GET, block them.
    header("Location: students.php?error=" . urlencode("Invalid request method. Deletions must be submitted securely."));
    exit();
}
?>
