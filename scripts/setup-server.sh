#!/usr/bin/env bash
# ==========================================================================
# PAMSIMAS - Setup Server (Ubuntu + aaPanel)
# Jalankan sebagai root:  bash scripts/setup-server.sh
# Idempotent: boleh dijalankan berulang kali.
#
# Opsi (env):
#   PAMSIMAS_DB_NAME  nama database            (default: pamsimas_db)
#   PAMSIMAS_DB_USER  user DB                  (default: pamsimas_user;
#                                              isi 'root' untuk memakai user root yang ada)
#   PAMSIMAS_DB_PASS  sandi DB                 (default: SandiKuat2026!)
#
# Contoh memakai user root MySQL yang sudah ada + sandi yang sudah dipakai .env
#   (ganti '<SANDI_ROOT_MYSQL>' dengan sandi asli — JANGAN disimpan di repo publik ini):
#   PAMSIMAS_DB_USER=root PAMSIMAS_DB_PASS='<SANDI_ROOT_MYSQL>' bash /home/admin/setup-server.sh
# ==========================================================================
set -euo pipefail

APP_DIR="/www/wwwroot/pamsimas.selur.my.id"
WEB_USER="www"
DB_NAME="${PAMSIMAS_DB_NAME:-pamsimas_db}"
# Default: user khusus aplikasi. Isi PAMSIMAS_DB_USER=root untuk memakai user
# root MySQL yang sudah ada (sandi root tidak akan diubah oleh script ini).
DB_USER="${PAMSIMAS_DB_USER:-pamsimas_user}"
DB_PASS="${PAMSIMAS_DB_PASS:-SandiKuat2026!}"
ROOT_PW_FILE="/www/server/panel/data/default_mysql_pwd"

