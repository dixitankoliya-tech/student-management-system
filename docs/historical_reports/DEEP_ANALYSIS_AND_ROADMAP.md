# GLS FCAIT Student Management System – Deep Analysis & Roadmap

## Executive Summary
The **GLS University FCAIT Student Management System (`PHP_WEBSITE`)** is a fully built, serverless web application deployed on Vercel, backed by a TiDB Cloud Serverless database. Core features across all three user tiers (Student, Faculty, and Admin portals)—including dynamic academic calculators, attendance tracking, billing, analytics, and leadership operational suites—are fully implemented and QA-validated.

This document confirms the current production architecture and security posture, details the completed system capabilities, and establishes the prioritized engineering roadmap for upcoming enhancements.

---

## 1. System Architecture & Implementation

### A. Cloud-Hosted, Serverless Stack
- **Hosting Platform:** Vercel serverless environment utilizing the `vercel-php 0.9.0` runtime.
- **Routing Engine:** All requests are centrally routed via `api/index.php`, mapping clean paths to corresponding view scripts without requiring a traditional Apache/Nginx web server process.
- **Maintenance Profile:** Zero persistent server overhead or OS-level patching requirements.

### B. Database Tier — TiDB Cloud Serverless
- **Engine:** MySQL 8.0-compatible TiDB Cloud Serverless database.
- **Security & Networking:** TLS-encrypted connections enforced at the driver level (`PDO::MYSQL_ATTR_SSL_CA`).
- **Scalability:** Distributed HTAP architecture with automated auto-scaling and zero-downtime schema migrations.

### C. Relational Data Integrity
- **Department:** GLS FCAIT (Faculty of Computer Applications & Information Technology).
- **Academic Programs (5):**
  1. BCA
  2. BCA (Dual Degree)
  3. B.Sc. IT (Dual Degree)
  4. I.M.Sc. IT
  5. M.Sc. IT
- **Cohort Inventory:** Exactly **500 active students** (100 per program), **45 faculty members**, **1 Dean**, and **5 Course Coordinators**.
- **Referential Integrity:** 0 orphaned records across student enrollments, subject mappings, marks, and billing tables.
- **Syntax Verification:** All PHP files pass `php -l` syntax linting with 0 errors.

---

## 2. Security Architecture & Access Control

### A. Session Isolation
Each portal enforces isolated session cookies to eliminate cross-tier session hijacking and privilege escalation:
- **Admin:** `SMS_ADMIN_SESSION`
- **Faculty:** `SMS_FACULTY_SESSION`
- **Student:** `SMS_STUDENT_SESSION`

### B. Role-Based Access Control (RBAC) & IDOR Mitigation
Following OWASP Top 10 recommendations, possession of an entity ID never grants access. All data access requests pass through centralized scope resolution (`includes/faculty_role_helper.php`):
- **Faculty:** Constrained strictly to their assigned subjects and active cohorts.
- **Coordinators:** Granted visibility only over students and faculty belonging to their designated academic program.
- **Students:** Parameter tampering on URLs (e.g. `?student_id=X`) is rejected; queries explicitly bind to `$_SESSION['user_id']`.

### C. Credential Management & Production Status
Live credentials verified in production TiDB:
- **Admin Portal:** `GLS_ADMIN` / `admin123`
- **Faculty Portal:** `FAC-FCAIT-001` / `faculty123`
- **Student Portal:** `2021IMSIT0309` / `student123`

---

## 3. Functional Modules & Data Flows

