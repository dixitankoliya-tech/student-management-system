# GLS FCAIT SMS — COMPLETE MISSING FUNCTION, FEATURE & DATA AUDIT

## 1. Executive Summary
The GLS FCAIT Student Management System (SMS) possesses a robust underlying database schema that supports almost all required realistic university operations (attendance, marks, assignments, study materials, fees, notifications). However, there is a significant disconnect between the database capabilities and the User Interface/Application logic. Many advanced features (e.g., SGPA/CGPA calculations, Promotion rules, Backlogs) are partially implemented, localized only to the Student Portal, or missing entirely from the Admin/Faculty Portals.

## 2. Current Architecture
- **Backend:** PHP with PDO
- **Database:** TiDB Cloud Serverless (MySQL compatible)
- **Deployment:** Vercel (3 separate portals gating logic via `PORTAL` env variable)
- **Authentication:** Unified `users` table with roles (`admin`, `faculty`, `student`)

## 3. Database Inventory
The TiDB database contains all the necessary tables for a full University ERP. Key tables include:
- **Academic:** `courses`, `departments`, `semesters_master`, `academic_years`, `subjects`, `timetables`
- **Users:** `students`, `faculty`, `users`
- **Enrollments:** `student_academic_enrollments`, `student_subject_enrollments`, `faculty_subjects`
- **Performance:** `marks`, `student_marks`, `examinations`, `academic_performance`, `achievements`
- **Activities:** `attendance`, `assignments`, `assignment_submissions`, `study_materials`
- **Operations:** `student_fees`, `fee_structures`, `fee_payments`, `notifications`, `activity_logs`

*Score: 90/100 (Highly capable schema)*

## 4. Academic Structure
- **Institution:** GLS
- **Department:** FCAIT
- **Courses:** B.Sc. IT (Dual), BCA (Dual), BCA, I.M.Sc. IT, M.Sc. IT
- **Students:** 500 (100 per course)
- **Faculty:** 45 (Including 1 HOD)
*Status: Successfully generated and mapped in the database.*

## 5. Data Quality
- Validation scripts confirm zero orphans across critical relational tables.
- All students are mapped to valid courses and semesters.
- All subjects belong to valid FCAIT courses.

## 6. Admin Audit
- **Dashboard:** Operational, shows summary stats.
- **Student Management:** Full CRUD supported.
- **Marks Management:** Basic marks viewing, but lacks holistic transcript/marksheet generation.
- **Report Card:** Shows dummy/cached data (`academic_performance`) rather than real-time calculated SGPA/CGPA like the Student Portal does.
- **Missing:** Promotion orchestration (ATKT/Backlog handling), mass fee assignment, comprehensive attendance reporting.

## 7. Faculty Audit
- **Dashboard:** Operational.
- **Subjects & Students:** Can view assigned subjects and enrolled students.
- **Attendance:** Faculty can mark attendance.
- **Assignments:** Faculty can create assignments and evaluate submissions.
- **Study Materials:** Faculty can upload materials.
- **Marks:** Can enter internal/external marks.
- **Missing:** Detailed performance tracking of their students, Backlog/ATKT exam evaluation.

## 8. Student Audit
- **Dashboard:** Operational.
- **Results:** Dynamically calculates SGPA and CGPA from actual marks. Highly realistic.
- **Assignments:** Can view and submit assignments.
- **Study Materials:** Can access uploaded files.
- **Missing:** Semester-by-semester printable transcript (FY/SY/TY marksheet), Backlog examination applying.

## 9. Attendance Audit
- **Status:** PARTIAL.
- **Database:** `attendance` table exists.
- **Faculty:** Can mark attendance.
- **Missing:** Student viewing their own aggregate attendance % (only dummy % shown in some views), Admin attendance shortage alerts based on a configurable threshold (e.g. 75%).

## 10. Marks Audit
- **Status:** PARTIAL.
- **Database:** `marks` (exam_id, internal/external logic in `student_marks`).
- **Functionality:** Faculty can enter marks, Admin can view.
- **Missing:** Robust bifurcation of Internal components (Unit Test 1, Unit test 2, Mid Sem, Assignments) vs External (University Exam).

## 11. Marksheet Audit
- **Status:** PARTIAL.
- **Problem:** Student portal has dynamic result viewing, but Admin portal `report_card.php` lacks the actual SGPA/CGPA engine.
- **Required:** Printable FY/SY/TY marksheets accessible by both Student and Admin.

## 12. SGPA/CGPA Audit
- **Status:** PARTIAL.
- **Logic:** Exists in `includes/academic_calculator.php` (Accurate calculation based on Credits & Grade Points).
- **Problem:** Only used in Student portal (`results.php`). Admin uses a static `academic_performance` table. 

## 13. Examination Audit
- **Status:** MISSING/BASIC.
- **Database:** `examinations` table exists (start_date, end_date, exam_type).
- **Missing:** Exam scheduling UI for Admin, Exam timetable view for Students.

