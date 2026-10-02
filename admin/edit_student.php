<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$id = (int)$_GET['id'];
$errors = [];

// Fetch existing data
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();

    if (!$student) {
        header("Location: students.php?error=" . urlencode("Student not found."));
        exit();
    }
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    header("Location: students.php?error=" . urlencode("Database Error."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrf();
    // Sanitize and validate inputs
    $first_name = trim(strip_tags($_POST['first_name'] ?? ''));
    $last_name = trim(strip_tags($_POST['last_name'] ?? ''));
    $gender = trim(strip_tags($_POST['gender'] ?? ''));
    $dob = trim(strip_tags($_POST['dob'] ?? ''));
    $email = trim(strip_tags($_POST['email'] ?? ''));
    $phone = trim(strip_tags($_POST['phone'] ?? ''));
    $address = trim(strip_tags($_POST['address'] ?? ''));
    $city = trim(strip_tags($_POST['city'] ?? ''));
    $semester = (int)($_POST['semester'] ?? 0);
    $division = trim(strip_tags($_POST['division'] ?? ''));

    // Basic validation
    if (empty($first_name)) $errors[] = "First Name is required.";
    elseif (strlen($first_name) > 50) $errors[] = "First Name cannot exceed 50 characters.";
    
    if (empty($last_name)) $errors[] = "Last Name is required.";
    elseif (strlen($last_name) > 50) $errors[] = "Last Name cannot exceed 50 characters.";
    
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = "Please select a valid gender (Male, Female, Other).";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid Email is required.";
    elseif (strlen($email) > 100) $errors[] = "Email cannot exceed 100 characters.";
    
    if (!empty($phone) && !preg_match('/^\+?[0-9]{10,15}$/', $phone)) $errors[] = "Valid Phone number (10-15 digits) is required.";
    
    if (!empty($city) && strlen($city) > 50) $errors[] = "City cannot exceed 50 characters.";
    
    if ($semester < 1 || $semester > 6) {
        $errors[] = "Semester must be between 1 and 6.";
    }
    
    if (!in_array($division, ['A', 'B', 'C'], true)) {
        $errors[] = "Division must be A, B, or C.";
    }
    
    // admission_year is no longer editable
    
    if (!empty($dob)) {
        $dob_timestamp = strtotime($dob);
        if (!$dob_timestamp || $dob_timestamp > strtotime('-15 years') || $dob_timestamp < strtotime('-100 years')) {
            $errors[] = "Student must be between 15 and 100 years old.";
        }
    } else {
        $errors[] = "Date of Birth is required.";
    }
    
    // Check if email already exists for OTHER students
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if($stmt->rowCount() > 0) {
            $errors[] = "Email already exists for another student.";
        }
    }

    // Check class capacity if division or semester changes
    if (empty($errors)) {
        if ($semester != $student['semester'] || $division != $student['division']) {
            require_once '../config/academic_rules.php';
            $academicEngine = new AcademicEngine($pdo);
            // We need to fetch the academic year from enrollments, or assume active
            $stmtAcad = $pdo->prepare("SELECT academic_year FROM student_academic_enrollments WHERE student_id = ? AND status = 'Active' LIMIT 1");
            $stmtAcad->execute([$id]);
            $acadRec = $stmtAcad->fetch();
            $acad_year = $acadRec ? $acadRec['academic_year'] : (function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : '2024-25');
            
            if (!$academicEngine->canEnrollStudent($student['course'], $semester, $division, $acad_year)) {
                $errors[] = "Class capacity reached. Maximum 100 students allowed in Division $division for {$student['course']} (Sem $semester).";
            }
        }
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE students SET 
                    first_name = ?, last_name = ?, gender = ?, dob = ?, 
                    email = ?, phone = ?, address = ?, city = ?, semester = ?, division = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$first_name, $last_name, $gender, $dob, $email, $phone, $address, $city, $semester, $division, $id]);
            
            // Sync with student_academic_enrollments
            $syncStmt = $pdo->prepare("UPDATE student_academic_enrollments SET semester = ?, division = ? WHERE student_id = ? AND status = 'Active'");
            $syncStmt->execute([$semester, $division, $id]);

            logActivity($pdo, 'UPDATE', $id, "Updated student profile: $first_name $last_name");
            
            header("Location: students.php?msg=updated");
            exit();
        } catch(PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $errors[] = "A database error occurred while updating the student. Please try again.";
        }
    }
}

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="students.php" class="text-decoration-none">Students</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Student</li>
            </ol>
        </nav>
        <h1 class="h3 text-gray-800 fw-bold mb-0">Edit Student</h1>
    </div>
    <a href="students.php" class="btn btn-outline-secondary shadow-sm rounded-pill px-3"><i class="fas fa-arrow-left me-1"></i> Directory</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm border-0 border-start border-danger border-4">
        <h6 class="fw-bold"><i class="fas fa-exclamation-circle me-2"></i>Please fix the following errors:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" class="needs-validation" novalidate>
    <?php csrfField(); ?>
    <div class="row">
        <!-- Main Form Column -->
        <div class="col-lg-8 mb-4">
            <!-- Personal Information -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user text-muted me-2"></i>Personal Information</h6>
                </div>
                <div class="card-body bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : htmlspecialchars($student['first_name']); ?>">
                            <div class="invalid-feedback">First name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : htmlspecialchars($student['last_name']); ?>">
                            <div class="invalid-feedback">Last name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Gender <span class="text-danger">*</span></label>
                            <?php $gender_val = isset($_POST['gender']) ? $_POST['gender'] : $student['gender']; ?>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($gender_val == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($gender_val == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($gender_val == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">Please select a gender.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" class="form-control" required value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : htmlspecialchars($student['dob']); ?>">
                            <div class="invalid-feedback">Date of birth is required.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-address-book text-muted me-2"></i>Contact Information</h6>
                </div>
                <div class="card-body bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($student['email']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($student['phone']); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : htmlspecialchars($student['address']); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">City</label>
                            <input type="text" name="city" class="form-control" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : htmlspecialchars($student['city']); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Form Column -->
        <div class="col-lg-4 mb-4">
            <!-- Academic Information -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-graduation-cap text-muted me-2"></i>Academic Profile</h6>
                </div>
                <div class="card-body bg-light">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Enrollment No</label>
                            <input type="text" class="form-control bg-light border-secondary fw-bold text-secondary" value="<?php echo htmlspecialchars($student['enrollment_no']); ?>" readonly disabled>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Course</label>
                            <input type="text" class="form-control bg-light text-muted" value="<?php echo htmlspecialchars($student['course'] ?? ''); ?>" readonly disabled>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Specialization</label>
                            <input type="text" name="specialization" class="form-control bg-white" value="<?php echo htmlspecialchars($student['specialization'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Semester <span class="text-danger">*</span></label>
                            <?php $sem_val = isset($_POST['semester']) ? $_POST['semester'] : $student['semester']; ?>
                            <select name="semester" class="form-select" required>
                                <?php for($i=1; $i<=6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($sem_val == $i) ? 'selected' : ''; ?>>Sem <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback">Semester is required.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Division <span class="text-danger">*</span></label>
                            <?php $div_val = isset($_POST['division']) ? $_POST['division'] : $student['division']; ?>
                            <select name="division" class="form-select" required>
                                <option value="A" <?php echo ($div_val == 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo ($div_val == 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo ($div_val == 'C') ? 'selected' : ''; ?>>C</option>
                            </select>
                            <div class="invalid-feedback">Division is required.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Admission Year</label>
                            <input type="number" class="form-control bg-light text-muted" value="<?php echo htmlspecialchars($student['admission_year']); ?>" readonly disabled>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill"><i class="fas fa-save me-2"></i>Update Student</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../includes/footer.php'; ?>
