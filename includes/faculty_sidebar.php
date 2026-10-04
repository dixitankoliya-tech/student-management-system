<?php
$current_page = basename($_SERVER['PHP_SELF']);
$_faculty_role = $_SESSION['faculty_role'] ?? 'faculty';

// Load university branding from system_settings
$_sidebar_uni_name = 'University';
$_sidebar_uni_short = '';
try {
    $_sidebar_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('university_name','university_short_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $_sidebar_uni_name = $_sidebar_settings['university_name'] ?? 'University';
    $_sidebar_uni_short = $_sidebar_settings['university_short_name'] ?? '';
} catch (Exception $e) { /* fallback silently */ }

$_portal_label = 'Faculty Portal';
$_portal_icon = 'fa-chalkboard-teacher';
if ($_faculty_role === 'dean') {
    $_portal_label = 'Dean / HOD Portal';
    $_portal_icon = 'fa-user-shield';
} elseif ($_faculty_role === 'coordinator') {
    $_portal_label = 'Coordinator Portal';
    $_portal_icon = 'fa-user-tie';
}
?>
<!-- Sidebar -->
<div class="border-end" id="sidebar-wrapper">
    <div class="sidebar-heading text-white py-4 px-3 text-center">
        <div class="mb-2">
            <i class="fas <?php echo $_portal_icon; ?> fs-5 text-primary"></i>
        </div>
        <div class="fs-5 fw-bold text-light" style="letter-spacing: -0.5px;"><?php echo htmlspecialchars(strtoupper($_sidebar_uni_name)); ?></div>
        <div class="text-primary fw-semibold mt-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.5px;"><?php echo $_portal_label; ?></div>
    </div>
    
    <div class="list-group list-group-flush mt-2 pb-4">
        
        <!-- OVERVIEW -->
        <div class="px-3 pt-2 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Overview</div>
        <a href="dashboard.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home me-3 nav-icon"></i> Dashboard
        </a>

        <?php if ($_faculty_role === 'dean'): ?>
        <!-- DEAN-ONLY: ORGANIZATION -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Organization</div>
        <a href="org_chart.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'org_chart.php') ? 'active' : ''; ?>">
            <i class="fas fa-sitemap me-3 nav-icon"></i> Organization Chart
        </a>
        <a href="coordinators.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'coordinators.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-tie me-3 nav-icon"></i> Course Coordinators
        </a>
        <a href="all_faculty.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'all_faculty.php') ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher me-3 nav-icon"></i> All Faculty
        </a>
        <a href="all_students.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'all_students.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate me-3 nav-icon"></i> All Students
        </a>
        <a href="dept_reports.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'dept_reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar me-3 nav-icon"></i> Department Reports
        </a>
        <?php endif; ?>

        <?php if ($_faculty_role === 'coordinator'): ?>
        <!-- COORDINATOR-ONLY: COURSE MANAGEMENT -->
        <div class="px-3 pt-3 pb-1 text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Course Management</div>
        <a href="course_overview.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'course_overview.php') ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap me-3 nav-icon"></i> Course Overview
        </a>
        <a href="course_faculty.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'course_faculty.php') ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard-teacher me-3 nav-icon"></i> Course Faculty
        </a>
        <a href="course_students.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'course_students.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate me-3 nav-icon"></i> Course Students
        </a>
        <a href="course_reports.php" class="list-group-item list-group-item-action py-2 px-3 <?php echo ($current_page == 'course_reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie me-3 nav-icon"></i> Course Reports
        </a>
        <?php endif; ?>

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
