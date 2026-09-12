#!/bin/bash
# Script Setup di Server Hosting
# Jalankan setelah upload file ke server

set -e

echo "==========================================="
echo "  PAMSIMAS SERVER SETUP"
echo "==========================================="
echo ""

# 1. Cek dependency
echo "[1/6] Cek requirement..."
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP tidak ditemukan!"
    exit 1
fi

if ! command -v composer &> /dev/null; then
    echo "WARNING: Composer tidak ditemukan. Silakan install manual."
fi

php -v | head -1
echo ""

# 2. Install dependency jika belum ada
if [ ! -d "vendor" ]; then
    echo "[2/6] Install PHP dependency (composer install)..."
    composer install --optimize-autoloader --no-dev --no-interaction
    echo "      Selesai."
else
    echo "[2/6] Vendor sudah ada, skip."
fi

# 3. Build frontend jika belum ada
if [ ! -d "public/build" ]; then
    echo "[3/6] Build frontend assets (npm build)..."
    if command -v node &> /dev/null && command -v npm &> /dev/null; then
        npm install
        npm run build
        echo "      Selesai."
    else
        echo "ERROR: Node.js/NPM tidak ditemukan. Upload folder public/build dari lokal."
        exit 1
    fi
else
    echo "[3/6] public/build sudah ada, skip."
fi

# 4. Link storage
echo "[4/6] Setup storage link..."
php artisan storage:link 2>/dev/null || echo "      (sudah ada atau tidak bisa di-link)"

# 5. Set permission
echo "[5/6] Set permissions..."
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chmod 755 artisan
echo "      Selesai."

# 6. Test environment
echo "[6/6] Test environment..."
if [ ! -f ".env" ]; then
    echo "ERROR: File .env tidak ditemukan!"
    echo "Silakan copy .env.example ke .env dan atur konfigurasinya."
    exit 1
fi

echo ""
echo "==========================================="
echo "  SETUP COMPLETE!"
echo "==========================================="
echo ""
echo "Selanjutnya:"
echo "  1. Edit .env dengan konfigurasi database Anda"
echo "  2. Jalankan: php artisan migrate --force"
echo "  3. Akses aplikasi via browser"
echo ""
echo "Jika ada error, cek: storage/logs/laravel.log"
echo ""
