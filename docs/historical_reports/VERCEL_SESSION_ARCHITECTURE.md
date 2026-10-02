# VERCEL SESSION ARCHITECTURE

## Issue with Default Sessions on Vercel
Vercel operates as a serverless platform. When a PHP application is deployed to Vercel (via a container or serverless function builder), the filesystem is ephemeral and isolated. 

By default, PHP's `session_start()` stores session state as files in the local `/tmp` directory. Because Vercel routes subsequent HTTP requests to potentially different serverless instances, the user's session file might not be present on the instance handling their next request. This leads to **Session Loss** (users being randomly logged out).

## The Solution: MySQL Database Sessions
To ensure production safety and 100% session persistence across all instances, the session architecture has been migrated from the local filesystem to the shared MySQL database.

### Implementation:
1. **`includes/session_handler.php`**: A custom class implementing PHP's `SessionHandlerInterface` was created. It maps `read`, `write`, `destroy`, and `gc` operations to a `sessions` table in the database using the existing PDO connection.
2. **Table Auto-creation**: The handler automatically runs `CREATE TABLE IF NOT EXISTS sessions` so that the database is instantly prepared for sessions on any environment without needing manual schema migrations.
3. **Integration**: The session handler is included directly in the auth middleware files (`admin_auth.php`, `faculty_auth.php`, and `student_auth.php`) right before `session_start()`.

### Roles & Security
This change is completely transparent to the rest of the application. The three isolated roles (Admin, Faculty, Student) continue to maintain their own isolated session namespaces (`SMS_ADMIN_SESSION`, `SMS_FACULTY_SESSION`, `SMS_STUDENT_SESSION`) securely.
