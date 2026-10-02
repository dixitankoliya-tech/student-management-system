<?php
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
require_once '../includes/enrollment_helper.php';
requireAuth();
requireRole('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrf();
    // Sanitize and validate inputs
    // $enrollment_no is auto-generated later
    $first_name = trim(strip_tags($_POST['first_name'] ?? ''));
    $last_name = trim(strip_tags($_POST['last_name'] ?? ''));
    $gender = trim(strip_tags($_POST['gender'] ?? ''));
    $dob = trim(strip_tags($_POST['dob'] ?? ''));
    $email = trim(strip_tags($_POST['email'] ?? ''));
    $phone = trim(strip_tags($_POST['phone'] ?? ''));
    $address = trim(strip_tags($_POST['address'] ?? ''));
    $city = trim(strip_tags($_POST['city'] ?? ''));
    $course = trim(strip_tags($_POST['course'] ?? ''));
    $academic_year = trim(strip_tags($_POST['academic_year'] ?? ''));
    $specialization = trim(strip_tags($_POST['specialization'] ?? ''));
    $semester = (int)($_POST['semester'] ?? 0);
    $division = trim(strip_tags($_POST['division'] ?? ''));
    $aadhaar_no = trim(strip_tags($_POST['aadhaar_no'] ?? ''));

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
    
    if (empty($course) || !array_key_exists($course, getEnrollmentMappings()['courseCodes'])) {
        $errors[] = "Please select a valid Course.";
    }
    
    if (empty($academic_year) || !array_key_exists($academic_year, getEnrollmentMappings()['startingYears'])) {
        $errors[] = "Please select a valid Academic Year.";
    }
    
    if (!empty($dob)) {
        $dob_timestamp = strtotime($dob);
        if (!$dob_timestamp || $dob_timestamp > strtotime('-15 years') || $dob_timestamp < strtotime('-100 years')) {
            $errors[] = "Student must be between 15 and 100 years old.";
        }
    } else {
        $errors[] = "Date of Birth is required.";
    }
    
    // Check if email already exists
    if(empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->rowCount() > 0) {
            $errors[] = "Email already exists in the system.";
        }
    }
    
    if (empty($aadhaar_no)) {
        $errors[] = "Aadhaar Number is required for account generation.";
    } elseif (!preg_match('/^[0-9]{12}$/', $aadhaar_no)) {
        $errors[] = "Aadhaar Number must be exactly 12 digits.";
    }

    // Auto-generate Enrollment Number and Insert (with retry for race condition safety)
    if (empty($errors)) {
        try {
            $prefixData = getEnrollmentPrefixData($course, $academic_year);
            if (!$prefixData) {
                $errors[] = "Invalid Course or Academic Year mapping.";
            } else {
                $admission_year = (int)$prefixData[0];
            }
        } catch (Exception $e) {
            $errors[] = "System Error: " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        require_once '../config/academic_rules.php';
        $academicEngine = new AcademicEngine($pdo);
        if (!$academicEngine->canEnrollStudent($course, $semester, $division, $academic_year)) {
            $errors[] = "Class capacity reached. Maximum 100 students allowed in Division $division for $course (Sem $semester).";
        }
    }

    if (empty($errors)) {
        $maxRetries = 3;
        $inserted = false;
        
        for ($attempt = 0; $attempt < $maxRetries && !$inserted; $attempt++) {
            try {
                $pdo->beginTransaction();
                
                $enrollment_no = generateNextEnrollmentNumber($pdo, $course, $academic_year);
                
                $sql = "INSERT INTO students (enrollment_no, first_name, last_name, gender, dob, email, phone, address, city, course, specialization, semester, division, admission_year) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enrollment_no, $first_name, $last_name, $gender, $dob, $email, $phone, $address, $city, $course, $specialization, $semester, $division, $admission_year]);
                
                $student_id = $pdo->lastInsertId();
                
                // Create user account for the student
                $default_password = password_hash($aadhaar_no, PASSWORD_DEFAULT);
                $user_sql = "INSERT INTO users (username, email, password_hash, full_name, role, is_active, student_id, must_change_password) 
                             VALUES (?, ?, ?, ?, 'student', 1, ?, 1)";
                $user_stmt = $pdo->prepare($user_sql);
                // We use enrollment_no as the username for students
                $user_stmt->execute([$enrollment_no, $email, $default_password, "$first_name $last_name", $student_id]);
                
                $pdo->commit();
                $inserted = true;
                
                logActivity($pdo, 'CREATE', $student_id, "Added new student: $first_name $last_name ($enrollment_no)");
                
                // Add to student_academic_enrollments
                try {
                    $acadEnroll = $pdo->prepare("INSERT INTO student_academic_enrollments (student_id, course, academic_year, semester, division, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                    $acadEnroll->execute([$student_id, $course, $academic_year, $semester, $division]);
                } catch (Exception $e) {
                    error_log("Enrollment Init Notice: " . $e->getMessage());
                }

                // Initialize default academic performance record
                try {
                    require_once '../includes/academic_helper.php';
                    $acad_year = function_exists('getCurrentAcademicYear') ? getCurrentAcademicYear() : '2024-2025';
                    $perfInit = $pdo->prepare("
                        INSERT INTO academic_performance 
                        (student_id, semester, academic_year, attendance_percentage, assignment_score, practical_score, performance_category, alert_level)
                        VALUES (?, ?, ?, 85.00, 80.00, 85.00, 'On Track', 'GREEN')
                        ON DUPLICATE KEY UPDATE student_id=student_id
                    ");
                    $perfInit->execute([$student_id, $semester, $acad_year]);
                } catch (Exception $ex) {
                    error_log("Academic Performance Init Notice: " . $ex->getMessage());
                }

                header("Location: students.php?msg=added&enrollment=" . urlencode($enrollment_no));
                exit();
            } catch(PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // If duplicate enrollment_no, retry
                if ($e->getCode() == 23000 && $attempt < $maxRetries - 1) {
                    continue;
                }
                error_log("Database Error: " . $e->getMessage());
            $errors[] = "A database error occurred while saving the student. Please try again.";
        }
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
                <li class="breadcrumb-item active" aria-current="page">Add Student</li>
            </ol>
        </nav>
        <h1 class="h3 text-gray-800 fw-bold mb-0">Add New Student</h1>
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
                            <input type="text" name="first_name" class="form-control" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" placeholder="Enter first name">
                            <div class="invalid-feedback">First name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" placeholder="Enter last name">
                            <div class="invalid-feedback">Last name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">Please select a gender.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" class="form-control" required value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                            <div class="invalid-feedback">Date of birth is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Aadhaar Number <span class="text-danger">*</span></label>
                            <input type="text" name="aadhaar_no" class="form-control" required value="<?php echo isset($_POST['aadhaar_no']) ? htmlspecialchars($_POST['aadhaar_no']) : ''; ?>" placeholder="12-digit Aadhaar Number">
                            <div class="invalid-feedback">12-digit Aadhaar Number is required.</div>
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
                            <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="student@example.com">
                            <div class="invalid-feedback">A valid email address is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="+1234567890">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Full residential address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">City</label>
                            <input type="text" name="city" class="form-control" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" placeholder="City name">
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
                            <input type="text" class="form-control bg-light border-primary fw-bold text-primary" value="[ AUTO-GENERATED ]" readonly disabled>
                            <div class="form-text text-success"><i class="fas fa-check-circle"></i> Automatically generated</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Course <span class="text-danger">*</span></label>
                            <select name="course" class="form-select" required>
                                <option value="">Select Course</option>
                                <option value="BSc IT" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSc IT') ? 'selected' : ''; ?>>BSc IT</option>
                                <option value="BCA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BCA') ? 'selected' : ''; ?>>BCA</option>
                                <option value="MBA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'MBA') ? 'selected' : ''; ?>>MBA</option>
                            </select>
                            <div class="invalid-feedback">Please select a course.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Academic Year <span class="text-danger">*</span></label>
                            <select name="academic_year" class="form-select" required>
                                <option value="">Select Year</option>
                                <option value="FY" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] == 'FY') ? 'selected' : ''; ?>>FY (First Year)</option>
                                <option value="SY" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] == 'SY') ? 'selected' : ''; ?>>SY (Second Year)</option>
                                <option value="TY" <?php echo (isset($_POST['academic_year']) && $_POST['academic_year'] == 'TY') ? 'selected' : ''; ?>>TY (Third Year)</option>
                            </select>
                            <div class="invalid-feedback">Please select an academic year.</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Specialization</label>
                            <input type="text" name="specialization" class="form-control bg-white" value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>" placeholder="e.g. AI/ML">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Semester <span class="text-danger">*</span></label>
                            <select name="semester" class="form-select" required>
                                <?php for($i=1; $i<=6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_POST['semester']) && $_POST['semester'] == $i) ? 'selected' : ''; ?>>Sem <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback">Semester is required.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Division <span class="text-danger">*</span></label>
                            <select name="division" class="form-select" required>
                                <option value="A" <?php echo (isset($_POST['division']) && $_POST['division'] == 'A') ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo (isset($_POST['division']) && $_POST['division'] == 'B') ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo (isset($_POST['division']) && $_POST['division'] == 'C') ? 'selected' : ''; ?>>C</option>
                            </select>
                            <div class="invalid-feedback">Division is required.</div>
                        </div>
                        <div class="col-md-12" style="display: none;">
                            <!-- Admission Year is auto-calculated -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill"><i class="fas fa-save me-2"></i>Save Student</button>
                        <button type="reset" class="btn btn-light text-muted rounded-pill">Reset Form</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../includes/footer.php'; ?>
