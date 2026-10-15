# GLS UNIVERSITY FCAIT STUDENT MANAGEMENT SYSTEM
## Safe Project Cleanup & Retention Manifest

**Project:** GLS University FCAIT Student Management System  
**Directory:** `C:\Users\dixit\OneDrive\Desktop\COLLEGE_PROJECT\PHP_WEBSITE\student-management-system`  
**Date of Audit:** October 2026  

---

## 1. Summary of Repository Sanitation & Preservation

The codebase was audited and organized to ensure clean maintainability, clear separation of production files from maintenance scripts, and zero risk to live deployments:

1. **Temporary Scratch Scripts Removed:** Diagnostic scratch scripts containing test database passwords or ad-hoc queries were safely removed.
2. **Historical Reports Consolidated:** Historical audit iterations and interim reports were archived in `docs/historical_reports/`.
3. **Pre-Migration Safety Assets Maintained:** Pre-migration snapshot (`scripts/pre_migration_backup.json`) and tested rollback script (`scripts/rollback_data_migration.php`) are preserved.
4. **Active Deployment & Codebase Preserved:** All 163 active PHP files across `admin/`, `faculty/`, `student/`, `includes/`, `config/`, and `api/` are 100% preserved.

---

## 2. Maintained Maintenance & Verification Utilities (`scripts/`)

| File Name | Purpose & Retention Rationale |
|:---|:---|
| `scripts/comprehensive_test_suite.php` | Automated 35-point regression, security, and linting test suite. |
| `scripts/probe_live_deployments.php` | Production Vercel live health probe. |
| `scripts/pre_migration_backup.json` | Complete snapshot of original student, faculty, and user records. |
| `scripts/rollback_data_migration.php` | Verified rollback procedure to restore pre-upgrade state if required. |
| `scripts/upgrade_system_realistic_data.php` | In-place entity upgrade and lifecycle generation script. |
| `scripts/sync_marks_performance.php` | Marks and academic performance synchronization utility. |
| `scripts/check_counts.php` | Real-time database table count inspector across all 38 tables. |
| `scripts/start-local-portals.ps1` | Multi-portal local server launcher (ports 8000, 9000, 9100). |
| `scripts/stop-local-portals.ps1` | Local development server shutdown utility. |

---

## 3. Retained & Preserved Application Modules

- **Router & Firewall:** `api/index.php` (Path traversal prevention, extension allowlisting).
- **Core Configuration:** `config/database.php` (TiDB Cloud TLS PDO connection).
- **Administrative Portal (`admin/`):** 50 production PHP scripts covering Students, Faculty, Courses, Subjects, Marks, Promotions, Timetables, Fees, and Leadership.
- **Faculty Portal (`faculty/`):** 27 production PHP scripts covering Assigned Rosters, Attendance, Grading, Assignments, Study Materials, and Dean/Coordinator Analytics.
- **Student Portal (`student/`):** 22 production PHP scripts covering Student Profile, Results, Attendance, Fee Receipts, Timetable, Hall Tickets, and Coursework.
- **Shared Helpers (`includes/`):** 20 shared components (`academic_calculator.php`, `faculty_role_helper.php`, `session_handler.php`, `file_storage.php`, `serve_file.php`, etc.).
- **Deployment Manifests:** `vercel.json`, `render.yaml`, `Dockerfile`.

---

## 4. `.gitignore` Configuration

```gitignore
.env
.env.*
*.log
logs/
cache/
tmp/
temp/
.DS_Store
Thumbs.db
.vscode/
.idea/
uploads/
.vercel
# Local Debug & Recovery
LOCAL_RECOVERY_BACKUP/
LOCAL_RECOVERY_DATABASE_BACKUP/
cookies.txt
scratch/
scratch_*.php
php_errors.log
```
