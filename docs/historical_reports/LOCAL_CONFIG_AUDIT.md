# LOCAL CONFIGURATION AUDIT

## Environment Variables
- **Local Config**: The application uses `.env` for local configuration (`DB_HOST=localhost`, `DB_USER=root`, `DB_PASS=`, `DB_NAME=student_management`).
- **Production Config**: `.env.local` contains Vercel CLI tokens, and `config/database.php` supports fallback checks for `DB_USERNAME` (Vercel) vs `DB_USER` (Local).
- **Result**: NO CONFLICT. Local XAMPP continues to read from `.env` and successfully connects as `root` without a password.

## Server Routing (index.php)
The root `index.php` relies on `$_SERVER['SERVER_PORT']`:
- Port 8000 -> `/admin/`
- Port 9100 -> `/faculty/`
- Port 9000 -> `/student/`

This logic requires the servers to be launched *from the root directory*. However, the original `README.md` instructs users to run `php -S localhost:8000 -t admin` (which sets the document root to the subfolder). 
- **Result**: The setup is working exactly as historically configured.

## Session Configuration
- **Old Mechanism**: Native file-based PHP sessions.
- **New Mechanism**: Database-backed sessions via `includes/session_handler.php`.
- **Result**: Safe. Local XAMPP MySQL handles the sessions perfectly via the `sessions` table, improving local consistency while making Vercel deployment possible.
