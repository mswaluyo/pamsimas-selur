# PAMSIMAS Deployment Script - Simple Version
# Jalankan: npm run deploy  atau  .\deploy\deploy-simple.ps1

$ErrorActionPreference = "Stop"
Write-Host ""

# Path PHP
$PHP_PATH = "php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $possiblePhp = @(
        "D:\xampp\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php.exe",
        "C:\php\php.exe"
    )
    foreach ($p in $possiblePhp) {
        if (Test-Path $p) { $PHP_PATH = $p; break }
    }
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  PAMSIMAS DEPLOY" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Build frontend
Write-Host "1. Building frontend..." -ForegroundColor Yellow
$env:NODE_ENV = "production"
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "   Build gagal!" -ForegroundColor Red
    exit 1
}
Write-Host "   Done." -ForegroundColor Green

# 2. Clear cache
Write-Host "2. Clearing Laravel cache..." -ForegroundColor Yellow
& $PHP_PATH artisan config:clear
& $PHP_PATH artisan route:clear
& $PHP_PATH artisan view:clear
& $PHP_PATH artisan cache:clear
Write-Host "   Done." -ForegroundColor Green

# 3. Buat ZIP
Write-Host "3. Creating deployment package..." -ForegroundColor Yellow

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$projectName = Split-Path -Leaf (Get-Location)
$zipName = "$projectName-$timestamp.zip"
$releaseDir = "deploy\release"
$zipPath = Join-Path $releaseDir $zipName

if (-not (Test-Path $releaseDir)) {
    New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null
}

# Folder yang di-include (KECUALI folder deploy itu sendiri)
$folders = @("app", "bootstrap", "config", "database", "public", "resources", "routes", "storage", "vendor")
$files = @("artisan", "composer.json", "composer.lock")
# .env: include dengan catatan - jangan overwrite di server jika sudah ada
$filesWithEnv = @(".env")

# Buat file sementara
$stagingDir = Join-Path $PWD "deploy/_staging"
if (Test-Path $stagingDir) { Remove-Item $stagingDir -Recurse -Force }
New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null

foreach ($f in $folders) {
    if (Test-Path $f) {
        Copy-Item -Path $f -Destination $stagingDir -Recurse -Force
    }
}

foreach ($f in $files) {
    if (Test-Path $f) {
        Copy-Item -Path $f -Destination $stagingDir -Force
    }
}

# .env: copy ke staging terpisah (dengan peringatan)
if (Test-Path ".env") {
    Copy-Item -Path ".env" -Destination (Join-Path $stagingDir ".env") -Force
    Write-Host "   Note: .env included - DO NOT overwrite on server if DB config exists" -ForegroundColor DarkYellow
}

# Buat ZIP
Compress-Archive -Path (Join-Path $stagingDir "*") -DestinationPath $zipPath -Force

# Bersihkan staging
Remove-Item $stagingDir -Recurse -Force -ErrorAction SilentlyContinue

$fileSize = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
Write-Host "   Done." -ForegroundColor Green
Write-Host ""

# Selesai
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  DEPLOY READY!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "File: $zipPath" -ForegroundColor White
Write-Host "Size: $fileSize MB" -ForegroundColor White
Write-Host ""
Write-Host "IMPORTANT - Database Safety:" -ForegroundColor Red
Write-Host "  1. DATABASE NOT in ZIP - stays on server, safe from overwrite" -ForegroundColor Yellow
Write-Host "  2. .env included - DO NOT overwrite if DB config already set on server" -ForegroundColor Yellow
Write-Host "  3. Backup database before deploy (optional):" -ForegroundColor Yellow
Write-Host "     mysqldump -u root -p pamsimas_db > backup-before-deploy.sql" -ForegroundColor DarkYellow
Write-Host ""
Write-Host "Upload & extract on server, then:" -ForegroundColor Yellow
Write-Host "  1. php artisan migrate --force  (ADD only, never deletes data)" -ForegroundColor DarkYellow
Write-Host "  2. chmod -R 775 storage/ bootstrap/cache/" -ForegroundColor DarkYellow
Write-Host "  3. Test aplikasi" -ForegroundColor DarkYellow
Write-Host ""
Write-Host "NEVER run: php artisan migrate:fresh --seed" -ForegroundColor Red
Write-Host "  (This DELETES all data! Only use migrate for deployment)" -ForegroundColor Red
Write-Host ""
