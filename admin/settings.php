<?php
/**
 * SMS 2.0: Academic Intelligence Configuration & Settings
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';
requireAuth();
requireRole('admin');
require_once '../includes/academic_helper.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    requireValidCsrf();
    $w_acad = (float)($_POST['weight_academic'] ?? 0.60);
    $w_att = (float)($_POST['weight_attendance'] ?? 0.15);
    $w_assign = (float)($_POST['weight_assignment'] ?? 0.10);
    $w_prac = (float)($_POST['weight_practical'] ?? 0.15);

    $sumWeights = round($w_acad + $w_att + $w_assign + $w_prac, 2);
    if (abs($sumWeights - 1.0) > 0.01) {
        $error = "The sum of all scoring weights must equal exactly 1.00 (100%). Current sum is: $sumWeights";
    } else {
        try {
            $updateStmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            $newSettings = [
                'weight_academic' => (string)$w_acad,
                'weight_attendance' => (string)$w_att,
                'weight_assignment' => (string)$w_assign,
                'weight_practical' => (string)$w_prac,
                'alert_red_attendance' => (string)(float)$_POST['alert_red_attendance'],
                'alert_red_percentage' => (string)(float)$_POST['alert_red_percentage'],
                'alert_yellow_attendance' => (string)(float)$_POST['alert_yellow_attendance'],
                'alert_yellow_percentage' => (string)(float)$_POST['alert_yellow_percentage']
            ];

            foreach ($newSettings as $k => $v) {
                $updateStmt->execute([$k, $v]);
            }

            // Recalculate performance scores for all existing students in Sem 5 and 6
            $students = $pdo->query("SELECT DISTINCT student_id, semester FROM academic_performance")->fetchAll();
            foreach ($students as $st) {
                recalculateStudentAcademicProfile($pdo, $st['student_id'], $st['semester']);
            }
            recalculateClassRanksAndAchievements($pdo, 5);
            recalculateClassRanksAndAchievements($pdo, 6);

            logActivity($pdo, 'UPDATE', null, "Modified academic scoring weights and alert thresholds in system settings");

            $message = "Settings saved successfully! All student intelligence scores and ranks were automatically re-computed.";
        } catch (PDOException $e) {
            error_log("Save Settings Error: " . $e->getMessage());
            $error = "Failed to persist settings to database.";
        }
    }
}

$settings = getAcademicSettings($pdo);

include '../includes/admin_header.php';
include '../includes/admin_sidebar.php';
include '../includes/admin_navbar.php';
?>

<div class="container-fluid py-4 px-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-4 mb-4">
    <div>
        <h1 class="h3 text-gray-800 fw-bold mb-1"><i class="fas fa-sliders-h text-primary me-2"></i>Academic Intelligence Settings</h1>
        <p class="text-muted small mb-0">Tune multi-factor predictive weights, alert sensitivity thresholds, and grading parameters.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <?php csrfField(); ?>
    <div class="row g-4 mb-4">
        <!-- Factor Weight Tuning -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-balance-scale me-2"></i>Multi-Factor Performance Intelligence Weights</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">Weights must sum up to exactly <strong>1.00 (100%)</strong>. Adjusting these values recalibrates every student's computed score.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex justify-content-between">
                            <span>Academic Examination Marks Weight:</span>
                            <span class="text-primary fw-bold" id="lbl_acad"><?php echo ($settings['weight_academic'] * 100); ?>% (<?php echo $settings['weight_academic']; ?>)</span>
                        </label>
                        <input type="number" step="0.05" min="0" max="1" class="form-control" name="weight_academic" 
                               value="<?php echo htmlspecialchars($settings['weight_academic']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex justify-content-between">
                            <span>Institutional Attendance Weight:</span>
                            <span class="text-success fw-bold" id="lbl_att"><?php echo ($settings['weight_attendance'] * 100); ?>% (<?php echo $settings['weight_attendance']; ?>)</span>
                        </label>
                        <input type="number" step="0.05" min="0" max="1" class="form-control" name="weight_attendance" 
                               value="<?php echo htmlspecialchars($settings['weight_attendance']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex justify-content-between">
                            <span>Assignment & Case Studies Weight:</span>
                            <span class="text-info fw-bold" id="lbl_assign"><?php echo ($settings['weight_assignment'] * 100); ?>% (<?php echo $settings['weight_assignment']; ?>)</span>
                        </label>
                        <input type="number" step="0.05" min="0" max="1" class="form-control" name="weight_assignment" 
                               value="<?php echo htmlspecialchars($settings['weight_assignment']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold d-flex justify-content-between">
                            <span>Practical Lab Examination Weight:</span>
                            <span class="text-warning-emphasis fw-bold" id="lbl_prac"><?php echo ($settings['weight_practical'] * 100); ?>% (<?php echo $settings['weight_practical']; ?>)</span>
                        </label>
                        <input type="number" step="0.05" min="0" max="1" class="form-control" name="weight_practical" 
                               value="<?php echo htmlspecialchars($settings['weight_practical']); ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Thresholds -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-exclamation-triangle me-2"></i>Early Academic Alert Trigger Thresholds</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">Define statistical boundaries that automatically trigger early intervention alerts.</p>

                    <h6 class="fw-bold text-danger border-bottom pb-1 mb-3"><i class="fas fa-radiation me-1"></i> RED Alert Boundaries (At-Risk)</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">Attendance Below (%):</label>
                            <input type="number" step="1" min="0" max="100" class="form-control" name="alert_red_attendance" 
                                   value="<?php echo htmlspecialchars($settings['alert_red_attendance']); ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">Marks Below (%):</label>
                            <input type="number" step="1" min="0" max="100" class="form-control" name="alert_red_percentage" 
                                   value="<?php echo htmlspecialchars($settings['alert_red_percentage']); ?>" required>
                        </div>
                    </div>

                    <h6 class="fw-bold text-warning-emphasis border-bottom pb-1 mb-3"><i class="fas fa-bell me-1"></i> YELLOW Alert Boundaries (Watchlist)</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">Attendance Below (%):</label>
                            <input type="number" step="1" min="0" max="100" class="form-control" name="alert_yellow_attendance" 
                                   value="<?php echo htmlspecialchars($settings['alert_yellow_attendance']); ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">Marks Below (%):</label>
                            <input type="number" step="1" min="0" max="100" class="form-control" name="alert_yellow_percentage" 
                                   value="<?php echo htmlspecialchars($settings['alert_yellow_percentage']); ?>" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mb-4">
        <button type="submit" name="save_settings" class="btn btn-primary px-4 shadow-sm rounded-pill">
            <i class="fas fa-save me-1"></i> Save Configuration & Re-evaluate Model
        </button>
    </div>
</form>

</div> <!-- Close container-fluid -->

<?php include '../includes/footer.php'; ?>
