# FULL PROJECT 0 → LIVE PRODUCTION AUDIT, FIX & DEPLOYMENT

## 1. UNDERSTAND THE ENTIRE PROJECT

**Architecture & Stack:**
*   **Framework/Language:** Core PHP (Vanilla PHP 8.2 compatible).
*   **Frontend/Backend:** Frontend is mostly HTML with Bootstrap and FontAwesome, backend is PHP.
*   **Database:** MySQL / MariaDB (using PDO).
*   **Authentication/Session:** Custom PHP Session-based authentication.
*   **APIs:** Vercel serverless routing logic maps paths to `api/index.php`.
*   **Environment Variables:** Driven by `.env` (locally) and Deployment environment variables (Vercel/Render).
*   **Deployment Platforms:** Configured for Vercel (Serverless PHP via `vercel-php`) and Render/Docker (via `render.yaml` and `Dockerfile`).
*   **User Roles:** `admin`, `faculty`, `student`.

**Database Architecture:**
*   `users`, `students`, `faculty`
*   `academic_performance`, `achievements`, `activity_logs`, `announcements`
*   `assignments`, `assignment_submissions`, `attendance`
*   `examinations`, `faculty_subjects`, `marks`, `student_marks`
*   `notifications`, `profile_change_requests`, `study_materials`, `subjects`
*   `system_settings`, `timetables`

## 2. SEARCH THE ENTIRE PROJECT FOR LOCALHOST

**Findings:**
*   `config/database.php`: Uses `localhost` as a fallback when `DB_HOST` is not set. This is a standard default for local setups and does not override production env vars.
*   `render.yaml`: Hardcoded `localhost` in `DB_HOST` env var, which the file notes to "CHANGE THIS IN DASHBOARD".
*   `index.php`: Previously had absolute links (`/admin/login.php`) that would break on local subdirectories and non-root production environments. (FIXED in a prior step to use relative paths).

No other destructive hardcoded `localhost`, `127.0.0.1`, or port dependencies exist in the main application logic, API calls, or frontend.

## 3. PRODUCTION URL ARCHITECTURE

The project leverages `api/index.php` for Vercel to dynamically route based on subdomains (`admin.`, `student.`, `faculty.`). If accessed via `admin.example.com`, it correctly prefixes internal requests with `admin/`. 
This allows for robust routing in production without depending on hardcoded ports.

## 4. ENVIRONMENT VARIABLES

*   `.env.example` exists and correctly documents the required variables without exposing secrets.
*   `config/database.php` securely loads `getenv()` and falls back gracefully. 
*   **Action Required**: When deploying to Vercel/Render, ensure `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, and `APP_ENV=production` are securely set in their respective dashboards.

## 5. DATABASE PRODUCTION AUDIT

*   The schema correctly uses `InnoDB` with proper foreign keys (`ON DELETE CASCADE` / `ON DELETE SET NULL`) ensuring referential integrity.
*   Required indexing is mostly present (e.g., `student_id`, `faculty_id`, `subject_id`).
*   Database connection uses PDO with `ERRMODE_EXCEPTION` and securely logs errors instead of dumping traces to the screen (verified in `config/database.php`).

## 6. ONGOING AUDIT

*   Next steps include verifying authentication flows, form submissions across all 3 roles, and ensuring cross-panel data flow (Admin -> Faculty -> Student).
