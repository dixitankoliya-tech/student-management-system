<?php
// Load university branding from system_settings
$_sidebar_uni_name = 'University';
$_sidebar_uni_short = '';
try {
    $_sidebar_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('university_name','university_short_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $_sidebar_uni_name = $_sidebar_settings['university_name'] ?? 'University';
    $_sidebar_uni_short = $_sidebar_settings['university_short_name'] ?? '';
} catch (Exception $e) { /* fallback silently */ }
?>
<!-- Sidebar -->
<div class="border-end" id="sidebar-wrapper">
    <div class="sidebar-heading text-white py-4 px-3 text-center">
        <div class="mb-2">
            <i class="fas fa-university fs-5 text-primary"></i>
        </div>
        <div class="fs-5 fw-bold text-light" style="letter-spacing: -0.5px;"><?php echo htmlspecialchars(strtoupper($_sidebar_uni_name)); ?></div>
        <div class="text-primary fw-semibold mt-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px;">Administration</div>
    </div>
    <div class="list-group list-group-flush mt-2 pb-4">
        <?php if (hasRole('admin')): ?>
        
        <!-- OVERVIEW -->
        <div class="px-3 pt-2 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Overview</div>
        <a href="dashboard.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-th-large me-3 nav-icon"></i> Dashboard
        </a>
        <a href="notifications.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>">
            <i class="fas fa-bell me-3 nav-icon"></i> Notifications
        </a>

        <!-- ACADEMICS -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Academics</div>
        <a href="departments.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'departments.php') ? 'active' : ''; ?>">
            <i class="fas fa-building me-3 nav-icon"></i> Departments
        </a>
        <a href="courses.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'courses.php') ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap me-3 nav-icon"></i> Programs
        </a>
        <a href="academic_years.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'academic_years.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt me-3 nav-icon"></i> Academic Years
        </a>
        <a href="semesters.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'semesters.php') ? 'active' : ''; ?>">
            <i class="fas fa-clock me-3 nav-icon"></i> Semesters
        </a>
        <a href="divisions.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'divisions.php') ? 'active' : ''; ?>">
            <i class="fas fa-layer-group me-3 nav-icon"></i> Divisions
        </a>
        <a href="subjects.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'subjects.php') ? 'active' : ''; ?>">
            <i class="fas fa-book me-3 nav-icon"></i> Subjects
        </a>
        <a href="faculty_assignments.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'faculty_assignments.php') ? 'active' : ''; ?>">
            <i class="fas fa-tasks me-3 nav-icon"></i> Subject Offerings
        </a>
        <a href="academic_calendar.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'academic_calendar.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-day me-3 nav-icon"></i> Academic Calendar
        </a>

        <!-- PEOPLE -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">People</div>
        <a href="leadership.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'leadership.php') ? 'active' : ''; ?>">
            <i class="fas fa-sitemap me-3 nav-icon"></i> Leadership
        </a>
        <a href="students.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['students.php', 'view_student.php', 'edit_student.php', 'add_student.php'])) ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate me-3 nav-icon"></i> Students
        </a>
        <a href="promote_students.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'promote_students.php') ? 'active' : ''; ?>">
            <i class="fas fa-level-up-alt me-3 nav-icon"></i> Promote Students
        </a>
        <a href="faculty.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'faculty.php') ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher me-3 nav-icon"></i> Faculty
        </a>

        <!-- ACADEMIC OPERATIONS -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Academic Operations</div>
        <a href="attendance.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check me-3 nav-icon"></i> Attendance
        </a>
        <a href="attendance_reports.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance_reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line me-3 nav-icon"></i> Attendance Reports
        </a>
        <a href="examinations.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'examinations.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt me-3 nav-icon"></i> Examinations
        </a>
        <a href="report_cards.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['report_cards.php', 'report_card.php', 'manage_marks.php'])) ? 'active' : ''; ?>">
            <i class="fas fa-award me-3 nav-icon"></i> Results
        </a>
        <a href="academic_reports.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'academic_reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar me-3 nav-icon"></i> Advanced Reports
        </a>
        <a href="assignments.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'assignments.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-signature me-3 nav-icon"></i> Assignments
        </a>
        <a href="timetable.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'timetable.php') ? 'active' : ''; ?>">
            <i class="fas fa-table me-3 nav-icon"></i> Timetable
        </a>

        <!-- FINANCE -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Finance</div>
        <a href="fee_structures.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'fee_structures.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar me-3 nav-icon"></i> Fee Structure
        </a>
        <a href="student_fees.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'student_fees.php') ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding-usd me-3 nav-icon"></i> Student Fees
        </a>
        <a href="fee_payments.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'fee_payments.php') ? 'active' : ''; ?>">
            <i class="fas fa-money-check-alt me-3 nav-icon"></i> Payments
        </a>
        <a href="fee_reports.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'fee_reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-receipt me-3 nav-icon"></i> Receipts & Reports
        </a>


        <!-- COMMUNICATION -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Communication</div>
        <a href="announcements.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn me-3 nav-icon"></i> Announcements
        </a>

        <!-- SYSTEM -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">System</div>
        <a href="users.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">
            <i class="fas fa-users-cog me-3 nav-icon"></i> Users & Roles
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog me-3 nav-icon"></i> Settings
        </a>
        <a href="audit_logs.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'audit_logs.php') ? 'active' : ''; ?>">
            <i class="fas fa-history me-3 nav-icon"></i> Audit Logs
        </a>
        
        <?php endif; ?>
    </div>
</div>
<!-- /#sidebar-wrapper -->
