@echo off
title PAMSIMAS - Cloudflare SSH Tunnel
echo ========================================
echo   PAMSIMAS SSH Tunnel
echo ========================================
echo.
echo Tunnel ke ssh.selur.my.id
echo Port lokal: 2222
echo.
echo JANGAN TUTUP window ini saat deploy!
echo Tekan Ctrl+C untuk berhenti.
echo.
echo ========================================
echo.
"C:\cloudflared\cloudflared.exe" access ssh --hostname ssh.selur.my.id --url localhost:2222
pause
