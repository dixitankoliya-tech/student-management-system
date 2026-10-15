# GLS UNIVERSITY FCAIT STUDENT MANAGEMENT SYSTEM
## Comprehensive System Security & Privacy Audit Report

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date of Audit:** October 2026  
**Auditor Classification:** Senior Application Security & Architecture Audit  

---

## 1. Executive Security Assessment

A complete source-code, database, session, and runtime security audit was performed covering Authentication, Session Management, Authorization & IDOR, SQL Injection, Cross-Site Scripting (XSS), Cross-Site Request Forgery (CSRF), File Upload Security, Information Disclosure, and Production Secrets.

All genuine security findings were remediated directly in source code:
1. **Router Path Traversal Filter (`api/index.php`):** Nested path traversal sequences (such as `....//....//config/database.php`) were patched with normalized path parsing, realpath boundary enforcement, and explicit blocking of all sensitive files (`.env*`, `.sql`, `.json`, `.yaml`, `.md`, `scripts/`, `config/`).
2. **CSRF Protection on Leadership Roles (`admin/leadership.php`):** Dean and Course Coordinator assignments now enforce strict CSRF tokens via `requireValidCsrf()` and hidden token inputs (`csrfField()`).
3. **CSRF & Server-Side ATKT Enforcement on Academic Promotion (`admin/process_promotion.php` & `admin/promote_students.php`):** Promotion requests enforce CSRF tokens and server-side SQL gating (`backlog_count <= 3`), preventing unauthorized promotion of students with excessive backlogs.
4. **Information Disclosure in Catch Blocks:** Raw `$e->getMessage()` exposures were sanitized across all student, faculty, and administrative forms to prevent leaking internal SQL schemas or stack traces when `APP_DEBUG` is disabled.

---

## 2. Authentication & Session Architecture Audit

### A. Password Hashing & Authentication
- **Status:** PASS
- **Implementation:** Employs PHP native `password_hash($password, PASSWORD_DEFAULT)` utilizing Bcrypt. Password verification uses `password_verify()`.
- **Username Enumeration Defense:** Failed login attempts consistently return the generic string `"Invalid credentials."` regardless of whether the identifier exists, is inactive, or has a mismatched role.
- **Session Fixation Defense:** `session_regenerate_id(true)` is strictly invoked upon successful authentication in all three portals (`loginUser()`).
- **Session Invalidation:** `logoutUser()` unsets `$_SESSION`, destroys the cookie with past expiration timestamps, and calls `session_destroy()`.

### B. Session Isolation & Cookie Security Flags
- **Status:** PASS
- **Dedicated Cookie Names:**
  - Admin Portal: `SMS_ADMIN_SESSION`
  - Faculty Portal: `SMS_FACULTY_SESSION`
  - Student Portal: `SMS_STUDENT_SESSION`
- **Security Attributes:**
  - `HttpOnly`: Enforced (`ini_set('session.cookie_httponly', 1)`).
  - `SameSite`: Configured to `Lax` to prevent cross-site request leakage.
  - `Secure`: Dynamically set to `1` when request is served over HTTPS or via Vercel proxy headers (`HTTP_X_FORWARDED_PROTO === 'https'`).
  - `Strict Mode`: Enabled (`ini_set('session.use_strict_mode', 1)`).

---

## 3. Authorization & Role-Based Access Control (RBAC)

### A. Role Boundary Enforcement
- **Admin:** Governed by `hasRole('admin')` in `includes/admin_auth.php`.
- **Dean:** Scoped in `faculty/dept_reports.php` via `isDean($pdo, $faculty_id)`. Non-deans are terminated with `403 Access Denied`.
- **Course Coordinator:** Governed by `isCourseCoordinator($pdo, $faculty_id)`. Queries in `course_reports.php`, `course_faculty.php`, and `course_students.php` are strictly bounded by `getCoordinatorCourseId($pdo, $faculty_id)`.
- **Faculty:** Strictly verified against `faculty_subjects` mappings. Faculty cannot enter marks, view students, or record attendance for classes they do not teach (`faculty/marks.php`, `faculty/attendance.php`).
- **Student:** All sensitive queries explicitly bind to `$_SESSION['student_id']` or `$_SESSION['user_id']`. Directly modifying query parameters (`?id=X`) in `print_receipt.php` or `results.php` fails server-side validation.

---

## 4. SQL Injection, XSS & Input Sanitization

- **Status:** PASS
- **Prepared Statements:** Universally implemented using PDO with bound parameters (`$stmt->execute([...])`).
- **Dynamic Identifiers:** Allowlisted strictly (e.g. pagination limit allowlisted to `[20, 50, 100]`).
- **XSS Escaping:** All output dynamic values are escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

## 5. File Uploads & BLOB Storage Security

- **Status:** PASS
- **Ephemeral Filesystem Handling:** Because Vercel has read-only/ephemeral storage, files are securely stored as binary data in the `file_storage` database table via `store_file_in_db()`.
- **File Type & MIME Validation:** Multi-tier validation checking both extension allowlists and verified libmagic MIME types (`finfo_file()`).
- **Executable Upload Prevention:** Executable extensions (`.php`, `.phtml`, `.exe`, `.sh`, `.bat`) are blocked. Generated filenames (`time() . '_' . sanitized_name`) eliminate null-byte and path-traversal attacks.
- **Authorization on Download:** Protected by `includes/serve_file.php`. Students cannot download submissions belonging to other students.

---

## 6. Production Credentials & Secret Handling Checklist

- [x] **No Hardcoded Passwords in Reports:** Password strings, session tokens, and database credentials are excluded from reports.
- [x] **Environment Separation:** Database credentials (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT`) are loaded exclusively from server environment variables via `config/database.php`.
- [x] **Git Isolation:** `.env*`, `.sql`, scratch scripts, and temporary logs are excluded via `.gitignore`.
- [x] **Recommended Credential Rotation:** Production database credentials in Vercel project settings should be rotated periodically according to university IT policy.
