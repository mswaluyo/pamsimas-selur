@echo off
title PAMSIMAS DEPLOY TO SERVER
echo ===========================================
echo   PAMSIMAS - DEPLOY TO SERVER
echo ===========================================
echo.
echo Server : root@ssh.selur.my.id
echo Path   : /var/www/pamsimas.selur.my.id
echo.
echo.
echo [1/3] Starting Cloudflare Tunnel...
echo.
start "Cloudflare Tunnel" cmd /c "D:\cloudflared\cloudflared.exe" access ssh --hostname ssh.selur.my.id --url localhost:2222
echo    Tunnel started in new window.
echo    Waiting 5 seconds for tunnel to connect...
echo.
ping -n 6 127.0.0.1 > nul
echo.
echo [2/3] Building + ZIP + Upload...
echo.
cd /d D:\pamsimas.selur.my.id
call npm run deploy:full
echo.
echo.
echo ===========================================
echo   DEPLOY SELESAI!
echo ===========================================
echo.
echo Tutup window tunnel Cloudflare jika sudah tidak dipakai.
echo.
pause
