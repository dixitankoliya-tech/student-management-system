# Student Management System 2.0
**GLS University**

## Description
**Status: READY FOR COLLEGE DEMONSTRATION (Phase 7 QA Completed)**

A comprehensive, secure, three-panel university academic management system built with PHP and MySQL. It digitizes the academic workflow by facilitating seamless communication, secure grading, and data management between Administration, Faculty, and Students.

## Architecture
The system employs a strict Three-Panel Architecture sharing a single, normalized MySQL Database.

1. **Admin Panel** (`localhost:8000`)
2. **Faculty Portal** (`localhost:9100`)
3. **Student Portal** (`localhost:9000`)

## Technologies Used
- **Backend:** PHP 8+
- **Database:** MySQL / MariaDB (via PDO)
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
- **Icons:** FontAwesome

## Key Features
- **Auto-Generated Enrollments:** Automatic sequencing (`YYYY + Course Code + Sequence`) without manual entry.
- **SGPA & CGPA Engine:** Centralized grade point calculator based on university credits.
- **Academic Workflows:** Full support for Attendance tracking, Study Material distribution, and Assignment submissions with strict deadlines.
- **Result Publishing Pipeline:** Faculty draft marks -> Admin review -> Admin publish (Locked) -> Student visibility.

## Security Implementations
- **Session Isolation:** Completely separated sessions to prevent role escalation.
- **CSRF Protection:** Secure tokens required for all state-changing `POST` requests.
- **SQL Injection Prevention:** 100% usage of PDO Prepared Statements.
- **IDOR Prevention:** Server-side ownership validation based on session ID and faculty mappings rather than trusting client-side `?id=` parameters.
- **Password Hashing:** Utilizing secure `password_hash()` encryption.

## Local Setup
1. Clone the repository into your local web environment (e.g., XAMPP/htdocs).
2. Import the database schema from `database/schema_dump_for_audit_utf8.sql`.
3. Configure `config/database.php` with your local MySQL credentials.
4. Launch the three isolated servers:
   - `php -S localhost:8000 -t admin`
   - `php -S localhost:9100 -t faculty`
   - `php -S localhost:9000 -t student`

## Demonstration Accounts
Please refer to `DEMO_ACCOUNTS.md` for information regarding presentation accounts. *(Note: Confidential passwords must be obtained locally).*
