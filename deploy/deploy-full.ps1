#!/usr/bin/env pwsh
# PAMSIMAS ONE-COMMAND DEPLOY
# Pakai tunnel dari SFTP-Connections-cloudflare.bat
# npm run deploy:full

$ErrorActionPreference = "SilentlyContinue"
$SRC = Split-Path -Parent $PSScriptRoot

$SERVER = "ssh.selur.my.id"
$USER = "root"
$REMOTE_PATH = "/var/www/pamsimas.selur.my.id"
$PORT = 2222
$PHP = "php"

Write-Host ""
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host "   PAMSIMAS ONE-COMMAND DEPLOY" -ForegroundColor Cyan
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host ""
Write-Host "Server : $USER@$SERVER" -ForegroundColor White
Write-Host "Path   : $REMOTE_PATH" -ForegroundColor White
Write-Host ""

# 1. Build
Write-Host "[1/4] Building frontend..." -ForegroundColor Yellow
Set-Location $SRC
$env:NODE_ENV = "production"
npm run build | Out-Host
if ($LASTEXITCODE -ne 0) { Write-Host "  Build GAGAL" -ForegroundColor Red; exit 1 }
Write-Host "  Build OK" -ForegroundColor Green

# 2. Clear cache
Write-Host "[2/4] Clearing cache..." -ForegroundColor Yellow
& $PHP artisan config:clear
& $PHP artisan route:clear
& $PHP artisan view:clear
& $PHP artisan cache:clear
Write-Host "  Done" -ForegroundColor Green

# 3. ZIP
Write-Host "[3/4] Membuat ZIP..." -ForegroundColor Yellow
$zipDir = Join-Path $PSScriptRoot "release"
if (!(Test-Path $zipDir)) { New-Item -ItemType Directory -Path $zipDir -Force | Out-Null }
$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$zipName = "pamsimas-$ts.zip"
$zipFull = Join-Path $zipDir $zipName
$tmp = Join-Path $env:TEMP "pams_stg_$([guid]::NewGuid().ToString().Substring(0,8))"
New-Item -ItemType Directory -Path $tmp -Force | Out-Null
$folders = "app","bootstrap","config","database","public","resources","routes","storage","vendor"
foreach ($f in $folders) { Copy-Item (Join-Path $SRC $f) (Join-Path $tmp $f) -Recurse -Force -EA SilentlyContinue }
Copy-Item (Join-Path $SRC "artisan") $tmp -Force -EA SilentlyContinue
Copy-Item (Join-Path $SRC "composer.json") $tmp -Force -EA SilentlyContinue
Copy-Item (Join-Path $SRC "composer.lock") $tmp -Force -EA SilentlyContinue
Copy-Item (Join-Path $SRC ".env") $tmp -Force -EA SilentlyContinue
Compress-Archive -Path (Join-Path $tmp "*") -DestinationPath $zipFull -Force
Remove-Item $tmp -Recurse -Force -EA SilentlyContinue
$sz = [math]::Round((Get-Item $zipFull).Length/1MB,2)
Write-Host "  ZIP: $zipName ($sz MB)" -ForegroundColor Green
Set-Location $SRC

# 4. Upload (pakai tunnel yang sudah jalan)
Write-Host "[4/4] Upload ke server..." -ForegroundColor Yellow
Write-Host "  Uploading via tunnel (port $PORT)..." -ForegroundColor Gray
& scp -P $PORT -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null $zipFull "${USER}@localhost:${REMOTE_PATH}/" 2>&1 | Out-Host
if ($LASTEXITCODE -ne 0) { Write-Host "  Upload GAGAL" -ForegroundColor Red; exit 1 }
Write-Host "  Upload OK" -ForegroundColor Green

# Setup server
Write-Host ""
Write-Host "Setup di server..." -ForegroundColor Yellow
$cmd = "cd $REMOTE_PATH; unzip -o $zipName -d /tmp/x 2>/dev/null; rsync -a /tmp/x/ . --exclude=.env --exclude=storage 2>/dev/null; rm -rf /tmp/x; chmod -R 775 storage/ bootstrap/cache/; php artisan storage:link 2>/dev/null || true; php artisan config:clear; php artisan cache:clear; php artisan route:clear; php artisan view:clear; php artisan migrate --force 2>&1 | tail -3; echo DONE"
& ssh -p $PORT -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null "$USER@localhost" $cmd 2>&1 | Out-Host

Write-Host ""
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host "   DEPLOYMENT SELESAI!" -ForegroundColor Cyan
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host ""
Write-Host "  URL: https://pamsimas.selur.my.id" -ForegroundColor White
Write-Host "  Tekan Ctrl+F5 di browser" -ForegroundColor Yellow
Write-Host ""

#!/usr/bin/env pwsh
# PAMSIMAS ONE-COMMAND DEPLOY
# npm run deploy:full

$ErrorActionPreference = "SilentlyContinue"
$SRC = Split-Path -Parent $PSScriptRoot

$SERVER = "ssh.selur.my.id"
$USER = "root"
$REMOTE_PATH = "/var/www/pamsimas.selur.my.id"
$CLOUDFLARED = "C:\cloudflared\cloudflared.exe"
$PORT = 2222
$PHP = "php"

