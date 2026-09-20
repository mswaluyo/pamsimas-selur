# PAMSIMAS - Create Deployment ZIP for aaPanel
# ---------------------------------------------------------------------------
# Jalankan dari root proyek:
#   powershell -ExecutionPolicy Bypass -File scripts\create-zip.ps1
#
# Hasil ZIP disimpan di root proyek dan otomatis di-ignore oleh .gitignore (*.zip).
# Isi ZIP = root proyek TANPA: .env, .git*, deploy/, scripts/, vendor/,
# node_modules/, backup_pamsimas/, dokumen *.md, dan artefak cloud
# (Dockerfile, docker-compose*, *.yaml/*.toml, *.ps1, *.bat, dll).
# .env.production IKUT disertakan (di server di-copy menjadi .env).
#
# Folder besar (.git/, vendor/, node_modules/) tidak dibaca sama sekali,
# jadi script ini tidak memakan ruang disk berlebih saat kompresi.
#
# Setelah unzip di server (lihat DEPLOY_AAPANEL.md bagian 2 & 4):
#   composer install --optimize-autoloader --no-dev
#   php artisan migrate --force && php artisan db:seed --force
# ---------------------------------------------------------------------------
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression.FileSystem

Write-Host "=== PAMSIMAS - Create Deployment ZIP ===" -ForegroundColor Cyan

$appRoot = Split-Path -Parent $PSScriptRoot     # scripts/ -> root proyek
$zipName = "pamsimas-aapanel-$(Get-Date -Format 'yyyyMMdd-HHmmss').zip"
$zipPath = Join-Path $appRoot $zipName

Write-Host "Source : $appRoot"
Write-Host "Output : $zipPath" -ForegroundColor Yellow

# Folder yang dibuang dari ZIP (dicocokkan pada elemen path TOP-LEVEL)
$excludeDirs = @(
    ".git", ".vscode", ".idea", "deploy", "scripts",
    "node_modules", "vendor", "backup_pamsimas", "google-apps-script"
)

# File yang dibuang dari ZIP (dicocokkan pada nama file)
$excludeFiles = @(
    ".env", ".gitignore", ".gitattributes", ".editorconfig", ".dockerignore",
    "*.md", "LICENSE", "CHANGELOG*", "*.yaml", "*.yml", "*.toml",
    "Dockerfile", "docker-compose*", ".DS_Store", "Thumbs.db",
    "*.key", "*.log", "*.ps1", "*.bat", "*.zip"
)

# Folder kosong yang tetap harus ada di ZIP (dipakai Laravel)
$requiredDirs = @(
    "storage/app", "storage/framework/cache/data", "storage/framework/sessions",
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

Write-Host "Compressing $($entriesToAdd.Count) files (lewat: $($skippedDirs -join ', '))..." -ForegroundColor Yellow
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
$seeders = @($entries | Where-Object { $_.FullName -match '^database[/\\]seeders/.+\.php$' })
$build = @($entries | Where-Object { $_.FullName -match '^public[/\\]build/' })
$hasEnv = @($entries | Where-Object { $_.FullName -match '^\.env$' })
$hasEnvProd = @($entries | Where-Object { $_.FullName -match '^\.env\.production$' })
$hasVendor = @($entries | Where-Object { $_.FullName -match '^vendor/' })
$hasNode = @($entries | Where-Object { $_.FullName -match '^node_modules/' })
$hasGit = @($entries | Where-Object { $_.FullName -match '^\.git/' })
$zipCheck.Dispose()

$zipSize = (Get-Item $zipPath).Length / 1MB
Write-Host ""
Write-Host "=== ZIP CREATED ===" -ForegroundColor Green
Write-Host "File    : $zipPath"
Write-Host "Size    : $([math]::Round($zipSize, 2)) MB"
Write-Host "Entries : $($entries.Count) files"
Write-Host "Seeders : $($seeders.Count) files" -ForegroundColor $(if ($seeders.Count -gt 0) { "Green" } else { "Red" })
Write-Host "Vite    : $($build.Count) files in public/build" -ForegroundColor $(if ($build.Count -gt 0) { "Green" } else { "Yellow" })
Write-Host "Env     : .env=$($hasEnv.Count) (harus 0)  .env.production=$($hasEnvProd.Count) (harus 1)"
Write-Host "Buang   : vendor=$($hasVendor.Count) node_modules=$($hasNode.Count) .git=$($hasGit.Count) (semua harus 0)"
Write-Host ""
Write-Host "Next: upload ke aaPanel -> unzip -> ubah doc root ke /public"
Write-Host "      composer install --optimize-autoloader --no-dev"
Write-Host "      php artisan migrate --force && php artisan db:seed --force"