## 14. Assignment Audit
- **Status:** PASS.
- **Flow:** Faculty creates -> Student Submits -> Faculty Evaluates.
- **Works:** End-to-end functionality exists.

## 15. Fee Audit
- **Status:** MISSING/BASIC.
- **Database:** Tables exist (`student_fees`, `fee_payments`).
- **Missing:** UI for Admin to generate mass fee demands per semester, Student UI to view overdue fees and print receipts.

## 16. Notification Audit
- **Status:** PASS.
- **Flow:** Admin can create notifications targeted by Role/Course.

## 17. Promotion Audit
- **Status:** MISSING.
- **Database:** `promotion_history` exists.
- **Missing:** The actual logic to promote students from Sem 1 to Sem 2 based on passing SGPA rules. Currently requires manual DB edits.

## 18. ATKT/Backlog Audit
- **Status:** MISSING.
- **Missing:** Logic to flag failed subjects as "Backlog" and allow students to appear for supplementary exams.

## 19. Reporting Audit
- **Status:** PARTIAL.
- **Missing:** Comprehensive printable reports for Failed students, Attendance shortages, Top performers, Faculty workload.

## 20. Security Audit
- **Status:** PASS.
- **Flow:** Roles are strictly enforced (`requireRole()`). Vercel `PORTAL` variables prevent cross-portal access. 
- **DB:** Prepared statements used throughout, preventing SQL injection.

## 21. Production Audit
- **Status:** PASS.
- **Env:** No localhost/127.0.0.1 hardcoded connections. Proper TiDB Cloud Serverless TLS connection via PDO.

## 22. UI/UX Audit
- **Status:** PASS.
- **Design:** Consistent Bootstrap 5 styling across Admin, Faculty, and Student portals. Responsive.

## 23. Dead Code Audit
- Old migration scripts (`migrate_to_tidb.php`) and old seeds (`seed_150_students.php`) are present but inactive. Safe to leave or delete.

## 24. Missing Features Summary
1. Exam Scheduling Engine
2. ATKT / Backlog Management
3. Promotion Automation Engine
4. FY/SY/TY Printable Transcript Marksheets
5. Admin Report Card synchronization with Student SGPA Engine
6. Automated Fee Demand Generation

## 25. Missing Data Summary
- Internal assessment components (Unit Tests, Practicals) are merged into a single "Internal Marks" field.

## 26. Bugs
- **BUG-01:** Admin `report_card.php` displays dummy/cached data instead of real-time SGPA/CGPA.
- **BUG-02:** Student portal attendance percentage is hardcoded/mocked in some views.

## 27. Feature Matrix

| FEATURE | ADMIN | FACULTY | STUDENT | DATABASE | STATUS |
|---------|-------|---------|---------|----------|--------|
| Student Management | YES | VIEW | VIEW | YES | PASS |
| Attendance | PARTIAL | YES | PARTIAL | YES | PARTIAL |
| Marks | YES | YES | YES | YES | PASS |
| Assignments | YES | YES | YES | YES | PASS |
| Fees | PARTIAL | NO | PARTIAL | YES | PARTIAL |
| Exams | PARTIAL | NO | NO | YES | MISSING |
| Results/Marksheet | BROKEN | NO | YES | YES | PARTIAL |
| SGPA/CGPA | BROKEN | NO | YES | YES | PARTIAL |
| Notifications | YES | NO | YES | YES | PASS |
| ATKT/Backlog | NO | NO | NO | NO | MISSING |
| Promotion | NO | NO | NO | YES | MISSING |

## 28. Priority Fix Plan
1. **CRITICAL:** Fix Admin Report Card to use the real SGPA/CGPA engine.
2. **HIGH:** Implement FY/SY/TY transcript generation.
3. **HIGH:** Build the Semester Promotion & ATKT logic.
4. **MEDIUM:** Build the Examination Scheduling UI.
5. **MEDIUM:** Implement dynamic Attendance % calculation and warnings.
6. **LOW:** Build advanced Reports (Top performers, shortages).

## 29. Recommended Phase Order
1. **Phase 1: Academic Engine Synchronization** (Unify SGPA/CGPA logic across Admin/Student, build official Marksheets).
2. **Phase 2: Academic Progression** (Build Promotion, ATKT, and Backlog handling).
3. **Phase 3: Operations & Logistics** (Exams, Fees, Attendance thresholds).
4. **Phase 4: Advanced Reporting & QA**

## 30. Final Completeness Score
- DATABASE COMPLETENESS: 90/100
- ADMIN COMPLETENESS: 75/100
- FACULTY COMPLETENESS: 85/100
- STUDENT COMPLETENESS: 80/100
- ACADEMIC WORKFLOW: 60/100
- REPORTING: 50/100
- SECURITY: 95/100
- UI/UX: 90/100

**OVERALL SMS COMPLETENESS: 78/100**
