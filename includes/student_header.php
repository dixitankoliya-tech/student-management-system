<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $sysTitle = $_SESSION['sys_title'] ?? null;
    if (!$sysTitle) {
        $sysTitle = 'GLS University';
        try {
            if (isset($pdo)) {
                $sysTitleStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'university_name'");
                $sysVal = $sysTitleStmt->fetchColumn();
                if ($sysVal) $sysTitle = $sysVal;
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['sys_title'] = $sysTitle;
                }
            }
        } catch (Exception $e) {}
    }
    ?>
    <title>Student Management System | <?php echo htmlspecialchars($sysTitle); ?></title>
    <!-- DNS Prefetch for External Assets -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Prevent flash of incorrect theme on load
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
        // Instant hover prefetcher for internal links
        document.addEventListener('DOMContentLoaded', function() {
            const prefetched = new Set();
            document.addEventListener('mouseover', function(e) {
                const a = e.target.closest('a');
                if (!a || !a.href || a.origin !== location.origin) return;
                if (a.pathname.endsWith('logout.php') || a.search.includes('action=delete') || prefetched.has(a.href)) return;
                prefetched.add(a.href);
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = a.href;
                document.head.appendChild(link);
            }, { passive: true });
        });
    </script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
