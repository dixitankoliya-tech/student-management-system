<?php
/**
 * SMS 2.0: Authentication & Security Middleware for Faculty
 * 
 * Include this file at the top of every protected page (after database.php).
 * Provides: session management, auth checking, CSRF token generation/validation,
 * and role-based access control helpers.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 7200); // 2 hours
    // Enable secure cookies when served over HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_name('SMS_FACULTY_SESSION');
    session_start();
}

// ===================================================================
// AUTHENTICATION FUNCTIONS
// ===================================================================

/**
 * Check if the current user is authenticated.
 * If not, redirect to login page.
 */
function requireAuth() {
    if (!isLoggedIn()) {
        // Store the intended destination for post-login redirect (validated)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        // Only allow relative paths starting with / (prevent open redirect)
        if (!empty($requestUri) && $requestUri[0] === '/' && strpos($requestUri, '//') !== 0) {
            $_SESSION['redirect_after_login'] = $requestUri;
        }
        header('Location: login.php');
        exit();
    }

    if (!hasRole('faculty')) {
        // Force logout and redirect
        logoutUser();
        header('Location: login.php?error=' . urlencode('Access Denied.'));
        exit();
    }

    // Session timeout: expire after 2 hours of inactivity
    $sessionMaxLifetime = 7200; // 2 hours in seconds
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionMaxLifetime) {
        logoutUser();
        header('Location: login.php?error=' . urlencode('Session expired. Please log in again.'));
        exit();
    }
    
    // Force password change if required
    if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage !== 'change_password.php' && $currentPage !== 'logout.php') {
            header('Location: change_password.php');
            exit();
        }
    }
    // Update last activity time
    $_SESSION['login_time'] = time();
}

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get the currently authenticated user's data.
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'faculty',
    ];
}

/**
 * Check if the current user has a specific role.
 */
function hasRole($role) {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === $role;
}

/**
 * Require a specific role to access the page. Redirects with error if insufficient.
 */
function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        header('Location: dashboard.php?error=' . urlencode('You do not have permission to access that page.'));
        exit();
    }
}

/**
 * Authenticate a user with username/email and password.
 * Returns user row on success, false on failure.
 */
function authenticateUser($pdo, $login, $password) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, email, password_hash, full_name, role, is_active, faculty_id, must_change_password 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Authentication Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a session for the authenticated user.
 */
function loginUser($pdo, $user) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['faculty_string_id'] = $user['faculty_id']; // Integer FK to faculty.id
    $_SESSION['must_change_password'] = $user['must_change_password'] ?? 0;
    $_SESSION['login_time'] = time();
    
    // Look up the internal integer ID from the faculty table and store it
    // users.faculty_id stores the integer PK (faculty.id), NOT the varchar faculty_id code
    try {
        if (!empty($user['faculty_id'])) {
            // users.faculty_id IS faculty.id, so we can use it directly
            $_SESSION['faculty_db_id'] = (int)$user['faculty_id'];
        }
    } catch (PDOException $e) {
        // Handle silently
    }
}

/**
 * Destroy the current session and log the user out.
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// ===================================================================
// CSRF PROTECTION FUNCTIONS
// ===================================================================

/**
 * Generate a CSRF token and store it in the session.
 * Returns the token string.
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field for use in forms.
 */
function csrfField() {
    $token = generateCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate the submitted CSRF token against the session token.
 * Returns true if valid, false otherwise.
 */
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require a valid CSRF token on POST requests. Dies with 403 on failure.
 */
function requireValidCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(403);
            die("CSRF validation failed. Please refresh the page and try again.");
        }
    }
}

// ===================================================================
// SECURITY HEADERS
// ===================================================================

/**
 * Send recommended security headers.
 */
function sendSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com data:;");
}

// Always send security headers when this file is included
sendSecurityHeaders();

