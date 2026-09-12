#!/bin/bash
set -e

echo "=== PAMSIMAS Docker Entrypoint ==="

# ============================================================
# 1. Pastikan .env ada (copy dari .env.example jika belum ada)
# ============================================================
cd /var/www/html

if [ ! -f .env ]; then
    echo "[INFO] .env tidak ditemukan, menyalin dari .env.example..."
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "[WARN] .env.example juga tidak ditemukan. Pastikan sudah di-set via environment variables."
    fi
fi

# ============================================================
# 2. Generate APP_KEY jika belum ada
# ============================================================
if ! grep -q "^APP_KEY=" .env || grep -q "^APP_KEY=$" .env; then
    echo "[INFO] Generating APP_KEY..."
    php artisan key:generate --no-interaction --force
fi

# ============================================================
# 3. Set permissions
# ============================================================
echo "[INFO] Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# ============================================================
# 4. Buat storage link jika belum ada
# ============================================================
if [ ! -L /var/www/html/public/storage ]; then
    echo "[INFO] Creating storage link..."
    php artisan storage:link --no-interaction 2>/dev/null || true
fi

# ============================================================
# 5. Pastikan direktori penting ada
# ============================================================
mkdir -p /var/www/html/storage/app/meter_photos
mkdir -p /var/www/html/storage/app/.easyocr/model
mkdir -p /var/www/html/storage/app/.easyocr/user_network
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# ============================================================
# 6. Clear & cache config (untuk memastikan env vars terbaca)
# ============================================================
echo "[INFO] Caching Laravel config..."
php artisan config:cache --no-interaction 2>/dev/null || true
php artisan route:cache --no-interaction 2>/dev/null || true
php artisan view:cache --no-interaction 2>/dev/null || true

# ============================================================
# 7. Jalankan database migration (jika diizinkan)
# ============================================================
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[INFO] Running database migrations..."
    php artisan migrate --force --no-interaction 2>/dev/null || echo "[WARN] Migration failed or skipped."
fi

# ============================================================
# 8. Konfigurasi PHP-FPM socket directory
# ============================================================
mkdir -p /run/php
chown www-data:www-data /run/php

# ============================================================
# 9. Pastikan disable_functions tidak blokir exec/shell_exec
# ============================================================
INI_FILE=$(php -r "echo php_ini_loaded_file();")
if [ -f "$INI_FILE" ]; then
    # Hapus exec, shell_exec, proc_open dari disable_functions jika ada
    sed -i 's/disable_functions =.*/disable_functions = passthru,system,popen,parse_ini_file,show_source/' "$INI_FILE"
fi

# ============================================================
# 10. Verifikasi Python OCR tersedia
# ============================================================
echo "[INFO] Verifying Python OCR..."
if [ -f /opt/venv/bin/python3 ]; then
    PYTHON_VERSION=$(/opt/venv/bin/python3 --version 2>&1)
    echo "[INFO] Python venv: $PYTHON_VERSION"
    
    # Test import
    /opt/venv/bin/python3 -c "
import cv2
import numpy
import easyocr
print(f'  ✓ OpenCV: {cv2.__version__}')
print(f'  ✓ NumPy: {numpy.__version__}')
print(f'  ✓ EasyOCR: available')
print(f'  ✓ PyTorch: available')
" 2>&1 || echo "[WARN] Python OCR verification failed"
else
    echo "[WARN] Python venv not found at /opt/venv/bin/python3"
fi

# ============================================================
# 11. Test PHP bisa panggil Python (integrasi OCR)
# ============================================================
echo "[INFO] Testing PHP->Python integration..."
php -r "
\$output = [];
\$returnCode = 0;
exec(escapeshellcmd('/opt/venv/bin/python3 --version') . ' 2>&1', \$output, \$returnCode);
echo '  → Python via exec(): ' . implode(' ', \$output) . ' (exit: ' . \$returnCode . ')\n';
"

# ============================================================
# 12. Start PHP-FPM di background
# ============================================================
echo "[INFO] Starting PHP-FPM..."
php-fpm8.2

# Tunggu sebentar pastikan PHP-FPM ready
sleep 2

# ============================================================
# 13. Start Nginx di foreground (blocking)
# ============================================================
echo "[INFO] Starting Nginx on port 8080..."
echo "=== PAMSIMAS Ready ==="

# ============================================================
# 13. Replace PORT in nginx config and start
# ============================================================
echo "[INFO] Starting Nginx on port ${PORT:-8080}..."
echo "=== PAMSIMAS Ready ==="

# Use envsubst to replace ${PORT} in nginx config
envsubst '\$PORT' < /etc/nginx/sites-available/default > /tmp/nginx-default
cp /tmp/nginx-default /etc/nginx/sites-available/default

# Create nginx PID directory
mkdir -p /run/nginx

exec nginx -g 'daemon off;'
exec nginx -g 'daemon off;'
