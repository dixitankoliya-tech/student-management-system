# GLS FCAIT SMS -- Start Local Multi-Portal Development Servers
# Admin: http://localhost:8000
# Student: http://localhost:9000
# Faculty: http://localhost:9100

$ScriptDir = $PSScriptRoot
$ProjectRoot = (Resolve-Path (Join-Path $ScriptDir "..")).Path
$Router = Join-Path $ProjectRoot "api\index.php"

Write-Host "==============================================================" -ForegroundColor Cyan
Write-Host " GLS FCAIT SMS -- MULTI-PORTAL LOCAL DEVELOPMENT LAUNCHER" -ForegroundColor Cyan
Write-Host "==============================================================" -ForegroundColor Cyan

# 1. Stop any existing processes on ports 8000, 9000, 9100
$ports = @(8000, 9000, 9100)
foreach ($p in $ports) {
    $conn = Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue
    if ($conn) {
        Write-Host "Stopping existing process on port $p (PID $($conn.OwningProcess))..." -ForegroundColor Yellow
        Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
    }
}

Start-Sleep -Milliseconds 500

# 2. Launch Portals
Write-Host "Starting Admin Portal on http://localhost:8000..." -ForegroundColor Green
Start-Process -WindowStyle Hidden -FilePath "cmd.exe" -ArgumentList "/c set PORTAL=admin && php -S 127.0.0.1:8000 `"$Router`"" -WorkingDirectory $ProjectRoot

Write-Host "Starting Student Portal on http://localhost:9000..." -ForegroundColor Green
Start-Process -WindowStyle Hidden -FilePath "cmd.exe" -ArgumentList "/c set PORTAL=student && php -S 127.0.0.1:9000 `"$Router`"" -WorkingDirectory $ProjectRoot

Write-Host "Starting Faculty Portal on http://localhost:9100..." -ForegroundColor Green
Start-Process -WindowStyle Hidden -FilePath "cmd.exe" -ArgumentList "/c set PORTAL=faculty && php -S 127.0.0.1:9100 `"$Router`"" -WorkingDirectory $ProjectRoot

Start-Sleep -Seconds 2

# 3. Verify socket binding
Write-Host ""
Write-Host "Verifying server bindings:" -ForegroundColor Yellow
foreach ($p in $ports) {
    $conn = Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue
    if ($conn) {
        Write-Host "  [ONLINE] Port $p is listening (PID: $($conn.OwningProcess))" -ForegroundColor Green
    } else {
        Write-Host "  [FAILED] Port $p failed to bind." -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Access URLs:" -ForegroundColor Cyan
Write-Host "  Admin Portal   : http://localhost:8000" -ForegroundColor White
Write-Host "  Student Portal : http://localhost:9000" -ForegroundColor White
Write-Host "  Faculty Portal : http://localhost:9100" -ForegroundColor White
Write-Host "==============================================================" -ForegroundColor Cyan
