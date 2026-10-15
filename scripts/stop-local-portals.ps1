# GLS FCAIT SMS -- Stop Local Multi-Portal Development Servers

$ports = @(8000, 9000, 9100)
Write-Host "Stopping local servers on ports 8000, 9000, 9100..." -ForegroundColor Yellow

foreach ($p in $ports) {
    $conn = Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue
    if ($conn) {
        Write-Host "  Stopping Port $p (PID $($conn.OwningProcess))..." -ForegroundColor Green
        Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
    } else {
        Write-Host "  Port $p is not running." -ForegroundColor Gray
    }
}
Write-Host "All local servers stopped." -ForegroundColor Green
