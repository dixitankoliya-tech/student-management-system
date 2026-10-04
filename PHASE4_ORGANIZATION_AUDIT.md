# PHASE 4: ORGANIZATION AUDIT

## 1. Objective
To audit the existing system and design a proper role-based academic hierarchy for GLS University, FCAIT department.

## 2. Existing Schema
- **Faculty:** 45 records, previously had no hierarchical role beyond standard `faculty`.
- **Users:** Had roles: `admin`, `faculty`, `student`. No differentiation between HOD and regular faculty.
- **Data Scope:** Faculty access to students was strictly bound by `faculty_subjects`, which correctly scoped standard teachers but lacked a way for a Dean or Coordinator to view data across their designated scope without explicitly being assigned to teach every subject.

## 3. Required Entities
1. **Dean / Head of Department:** Needs department-wide visibility. Must be singular per department.
2. **Course Coordinator:** Needs course-wide visibility. Must be singular per course.
3. **Faculty:** Needs subject-wide visibility.
4. **Students:** Need personal data visibility.
5. **Admin:** Needs system configuration and master data management.

## 4. Gap Analysis
- No database tables existed to track `department_heads` or `course_coordinators`.
- The `faculty` table lacked a `role` column to cache the hierarchical position.
- Sessions for faculty only tracked `user_role = faculty`, ignoring the hierarchical difference.
- Dashboards were statically built for standard faculty, with no aggregate data for leadership roles.
- `faculty_auth.php` did not load or verify hierarchical roles.

## 5. Architectural Decisions
- Created `department_heads` and `course_coordinators` tables to maintain assignment history and enforce uniqueness (1 Dean per Dept, 1 Coord per Course).
- Added a `role` ENUM (`faculty`, `coordinator`, `dean`) to the `faculty` table for fast access.
- Expanded `users.role` to allow `dean` and `coordinator` (though for login, they use the `faculty` portal with elevated privileges).
- Implemented `includes/faculty_role_helper.php` to centralize IDOR and scope checks (`hasCourseAccess`, `getAccessibleStudentIds`, etc.).
