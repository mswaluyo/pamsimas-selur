@echo off
title PAMSIMAS SFTP DEPLOY
echo ===========================================
echo   PAMSIMAS - SFTP DEPLOY
echo ===========================================
echo.
echo Server : root@ssh.selur.my.id
echo Path   : /var/www/pamsimas.selur.my.id
echo.
echo.
echo [1/4] Starting Cloudflare Tunnel...
echo.
start "Cloudflare Tunnel" cmd /c "D:\cloudflared\cloudflared.exe" access ssh --hostname ssh.selur.my.id --url localhost:2222
echo    Waiting for tunnel...
ping -n 6 127.0.0.1 > nul
echo.
echo [2/4] Building frontend...
cd /d D:\pamsimas.selur.my.id
call npm run build
echo.
echo [3/4] Uploading files via SFTP...
echo.
set PSFTP=psftp
where psftp > nul 2>&1
if %errorlevel% neq 0 (
    echo    psftp not found, trying scp...
    goto :use_scp
)
:use_psftp
    echo    Using psftp...
    echo open localhost > sftp_cmd.txt
    echo cd /var/www/pamsimas.selur.my.id >> sftp_cmd.txt
    echo lcd D:\pamsimas.selur.my.id >> sftp_cmd.txt
    echo put -r app >> sftp_cmd.txt
    echo put -r bootstrap >> sftp_cmd.txt
    echo put -r config >> sftp_cmd.txt
    echo put -r database >> sftp_cmd.txt
    echo put -r public >> sftp_cmd.txt
    echo put -r resources >> sftp_cmd.txt
    echo put -r routes >> sftp_cmd.txt
    echo put -r vendor >> sftp_cmd.txt
    echo put artisan >> sftp_cmd.txt
    echo put composer.json >> sftp_cmd.txt
    echo put composer.lock >> sftp_cmd.txt
    echo bye >> sftp_cmd.txt
    psftp -b sftp_cmd.txt
    del sftp_cmd.txt
    goto :server_setup
:use_scp
    echo    Using scp...
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\app root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\bootstrap root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\config root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\database root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\public root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\resources root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\routes root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -r D:\pamsimas.selur.my.id\vendor root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null D:\pamsimas.selur.my.id\artisan root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null D:\pamsimas.selur.my.id\composer.json root@localhost:/var/www/pamsimas.selur.my.id/
    scp -P 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null D:\pamsimas.selur.my.id\composer.lock root@localhost:/var/www/pamsimas.selur.my.id/
:server_setup
echo.
echo [4/4] Server setup...
echo.
ssh -p 2222 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@localhost "cd /var/www/pamsimas.selur.my.id && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear && chmod -R 775 storage/ bootstrap/cache/ && php artisan storage:link 2>/dev/null || true && php artisan migrate --force 2>&1 | tail -3"
echo.
echo.
echo ===========================================
echo   DEPLOY SELESAI!
echo ===========================================
echo.
echo Tutup window tunnel Cloudflare.
echo.
pause