Write-Host ""
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host "   PAMSIMAS ONE-COMMAND DEPLOY TO SERVER" -ForegroundColor Cyan
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host ""
Write-Host "Server : $USER@$SERVER" -ForegroundColor White
Write-Host "Path   : $REMOTE_PATH" -ForegroundColor White
Write-Host ""

# Precheck
if (-not (Test-Path $CLOUDFLARED)) {
    Write-Host "  cloudflared.exe TIDAK DITEMUKAN!" -ForegroundColor Red
    Write-Host "  Install: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation/" -ForegroundColor Yellow
    exit 1
}
Write-Host "  cloudflared: OK" -ForegroundColor Green

# 1. Build
Write-Host "[1/4] Building frontend..." -ForegroundColor Yellow
Set-Location $SRC
$env:NODE_ENV = "production"
npm run build | Out-Host
if ($LASTEXITCODE -ne 0) { Write-Host "  Build GAGAL" -ForegroundColor Red; exit 1 }
Write-Host "  Build OK" -ForegroundColor Green

# 2. Clear cache
Write-Host "[2/4] Clearing cache..." -ForegroundColor Yellow
& $PHP artisan config:clear
& $PHP artisan route:clear
& $PHP artisan view:clear
& $PHP artisan cache:clear
Write-Host "  Done" -ForegroundColor Green

# 3. ZIP
Write-Host "[3/4] Membuat ZIP..." -ForegroundColor Yellow
$zipDir = Join-Path $PSScriptRoot "release"
if (!(Test-Path $zipDir)) { New-Item -ItemType Directory -Path $zipDir -Force | Out-Null }
$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$zipName = "pamsimas-$ts.zip"
$zipFull = Join-Path $zipDir $zipName
$tmp = Join-Path $env:TEMP "pams_stg_$([guid]::NewGuid().ToString().Substring(0,8))"
New-Item -ItemType Directory -Path $tmp -Force | Out-Null
$folders = "app","bootstrap","config","database","public","resources","routes","storage","vendor"
foreach ($f in $folders) { Copy-Item (Join-Path $SRC $f) (Join-Path $tmp $f) -Recurse -Force -EA SilentlyContinue }
Copy-Item (Join-Path $SRC "artisan") $tmp -Force -EA SilentlyContinue
Copy-Item (Join-Path $SRC "composer.json") $tmp -Force -EA SilentlyContinue
Copy-Item (Join-Path $SRC "composer.lock") $tmp -Force -EA SilentlyContinue
Copy-Item (Join-Path $SRC ".env") $tmp -Force -EA SilentlyContinue
Compress-Archive -Path (Join-Path $tmp "*") -DestinationPath $zipFull -Force
Remove-Item $tmp -Recurse -Force -EA SilentlyContinue
$sz = [math]::Round((Get-Item $zipFull).Length/1MB,2)
Write-Host "  ZIP: $zipName ($sz MB)" -ForegroundColor Green
Set-Location $SRC

# 4. Upload
Write-Host "[4/4] Upload ke server..." -ForegroundColor Yellow
$tunnel = Start-Process -FilePath $CLOUDFLARED -ArgumentList "access ssh --hostname $SERVER --url localhost:$PORT" -PassThru -WindowStyle Hidden
Start-Sleep 5
if ($tunnel.HasExited) { Write-Host "  Tunnel gagal" -ForegroundColor Red; exit 1 }
Write-Host "  Uploading..." -ForegroundColor Gray
& scp -P $PORT -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null $zipFull "${USER}@localhost:${REMOTE_PATH}/" 2>&1 | Out-Host
if ($LASTEXITCODE -ne 0) { Stop-Process $tunnel -EA SilentlyContinue; Write-Host "  Upload GAGAL" -ForegroundColor Red; exit 1 }
Write-Host "  Upload OK" -ForegroundColor Green
Stop-Process $tunnel -EA SilentlyContinue

# Setup server
Write-Host ""
Write-Host "Setup di server..." -ForegroundColor Yellow
$tunnel2 = Start-Process -FilePath $CLOUDFLARED -ArgumentList "access ssh --hostname $SERVER --url localhost:$PORT" -PassThru -WindowStyle Hidden
Start-Sleep 3
$cmd = "cd $REMOTE_PATH; unzip -o $zipName -d /tmp/x 2>/dev/null; rsync -a /tmp/x/ . --exclude=.env --exclude=storage 2>/dev/null; rm -rf /tmp/x; chmod -R 775 storage/ bootstrap/cache/; php artisan storage:link 2>/dev/null || true; php artisan config:clear; php artisan cache:clear; php artisan route:clear; php artisan view:clear; php artisan migrate --force 2>&1 | tail -3; echo DONE"
& ssh -p $PORT -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null "$USER@localhost" $cmd 2>&1 | Out-Host
Stop-Process $tunnel2 -EA SilentlyContinue

Write-Host ""
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host "   DEPLOYMENT SELESAI!" -ForegroundColor Cyan
Write-Host ("=" * 55) -ForegroundColor Cyan
Write-Host ""
Write-Host "  URL: https://pamsimas.selur.my.id" -ForegroundColor White
Write-Host "  Tekan Ctrl+F5 di browser" -ForegroundColor Yellow
Write-Host ""

