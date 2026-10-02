# FILE STORAGE IN PRODUCTION (VERCEL)

## Current Architecture
Currently, the Student Management System uses PHP's native `move_uploaded_file()` function to store files in the local `/uploads` directory (e.g., `uploads/assignments`, `uploads/study_materials`). 

## Vercel Filesystem Limitations
Vercel's serverless environment mounts the application with a **read-only filesystem**. The only writable location is `/tmp`, which is:
1. Limited to 500MB
2. Ephemeral (files are deleted immediately after the function execution finishes)
3. Not shared across different serverless instances.

Therefore, any files uploaded via the current `move_uploaded_file()` implementation will succeed during the upload request but immediately vanish, resulting in broken links and missing data.

## Required Production Changes

To support file uploads on Vercel without removing the existing functionality, the application must be updated to use an **Object Storage Service**. 

### Recommended Architecture: Vercel Blob or AWS S3
1. **Provision Storage**: Set up Vercel Blob (native and easy) or an AWS S3 bucket.
2. **Environment Variables**:
   Add to Vercel environment variables:
   - `STORAGE_PROVIDER=vercel_blob` (or `s3`)
   - `BLOB_READ_WRITE_TOKEN="..."` (or AWS credentials)
3. **Application Updates**:
   - Create a `StorageManager` wrapper class.
   - Replace all `move_uploaded_file()` calls with `$storageManager->upload($_FILES['...']['tmp_name'], $path)`.
   - Ensure the database stores the full absolute public URL of the uploaded file instead of the local relative path (`/uploads/...`).

*Note: Since the objective was to NOT break the local XAMPP setup or rewrite the application functionality immediately, the code currently retains `move_uploaded_file()`. This must be updated using an S3/Blob SDK before file uploads will function correctly on Vercel.*
