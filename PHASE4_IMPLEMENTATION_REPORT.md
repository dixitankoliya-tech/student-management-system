# PHASE 4: IMPLEMENTATION REPORT

## 1. Database Schema Additions
- **`department_heads` table created**: Maps `department_id` to `faculty_id` with start/end dates and status.
- **`course_coordinators` table created**: Maps `course_id` to `faculty_id` with start/end dates and status.
- **`faculty` table altered**: Added `role` column (`faculty`, `coordinator`, `dean`).
- **`users` table altered**: `role` ENUM expanded to include `coordinator` and `dean`.

## 2. Server-Side Authorization Engine
- **Created `includes/faculty_role_helper.php`:**
  - `getFacultyRoleInfo()`: Fetches the hierarchical role, associated department, and assigned courses.
  - `isDean()`, `isCoordinator()`: Fast boolean checks for UI rendering.
  - `hasCourseAccess($course_id)`: Verifies if the faculty is Dean (all courses), Coordinator (assigned course), or Faculty (assigned subject).
  - `getAccessibleCourseIds()`, `getAccessibleStudentIds()`: Generates scoped lists of IDs that can be directly safely embedded into `IN (...)` SQL queries to enforce IDOR protection.

## 3. Session & Authentication Updates
- **Modified `faculty_auth.php`:**
  - `loginUser()` now additionally queries the `faculty` table to fetch the `role` and stores it in `$_SESSION['faculty_role']` upon login. This prevents excessive database querying on every page load while maintaining strict authorization.

## 4. UI Implementations

### Admin Portal
- **Added `admin/leadership.php`**: A UI for the System Admin to view and assign the Dean and Course Coordinators. Handles deactivating old assignments and promoting/demoting faculty roles cleanly via transactions.
- **Updated `admin_sidebar.php`**: Integrated the new Leadership page.

### Faculty / Leadership Portal
- **Updated `faculty_sidebar.php`**: Dynamically displays navigation items based on the session role.
  - **Dean:** Sees "Organization" (Org Chart, All Faculty, All Students, Dept Reports).
  - **Coordinator:** Sees "Course Management" (Course Overview, Course Faculty, Course Students, Course Reports).
  - **Faculty:** Sees standard "Teaching & Academics".
- **Updated `dashboard.php`**: Injects customized statistical overviews for Deans (Dept KPIs) and Coordinators (Course KPIs) before rendering the standard teaching grid.
- **Created new placeholder/view pages:** 
  - `org_chart.php`, `coordinators.php`, `all_faculty.php`, `all_students.php`, `dept_reports.php`
  - `course_overview.php`, `course_students.php`, `course_faculty.php`, `course_reports.php`

## 5. Security & IDOR Mitigation
- **Updated `faculty/my_students.php`**: Replaced standard join queries with `getAccessibleStudentIds()` to allow Deans and Coordinators to view all students within their hierarchical scope, while standard faculty remain restricted to their subjects.
- **Updated `faculty/student_detail.php`**: Enforced an explicit `in_array($student_id, $accessible_student_ids)` check. If a coordinator attempts to view a student from a different course via URL manipulation, they receive a 403 redirect.
- **Attendance & Marks Entry**: Remains structurally scoped to `faculty_subjects`, ensuring leadership cannot mistakenly overwrite primary teachers' registers while utilizing the standard UI, preserving data integrity.
