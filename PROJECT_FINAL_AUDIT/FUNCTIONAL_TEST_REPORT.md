# GLS UNIVERSITY FCAIT STUDENT MANAGEMENT SYSTEM
## Functional & Automated Test Execution Report

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Test Suite:** `scripts/comprehensive_test_suite.php`  
**Date of Execution:** October 2026  

---

## 1. Automated Test Execution Summary

```
====================================================================
 TEST RUN SUMMARY: TOTAL: 35 | PASSED: 35 | FAILED: 0 (100.0%)
====================================================================
```

### Detailed Test Results Matrix

| Test ID | Category | Test Description | Expected Result | Actual Result | Status |
|:---|:---|:---|:---:|:---:|:---:|
| **SEC-RTR-01** | Router Security | Nested Path Traversal (`....//....//config/database.php`) | 403 Forbidden | 403 Forbidden | **PASS** |
| **SEC-RTR-02** | Router Security | Direct `.env` file access blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| **SEC-RTR-03** | Router Security | Direct `.env.production` file access blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| **SEC-RTR-04** | Router Security | Direct `config/database.php` access blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| **SEC-RTR-05** | Router Security | Direct `scripts/schema.sql` access blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| **SEC-RTR-06** | Router Security | Direct `migrate_to_tidb.php` access blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| **AUTH-01** | Authentication | Bcrypt hash verification with correct password | TRUE | TRUE | **PASS** |
| **AUTH-02** | Authentication | Bcrypt hash verification with invalid password rejected | FALSE | FALSE | **PASS** |
| **CSRF-01** | CSRF Defense | Matching token validation with `hash_equals()` | TRUE | TRUE | **PASS** |
| **CSRF-02** | CSRF Defense | Forged or mismatched token rejected | FALSE | FALSE | **PASS** |
| **ACAD-GRD-01** | Academic Engine | Grade calculation: 95% -> Grade O (10 pts) | O:10 | O:10 | **PASS** |
| **ACAD-GRD-02** | Academic Engine | Grade calculation: 85% -> Grade A+ (9 pts) | A+:9 | A+:9 | **PASS** |
| **ACAD-GRD-03** | Academic Engine | Grade calculation: 72% -> Grade A (8 pts) | A:8 | A:8 | **PASS** |
| **ACAD-GRD-04** | Academic Engine | Grade calculation: 64% -> Grade B+ (7 pts) | B+:7 | B+:7 | **PASS** |
| **ACAD-GRD-05** | Academic Engine | Grade calculation: 55% -> Grade B (6 pts) | B:6 | B:6 | **PASS** |
| **ACAD-GRD-06** | Academic Engine | Grade calculation: 42% -> Grade C (5 pts) | C:5 | C:5 | **PASS** |
| **ACAD-GRD-07** | Academic Engine | Grade calculation: 35% -> Grade F (0 pts) | F:0 | F:0 | **PASS** |
| **ACAD-SGPA-01** | Academic Engine | Weighted SGPA calculation across credits [4,4,3] | 9.09 | 9.09 | **PASS** |
| **ACAD-SGPA-02** | Academic Engine | Semester Result Status: Pass | Pass | Pass | **PASS** |
| **ACAD-SGPA-03** | Academic Engine | Semester Result Status: Fail with Backlog | Fail | Fail | **PASS** |
| **ATT-01** | Attendance | 80/100 (80%) -> Status evaluation | SAFE | SAFE | **PASS** |
| **ATT-02** | Attendance | 75/100 (75%) -> Boundary status evaluation | SAFE | SAFE | **PASS** |
| **ATT-03** | Attendance | 74/100 (74%) -> Shortage alert threshold | SHORTAGE | SHORTAGE | **PASS** |
| **ATT-04** | Attendance | 50/100 (50%) -> Shortage alert threshold | SHORTAGE | SHORTAGE | **PASS** |
| **PROM-01** | Promotion Engine | Promotion eligibility: 0 Backlogs | ELIGIBLE | ELIGIBLE | **PASS** |
| **PROM-02** | Promotion Engine | Promotion eligibility: 2 Backlogs (<=3) | ELIGIBLE | ELIGIBLE | **PASS** |
| **PROM-03** | Promotion Engine | Promotion eligibility: 3 Backlogs (Max allowable) | ELIGIBLE | ELIGIBLE | **PASS** |
| **PROM-04** | Promotion Engine | Promotion eligibility: 4 Backlogs (>3 Blocked) | BLOCKED | BLOCKED | **PASS** |
| **DB-INT-01** | Database Integrity | FCAIT Department Active count | 1 | 1 | **PASS** |
| **DB-INT-02** | Database Integrity | Course count across FCAIT | 5 | 5 | **PASS** |
| **DB-INT-03** | Database Integrity | Total student records registered | 500 | 500 | **PASS** |
| **DB-INT-04** | Database Integrity | Total faculty records registered | 45 | 45 | **PASS** |
| **DB-INT-05** | Relational Health | Orphan student records check | 0 | 0 | **PASS** |
| **DB-INT-06** | Relational Health | Orphan student marks records check | 0 | 0 | **PASS** |
| **LINT-ALL** | Syntax Linting | `php -l` iteration over 163 repository files | 0 errors | 0 errors | **PASS** |

