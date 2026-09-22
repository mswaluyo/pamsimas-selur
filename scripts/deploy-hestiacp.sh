#!/usr/bin/env bash
# =====================================================================
# PAMSIMAS - Deployment HestiaCP (pamsimas.selur.my.id)
# Jalankan SETELAH pamsimas_project.zip diekstrak di:
#   /home/admin/web/pamsimas.selur.my.id/public_html/
#
# Cara jalankan (pilih salah satu):
#   sudo bash scripts/deploy-hestiacp.sh     # sebagai root
#   bash scripts/deploy-hestiacp.sh          # sebagai user admin
#
# SEBELUM jalan: edit DB_PASS di bawah, dan pastikan database
#   admin_pamsimas_db + user admin_pamsimas_user sudah dibuat
#   (via HestiaCP -> DB, atau biarkan skrip buat otomatis dari
#   /usr/local/hestia/conf/mysql.conf)
# =====================================================================
set -euo pipefail

DB_NAME='admin_pamsimas_db'
DB_USER='admin_pamsimas_user'
DB_PASS='GANTI_SANDI_KUAT'      # <-- WAJIB DIGANTI sebelum eksekusi

cd /home/admin/web/pamsimas.selur.my.id/public_html

# Deteksi PHP 8.x (HestiaCP multi-PHP: php8.2 / php8.3 / php)
PHP_BIN="$(command -v php8.3 || command -v php8.2 || command -v php)"
echo "PHP  : $($PHP_BIN -v | head -1)"

# COMPOSER: pakai composer global, fallback ke composer.phar (ikut dalam ZIP)
if command -v composer >/dev/null 2>&1; then
    COMPOSER="composer"
elif [ -f composer.phar ]; then
    COMPOSER="php composer.phar"
else
    echo "ERROR: composer & composer.phar tidak ditemukan" >&2; exit 1
fi

# --- 1. .env dari .env.production (tidak menimpa .env yang sudah ada) ---
cp -n .env.production .env

# --- 2. Kredensial database (admin_pamsimas_db & admin_pamsimas_user) ---
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|; s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|; s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
grep -E '^DB_|^APP_URL' .env

# --- 3. Database & user MariaDB (idempotent) ---
# Pakai kredensial MySQL milik HestiaCP jika tersedia
if [ -f /usr/local/hestia/conf/mysql.conf ]; then
    MYSQL_HOST="$(grep -oP '^HOST="\K[^"]+' /usr/local/hestia/conf/mysql.conf || echo localhost)"
    MYSQL_USER="$(grep -oP '^USER="\K[^"]+' /usr/local/hestia/conf/mysql.conf || echo admin)"
    MYSQL_PASS="$(grep -oP '^PASSWORD="\K[^"]+' /usr/local/hestia/conf/mysql.conf || true)"
    mysql -h "${MYSQL_HOST:-localhost}" -u "${MYSQL_USER:-admin}" -p"${MYSQL_PASS}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
fi

# --- 4. Dependency produksi ---
export COMPOSER_ALLOW_SUPERUSER=1
$COMPOSER install --optimize-autoloader --no-dev

# --- 5. APP KEY ---
$PHP_BIN artisan key:generate --force

# --- 6. Migrasi + seed ---
$PHP_BIN artisan migrate --force && $PHP_BIN artisan db:seed --force

# --- 7. Cache produksi ---
$PHP_BIN artisan config:cache && $PHP_BIN artisan route:cache && $PHP_BIN artisan view:cache

# --- 8. Kepemilikan & permission ---
chown -R admin:admin .
chmod -R 775 storage bootstrap/cache

# --- 9. Verifikasi ---
$PHP_BIN artisan about | head -15
$PHP_BIN artisan migrate:status | tail -5
curl -sS -o /dev/null -w "HTTP %{http_code}\n" -H 'Host: pamsimas.selur.my.id' http://127.0.0.1/
echo "=== DEPLOY SELESAI ==="
