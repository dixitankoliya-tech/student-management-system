<?php
/**
 * SMS 2.0: Login Page
 * 
 * This page handles user authentication.
 * It is the ONLY page that does not require authentication.
 */
require_once '../config/database.php';
require_once '../includes/admin_auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$login_value = '';

// Rate limiting configuration
$max_attempts = 5;
$lockout_time = 900; // 15 minutes
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first_attempt' => time()];
}

// Reset lockout window if expired
if ((time() - $_SESSION['login_attempts']['first_attempt']) >= $lockout_time) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first_attempt' => time()];
}

$is_locked_out = ($_SESSION['login_attempts']['count'] >= $max_attempts);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    requireValidCsrf();
    
    if ($is_locked_out) {
        $remaining = ceil(($lockout_time - (time() - $_SESSION['login_attempts']['first_attempt'])) / 60);
        $error = "Too many failed attempts. Security lockout active. Try again in $remaining minute(s).";
    } else {
        $login = trim($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $login_value = htmlspecialchars($login);
        
        if (empty($login) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            $user = authenticateUser($pdo, $login, $password);
            
            if ($user) {
                if ($user['role'] !== 'admin') {
                    $error = "Invalid credentials.";
                    logActivity($pdo, 'LOGIN_FAILED', null, 'Unauthorized role attempt (Student) on Admin panel: ' . $login);
                } else {
                    // Reset failed attempts upon successful authentication
                    unset($_SESSION['login_attempts']);
                    
                    loginUser($pdo, $user);
                    logActivity($pdo, 'LOGIN', null, 'User "' . $user['username'] . '" logged in successfully');
                    
                    // Redirect to intended page or dashboard
                    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit();
                }
            } else {
                $_SESSION['login_attempts']['count']++;
                $remaining_attempts = $max_attempts - $_SESSION['login_attempts']['count'];
                
                if ($remaining_attempts <= 0) {
                    $error = "Too many failed attempts. Security lockout active for 15 minutes.";
                } else {
                    $error = "Invalid credentials.";
                }
                logActivity($pdo, 'LOGIN_FAILED', null, 'Failed login attempt for: ' . $login);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Student Management System</title>
    <meta name="description" content="Login to the Student Management System to manage academic records.">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --bg-gradient-start: #0f172a;
            --bg-gradient-end: #1e293b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background Particles */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.06) 0%, transparent 50%),
                        radial-gradient(circle at 50% 80%, rgba(6, 182, 212, 0.05) 0%, transparent 50%);
            animation: bgShift 15s ease-in-out infinite;
            z-index: 0;
        }
        
        @keyframes bgShift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(2%, -2%) rotate(1deg); }
            66% { transform: translate(-1%, 1%) rotate(-0.5deg); }
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 1.5rem;
        }
        
        .login-card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.25rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
            animation: cardIn 0.5s ease-out;
        }
        
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
        }
        
        .login-title {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 0.25rem;
            letter-spacing: -0.025em;
        }
        
        .login-subtitle {
            color: #94a3b8;
            font-size: 0.875rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating .form-control {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            color: #e2e8f0;
            padding: 1rem 0.75rem 0.5rem 2.75rem;
            height: 3.25rem;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .form-floating .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            color: #f1f5f9;
        }
        
        .form-floating label {
            color: #64748b;
            padding-left: 2.75rem;
            font-size: 0.875rem;
        }
        
        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label {
            color: #94a3b8;
        }
        
        .input-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            z-index: 5;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        
        .form-floating:focus-within .input-icon {
            color: var(--primary);
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.025em;
            margin-top: 0.5rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.35);
            background: linear-gradient(135deg, var(--primary-dark), #1d4ed8);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::after {
            left: 100%;
        }
        
        .alert-login {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            animation: shakeIn 0.4s ease-out;
        }
        
        @keyframes shakeIn {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(2px); }
        }
        
        .login-footer {
            color: #475569;
            font-size: 0.75rem;
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .credential-hint {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 0.5rem;
            padding: 0.6rem 0.8rem;
            margin-bottom: 1.25rem;
            font-size: 0.78rem;
            color: #93c5fd;
        }
        
        .credential-hint strong {
            color: #bfdbfe;
        }
        
        .toggle-password {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            z-index: 5;
            padding: 0.25rem;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        
        .toggle-password:hover {
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            
            <h1 class="login-title">GLS University<br>Student Management System<br>Administrator Portal</h1>
            <p class="login-subtitle">Authorized administrators only</p>
            
            <?php if ($error): ?>
                <div class="alert-login mb-3">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" autocomplete="off">
                <?php csrfField(); ?>
                
                <div class="form-floating position-relative">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" class="form-control" id="login_id" name="login_id" 
                           placeholder="Enter administrator ID" required autofocus
                           value="<?php echo $login_value; ?>">
                    <label for="login_id">Admin ID</label>
                </div>
                
                <div class="form-floating position-relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password">Password</label>
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                
                <button type="submit" name="login" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
            
            <div class="login-footer">
                <i class="fas fa-shield-halved me-1"></i> Protected by Session Authentication
            </div>
        </div>
    </div>
    
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
