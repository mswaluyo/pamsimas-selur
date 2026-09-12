#!/bin/bash
# ============================================================
# PAMSIMAS - Script Penyiapan Sebelum ZIP untuk aaPanel
# ============================================================
# Jalankan script ini di terminal Linux/Armbian sebelum upload
# ============================================================

echo "=== PAMSIMAS - Prepare for aaPanel Deployment ==="

# 1. Pastikan direktori storage writable
echo "[1/6] Setting storage permissions..."
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
mkdir -p storage/app/meter_photos
mkdir -p storage/app/.easyocr/model
mkdir -p storage/app/.easyocr/user_network
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p database

# 2. Buat file database.sqlite jika belum ada
echo "[2/6] Creating SQLite database file..."
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chmod 664 database/database.sqlite
    echo "  ✓ database/database.sqlite created"
else
    echo "  ✓ database/database.sqlite already exists"
fi

# 3. Copy .env.example ke .env jika belum ada
echo "[3/6] Checking .env file..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "  ✓ .env created from .env.example"
    echo "  ⚠ EDIT .env dan set APP_KEY dengan: php artisan key:generate"
else
    echo "  ✓ .env already exists"
fi

# 4. Optimize Laravel
echo "[4/6] Optimizing Laravel..."
php artisan config:cache 2>/dev/null || echo "  ⚠ config:cache failed (OK if .env not configured)"
php artisan route:cache 2>/dev/null || echo "  ⚠ route:cache failed (OK if .env not configured)"

# 5. Verifikasi Python OCR
echo "[5/6] Verifying Python OCR..."
PYTHON_CMD=$(grep "^PYTHON_PATH=" .env 2>/dev/null | cut -d '=' -f2 || echo "/usr/local/bin/python_ocr")
if [ -f "$PYTHON_CMD" ]; then
    echo "  ✓ Python OCR found: $PYTHON_CMD"
    $PYTHON_CMD -c "import cv2; import numpy; import easyocr; print('  ✓ All Python packages OK')" 2>/dev/null || echo "  ⚠ Python packages not installed"
else
    echo "  ⚠ Python OCR not found at: $PYTHON_CMD"
    echo "  Install dengan: sudo ln -s /usr/bin/python3 /usr/local/bin/python_ocr"
fi

# 6. Tampilkan ringkasan
echo ""
echo "=== SUMMARY ==="
echo "Storage path: $(pwd)/storage"
echo "Database: $(pwd)/database/database.sqlite"
echo "Python OCR: $PYTHON_CMD"
echo ""
echo "=== FILES TO EXCLUDE IN ZIP ==="
echo "- .env (contains secrets)"
echo "- .git (version control)"
echo "- node_modules (development)"
echo "- storage/*.key (encryption keys)"
echo "- .vscode, .idea (editor config)"
echo "- *.md (documentation)"
echo "- deploy/ (deployment scripts)"
echo "- backup_pamsimas/ (backup files)"
echo ""
echo "=== NEXT STEPS ==="
echo "1. Edit .env: nano .env"
echo "2. Generate key: php artisan key:generate"
echo "3. Create ZIP: zip -r pamsimas-aapanel.zip . -x '*.git*' -x 'node_modules/*' -x '.env'"
echo "4. Upload ZIP to aaPanel and extract"
echo "5. Run: php artisan migrate --force"
echo ""
echo "=== DONE ==="
