<?php
/**
 * SMS 2.0: Force Password Change Page
 */
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

// Check if user is logged in (but don't enforce requireAuth which would loop)
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}
if (!hasRole('faculty')) {
    logoutUser();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit();
}

$error = '';
$success = '';
$faculty_id = $_SESSION['faculty_db_id'];
$faculty_str_id = $_SESSION['faculty_string_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Hash and update
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            $pdo->beginTransaction();
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $_SESSION['must_change_password'] = 0;
            logActivity($pdo, 'PASSWORD_CHANGE', "Faculty $faculty_str_id", 'Faculty changed their initial password.');
            
            $pdo->commit();
            $success = "Password updated successfully. You will be redirected to the dashboard.";
            header("refresh:2;url=dashboard.php");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "An error occurred while updating the password.";
        }
    }
}
?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>

<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">Change Password</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active">Change Password</li>
            </ol>
        </nav>
    </div>
</header>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold text-dark mb-2"><i class="fas fa-lock text-primary me-2"></i>Update Password</h4>
                            <p class="text-muted small">For security reasons, ensure your password is strong and updated regularly.</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger rounded-3"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success rounded-3"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <?php csrfField(); ?>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">New Password</label>
                                    <input type="password" name="new_password" class="form-control form-control-lg bg-light" required minlength="8" placeholder="Min 8 characters">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-bold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg bg-light" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 fw-bold"><i class="fas fa-save me-2"></i>Update Password</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
<?php include '../includes/footer.php'; ?>
