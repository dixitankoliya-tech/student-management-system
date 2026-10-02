<?php
/**
 * SMS 2.0: Student Profile
 */
require_once '../config/database.php';
require_once '../includes/student_auth.php';
requireRole('student');

$student_id = $_SESSION['student_id'];
$success = '';
$error = '';

// Handle Profile Change Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_change'])) {
    requireValidCsrf();
    
    $change_type = $_POST['change_type'] ?? '';
    $details = $_POST['details'] ?? '';
    
    if (empty($change_type) || empty($details)) {
        $error = "Please provide all details for the change request.";
    } else {
        // We'll insert this into the notifications table for admin (user_id = admin_id)
        // For simplicity, we'll assume admin user_id = 1, or just store it as a system notification
        // Find an admin user
        $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $adminStmt->fetch();
        
        if ($admin) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $admin['id'], 
                "Profile Change Request", 
                "Student ID $student_id requested a change ($change_type): $details", 
                "alert"
            ]);
            $success = "Your request has been submitted to the administration for review.";
        }
    }
}

// Fetch student
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: login.php");
    exit();
}

include '../includes/student_header.php';
include '../includes/student_sidebar.php';
include '../includes/student_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-4 pb-2 border-bottom">
    <div>
        <h2 class="h3 mb-1 text-dark fw-bold">My Profile</h2>
        <p class="text-muted mb-0">Manage your personal and academic information</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success rounded-3"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger rounded-3"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Quick Profile -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm rounded-4 text-center">
            <div class="card-body p-5">
                <div class="mb-4">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['first_name'].' '.$student['last_name']); ?>&background=0D6EFD&color=fff&size=120" class="rounded-circle shadow-sm" alt="Profile Picture">
                </div>
                <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($student['enrollment_no']); ?></p>
                <div class="d-flex justify-content-center gap-2 mb-4">
                    <span class="badge bg-primary-subtle text-primary fs-6 px-3 py-2 rounded-pill"><?php echo htmlspecialchars($student['course']); ?></span>
                    <span class="badge bg-secondary-subtle text-secondary fs-6 px-3 py-2 rounded-pill">Sem <?php echo htmlspecialchars($student['semester']); ?></span>
                </div>
                
                <hr class="mb-4">
                
                <button type="button" class="btn btn-outline-primary rounded-pill w-100" data-bs-toggle="modal" data-bs-target="#requestChangeModal">
                    <i class="fas fa-edit me-2"></i> Request Profile Update
                </button>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Detailed Info -->
    <div class="col-lg-8 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 p-md-5">
                
                <h5 class="fw-bold text-dark border-bottom pb-3 mb-4"><i class="fas fa-user text-primary me-2"></i> Personal Details</h5>
                <div class="row mb-5">
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">First Name</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['first_name']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Last Name</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['last_name']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Email Address</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['email']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Phone Number</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['phone']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Date of Birth</div>
                        <div class="fw-bold text-dark fs-5"><?php echo date('F d, Y', strtotime($student['dob'])); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Gender</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['gender']); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Address</div>
                        <div class="fw-bold text-dark fs-5"><?php echo nl2br(htmlspecialchars($student['address'])); ?></div>
                    </div>
                </div>
                
                <h5 class="fw-bold text-dark border-bottom pb-3 mb-4"><i class="fas fa-graduation-cap text-primary me-2"></i> Academic Details</h5>
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Course / Program</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['course']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Admission Year</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['admission_year'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Current Semester</div>
                        <div class="fw-bold text-dark fs-5">Semester <?php echo htmlspecialchars($student['semester']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-xs fw-bold text-muted text-uppercase mb-1">Division / Batch</div>
                        <div class="fw-bold text-dark fs-5">Division <?php echo htmlspecialchars($student['division']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Change Modal -->
<div class="modal fade" id="requestChangeModal" tabindex="-1" aria-labelledby="requestChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="requestChangeModalLabel">Request Profile Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <?php csrfField(); ?>
                <div class="modal-body p-4">
                    <div class="alert alert-info rounded-3 small">
                        <i class="fas fa-info-circle me-2"></i> Student details cannot be changed directly to maintain academic integrity. Submit a request to the admin below.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">What do you want to change?</label>
                        <select class="form-select rounded-3" name="change_type" required>
                            <option value="">Select an option</option>
                            <option value="Name Correction">Name Correction</option>
                            <option value="Phone Number Update">Phone Number Update</option>
                            <option value="Address Update">Address Update</option>
                            <option value="Date of Birth Correction">Date of Birth Correction</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted">Please provide the new correct details</label>
                        <textarea class="form-control rounded-3" name="details" rows="4" required placeholder="E.g. My new phone number is 9876543210"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="request_change" class="btn btn-primary rounded-pill px-4">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
