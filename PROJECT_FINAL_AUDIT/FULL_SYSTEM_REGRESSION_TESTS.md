# FULL SYSTEM REGRESSION TESTS & SECURITY AUDIT REPORT

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date:** October 12, 2026  
**Auditor:** Antigravity Autonomous Security & Performance Engineering  

---

## 1. REGRESSION SUITE EXECUTION SUMMARY

The automated test runner (`scripts/comprehensive_test_suite.php`) was executed following all system repairs.

* **Total Tests Executed:** 35
* **Total Tests Passed:** 35 (100.0%)
* **Total Tests Failed:** 0 (0.0%)
* **Total PHP Source Files Linted:** 174 files
* **Syntax / Lint Errors Found:** 0 errors

---

## 2. DETAILED TEST MATRIX BREAKDOWN

### SECTION 1: ROUTER & DIRECTORY TRAVERSAL DEFENSES
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `SEC-RTR-01` | Nested Path Traversal Blocked (`../../etc/passwd`) | 403 Forbidden | 403 Forbidden | **PASS** |
| `SEC-RTR-02` | `.env` Direct Access Blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| `SEC-RTR-03` | `.env.production` Direct Access Blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| `SEC-RTR-04` | `config/database.php` Direct Browser Access Blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| `SEC-RTR-05` | `scripts/schema.sql` Direct Access Blocked | 403 Forbidden | 403 Forbidden | **PASS** |
| `SEC-RTR-06` | `migrate_to_tidb.php` Migration Script Blocked | 403 Forbidden | 403 Forbidden | **PASS** |

### SECTION 2: AUTHENTICATION & CSRF PROTECTION
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `AUTH-01` | Bcrypt Hash Verification (Valid Credentials) | `TRUE` | `TRUE` | **PASS** |
| `AUTH-02` | Bcrypt Hash Verification (Tampered Password Rejected) | `FALSE` | `FALSE` | **PASS** |
| `CSRF-01` | CSRF Token Match Validation | `TRUE` | `TRUE` | **PASS** |
| `CSRF-02` | CSRF Token Tamper / Mismatch Rejection | `FALSE` | `FALSE` | **PASS** |

### SECTION 3: ACADEMIC CALCULATOR ENGINE (SGPA, CGPA, GRADES)
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `ACAD-GRD-01` | Grade Boundary: 95% Marks -> O Grade (10 Grade Points) | `O:10` | `O:10` | **PASS** |
| `ACAD-GRD-02` | Grade Boundary: 85% Marks -> A+ Grade (9 Grade Points) | `A+:9` | `A+:9` | **PASS** |
| `ACAD-GRD-03` | Grade Boundary: 72% Marks -> A Grade (8 Grade Points) | `A:8` | `A:8` | **PASS** |
| `ACAD-GRD-04` | Grade Boundary: 64% Marks -> B+ Grade (7 Grade Points) | `B+:7` | `B+:7` | **PASS** |
| `ACAD-GRD-05` | Grade Boundary: 55% Marks -> B Grade (6 Grade Points) | `B:6` | `B:6` | **PASS** |
| `ACAD-GRD-06` | Grade Boundary: 42% Marks -> C Grade (5 Grade Points) | `C:5` | `C:5` | **PASS** |
| `ACAD-GRD-07` | Grade Boundary: 35% Marks -> F Grade (0 Grade Points) | `F:0` | `F:0` | **PASS** |
| `ACAD-SGPA-01` | Weighted SGPA Calculation across Credit Hours | `9.09` | `9.09` | **PASS** |
| `ACAD-SGPA-02` | Semester Result Status: All Passed -> Pass | `Pass` | `Pass` | **PASS** |
| `ACAD-SGPA-03` | Semester Result Status: Backlog Detected -> Fail | `Fail` | `Fail` | **PASS** |

### SECTION 4: ATTENDANCE ALERT ENGINE
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `ATT-01` | Attendance 80/100 (80.0%) -> SAFE | `SAFE` | `SAFE` | **PASS** |
| `ATT-02` | Attendance 75/100 (75.0% Minimum Threshold) -> SAFE | `SAFE` | `SAFE` | **PASS** |
| `ATT-03` | Attendance 74/100 (74.0%) -> SHORTAGE Alert | `SHORTAGE` | `SHORTAGE` | **PASS** |
| `ATT-04` | Attendance 50/100 (50.0%) -> Critical SHORTAGE Alert | `SHORTAGE` | `SHORTAGE` | **PASS** |

### SECTION 5: PROMOTION & ATKT POLICY RULES
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `PROM-01` | Promotion with 0 Backlogs | `ELIGIBLE` | `ELIGIBLE` | **PASS** |
| `PROM-02` | Promotion with 2 Backlogs (<= 3 Allowed) | `ELIGIBLE` | `ELIGIBLE` | **PASS** |
| `PROM-03` | Promotion with 3 Backlogs (Max Allowable Ceiling) | `ELIGIBLE` | `ELIGIBLE` | **PASS** |
| `PROM-04` | Promotion with 4 Backlogs (> 3 Blocked) | `BLOCKED` | `BLOCKED` | **PASS** |

### SECTION 6: PRODUCTION DATABASE INTEGRITY
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `DB-INT-01` | FCAIT Department Active Status | `1` | `1` | **PASS** |
| `DB-INT-02` | Academic Programs Count (5 Programs) | `5` | `5` | **PASS** |
| `DB-INT-03` | Total Students Registered (500 Cohort) | `500` | `500` | **PASS** |
| `DB-INT-04` | Total Faculty Count (45 Members) | `45` | `45` | **PASS** |
| `DB-INT-05` | Orphan Student Records in Enrollments | `0` | `0` | **PASS** |
| `DB-INT-06` | Orphan Student Marks Entries | `0` | `0` | **PASS** |

### SECTION 7: REPOSITORY PHP SYNTAX LINTING
| Test ID | Test Case Description | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| `LINT-ALL` | Comprehensive PHP Syntax Lint across Workspace | `0 errors` | `0 errors (174 files)` | **PASS** |

---

## 3. LIVE PRODUCTION VERCEL DEPLOYMENT PROBES

Automated HTTP verification against live public production URLs:

| Portal | Production URL | HTTP Status | Effective URL | Response State |
|---|---|---|---|---|
| **Admin Portal** | `https://gls-sms-admin.vercel.app` | 200 OK | `https://gls-sms-admin.vercel.app/login.php` | Operational |
| **Faculty Portal** | `https://gls-sms-faculty.vercel.app` | 200 OK | `https://gls-sms-faculty.vercel.app/login.php` | Operational |
| **Student Portal** | `https://gls-sms-student.vercel.app` | 200 OK | `https://gls-sms-student.vercel.app/login.php` | Operational |

*Note: In accordance with project instructions, live public probes tested unauthenticated entry points. Code fixes for protected pages (`fees.php`, `fee_payments.php`, `print_receipt.php`) were fully verified in the local production-mirror environment, ready for deployment push upon explicit user authorization.*
