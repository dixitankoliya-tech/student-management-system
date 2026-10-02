<?php
/**
 * Main Entry Point Redirector
 * 
 * LOCAL: uses port to determine which portal
 * PRODUCTION: uses hostname to determine which portal, or redirects to a portal selector
 */

$port = $_SERVER['SERVER_PORT'] ?? 80;
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

// Remove port from hostname for subdomain detection
$hostWithoutPort = preg_replace('/:\d+$/', '', $host);

// === HOSTNAME-BASED ROUTING (Production) ===
if (preg_match('/^admin\./', $hostWithoutPort)) {
    header("Location: /admin/");
    exit;
} elseif (preg_match('/^student\./', $hostWithoutPort)) {
    header("Location: /student/");
    exit;
} elseif (preg_match('/^faculty\./', $hostWithoutPort)) {
    header("Location: /faculty/");
    exit;
}

// === PORT-BASED ROUTING (Local development) ===
if ($port == 8000) {
    header("Location: /admin/");
    exit;
} elseif ($port == 9100) {
    header("Location: /faculty/");
    exit;
} elseif ($port == 9000) {
    header("Location: /student/");
    exit;
}

// === DEFAULT: Show portal selection page ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GLS University — Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .portal-container {
            max-width: 900px;
            width: 100%;
        }
        .portal-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .portal-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.75rem;
            color: white;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }
        .portal-header h1 {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 1.75rem;
            letter-spacing: -0.025em;
        }
        .portal-header p {
            color: #94a3b8;
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        .portal-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.25rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            height: 100%;
        }
        .portal-card:hover {
            transform: translateY(-6px);
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: 0 12px 32px rgba(59, 130, 246, 0.2);
        }
        .portal-card .icon-wrap {
            width: 60px;
            height: 60px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.5rem;
            color: white;
        }
        .portal-card h3 {
            color: #f1f5f9;
            font-weight: 600;
            font-size: 1.15rem;
            margin-bottom: 0.5rem;
        }
        .portal-card p {
            color: #94a3b8;
            font-size: 0.85rem;
            margin: 0;
        }
        .admin-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .student-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .faculty-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .portal-footer {
            text-align: center;
            margin-top: 2rem;
            color: #475569;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="portal-container">
        <div class="portal-header">
            <div class="portal-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1>GLS University</h1>
            <p>Student Management System</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <a href="/admin/login.php" class="portal-card">
                    <div class="icon-wrap admin-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Administrator</h3>
                    <p>University administration panel</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="/student/login.php" class="portal-card">
                    <div class="icon-wrap student-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>Student</h3>
                    <p>Access your academic portal</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="/faculty/login.php" class="portal-card">
                    <div class="icon-wrap faculty-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Faculty</h3>
                    <p>Teaching staff portal</p>
                </a>
            </div>
        </div>
        <div class="portal-footer">
            <i class="fas fa-shield-halved me-1"></i> Secured with Session Authentication &bull; &copy; <?php echo date('Y'); ?> GLS University
        </div>
    </div>
</body>
</html>
