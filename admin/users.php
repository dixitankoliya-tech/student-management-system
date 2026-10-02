<?php
/**
 * SMS 2.0: User Management (Admin)
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

// Handle Actions (Activate/Deactivate, Reset Password, Add Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    if (isset($_POST['toggle_status'])) {
        $user_id = (int)$_POST['user_id'];
        $status = (int)$_POST['status'];
        // Don't allow deactivating oneself
        if ($user_id !== $_SESSION['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            logActivity($pdo, 'UPDATE', null, "Changed status of user ID $user_id to $status");
            $msg = "User status updated.";
        } else {
            $error = "You cannot deactivate your own account.";
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = (int)$_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        logActivity($pdo, 'UPDATE', null, "Reset password for user ID $user_id");
        $msg = "Password reset successfully.";
    } elseif (isset($_POST['add_admin'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
            $stmt->execute([$username, $email, $password, $full_name]);
            logActivity($pdo, 'CREATE', null, "Added new admin user: $username");
            $msg = "Admin user added successfully.";
        } catch(PDOException $e) {
            $error = "Error adding admin: " . ($e->getCode() == 23000 ? "Username or email already exists." : $e->getMessage());
        }
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC");
$users = $stmt->fetchAll();

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4">
    <h2 class="h3 mb-0 text-gray-800">User Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
        <i class="fas fa-user-plus me-2"></i>Add Admin
    </button>
</div>

<?php if (isset($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">User</th>
                        <th>Role</th>
                        <th>Student Link</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        <tr>
                            <td class="px-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 50%;">
                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?> | @<?php echo htmlspecialchars($u['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $u['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($u['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if($u['student_id']): ?>
                                    <a href="view_student.php?id=<?php echo $u['student_id']; ?>" class="badge bg-info text-decoration-none">Linked ID: <?php echo $u['student_id']; ?></a>
                                <?php else: ?>
                                    <span class="text-muted small">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['is_active']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never'; ?>
                            </td>
                            <td class="text-end px-4">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal<?php echo $u['id']; ?>" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if($u['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $u['is_active'] ? '0' : '1'; ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'danger' : 'success'; ?>" title="<?php echo $u['is_active'] ? 'Disable User' : 'Enable User'; ?>">
                                            <i class="fas fa-<?php echo $u['is_active'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Reset Password Modal -->
                        <div class="modal fade" id="resetModal<?php echo $u['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <?php csrfField(); ?>
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reset Password for <?php echo htmlspecialchars($u['full_name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">New Password</label>
                                                <input type="password" name="new_password" class="form-control" required minlength="6">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Add Admin User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