log() { echo -e "\n\033[1;36m==> $*\033[0m"; }
die() { echo -e "\033[1;31m[ERROR] $*\033[0m" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Script ini harus dijalankan sebagai root (sudo -i)."
[[ -d "$APP_DIR" ]] || die "Folder aplikasi tidak ditemukan: $APP_DIR"
cd "$APP_DIR"

# --------------------------------------------------------------------------
# 1. Cek ekstensi PHP wajib
# --------------------------------------------------------------------------
log "1. Memeriksa ekstensi PHP"
MISSING=()
for ext in fileinfo mbstring gd curl zip openssl pdo_mysql tokenizer xml ctype json; do
    php -m | grep -qi "^${ext}$" || MISSING+=("$ext")
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
    die "Ekstensi PHP belum aktif: ${MISSING[*]}
   -> aktifkan via aaPanel: App Store > PHP 8.3 > Settings > Install Extensions
   -> atau tambahkan ke /www/server/php/83/etc/php-cli.ini dan php.ini"
fi
echo "    OK: semua ekstensi wajib tersedia"

# --------------------------------------------------------------------------
# 2. Cek versi Composer (Laravel 12 butuh runtime-api >= 2.2)
# --------------------------------------------------------------------------
log "2. Memeriksa Composer"
COMPOSER_MAJOR_MINOR="$(composer --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+' | head -1 || true)"
echo "    Composer terdeteksi: ${COMPOSER_MAJOR_MINOR:-tidak diketahui}"
LOWEST="$(printf '%s\n' "${COMPOSER_MAJOR_MINOR:-0.0}" "2.2" | sort -V | head -n1)"
if [[ "$LOWEST" != "2.2" ]]; then
    die "Composer terlalu lama (< 2.2). Jalankan: composer self-update"
fi

# --------------------------------------------------------------------------
# 3. Database + user aplikasi
# --------------------------------------------------------------------------
log "3. Menyiapkan database & user MySQL"
[[ -f "$ROOT_PW_FILE" ]] || die "Sandi root MySQL aaPanel tidak ditemukan: $ROOT_PW_FILE"
MYSQL_ROOT_PW="$(cat "$ROOT_PW_FILE")"

# MySQL harus jalan; kalau tidak, berhenti dengan instruksi yang jelas
if ! mysqladmin -u root -p"$MYSQL_ROOT_PW" status >/dev/null 2>&1; then
    die "MySQL tidak bisa diakses dengan sandi root aaPanel.
   -> Nyalakan MySQL :  /etc/init.d/mysqld start   (atau aaPanel > App Store > MySQL > Start)
   -> Cek sandi root :  cat $ROOT_PW_FILE"
fi
echo "    OK: MySQL aktif"

if [[ "$DB_USER" == "root" ]]; then
    # Mode "pakai user root yang sudah ada": sandi root TIDAK diubah.
    # Kalau PAMSIMAS_DB_PASS tidak diisi, .env memakai sandi root aaPanel asli.
    DB_PASS="${PAMSIMAS_DB_PASS:-$MYSQL_ROOT_PW}"

    mysql -u root -p"$MYSQL_ROOT_PW" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
SQL

    # Pastikan sandi yang akan ditulis ke .env benar-benar diterima MySQL
    if ! mysql -u root -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
        die "Sandi root yang akan dipakai .env (DB_PASSWORD=${DB_PASS}) ditolak MySQL.
   -> Samakan sandi root ke nilai itu:
        mysql -u root -p'$MYSQL_ROOT_PW' -e \"ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_PASS}'; FLUSH PRIVILEGES;\"
        printf '%s' '${DB_PASS}' > $ROOT_PW_FILE && chmod 600 $ROOT_PW_FILE
        (baris ke-2 wajib supaya aaPanel tetap bisa mengelola database)
   -> Atau jalankan ulang TANPA PAMSIMAS_DB_PASS agar memakai sandi root aaPanel asli."
    fi
    echo "    OK: database ${DB_NAME} siap (memakai user root yang sudah ada)"
else
    mysql -u root -p"$MYSQL_ROOT_PW" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    echo "    OK: database ${DB_NAME} + user ${DB_USER} siap"
fi

# --------------------------------------------------------------------------
# 4. File .env
# --------------------------------------------------------------------------
log "4. Menyiapkan file .env"
if [[ ! -f .env ]]; then
    [[ -f .env.production ]] || die ".env dan .env.production keduanya tidak ada"
    cp .env.production .env
    echo "    .env dibuat dari .env.production"
else
    echo "    .env sudah ada (tidak ditimpa)"
fi

sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" .env
sed -i "s/^DB_PORT=.*/DB_PORT=3306/" .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
grep -E '^DB_' .env | sed 's/^/    /'

# --------------------------------------------------------------------------
# 5. Dependency PHP
# --------------------------------------------------------------------------
log "5. composer install (tanpa dev)"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --optimize-autoloader --no-dev

# --------------------------------------------------------------------------
# 6. App key
# --------------------------------------------------------------------------
log "6. APP_KEY"
if grep -qE '^APP_KEY=$' .env || ! grep -q '^APP_KEY=' .env; then
    php artisan key:generate --force
else
    echo "    APP_KEY sudah terisi"
fi

# --------------------------------------------------------------------------
# 7. Migrasi database
# --------------------------------------------------------------------------
log "7. php artisan migrate"
php artisan config:clear
php artisan migrate --force

log "7b. php artisan db:seed (akun & master data awal)"
php artisan db:seed --force || echo "    PERINGATAN: db:seed gagal - jalankan manual: php artisan db:seed --force"

# --------------------------------------------------------------------------
# 8. Optimasi produksi
# --------------------------------------------------------------------------
log "8. Cache konfigurasi/route/view"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --------------------------------------------------------------------------
# 9. Permission
# --------------------------------------------------------------------------
log "9. Permission"
mkdir -p storage/app/meter_photos storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod -R 755 public/build 2>/dev/null || true

# --------------------------------------------------------------------------
# 10. Verifikasi
# --------------------------------------------------------------------------
log "10. Verifikasi"
php artisan about || true
echo
echo "    Tabel di database:"
mysql -u "$DB_USER" -p"$DB_PASS" -N -e "SHOW TABLES;" "$DB_NAME" | sed 's/^/      - /'

log "SELESAI. Langkah lanjutan: Supervisor (queue), Cron (schedule:run), PM2 (wa-gateway), SSL Cloudflare."
