<?php
/**
 * SMS 2.0: Force Password Change Page
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';

// Check if user is logged in (but don't enforce requireAuth which would loop)
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}
if (!hasRole('student')) {
    logoutUser();
    header('Location: login.php?error=' . urlencode('Access Denied.'));
    exit();
}

$error = '';
$success = '';

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
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $_SESSION['must_change_password'] = 0;
            logActivity($pdo, 'PASSWORD_CHANGE', $_SESSION['student_id'], 'Student changed their initial password.');
            
            $success = "Password updated successfully. You will be redirected to the dashboard.";
            header("refresh:2;url=dashboard.php");
        } catch (PDOException $e) {
            $error = "An error occurred while updating the password.";
        }
    }
}

// We don't include the full layout so they are focused on changing password
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fc; }
        .card { border-radius: 1rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h1 class="h4 text-gray-900 mb-2"><i class="fas fa-lock text-primary me-2"></i>Change Password</h1>
                            <p class="text-muted small">For security reasons, you must change your default password before accessing your account.</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <?php csrfField(); ?>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="8" placeholder="Min 8 characters">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-bold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-save me-2"></i>Update Password</button>
                            </form>
                        <?php endif; ?>
                        <div class="text-center mt-4">
                            <a href="logout.php" class="text-danger small text-decoration-none"><i class="fas fa-sign-out-alt me-1"></i>Cancel and Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
