<?php
/**
 * Audit live production endpoints for Admin, Faculty, and Student portals.
 */

$portals = [
    'Admin' => [
        'base' => 'https://gls-sms-admin.vercel.app',
        'login_id' => 'GLS_ADMIN',
        'password' => 'admin123',
        'dashboard' => 'dashboard.php',
        'routes' => [
            'dashboard.php',
            'students.php',
            'faculty.php',
            'semesters.php',
            'attendance_reports.php',
            'fee_structures.php',
            'fee_reports.php',
            'leadership.php',
            'academic_calendar.php',
            'notifications.php'
        ]
    ],
    'Faculty' => [
        'base' => 'https://gls-sms-faculty.vercel.app',
        'login_id' => 'EMP0001',
        'password' => 'Faculty@123',
        'fallback_id' => 'FAC-FCAIT-001',
        'fallback_pwd' => 'faculty123',
        'dashboard' => 'dashboard.php',
        'routes' => [
            'dashboard.php',
            'attendance.php',
            'marks.php',
            'assignments.php',
            'study_materials.php'
        ]
    ],
    'Student' => [
        'base' => 'https://gls-sms-student.vercel.app',
        'login_id' => 'BCA2024001',
        'password' => 'Student@123',
        'fallback_id' => '2021IMSIT0309',
        'fallback_pwd' => 'student123',
        'dashboard' => 'dashboard.php',
        'routes' => [
            'dashboard.php',
            'profile.php',
            'attendance.php',
            'timetable.php',
            'fees.php',
            'report_card.php',
            'announcements.php'
        ]
    ]
];

echo "====================================================================\n";
echo " LIVE THREE-PORTAL PRODUCTION AUDIT\n";
echo "====================================================================\n\n";

foreach ($portals as $name => $cfg) {
    echo ">>> AUDITING $name PORTAL ({$cfg['base']}) <<<\n";
    $cookieFile = __DIR__ . "/audit_cookie_{$name}.txt";
    if (file_exists($cookieFile)) unlink($cookieFile);

    // 1. Check Login Page Reachability
    $loginUrl = $cfg['base'] . '/login.php';
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => true
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($res, $hdrSize);
    curl_close($ch);

    echo "  [1] Login Page HTTP: $code\n";
    $hasDemoCard = (stripos($body, 'Demo Login Credentials') !== false || stripos($body, 'Demo Credentials') !== false);
    echo "  [2] Demo Credentials Card Visible: " . ($hasDemoCard ? "YES (PASS)" : "NO (FAIL)") . "\n";

    // Extract CSRF Token
    preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $body, $cm);
    $csrf = $cm[1] ?? '';
    echo "  [3] CSRF Token Present: " . ($csrf ? "YES (" . substr($csrf, 0, 10) . "...)" : "NO") . "\n";

    // 2. Attempt Login with primary credentials
    $postFields = [
        'login' => '1',
        'login_id' => $cfg['login_id'],
        'password' => $cfg['password'],
        'csrf_token' => $csrf
    ];
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => true
    ]);
    $loginResp = curl_exec($ch);
    $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loginHdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $loginBody = substr($loginResp, $loginHdrSize);
    curl_close($ch);

    $loginSuccess = (stripos($loginBody, 'dashboard') !== false || stripos($loginBody, 'Logout') !== false || stripos($loginBody, 'Sign Out') !== false);
    $usedCreds = $cfg['login_id'];

    // If primary failed and fallback exists, attempt fallback
    if (!$loginSuccess && isset($cfg['fallback_id'])) {
        // Re-extract CSRF
        preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $loginBody, $cm2);
        $csrf2 = $cm2[1] ?? '';
        $postFields['login_id'] = $cfg['fallback_id'];
        $postFields['password'] = $cfg['fallback_pwd'];
        $postFields['csrf_token'] = $csrf2;
        $ch = curl_init($loginUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HEADER => true
        ]);
        $loginResp2 = curl_exec($ch);
        $loginHdrSize2 = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $loginBody = substr($loginResp2, $loginHdrSize2);
        curl_close($ch);
        $loginSuccess = (stripos($loginBody, 'dashboard') !== false || stripos($loginBody, 'Logout') !== false || stripos($loginBody, 'Sign Out') !== false);
        if ($loginSuccess) $usedCreds = $cfg['fallback_id'];
    }

    echo "  [4] Authentication Status: " . ($loginSuccess ? "AUTHENTICATED via $usedCreds (PASS)" : "FAILED (FAIL)") . "\n";

    // 3. Test Sub-Routes
    if ($loginSuccess) {
        echo "  [5] Testing Core Sub-Routes:\n";
        foreach ($cfg['routes'] as $r) {
            $routeUrl = $cfg['base'] . '/' . $r;
            $ch = curl_init($routeUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIEJAR => $cookieFile,
                CURLOPT_COOKIEFILE => $cookieFile,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HEADER => true
            ]);
            $rtResp = curl_exec($ch);
            $rtCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $rtHdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $rtBody = substr($rtResp, $rtHdrSize);
            curl_close($ch);

            $isLoginRedirect = (stripos($rtBody, 'Sign In') !== false && stripos($rtBody, 'Login ID') !== false);
            $status = ($rtCode === 200 && !$isLoginRedirect) ? "HTTP 200 OK" : "FAILED (Code: $rtCode)";
            echo "      - $r: $status\n";
        }
    }
    echo "\n";
}
