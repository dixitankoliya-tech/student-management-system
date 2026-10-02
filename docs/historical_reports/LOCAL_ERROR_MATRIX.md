# LOCAL ERROR MATRIX

| Component | Expected State | Actual State (Pre-Fix) | Error Encountered | Root Cause |
| :--- | :--- | :--- | :--- | :--- |
| **MySQL Service** | Running | Stopped | PDOException: Could not connect to database | `mysqld.exe` process was terminated, closing port 3306. |
| **Admin Panel (Port 8000)** | Running | Offline | Connection Refused (ERR_CONNECTION_REFUSED) | PHP built-in server was not running. |
| **Student Panel (Port 9000)** | Running | Offline | Connection Refused (ERR_CONNECTION_REFUSED) | PHP built-in server was not running. |
| **Faculty Panel (Port 9100)** | Running | Offline | Connection Refused (ERR_CONNECTION_REFUSED) | PHP built-in server was not running. |
| **Session Initialization** | Success | Failure | Fatal Error on login attempts | Cannot execute `CREATE TABLE sessions` without a valid MySQL connection. |
| **Environment Fallback** | Use `.env` root | Used `.env` root | None (Working as expected) | The Vercel `.env.local` did not override XAMPP credentials. |

## Conclusion
There were no syntax errors, framework conflicts, or Vercel production overrides interfering with the local XAMPP environment. The entire cascade of errors was caused strictly by the XAMPP background services (MySQL and PHP CLI) being taken offline.