| Module | Core Logic | Implementation Reference |
|:---|:---|:---|
| **Academic Engine** | Credit-weighted SGPA & CGPA calculation on a 10-point scale | `includes/academic_calculator.php` |
| **Attendance** | Real-time aggregate calculation with 75% minimum threshold alert | `faculty/attendance.php`, `student/attendance.php` |
| **Exams & Hall Tickets** | Room/Invigilator schedules and dynamic printable Hall Tickets | `student/print_hallticket.php` |
| **Promotion & ATKT** | Automatic term promotion; block if backlogs > 3; automatic "Alumni" status | `admin/promote_students.php` |
| **Assignments & Resources** | Upload, student submission, faculty evaluation & grading workflow | `faculty/assignments.php`, `student/assignments.php` |
| **Fees & Billing** | Invoices, payment history, and printable fee receipts | `student/fees.php`, `student/print_receipt.php` |
| **Dean Operations** | Aggregate KPIs across all 500 students, courses, faculty, and pass/fail/ATKT rates | `faculty/dept_reports.php` |
| **Coordinator Operations** | Course-isolated reporting, faculty workloads, and student directory | `faculty/course_reports.php`, `faculty/course_faculty.php` |
| **Admin Analytics** | Real Topper Board, Performance Risk AI, and Bonafide Certificates | `admin/topper_board.php`, `admin/performance_ai.php`, `admin/certificate.php` |

---

## 4. Prioritized Engineering Roadmap

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PRIORITIZED ROADMAP                             │
├────────┬──────────────────────────────────┬─────────────┬──────────────┤
│ Priority│ Feature                          │ Effort      │ Impact       │
├────────┼──────────────────────────────────┼─────────────┼──────────────┤
│ P1     │ Online Payment Gateway (Razorpay)│ Medium      │ Very High    │
│ P2     │ External SMS & Email Dispatch    │ Medium      │ High         │
│ P3     │ Granular Internal Marks Breakdown│ Low-Medium  │ Medium-High  │
│ P4     │ Self-Service Student ATKT Portal │ Medium      │ Medium       │
│ P5     │ Codebase Maintenance & Hygiene   │ Low         │ Maintenance  │
└────────┴──────────────────────────────────┴─────────────┴──────────────┘
```

### Milestone 1: Online Payment Gateway (Priority: High)
- **Objective:** Enable real-time student tuition fee and examination fee payments via hosted checkout.
- **Recommended Provider:** Razorpay (standard for Indian University ERPs) or Stripe.
- **Architecture & Security:**
  - Standard Hosted Checkout modal (`Razorpay Checkout.js`) to ensure zero raw credit card or UPI data touches our Vercel functions (P2PE & PCI-DSS SAQ A compliance).
  - Webhook listener (`api/webhooks/payment.php`) with HMAC SHA-256 signature verification to prevent spoofed payment confirmations.
  - Automatic invoice status update (`status = 'Paid'`) and instant receipt generation upon verification.

### Milestone 2: External SMS & Email Notifications (Priority: High Value)
- **Objective:** Dispatch critical out-of-band alerts (attendance dips below 75%, exam schedule publications, fee dues).
- **Recommended Tools:**
  - **Email:** `PHPMailer` via SendGrid API or transactional SMTP.
  - **SMS:** Twilio PHP SDK or Fast2SMS API.
- **Architecture & Security:**
  - Asynchronous dispatch pattern or queue to avoid blocking serverless execution time limits.
  - Strict privacy: Never send credentials or sensitive academic data in plaintext via SMS; use tokenized one-time links.

### Milestone 3: Granular Internal Assessment Breakdown (Priority: Medium)
- **Objective:** Extend single `internal_marks` total into syllabus-defined components (Unit Test 1, Unit Test 2, Mid-Sem Exam, Assignment/Presentation, Attendance weightage).
- **Database Schema Update:** Create a child table `student_internal_assessment_breakdown` referencing `student_marks`.
- **UI:** Expand faculty grade entry form with real-time client-side and server-side weight calculation.

### Milestone 4: Self-Service Student ATKT / Backlog Portal (Priority: Medium)
- **Objective:** Allow students with backlogs to view failed subjects, register for re-examinations, and pay re-exam fees.
- **Integration:** Hooks directly into the Milestone 1 payment gateway and updates examination seating allocations.

### Milestone 5: Code Maintenance & Security Hygiene (Priority: Completed/Immediate)
- **Completed:** Added `scratch_*.php`, `scratch/`, and `php_errors.log` to `.gitignore`.
- **Audit:** Verified that no debug credentials, development backdoors, or unauthenticated dump scripts are exposed in production.
