# PHASE 1: PROJECT DISCOVERY AND BASELINE REPORT

**Project Name:** GLS University FCAIT Student Management System  
**Working Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date of Audit:** 2026-10-11  
**Audit Phase:** Phase 1 — Discovery & Baseline  

---

## 1. Initial Git & Working Tree Baseline
- **Current Branch:** `main` (tracked to `origin/main`)
- **Remote Repository:** `https://github.com/dixitankoliya-tech/student-management-system.git`
- **Initial Commit Hash:** `e09dbc8` (`final production QA fixes`)
- **Preceding Commit:** `a657f8d` (`Fix CSRF lambda freezing issue and secure cookies detection`)
- **Working Tree State:** Clean, with `.gitignore` updated to exclude temporary scratch scripts (`scratch_*.php`, `scratch/`, `php_errors.log`).
- **Untracked Artifacts Identified:**
  - `LOGIN_PRODUCTION_FIX_REPORT.md` (historical fix notes)
  - `DEEP_ANALYSIS_AND_ROADMAP.md` (prior roadmap document)

---

## 2. Architecture & Runtime Configuration
- **Runtime Environment:** Core PHP 8.3 (local CLI: `PHP 8.3.33`) / Vercel Serverless Function (`vercel-php@0.9.0`).
- **Dependency Management:** Zero external Composer runtime dependencies required; pure native PHP with PDO, OpenSSL, and cURL.
- **Entry Points & Routing:**
  - **Vercel Gateway:** Configured via `vercel.json` to route non-static requests (`/(.*)`) to `api/index.php`.
  - **Single-Lambda Router (`api/index.php`):** Resolves requests based on subdomains or the `PORTAL` environment variable (`admin`, `faculty`, `student`). Sets `SCRIPT_FILENAME`, shifts working directory via `chdir()`, and invokes the respective endpoint.
  - **Static Assets:** `vercel.json` provides direct pass-through for `/assets/`, `/admin/assets/`, `/student/assets/`, `/faculty/assets/`, and `/uploads/`.
  - **Local Development Entry Point:** `index.php` at repository root acts as a central role dispatcher directing traffic to `/admin/login.php`, `/faculty/login.php`, and `/student/login.php`.

---

## 3. Database & Persistence Layer
- **Engine:** MySQL 8.0-compatible TiDB Cloud Serverless database.
- **Connection Architecture (`config/database.php`):**
  - PDO driver configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, and a 15-second connection timeout.
  - TLS enforced automatically for all remote non-localhost hosts using `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false` with CA bundle autodetect (`C:/xampp/...` or `/etc/ssl/certs/...`).
  - Production safety: Throws fatal die message without dumping database stack traces or credentials if connection fails.
- **Relational Schema:** Defined in `scripts/schema.sql` and `database/production_schema.sql`, featuring primary entities:
  - Institutions: `departments`, `courses`, `semesters_master`, `academic_years`, `subjects`
  - Stakeholders: `users`, `students`, `faculty`, `department_heads`, `course_coordinators`
  - Relational Records: `student_academic_enrollments`, `faculty_subjects`, `attendance`, `student_marks`, `examinations`, `exam_schedules`, `assignments`, `assignment_submissions`, `fee_payments`

---

## 4. Authentication & Session Architecture
- **Password Hashing:** Standard PHP `password_hash()` with `PASSWORD_DEFAULT` (Bcrypt) and `password_verify()`.
- **Session Isolation:** Three distinct session namespaces:
  - Admin: `SMS_ADMIN_SESSION` (in `includes/admin_auth.php`)
  - Faculty: `SMS_FACULTY_SESSION` (in `includes/faculty_auth.php`)
  - Student: `SMS_STUDENT_SESSION` (in `includes/student_auth.php`)
- **Database Session Storage (`includes/session_handler.php`):** Database-backed session handler using `user_sessions` table ensures stateless compatibility across multiple serverless Lambda invocations.

---

## 5. Existing Automated Tests & Audit Scripts
Located in `scripts/`:
- `scripts/phase3_db_audit.php`: Automated relational integrity & orphan record checker.
- `scripts/phase3_code_audit.php`: Static analysis scanner for hardcoded values and missing link targets.
- `scripts/audit_checker.php`: Schema validation runner.
- `scripts/db_validate.php`: Table count and connectivity verifier.

---

## 6. Confirmed Baseline Security & Code Issues
1. **Router Path Traversal Filter (`api/index.php` line 16):**
   - The sanitizer uses a single-pass `str_replace(['../', '..\\'], '', $uri)`. Nested sequences like `....//` can circumvent this to achieve directory traversal.
2. **Exposure of Sensitive Root Scripts:**
   - `migrate_to_tidb.php` and root diagnostic scripts reside in the project root. While `.env` is blocked, any unauthenticated web request routed to `/migrate_to_tidb.php` could trigger execution.
3. **Sensitive Config Direct Access:**
   - In `api/index.php`, the condition `strpos($path, 'config/') === 0 && !str_ends_with($path, '.php')` leaves `.php` files under `config/` (like `config/database.php`) open to direct web invocation.
4. **Scattered Historical Reports:**
   - 17 markdown report files clutter the project root, creating confusion regarding the active documentation source of truth.
5. **Plaintext Credential References in Prior Reports:**
   - Historical audit documents contained raw test passwords. These files must be sanitized or isolated, and an explicit remediation checklist established.

---

## 7. Cleanup & Consolidation Candidates
- **Temporary debug scripts:** `scratch_check_login.php`, `scratch_check_users.php`, `scratch_describe_students.php`, `scratch_describe_users.php`, `scratch_fix_passwords.php`, `scratch_fix_student_password.php`, `scratch_get_student.php`.
- **Obsolete duplicate documentation:** Multiple overlapping phase reports (`PHASE3_*.md`, `PHASE4_*.md`, `PHASE5_*.md`) to be archived cleanly into `docs/historical_reports/`.
- **Backup directories:** `LOCAL_RECOVERY_BACKUP/`, `LOCAL_RECOVERY_DATABASE_BACKUP/` to remain safely git-ignored.