---

## 2. Live Production Deployment Probe Results

Probing live Vercel deployments backed by TiDB Cloud Serverless:

```
[PASS] Admin Portal    : https://gls-sms-admin.vercel.app -> HTTP 200 (URL: https://gls-sms-admin.vercel.app/login.php)
[PASS] Faculty Portal  : https://gls-sms-faculty.vercel.app -> HTTP 200 (URL: https://gls-sms-faculty.vercel.app/login.php)
[PASS] Student Portal  : https://gls-sms-student.vercel.app -> HTTP 200 (URL: https://gls-sms-student.vercel.app/login.php)
```

---

## 3. Functional Portal Verification Across Modules

### A. Admin Portal
- **Dashboard & Analytics:** Displays real, database-backed metrics (500 active students, 45 faculty, fee collections, academic performance distributions).
- **Academic Setup:** Full configuration for 5 Courses, 128 Accredited Subjects, and Semesters.
- **Leadership Management (`admin/leadership.php`):** Dean and Course Coordinator assignments verified with CSRF protection.
- **Academic Promotion (`admin/promote_students.php` & `process_promotion.php`):** Automated promotion engine enforces server-side ATKT rule (`backlogs <= 3`).
- **Exam Management (`examinations.php` & `exam_schedules.php`):** 56 terms and 112 examination schedule slots with room allocations.
- **Fee Management (`fee_structures.php` & `fee_payments.php`):** 32 structures, 500 student balances, and 450 verified payment receipts.

### B. Faculty Portal
- **Dashboard:** Scoped by logged-in faculty member; displays assigned subjects and student cohorts.
- **Class Rosters (`my_students.php`, `student_detail.php`):** RBAC enforced; faculty can access only authorized classes and students.
- **Attendance (`attendance.php`):** Session attendance logging with automatic 75% shortage alert trigger.
- **Marks Entry (`marks.php`):** Grade input bounded by max marks and verified class rosters.
- **Study Materials & Assignments:** Coursework creation and grading workflows with database BLOB storage.
- **Dean & Coordinator Analytics:** Deans access `dept_reports.php`; Coordinators access `course_reports.php`, `course_faculty.php`, and `course_students.php`.

### C. Student Portal
- **Dashboard:** Personalized student metrics (current semester, subject list, real-time SGPA/CGPA, attendance percentage, active notices).
- **Results (`results.php`):** Dynamic weighted SGPA and CGPA calculated from marks.
- **Attendance (`attendance.php`):** Subject-by-subject attendance logs with 75% shortage warnings.
- **Examinations & Hall Ticket:** Timetable view and printable examination Hall Ticket.
- **Assignments:** Download assignments, submit files, and view grading feedback.
- **Fees & Receipts:** Fee balance breakdown and printable payment receipts.
