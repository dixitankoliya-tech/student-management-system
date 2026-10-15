# FINAL SECURITY AUDIT REPORT

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date of Audit:** 2026-10-11  
**Auditor Classification:** Senior Application Security & Architecture Audit  

---

## 1. Executive Security Assessment
A comprehensive source-code and runtime security audit was conducted covering Authentication, Session Management, Authorization/IDOR, SQL Injection, CSRF, File Uploads, Error Handling, and Production Secrets. 

Four genuine security defects were discovered and remediated in place:
1. **Router Path Traversal Filter (`api/index.php`):** Sanitizer was vulnerable to nested traversal sequences (e.g. `....//`); replaced with loop-based normalization, realpath boundary enforcement, and explicit blocking of `.env*`, `.sql`, `.json`, `.yaml`, `.md`, `scripts/`, and `config/`.
2. **Missing CSRF on Leadership Roles (`admin/leadership.php`):** Dean and Course Coordinator assignments lacked CSRF validation; secured with `requireValidCsrf()` and `csrfField()`.
3. **Missing CSRF & Server-Side ATKT Enforcement (`admin/process_promotion.php` & `admin/promote_students.php`):** Promotions were unverified against CSRF and relied solely on frontend disabled checkboxes for ATKT limits; secured with `requireValidCsrf()`, `csrfField()`, and strict server-side `backlog <= 3` SQL gating.
4. **Information Disclosure in Catch Blocks:** Raw `$e->getMessage()` was output in user banners across `student/submit_assignment.php`, `faculty/study_materials.php`, `faculty/marks.php`, `faculty/attendance.php`, `faculty/assignments.php`, and `admin/faculty.php`; updated to log securely and return sanitized messages when `APP_DEBUG` is false.

---

## 2. Authentication & Session Architecture Audit

### A. Password Hashing & Management
- **Status:** PASS
- **Implementation:** Employs PHP native `password_hash($password, PASSWORD_DEFAULT)` utilizing Bcrypt. Password verification uses `password_verify()`.
- **Username Enumeration Defense:** Failed login attempts consistently return the generic string `"Invalid credentials."` regardless of whether the identifier exists, is inactive, or has a mismatched role.
- **Session Fixation Defense:** `session_regenerate_id(true)` is strictly invoked upon successful authentication in all three portals (`loginUser()`).
- **Session Invalidation:** `logoutUser()` unsets `$_SESSION`, destroys the cookie with past expiration timestamps, and calls `session_destroy()`.

### B. Session Isolation & Cookie Flags
- **Status:** PASS
- **Dedicated Cookie Names:**
  - Admin: `SMS_ADMIN_SESSION`
  - Faculty: `SMS_FACULTY_SESSION`
  - Student: `SMS_STUDENT_SESSION`
- **Security Attributes:**
  - `HttpOnly`: Enforced (`ini_set('session.cookie_httponly', 1)`).
  - `SameSite`: Configured to `Lax` to prevent cross-site request leakage.
  - `Secure`: Dynamically set to `1` when request is served over HTTPS or via Vercel proxy headers (`HTTP_X_FORWARDED_PROTO === 'https'`).
  - `Strict Mode`: Enabled (`ini_set('session.use_strict_mode', 1)`).

---

## 3. Authorization & IDOR Analysis

### A. Role Boundary Enforcement
- **Admin:** Governed by `hasRole('admin')` in `includes/admin_auth.php`.
- **Dean:** Scoped in `faculty/dept_reports.php` via `isDean($pdo, $faculty_id)`. Non-deans are terminated with `403 Access Denied`.
- **Course Coordinator:** Governed by `isCourseCoordinator($pdo, $faculty_id)`. Queries in `course_reports.php`, `course_faculty.php`, and `course_students.php` are strictly bounded by `getCoordinatorCourseId($pdo, $faculty_id)`.
- **Faculty:** Strictly verified against `faculty_subjects` mappings. Faculty cannot enter marks, view students, or record attendance for classes they do not teach (`faculty/marks.php`, `faculty/attendance.php`).
- **Student:** All sensitive queries explicitly bind to `$_SESSION['student_id']` or `$_SESSION['user_id']`. Directly modifying query parameters (`?id=X`) in `print_receipt.php` or `results.php` fails server-side validation.

---

## 4. SQL Injection & Input Sanitization
- **Status:** PASS
- **Prepared Statements:** Universally implemented using PDO with bound parameters (`$stmt->execute([...])`).
- **Dynamic Identifiers:** Allowlisted strictly (e.g. pagination limit allowlisted to `[20, 50, 100]`).
- **XSS Escaping:** All output dynamic values are escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

## 5. File Uploads & BLOB Storage
- **Status:** PASS
- **Ephemeral Filesystem Handling:** Because Vercel has read-only/ephemeral storage, files are securely stored as binary data in the `file_storage` database table via `store_file_in_db()`.
- **File Type & MIME Validation:** Multi-tier validation checking both extension allowlists and verified libmagic MIME types (`finfo_file()`).
- **Executable Upload Prevention:** Executable extensions (`.php`, `.phtml`, `.exe`, `.sh`, `.bat`) are blocked. Generated filenames (`time() . '_' . sanitized_name`) eliminate null-byte and path-traversal attacks.
- **Authorization on Download:** Protected by `includes/serve_file.php`. Students cannot download submissions belonging to other students.

---

## 6. Production Credentials Remediation Checklist
Historical reports contained example test passwords. To prevent security debt, follow this operational checklist:

- [x] **Code Sanitization:** Remove plaintext credential mentions from active source files.
- [x] **Git Isolation:** Ignore scratch files (`scratch_*.php`) and temporary audit scripts in `.gitignore`.
- [x] **Documentation Archival:** Consolidate historical markdown reports into `docs/historical_reports/`.
- [ ] **Operational Step 1 (Admin):** Prompt university admin to update default password for account `GLS_ADMIN` via Settings upon initial deployment.
- [ ] **Operational Step 2 (Faculty/Student):** Advise faculty and students to change default passwords on initial login (`change_password.php`).
- [ ] **Operational Step 3 (Environment Variables):** Ensure production database credentials in Vercel project settings (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) remain rotated periodically according to university IT policy.
