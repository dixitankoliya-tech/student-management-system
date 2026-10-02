<?php
/**
 * SMS 2.0: Admin Divisions Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_division'])) {
        $division_name = trim($_POST['division_name'] ?? '');
        
        if (empty($division_name)) {
            $error = 'Division name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO divisions (division_name) VALUES (?)");
                $stmt->execute([$division_name]);
                logActivity($pdo, 'ADD_DIVISION', null, "Added division: $division_name");
                $success = 'Division added successfully.';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = 'Division already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $div_id = (int)$_POST['division_id'];
        $new_status = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
        try {
            $stmt = $pdo->prepare("UPDATE divisions SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $div_id]);
            logActivity($pdo, 'UPDATE_DIVISION', null, "Toggled division $div_id status to $new_status");
            $success = 'Status updated.';
        } catch (PDOException $e) {
            $error = 'Error updating status.';
        }
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM divisions ORDER BY division_name ASC");
    $divisions = $stmt->fetchAll();
} catch (PDOException $e) {
    $divisions = [];
}
?>
<?php
include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
?>
<div id="page-content-wrapper" class="w-100 bg-light">
<?php include '../includes/admin_navbar.php'; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div class="mb-3 mb-md-0">
        <h1 class="h3 text-gray-800 fw-bold mb-1">Divisions</h1>
        <p class="text-muted small mb-0">Manage Divisions in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Add Division
            </button>
    </div>
</div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Division Name</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisions as $div): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($div['division_name']) ?></strong></td>
                            <td>
                                <?php if ($div['status'] === 'Active'): ?>
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="division_id" value="<?= $div['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $div['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm btn-<?= $div['status'] === 'Active' ? 'warning' : 'success' ?>">
                                        <i class="fas fa-<?= $div['status'] === 'Active' ? 'ban' : 'check' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Division Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Add Division</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Division Name (e.g. A, B)</label>
                        <input type="text" name="division_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_division" class="btn btn-primary px-4">Save</button>
                </div>
            </form>
        </div>
    <?php include '../includes/footer.php'; ?>

