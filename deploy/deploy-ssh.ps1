param(
    [string]$SshHost = "ssh.selur.my.id",
    [string]$SshUser = "root",
    [string]$RemotePath = "/var/www/pamsimas.selur.my.id",
    [switch]$NoBuild,
    [switch]$NoUpload,
    [switch]$NoSetup
)
$ErrorActionPreference = "Stop"

# PHP Path
$PHP = "php"
if (-not (Get-Command php -EA SilentlyContinue)) {
    foreach ($p in @("D:\xampp\php\php.exe","C:\xampp\php\php.exe","C:\php\php.exe")) {
        if (Test-Path $p) { $PHP = $p; break }
    }
}

Write-Host "`n=== PAMSIMAS DEPLOYMENT (SSH/SFTP) ===" -ForegroundColor Cyan
Write-Host "Server : $SshUser@$SshHost" -ForegroundColor White
Write-Host "Remote : $RemotePath`n" -ForegroundColor White

# Step 1: Build
if (-not $NoBuild) {
    Write-Host "[1/4] Building frontend..." -ForegroundColor Yellow
    $env:NODE_ENV = "production"
    npm run build | Out-Host
    if ($LASTEXITCODE -ne 0) { Write-Host "ERROR: Build failed!" -ForegroundColor Red; exit 1 }
    Write-Host "      Done." -ForegroundColor Green
} else {
    Write-Host "[1/4] Skip build (NoBuild)" -ForegroundColor Gray
}

# Step 2: Clear cache
Write-Host "`n[2/4] Clearing cache..." -ForegroundColor Yellow
& $PHP artisan config:clear | Out-Host
& $PHP artisan route:clear | Out-Host
& $PHP artisan view:clear | Out-Host
& $PHP artisan cache:clear | Out-Host
Write-Host "      Done." -ForegroundColor Green

# Step 3: Upload via SFTP
if (-not $NoUpload) {
    Write-Host "`n[3/4] Uploading to server..." -ForegroundColor Yellow
    Write-Host "      Host : $SshHost" -ForegroundColor Gray
    Write-Host "      User : $SshUser" -ForegroundColor Gray
    Write-Host "      Path : $RemotePath`n" -ForegroundColor Gray

    $staging = "deploy/_staging"
    if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
    New-Item -ItemType Directory -Path $staging -Force | Out-Null

    $items = @("app","bootstrap","config","database","public","resources","routes","storage","vendor","artisan","composer.json","composer.lock",".env")
    foreach ($item in $items) {
        if (Test-Path $item) {
            Copy-Item $item (Join-Path $staging $item) -Recurse -Force -EA SilentlyContinue
        }
    }

    Write-Host "      Files staged in: $staging" -ForegroundColor Yellow
    Write-Host "`n      Upload options:" -ForegroundColor Cyan
    Write-Host "      A) Manual: WinSCP/FileZilla to $SshUser@$SshHost" -ForegroundColor White
    Write-Host "      B) WinSCP auto (if installed)" -ForegroundColor White

    $winscp = "C:\Program Files (x86)\WinSCP\WinSCP.com"
    if (Test-Path $winscp) {
        Write-Host "`n      WinSCP found - auto-upload..." -ForegroundColor Green
        $script = @"
open sftp://$SshUser@$SshHost/
put -r "$staging\*" "$RemotePath/"
exit
"@
        $sp = Join-Path $staging "_upload.txt"
        $script | Out-File -FilePath $sp -Encoding UTF8
        & $winscp /console /script=$sp /log="$staging\upload.log" | Out-Host
        if ($LASTEXITCODE -eq 0) { Write-Host "      Upload complete!" -ForegroundColor Green }
        else { Write-Host "      Upload may have failed." -ForegroundColor Yellow }
    }
} else {
    Write-Host "[3/4] Skip upload (NoUpload)" -ForegroundColor Gray
}

# Step 4: Server setup
Write-Host "`n[4/4] Server setup instructions:" -ForegroundColor Yellow
Write-Host "`n      SSH to server:" -ForegroundColor Cyan
Write-Host "      ssh $SshUser@$SshHost" -ForegroundColor DarkYellow
Write-Host "      cd $RemotePath`n" -ForegroundColor DarkYellow
Write-Host "      Commands:" -ForegroundColor White
Write-Host "      composer install --optimize-autoloader --no-dev" -ForegroundColor DarkYellow
Write-Host "      php artisan storage:link" -ForegroundColor DarkYellow
Write-Host "      chmod -R 775 storage/" -ForegroundColor DarkYellow
Write-Host "      chmod -R 775 bootstrap/cache/" -ForegroundColor DarkYellow
Write-Host "      php artisan migrate --force" -ForegroundColor DarkYellow
} else {
    Write-Host "[4/4] Skip setup (NoSetup)" -ForegroundColor Gray
}

Write-Host "`n=== DEPLOYMENT COMPLETE ===" -ForegroundColor Cyan
Write-Host "Next on server:" -ForegroundColor Yellow
Write-Host "  - Edit .env for server" -ForegroundColor White
Write-Host "  - composer install --no-dev" -ForegroundColor White
Write-Host "  - php artisan migrate --force" -ForegroundColor White
Write-Host "  - Setup WA Gateway if needed" -ForegroundColor White
Write-Host "`n"