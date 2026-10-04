<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');

// Load environment variables from .env file if it exists
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Database configuration (supports both local conventions and Vercel conventions)
define('DB_HOST', getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost');
define('DB_PORT', getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306');
$db_user = getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : (getenv('DB_USER') !== false ? getenv('DB_USER') : 'root');
define('DB_USER', $db_user);
$db_pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_PASS', $db_pass);
$db_name = getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : (getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'student_management');
define('DB_NAME', $db_name);


// Application configuration
define('ENROLLMENT_PREFIX', getenv('ENROLLMENT_PREFIX') !== false ? getenv('ENROLLMENT_PREFIX') : '20240161301');
define('APP_ENV', getenv('APP_ENV') !== false ? getenv('APP_ENV') : 'production');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN));

// Attempt to connect to MySQL database using PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // Log error securely instead of exposing credentials/paths
    error_log("Database Connection Error: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Please contact the administrator.");
}

/**
 * Log user activity
 * 
 * @param PDO $pdo The PDO instance
 * @param string $action The action performed (e.g. 'CREATE', 'UPDATE', 'DELETE')
 * @param int|null $student_id The ID of the student affected
 * @param string $description A human readable description of the action
 */
function logActivity($pdo, $action, $student_id, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (action, student_id, description) VALUES (?, ?, ?)");
        $stmt->execute([$action, $student_id, $description]);
    } catch(PDOException $e) {
        // Just log the error, don't break the main application flow
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    // Also write to audit_logs for comprehensive audit trail
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $target = $student_id ? "student:$student_id" : 'system';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $target, $description]);
    } catch(PDOException $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}


/**
 * Send Notification Helper
 * Maps internal role IDs to user_id for notifications.
 */
function send_notification($pdo, $target_role, $target_id, $title, $message, $type, $link = null) {
    try {
        $user_id = null;
        if ($target_role === 'student') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
            $stmt->execute([$target_id]);
            $user_id = $stmt->fetchColumn();
        } elseif ($target_role === 'faculty') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE faculty_id = ?");
            $stmt->execute([$target_id]);
            $user_id = $stmt->fetchColumn();
        } elseif ($target_role === 'admin') {
            // Find all admins
            $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
            $admins = $stmt->fetchAll();
            foreach ($admins as $admin) {
                $insert = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$admin['id'], $title, $message, $type, $link]);
            }
            return;
        } elseif ($target_role === 'user') {
            $user_id = $target_id; // Direct user_id
        }

        if ($user_id) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $message, $type, $link]);
        }
    } catch(PDOException $e) {
        error_log("Failed to send notification: " . $e->getMessage());
    }
}
