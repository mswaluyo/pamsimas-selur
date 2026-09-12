# Script Deployment Otomatis PAMSIMAS
# Script ini membangun aset, membersihkan cache, dan membuat file ZIP untuk diupload ke hosting
# Jalankan dari folder D:\pamsimas.selur.my.id

param(
    [string]$OutputDir = "deploy\release",
    [switch]$NoBuild
)

$ErrorActionPreference = "Stop"

# Cek dan setup PHP path
$PHP_PATH = "php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    # Coba beberapa lokasi umum PHP di Windows
    $possiblePhpPaths = @(
        "D:\xampp\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php.exe",
        "C:\php\php.exe",
        "C:\wamp64\bin\php\php.exe"
    )
    foreach ($path in $possiblePhpPaths) {
        if (Test-Path $path) {
            $PHP_PATH = $path
            Write-Host "Menggunakan PHP: $PHP_PATH" -ForegroundColor Gray
            break
        }
    }
}

Write-Host ""
Write-Host "===========================================" -ForegroundColor Cyan
Write-Host "  PAMSIMAS DEPLOYMENT SCRIPT" -ForegroundColor Cyan
Write-Host "===========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Build Aset Frontend
if (-not $NoBuild) {
    Write-Host "[1/5] Building frontend assets..." -ForegroundColor Yellow
    $env:NODE_ENV = "production"
    npm run build
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Build gagal!" -ForegroundColor Red
        exit 1
    }
    Write-Host "      Selesai." -ForegroundColor Green
} else {
    Write-Host "[1/5] Skip build (NoBuild flag)" -ForegroundColor Gray
}

# 2. Clear Laravel Cache
Write-Host "[2/5] Clearing Laravel cache..." -ForegroundColor Yellow
& $PHP_PATH artisan config:clear
& $PHP_PATH artisan route:clear
& $PHP_PATH artisan view:clear
& $PHP_PATH artisan cache:clear
Write-Host "      Selesai." -ForegroundColor Green

# 3. Prepare release directory
Write-Host "[3/5] Preparing release package..." -ForegroundColor Yellow
if (Test-Path $OutputDir) {
    Remove-Item $OutputDir -Recurse -Force
}
New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null

# 4. Copy files to release directory
Write-Host "[4/5] Packaging files..." -ForegroundColor Yellow

$includePaths = @(
    "app",
    "bootstrap",
    "config",
    "database/migrations",
    "public",
    "resources",
    "routes",
    "storage",
    "vendor",
    "artisan",
    "composer.json",
    "composer.lock"
)

foreach ($path in $includePaths) {
    if (Test-Path $path) {
        Copy-Item -Path $path -Destination (Join-Path $OutputDir $path) -Recurse -Force
        Write-Host "      + $path" -ForegroundColor Gray
    } else {
        Write-Host "      - $path (tidak ada)" -ForegroundColor DarkGray
    }
}

# 5. Create ZIP file
Write-Host "[5/5] Creating ZIP archive..." -ForegroundColor Yellow
$zipName = "pamsimas-$(Get-Date -Format 'yyyyMMdd-HHmmss').zip"
$zipPath = Join-Path $OutputDir $zipName

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Compress-Archive -Path "$OutputDir\*" -DestinationPath $zipPath -Force

Write-Host ""
Write-Host "===========================================" -ForegroundColor Cyan
Write-Host "  DEPLOYMENT READY!" -ForegroundColor Cyan
Write-Host "===========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "File ZIP: $zipPath" -ForegroundColor Green
$size = (Get-Item $zipPath).Length / 1MB
Write-Host "Ukuran: $('{0:N2}' -f $size) MB" -ForegroundColor White
Write-Host ""
Write-Host "Langkah selanjutnya:" -ForegroundColor Yellow
Write-Host "  1. Upload ZIP ke hosting (via FTP/File Manager)" -ForegroundColor White
Write-Host "  2. Ekstrak di folder public_html atau subdomain" -ForegroundColor White
Write-Host "  3. Atur .env sesuai konfigurasi hosting" -ForegroundColor White
Write-Host "  4. Atur permission: storage/ dan bootstrap/cache/ (775)" -ForegroundColor White
Write-Host "  5. Import database atau jalankan migrate" -ForegroundColor White
Write-Host ""

