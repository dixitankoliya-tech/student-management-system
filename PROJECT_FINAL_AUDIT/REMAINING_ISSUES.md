# REMAINING ISSUES AND FUTURE ROADMAP REPORT

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date of Audit:** 2026-10-11  

---

## 1. Issue Categorization Matrix

| Category | Item | Severity | Current Status | Blocker for Launch? |
|:---|:---|:---:|:---:|:---:|
| **Immediate Security Fixes** | None remaining (all 4 audited vulnerabilities patched) | High | **RESOLVED** | **NO** |
| **Required Core Functionality** | Student/Faculty/Admin modules, RBAC, Academic Calc, DB Health | Critical | **100% COMPLETE** | **NO** |
| **Optional Enhancement** | Live Online Payment Gateway (Razorpay / Stripe) | Low-Medium | Documented Roadmap | **NO** |
| **Optional Enhancement** | External SMS & Email Gateway (Twilio / SendGrid) | Low-Medium | In-App Functional | **NO** |
| **Optional Enhancement** | Granular Internal Marks Breakdown (UT1, UT2, Mid-sem) | Low | Aggregate Managed | **NO** |
| **Optional Enhancement** | Self-Service ATKT Re-exam Student Application Form | Low | Admin Managed | **NO** |
| **Future Scope** | Multi-Department Scaling beyond FCAIT | Low | Single Dept Ready | **NO** |

---

## 2. Immediate Security Fixes Status
All confirmed security defects identified during this audit have been remediated in code and validated with passing automated tests:

1. **Router Path Traversal (`api/index.php`):** Resolved. Strict loop normalization and blocking of sensitive file extensions (`.env*`, `.sql`, `.json`, `.yaml`, `.md`, `scripts/`, `config/`).
2. **Leadership Management CSRF (`admin/leadership.php`):** Resolved. Dean and Coordinator assignments now require valid CSRF tokens.
3. **Academic Promotion CSRF & Server-Side ATKT Verification (`admin/process_promotion.php` & `promote_students.php`):** Resolved. Added CSRF token protection and enforced server-side `backlog <= 3` validation.
4. **Information Disclosure in Catch Blocks:** Resolved. Raw database exception messages are no longer displayed to end-users in production mode.

---

## 3. Optional Enhancements & Non-Blocking Roadmap
The following items represent prospective enhancements that can be scheduled for future development phases:

### A. Online Payment Gateway (Milestone 1)
- **Current State:** Fee payments are accurately tracked in the database and recorded manually by the finance administrator (Cash, UPI, Cheque, Bank Transfer). Printable receipts are generated.
- **Future Enhancement:** Integrate Razorpay or Stripe Hosted Modal to allow direct student online credit card and UPI transactions.

### B. Out-of-Band SMS / Email Notifications (Milestone 2)
- **Current State:** Alerts (low attendance, published marks, assignments) are dispatched in-app via the `notifications` table and rendered in user navbar alerts.
- **Future Enhancement:** Wire an asynchronous queue with PHPMailer (SMTP/SendGrid) or Twilio/Fast2SMS to deliver SMS and email copies to student mobile numbers.

### C. Granular Assessment Sub-Components (Milestone 3)
- **Current State:** The system tracks `internal_marks` and `external_marks` as weighted aggregates according to university syllabus credits.
- **Future Enhancement:** Subdivide internal marks into discrete columns for Unit Test 1, Unit Test 2, Mid-Sem Exam, and Presentation weightages.

### D. Student Self-Service ATKT Registration (Milestone 4)
- **Current State:** Students with backlogs view their ATKT counts and promotion status on their dashboard and result cards. Promotion is gated by the university admin.
- **Future Enhancement:** Provide a self-service student screen to register for backlog re-examinations and pay exam fees online once Milestone 1 is deployed.

---

## 4. Deployment Blockers
**Zero (0) deployment blockers exist.** The application runs cleanly on both local multi-port configurations and live Vercel production serverless environments.
