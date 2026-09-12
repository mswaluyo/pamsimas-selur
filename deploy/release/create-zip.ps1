# PAMSIMAS - Create Deployment ZIP for aaPanel
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression.FileSystem

Write-Host "=== PAMSIMAS - Create Deployment ZIP ===" -ForegroundColor Cyan

$zipName = "pamsimas-aapanel-$(Get-Date -Format 'yyyyMMdd-HHmmss').zip"
$zipPath = Split-Path -Parent $PSScriptRoot | Split-Path -Parent | Join-Path -ChildPath $zipName

Write-Host "Output: $zipPath" -ForegroundColor Yellow

$exclude = @(
    ".env", ".git", ".gitignore", ".vscode", ".idea",
    "node_modules", "vendor\bin\phpunit", "*.md", "LICENSE", "CHANGELOG*",
    "deploy", "backup_pamsimas", "google-apps-script",
    "*.yaml", "*.toml", "Dockerfile", "docker-compose*", ".dockerignore",
    ".DS_Store", "Thumbs.db", "storage\*.key", "storage\pail",
    "*.log", "npm-debug.log", "yarn-error.log",
    "create-zip.ps1", "prepare-for-aapanel.ps1", "README_DEPLOY.md"
)

Write-Host "Checking seeders..." -ForegroundColor Yellow
$seederFiles = Get-ChildItem -Path "database\seeders" -Filter "*.php" -ErrorAction SilentlyContinue
Write-Host "  Found $($seederFiles.Count) seeder files"

Write-Host "Compressing..." -ForegroundColor Yellow
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

[System.IO.Compression.ZipFile]::CreateFromDirectory($PSScriptRoot, $zipPath, "Optimal", $false)

Write-Host "Removing excluded files..." -ForegroundColor Yellow
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, "Update")
foreach ($pattern in $exclude) {
    $entries = $zip.Entries | Where-Object { $_.Name -like $pattern -or $_.FullName -like "*\$pattern" }
    foreach ($entry in $entries) { $entry.Delete() }
}
$zip.Dispose()

Write-Host "Verifying..." -ForegroundColor Yellow
$zipCheck = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$seeders = @($zipCheck.Entries | Where-Object { $_.FullName -match "database[/\\\\]seeders" })
$zipCheck.Dispose()

$zipSize = (Get-Item $zipPath).Length / 1MB
Write-Host ""
Write-Host "=== ZIP CREATED ===" -ForegroundColor Green
Write-Host "File: $zipPath"
Write-Host "Size: $([math]::Round($zipSize, 2)) MB"
Write-Host "Seeders: $($seeders.Count) files" -ForegroundColor $(if($seeders.Count -gt 0){"Green"}else{"Red"})
Write-Host ""
Write-Host "Next: Upload to aaPanel, then run:"
Write-Host "  php artisan migrate --force"
Write-Host "  php artisan db:seed --force"
