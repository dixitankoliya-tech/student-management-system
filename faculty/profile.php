<?php
require_once '../config/database.php';
require_once '../includes/faculty_auth.php';

requireAuth();

$faculty_id = $_SESSION['faculty_db_id'];
$user = getCurrentUser();

$stmt = $pdo->prepare("SELECT * FROM faculty WHERE id = ?");
$stmt->execute([$faculty_id]);
$profile = $stmt->fetch();

?>
<?php
include '../includes/faculty_header.php';
include '../includes/faculty_sidebar.php';
?>
<?php include '../includes/faculty_navbar.php'; ?>
<header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
    <div>
        <h2 class="fw-bold text-dark mb-0">My Profile</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </nav>
    </div>
</header>

<div class="row g-4">
    <!-- Left Column: Avatar & Basic Info -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 text-center h-100">
            <div class="card-body p-5">
                <div class="avatar-circle bg-primary text-white mx-auto mb-4" style="width: 120px; height: 120px; font-size: 3rem; line-height: 120px;">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
                <p class="text-muted mb-3"><i class="fas fa-chalkboard-teacher me-2"></i>Faculty Member</p>
                
                <hr class="text-muted opacity-25 my-4">
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Status:</span>
                    <?php if ($profile['status'] == 'Active'): ?>
                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i> Active</span>
                    <?php else: ?>
                        <span class="badge bg-danger rounded-pill px-3 py-2"><i class="fas fa-ban me-1"></i> Inactive</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Account ID:</span>
                    <span class="fw-semibold text-dark">#<?= $user['id'] ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Details -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold text-primary mb-0"><i class="fas fa-id-card me-2"></i>Official Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted fw-bold small text-uppercase">Faculty ID / Emp No</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($profile['faculty_id']) ?>" readonly>
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted fw-bold small text-uppercase">Full Name</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['full_name']) ?>" readonly>
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted fw-bold small text-uppercase">Primary Email</label>
                        <div class="input-group">
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($profile['email']) ?>" readonly>
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted fw-bold small text-uppercase">Contact Number</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($profile['phone'] ?: 'Not Provided') ?>" readonly>
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                        </div>
                    </div>
                </div>

                <div class="mt-5 p-4 bg-primary bg-opacity-10 rounded-4 d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-info-circle text-primary fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-primary mb-1">Need to update these details?</h6>
                        <p class="text-muted small mb-0">Profile updates such as name, email, or contact changes must be requested through the central administration office for security and payroll verification purposes.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

