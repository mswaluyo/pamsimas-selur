# PAMSIMAS - Create Deployment ZIP for HestiaCP (pamsimas_project.zip)
# ---------------------------------------------------------------------------
# Jalankan dari root proyek:
#   powershell -ExecutionPolicy Bypass -File scripts\create-zip-hestiacp.ps1
#
# Target: server HestiaCP (Nginx + PHP-FPM + MariaDB)
#   /home/admin/web/pamsimas.selur.my.id/public_html/
#
# DIBUANG dari ZIP : node_modules/, vendor/, .git/, .vscode/, backup_pamsimas/,
#                    *.zip, diag-*.php, database/database.sqlite, .env (lokal)
# PENTING IKUT     : public/build/, .env.production, composer.json/lock/phar,
#                    wa-gateway/, deploy/ (pamsimas-queue.conf), scripts/deploy-hestiacp.sh
# ---------------------------------------------------------------------------
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression.FileSystem

Write-Host "=== PAMSIMAS - Create Deployment ZIP (HestiaCP) ===" -ForegroundColor Cyan

$appRoot = Split-Path -Parent $PSScriptRoot     # scripts/ -> root proyek
$zipName = "pamsimas_project.zip"
$zipPath = Join-Path $appRoot $zipName

Write-Host "Source : $appRoot"
Write-Host "Output : $zipPath" -ForegroundColor Yellow

# Folder yang dibuang dari ZIP (dicocokkan pada elemen path TOP-LEVEL)
$excludeDirs = @(
    ".git", ".vscode", ".idea", "node_modules", "vendor", "backup_pamsimas"
)

# File yang dibuang dari ZIP (dicocokkan pada nama file, level mana pun)
$excludeFiles = @(
    ".env",              # .env lokal TIDAK ikut; server pakai .env.production
    "database.sqlite",
    "*.zip",
    "diag-*.php",        # file diagnosa sementara
    ".DS_Store", "Thumbs.db"
)

# Folder kosong yang tetap harus ada di ZIP (dipakai Laravel)
# 'local' disk Laravel 11+ => storage/app/private (foto meteran: meter_photos/)
$requiredDirs = @(
    "storage/app", "storage/app/private", "storage/app/private/meter_photos",
    "storage/app/public",
    "storage/framework/cache/data", "storage/framework/sessions",
    "storage/framework/views", "storage/logs", "bootstrap/cache"
)

Write-Host "Collecting files..." -ForegroundColor Yellow
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$entriesToAdd = New-Object System.Collections.Generic.List[object]
$skippedDirs = New-Object System.Collections.Generic.List[string]

foreach ($item in (Get-ChildItem -Path $appRoot -Force)) {
    if ($item.PSIsContainer) {
        if ($excludeDirs -contains $item.Name) {
            $skippedDirs.Add($item.Name)
            continue
        }
        $candidates = Get-ChildItem -Path $item.FullName -Recurse -File -Force
    } else {
        $candidates = @($item)
    }

    foreach ($file in $candidates) {
        $skip = $false
        foreach ($pattern in $excludeFiles) {
            if ($file.Name -like $pattern) { $skip = $true; break }
        }
        if ($skip) { continue }

        $rel = $file.FullName.Substring($appRoot.Length).TrimStart('\') -replace '\\', '/'
        $entriesToAdd.Add([pscustomobject]@{ Full = $file.FullName; Rel = $rel }) | Out-Null
    }
}

Write-Host "Compressing $($entriesToAdd.Count) files (buang folder: $($skippedDirs -join ', '))..." -ForegroundColor Yellow
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, "Create")
try {
    foreach ($entry in $entriesToAdd) {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $entry.Full, $entry.Rel, "Optimal") | Out-Null
    }
    foreach ($dir in $requiredDirs) {
        $present = @($entriesToAdd.Rel | Where-Object { $_ -like "$dir/*" })
        if ($present.Count -eq 0) { $zip.CreateEntry("$dir/") | Out-Null }
    }
}
finally {
    $zip.Dispose()
}

Write-Host "Verifying..." -ForegroundColor Yellow
$zipCheck = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$entries = @($zipCheck.Entries | Where-Object { $_.FullName -notmatch '/$' })
$build = @($entries | Where-Object { $_.FullName -like 'public/build/*' })
$hasEnvProd = @($entries | Where-Object { $_.FullName -eq '.env.production' })
$hasEnv = @($entries | Where-Object { $_.FullName -eq '.env' })
$hasVendor = @($entries | Where-Object { $_.FullName -like 'vendor/*' })
$hasNode = @($entries | Where-Object { $_.FullName -like 'node_modules/*' })
$hasGit = @($entries | Where-Object { $_.FullName -like '.git/*' })
$hasBackup = @($entries | Where-Object { $_.FullName -like 'backup_pamsimas/*' })
$hasDiag = @($entries | Where-Object { $_.Name -like 'diag-*.php' })
$hasZip = @($entries | Where-Object { $_.Name -like '*.zip' })
$migrations = @($entries | Where-Object { $_.FullName -like 'database/migrations/*.php' })
$waGateway = @($entries | Where-Object { $_.FullName -like 'wa-gateway/*' })
$zipCheck.Dispose()

$zipSize = (Get-Item $zipPath).Length / 1MB
Write-Host ""
Write-Host "=== ZIP CREATED ===" -ForegroundColor Green
Write-Host "File        : $zipPath"
Write-Host "Size        : $([math]::Round($zipSize, 2)) MB"
Write-Host "Entries     : $($entries.Count) files"
Write-Host "Vite build  : $($build.Count) files di public/build (harus > 0)" -ForegroundColor $(if ($build.Count -gt 0) { "Green" } else { "Red" })
Write-Host "Migrations  : $($migrations.Count) file"
Write-Host "wa-gateway  : $($waGateway.Count) file"
Write-Host ".env.production: $($hasEnvProd.Count) (harus 1)   .env lokal: $($hasEnv.Count) (harus 0)"
Write-Host "Buang       : vendor=$($hasVendor.Count) node_modules=$($hasNode.Count) .git=$($hasGit.Count) backup=$($hasBackup.Count) diag=$($hasDiag.Count) zip=$($hasZip.Count) (semua harus 0)"
if ($build.Count -eq 0 -or $hasEnvProd.Count -ne 1 -or $hasEnv.Count -ne 0 -or $hasVendor.Count -ne 0 -or $hasNode.Count -ne 0 -or $hasGit.Count -ne 0 -or $hasBackup.Count -ne 0 -or $hasDiag.Count -ne 0 -or $hasZip.Count -ne 0) {
    Write-Host "VERIFIKASI GAGAL - periksa isi ZIP sebelum upload!" -ForegroundColor Red
    exit 1
}
Write-Host ""
Write-Host "Next: upload pamsimas_project.zip ke HestiaCP ->" -ForegroundColor Cyan
Write-Host "      unzip di /home/admin/web/pamsimas.selur.my.id/public_html/" -ForegroundColor Cyan
Write-Host "      set Custom Document Root -> .../public_html/public" -ForegroundColor Cyan
Write-Host "      jalankan scripts/deploy-hestiacp.sh" -ForegroundColor Cyan