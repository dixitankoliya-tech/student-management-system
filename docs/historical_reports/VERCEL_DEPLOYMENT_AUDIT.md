# VERCEL DEPLOYMENT AUDIT

## Current Architecture
- **Framework**: Native PHP 8+ (No Framework)
- **Database**: MySQL (via PDO)
- **Routing**: Folder-based routing (`/admin`, `/faculty`, `/student`) with a redirector in `index.php`.
- **Sessions**: Native PHP file-based sessions (`session_start()`).
- **File Uploads**: Local file system uploads (`move_uploaded_file()`) stored in `/uploads/`.
- **Environment**: Reads from `.env` using a custom parser in `config/database.php` and `getenv()`.

## Vercel Compatibility & Deployment Risks
Vercel is primarily a serverless environment optimized for static assets and stateless functions. Deploying a traditional stateful PHP application presents several architectural challenges:

1. **Read-Only Filesystem**: Vercel's serverless environment has a read-only filesystem (except for `/tmp`, which is ephemeral). Any local file uploads (`/uploads/`) will fail or be lost immediately.
2. **Stateless Serverless Functions**: Native PHP sessions store session data in local temporary files. On Vercel, subsequent requests might be routed to different serverless instances, resulting in users getting randomly logged out (Session Loss). 
3. **Database Connectivity**: Vercel does not host MySQL databases. The application requires an external, publicly accessible MySQL database (e.g., Aiven, PlanetScale, or a VPS). Serverless functions also rapidly open/close connections, which can exhaust database connection limits if pooling is not used.
4. **Routing & Directory Structure**: The local setup relies on PHP's built-in development server running on three different ports (8000, 9000, 9100). Vercel routes everything through a single domain. The `index.php` redirector routes based on port, which will not work on Vercel. 

## Required Changes for Vercel

### 1. Database Requirements
- **Action**: Provision an external MySQL database.
- **Action**: Modify `config/database.php` to ensure it securely reads `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, and `DB_NAME` from Vercel's securely injected environment variables rather than relying solely on the local `.env` parser.
- **Action**: Create `database/production_schema.sql` and `database/production_seed.sql` for setting up the external database.

### 2. Session Requirements (CRITICAL)
- **Action**: Move session persistence away from the local filesystem. 
- **Options**: 
  - Store sessions in the external MySQL database (requires a custom session handler via `session_set_save_handler`).
  - Use Redis (Upstash) for session storage.
- **Risk**: Without this, authentication for Admin, Student, and Faculty will break entirely.

### 3. File Storage Requirements
- **Action**: Replace `move_uploaded_file()` with an object storage solution (e.g., Vercel Blob or AWS S3) or a database-backed storage for small files.
- **Risk**: Assignments and study materials will not be saved if left as local filesystem uploads.

### 4. Routing & Configuration
- **Action**: Create a `vercel.json` file to define the PHP builder (e.g., `vercel-php` community runtime) or Dockerfile (if using a Vercel-supported container approach like FrankenPHP) and explicitly route requests for `/admin`, `/faculty`, and `/student` so they resolve correctly without port numbers.
- **Action**: Update `index.php` to route based on URI or provide a unified landing page instead of relying on `$_SERVER['SERVER_PORT']`.

### 5. Localhost Cleanup
- **Action**: Remove hardcoded `http://localhost:8000` etc. (found in `tests/` and some documentation) and ensure all panel cross-links use relative paths (`/admin/`, `/student/`).
