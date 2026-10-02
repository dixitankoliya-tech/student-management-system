<?php
/**
 * Vercel Serverless PHP Router
 * Routes all PHP requests through a single Lambda.
 * 
 * Supports:
 * - Path-based routing: /admin/*, /student/*, /faculty/*
 * - Hostname-based routing: admin.DOMAIN, student.DOMAIN, faculty.DOMAIN
 * - Database-backed sessions (via session_handler.php)
 */

// Determine the request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Prevent directory traversal attacks
$uri = str_replace(['../', '..\\'], '', $uri);

// Strip the leading slash for local filesystem lookup
$path = ltrim($uri, '/');

// Detect hostname for subdomain-based portal routing
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$host = strtolower(preg_replace('/:\d+$/', '', $host)); // remove port if present

// Determine portal from hostname
$portalFromHost = '';
if (preg_match('/^admin\./', $host)) {
    $portalFromHost = 'admin';
} elseif (preg_match('/^student\./', $host)) {
    $portalFromHost = 'student';
} elseif (preg_match('/^faculty\./', $host)) {
    $portalFromHost = 'faculty';
}

// If accessed via a subdomain AND the path doesn't already start with the portal prefix,
// prepend the portal directory so the correct files are served
if ($portalFromHost !== '') {
    // Check if path already starts with the portal name
    if (strpos($path, $portalFromHost . '/') !== 0 && $path !== $portalFromHost) {
        $path = $portalFromHost . '/' . $path;
    }
}

// Default to index.php if root is requested
if ($path === '' || $path === '/') {
    $path = 'index.php';
}

// Build the absolute filesystem path
$absolutePath = __DIR__ . '/../' . $path;

// If a directory is requested, try to load its index.php
if (is_dir($absolutePath)) {
    $path = rtrim($path, '/') . '/index.php';
    $absolutePath = __DIR__ . '/../' . $path;
}

// Security: Do not allow serving the api folder itself to prevent loops
if (strpos($path, 'api/') === 0) {
    http_response_code(403);
    die('403 Forbidden');
}

// Security: Do not allow serving .env, .git, or other sensitive files
$basename = basename($path);
if (in_array($basename, ['.env', '.env.local', '.env.example', '.gitignore'])
    || strpos($path, '.git/') === 0
    || strpos($path, 'config/') === 0 && !str_ends_with($path, '.php')) {
    http_response_code(403);
    die('403 Forbidden');
}

// Check if the requested file exists
if (file_exists($absolutePath) && is_file($absolutePath)) {
    
    $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    
    if ($ext === 'php') {
        // Mock the environment variables that scripts expect
        $_SERVER['SCRIPT_FILENAME'] = $absolutePath;
        $_SERVER['SCRIPT_NAME'] = '/' . $path;
        $_SERVER['PHP_SELF'] = '/' . $path;
        
        // IMPORTANT: Change the working directory so relative requires/includes work normally
        chdir(dirname($absolutePath));
        
        // Execute the PHP script
        require $absolutePath;
        
    } else {
        // Serve static files that somehow reached this handler
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
        ];
        $mime = $mimeTypes[$ext] ?? (function_exists('mime_content_type') ? mime_content_type($absolutePath) : 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolutePath));
        readfile($absolutePath);
    }
} else {
    // File not found
    http_response_code(404);
    // Try to use the project's 404 page
    $custom404 = __DIR__ . '/../404.php';
    if (file_exists($custom404)) {
        chdir(dirname($custom404));
        require $custom404;
    } else {
        echo "<h1>404 Not Found</h1>";
        echo "<p>The requested page could not be found.</p>";
    }
}
