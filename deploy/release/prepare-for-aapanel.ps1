# ============================================================
# PAMSIMAS - Script Penyiapan Sebelum ZIP (PowerShell/Windows)
# ============================================================
# Jalankan script ini di PowerShell sebelum kompresi ZIP
# ============================================================

Write-Host "=== PAMSIMAS - Prepare for aaPanel Deployment ===" -ForegroundColor Cyan

# 1. Pastikan direktori storage ada dan writable
Write-Host "[1/5] Creating storage directories..." -ForegroundColor Yellow
$dirs = @(
    "storage\app\meter_photos",
    "storage\app\.easycr\model",
    "storage\app\.easycr\user_network",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\views",
    "storage\logs",
    "database"
)
foreach ($dir in $dirs) {
    if (!(Test-Path $dir)) {
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        Write-Host "  Created: $dir" -ForegroundColor Green
    }
}

# 2. Buat database.sqlite jika belum ada
Write-Host "[2/5] Checking SQLite database..." -ForegroundColor Yellow
if (!(Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Force -Path "database\database.sqlite" | Out-Null
    Write-Host "  Created: database\database.sqlite" -ForegroundColor Green
} else {
    Write-Host "  Already exists: database\database.sqlite" -ForegroundColor Green
}

# 3. Copy .env.example ke .env jika belum ada
Write-Host "[3/5] Checking .env file..." -ForegroundColor Yellow
if (!(Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "  Created .env from .env.example" -ForegroundColor Green
    Write-Host "  WARNING: Edit .env and set APP_KEY!" -ForegroundColor Red
} else {
    Write-Host "  .env already exists" -ForegroundColor Green
}

# 4. Optimize Laravel (jika PHP tersedia)
Write-Host "[4/5] Optimizing Laravel..." -ForegroundColor Yellow
if (Get-Command php -ErrorAction SilentlyContinue) {
    php artisan config:cache 2>$null
    php artisan route:cache 2>$null
    Write-Host "  Laravel optimized" -ForegroundColor Green
} else {
    Write-Host "  PHP not found - skip optimization (will run on server)" -ForegroundColor Yellow
}

# 5. Tampilkan ringkasan dan file yang di-exclude
Write-Host ""
Write-Host "=== SUMMARY ===" -ForegroundColor Cyan
Write-Host "Storage path: $(Get-Location)\storage"
Write-Host "Database: $(Get-Location)\database\database.sqlite"
Write-Host ""
Write-Host "=== FILES TO EXCLUDE IN ZIP ===" -ForegroundColor Cyan
Write-Host @"
- .env                  (contains secrets - will be created on server)
- .git                  (version control)
- node_modules          (development dependencies)
- storage\*.key         (encryption keys)
- .vscode, .idea        (editor config)
- *.md                  (documentation files)
- deploy\               (deployment scripts)
- backup_pamsimas\      (backup files)
- google-apps-script\   (IoT alternative)
- *.yaml, *.toml        (deployment configs for other platforms)
"@

Write-Host "=== COMPRESSION COMMAND ===" -ForegroundColor Cyan
Write-Host "Compress-Archive -Path . -DestinationPath 'pamsimas-aapanel.zip' -Force"
Write-Host ""
Write-Host "=== NEXT STEPS ===" -ForegroundColor Cyan
Write-Host "1. Open .env and configure APP_KEY, DB path, PYTHON_PATH"
Write-Host "2. Run: php artisan key:generate (on server)"
Write-Host "3. Compress to ZIP excluding files above"
Write-Host "4. Upload ZIP to aaPanel and extract to /www/wwwroot/pamsimas.selur.my.id/"
Write-Host "5. Run on server: php artisan migrate --force"
Write-Host ""
Write-Host "=== DONE ===" -ForegroundColor Cyan
