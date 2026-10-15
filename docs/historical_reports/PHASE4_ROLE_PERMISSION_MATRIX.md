# PHASE 4: ROLE & PERMISSION MATRIX

## 1. Principles
- **Deny by Default:** Access is denied unless the user's role explicitly grants it within the correct scope.
- **Server-Side Enforcement:** UI hiding is insufficient; backend queries must filter by scope.
- **Role Isolation:** Admin is for system config; Dean/Coordinator are for academic management.

## 2. Access Scopes
| Role | Scope Parameter | Data Visibility |
| :--- | :--- | :--- |
| **System Admin** | Global | Master data, users, roles, settings, departments, courses. |
| **Dean (HOD)** | Department ID | All courses, faculty, students, and academics within the assigned department. |
| **Coordinator** | Course ID | All students, faculty, subjects, and academics within their assigned course. |
| **Faculty** | Subject ID / Division | Students and academics specifically assigned via `faculty_subjects`. |
| **Student** | Student ID | Own academic record, marks, attendance, fees, profile. |

## 3. Module Permissions

### Dashboard
- **Admin:** System KPIs (Total Students, Courses, Active Users, etc.)
- **Dean:** Department KPIs (Students per course, Faculty count, Upcoming exams, active coordinators).
- **Coordinator:** Course KPIs (Total enrolled, faculty assigned, subjects).
- **Faculty:** Teaching KPIs (My Subjects, My Classes, Pending marks).

### My Students (`student_detail.php`)
- **Dean:** Access allowed for `student.department_id = Dean.department_id`.
- **Coordinator:** Access allowed for `student.course_name = Coordinator.course_name`.
- **Faculty:** Access allowed for `student.id IN (students enrolled in Faculty.subjects)`.

### Attendance & Marks
- **Dean/Coordinator:** Can view aggregate reports. Marking is primarily restricted to the assigned faculty via `faculty_subjects`, enforcing data integrity so coordinators don't accidentally overwrite a teacher's register.
- **Faculty:** Full create/update access for assigned subjects.

### Leadership Assignment (`admin/leadership.php`)
- **Admin:** Full access to assign/revoke Dean and Course Coordinators.
- **Dean/Coordinator:** Read-only visibility (Org Chart, list of coordinators).
