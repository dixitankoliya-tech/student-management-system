<?php
/**
 * SMS 2.0: Admin Academic Year Management
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (isset($_POST['add_year'])) {
        $year_name = trim($_POST['year_name'] ?? '');
        $starting_year = (int)($_POST['starting_year'] ?? 0);
        
        if (empty($year_name) || $starting_year < 2000) {
            $error = 'Valid year name and starting year are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO academic_years (year_name, starting_year) VALUES (?, ?)");
                $stmt->execute([$year_name, $starting_year]);
                logActivity($pdo, 'ADD_ACADEMIC_YEAR', null, "Added academic year: $year_name ($starting_year)");
                $success = 'Academic year added successfully.';
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = 'Academic year name already exists.';
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $year_id = (int)$_POST['year_id'];
        $new_status = $_POST['new_status'] === 'Active' ? 'Active' : 'Inactive';
        try {
            $stmt = $pdo->prepare("UPDATE academic_years SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $year_id]);
            logActivity($pdo, 'UPDATE_ACADEMIC_YEAR', null, "Toggled year $year_id status to $new_status");
            $success = 'Status updated.';
        } catch (PDOException $e) {
            $error = 'Error updating status.';
        }
    } elseif (isset($_POST['update_starting_year'])) {
        $year_id = (int)$_POST['year_id'];
        $starting_year = (int)$_POST['starting_year'];
        try {
            $stmt = $pdo->prepare("UPDATE academic_years SET starting_year = ? WHERE id = ?");
            $stmt->execute([$starting_year, $year_id]);
            logActivity($pdo, 'UPDATE_ACADEMIC_YEAR', null, "Updated year $year_id starting year to $starting_year");
            $success = 'Starting year updated.';
        } catch (PDOException $e) {
            $error = 'Error updating year.';
        }
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM academic_years ORDER BY starting_year DESC");
    $years = $stmt->fetchAll();
} catch (PDOException $e) {
    $years = [];
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
        <h1 class="h3 text-gray-800 fw-bold mb-1">Academic Years</h1>
        <p class="text-muted small mb-0">Manage Academic Years in the system.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addYearModal">
                <i class="fas fa-plus me-2"></i> Add Academic Year
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
                            <th>Year Name</th>
                            <th>Starting Year (For Enrollment)</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($years as $year): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($year['year_name']) ?></strong></td>
                            <td>
                                <form method="POST" class="d-flex align-items-center" style="max-width: 150px;">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="year_id" value="<?= $year['id'] ?>">
                                    <input type="number" name="starting_year" value="<?= $year['starting_year'] ?>" class="form-control form-control-sm me-2" required>
                                    <button type="submit" name="update_starting_year" class="btn btn-sm btn-outline-primary"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                            <td>
                                <?php if ($year['status'] === 'Active'): ?>
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" class="d-inline">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="year_id" value="<?= $year['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $year['status'] === 'Active' ? 'Inactive' : 'Active' ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm btn-<?= $year['status'] === 'Active' ? 'warning' : 'success' ?>" title="Toggle Status">
                                        <i class="fas fa-<?= $year['status'] === 'Active' ? 'ban' : 'check' ?>"></i>
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

    <!-- Add Year Modal -->
    <div class="modal fade" id="addYearModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content border-0 shadow">
                <?php csrfField(); ?>
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">Add Academic Year</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Year Name (e.g. FY, SY)</label>
                        <input type="text" name="year_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-semibold">Starting Year</label>
                        <input type="number" name="starting_year" class="form-control" required value="<?= date('Y') ?>">
                        <small class="text-muted">Used for automatic enrollment number generation.</small>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_year" class="btn btn-primary px-4">Save</button>
                </div>
            </form>
        </div>
    <?php include '../includes/footer.php'; ?>

