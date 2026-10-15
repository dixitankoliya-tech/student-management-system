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
while (strpos($uri, '../') !== false || strpos($uri, '..\\') !== false) {
    $uri = str_replace(['../', '..\\'], '', $uri);
}

// Strip the leading slash for local filesystem lookup
$path = ltrim($uri, '/');

// Detect portal from environment variable, server port, or hostname
$portalFromHost = getenv('PORTAL') ?: '';
if (!$portalFromHost) {
    $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '');
    $httpHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if ($serverPort === '8000' || str_starts_with($httpHost, 'admin.')) {
        $portalFromHost = 'admin';
    } elseif ($serverPort === '9000' || str_starts_with($httpHost, 'student.')) {
        $portalFromHost = 'student';
    } elseif ($serverPort === '9100' || str_starts_with($httpHost, 'faculty.')) {
        $portalFromHost = 'faculty';
    }
}

// If PORTAL is set AND the path doesn't already start with the portal prefix,
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

// Security: Do not allow serving sensitive configuration, migrations, or internal scripts
$basename = basename($path);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$blockedExtensions = ['env', 'sql', 'json', 'yaml', 'yml', 'md', 'lock', 'log'];
$blockedFiles = ['migrate_to_tidb.php', 'dockerfile', 'render.yaml', 'vercel.json', '.gitattributes', '.gitignore'];

if (str_starts_with($basename, '.env')
    || in_array(strtolower($basename), $blockedFiles)
    || in_array($ext, $blockedExtensions)
    || strpos($path, '.git') !== false
    || strpos($path, 'config/') === 0
    || strpos($path, 'scripts/') === 0
    || strpos($path, 'LOCAL_RECOVERY') !== false
    || str_starts_with($basename, 'scratch_')) {
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
