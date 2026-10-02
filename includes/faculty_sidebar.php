<?php
$current_page = basename($_SERVER['PHP_SELF']);

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
            <i class="fas fa-chalkboard-teacher fs-5 text-primary"></i>
        </div>
        <div class="fs-5 fw-bold text-light" style="letter-spacing: -0.5px;"><?php echo htmlspecialchars(strtoupper($_sidebar_uni_name)); ?></div>
        <div class="text-primary fw-semibold mt-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px;">Faculty Portal</div>
    </div>
    
    <div class="list-group list-group-flush mt-2 pb-4">
        
        <!-- OVERVIEW -->
        <div class="px-3 pt-2 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Overview</div>
        <a href="dashboard.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home me-3 nav-icon"></i> Dashboard
        </a>

        <!-- TEACHING & ACADEMICS -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Teaching & Academics</div>
        <a href="my_subjects.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'my_subjects.php') ? 'active' : ''; ?>">
            <i class="fas fa-book-open me-3 nav-icon"></i> My Subjects
        </a>
        <a href="my_students.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'my_students.php' || $current_page == 'student_detail.php') ? 'active' : ''; ?>">
            <i class="fas fa-users me-3 nav-icon"></i> My Students
        </a>
        <a href="attendance.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-user me-3 nav-icon"></i> Attendance
        </a>
        <a href="timetable.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'timetable.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt me-3 nav-icon"></i> Timetable
        </a>

        <!-- ASSESSMENTS -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Assessments</div>
        <a href="assignments.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'assignments.php' || $current_page == 'view_submissions.php') ? 'active' : ''; ?>">
            <i class="fas fa-tasks me-3 nav-icon"></i> Assignments
        </a>
        <a href="marks.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'marks.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-signature me-3 nav-icon"></i> Marks & Results
        </a>

        <!-- LEARNING RESOURCES -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Learning Resources</div>
        <a href="study_materials.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'study_materials.php' || $current_page == 'study_material.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-pdf me-3 nav-icon"></i> Study Materials
        </a>

        <!-- COMMUNICATION -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Communication</div>
        <a href="announcements.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn me-3 nav-icon"></i> Announcements
        </a>
        <a href="notifications.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">
            <i class="fas fa-bell me-3 nav-icon"></i> Notifications
        </a>

        <!-- ACCOUNT -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Account</div>
        <a href="profile.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-id-badge me-3 nav-icon"></i> Profile
        </a>
        <a href="change_password.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
            <i class="fas fa-key me-3 nav-icon"></i> Change Password
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action py-2 px-3 text-danger mt-3 border-top border-secondary rounded-0">
            <i class="fas fa-sign-out-alt me-3 nav-icon"></i> Logout
        </a>
    </div>
</div>
<!-- /#sidebar-wrapper -->
