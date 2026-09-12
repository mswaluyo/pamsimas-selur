# Panduan Deployment PAMSIMAS

## Overview

Folder ini berisi script otomatis untuk memudahkan deployment ke hosting sendiri.

## Struktur Folder

```
deploy/
├── deploy.ps1          # Script build & package (Windows/Local)
├── deploy-server.sh    # Script setup di server (Linux/SSH)
└── README.md           # Dokumen ini
```

---

## Cara Penggunaan

### A. Di Lokal (Windows) - Script `deploy.ps1`

Script ini akan:
1. Build aset frontend (Vite)
2. Clear Laravel cache
3. Package semua file yang diperlukan ke folder `deploy/release/`
4. Membuat file ZIP siap upload

**Jalankan:**

```powershell
# Masuk ke folder deploy
cd D:\pamsimas.selur.my.id\deploy

# Jalankan script
.\deploy.ps1
```

**Output:**
- File ZIP di `deploy/release/pamsimas-YYYYMMDD-HHMMSS.zip`
- Upload file ZIP ini ke hosting Anda

**Optional:**
- Skip build: `.\deploy.ps1 -NoBuild`
- Ubah folder output: `.\deploy.ps1 -OutputDir "D:\lain\release"`

---

### B. Di Server (Linux) - Script `deploy-server.sh`

Script ini digunakan **setelah** upload file ke server, untuk:
1. Install composer dependency (jika belum ada)
2. Build frontend (jika public/build belum ada)
3. Setup storage link
4. Set permission yang benar
5. Cek environment

**Jalankan via SSH:**

```bash
# Masuk ke direktori aplikasi
cd /path/to/pamsima

# Berikan permission eksekusi
chmod +x deploy-server.sh

# Jalankan setup
./deploy-server.sh
```

---

## Langkah Lengkap Deployment

### Tahap 1: Package di Lokal

```powershell
cd D:\pamsimas.selur.my.id\deploy
.\deploy.ps1
```

### Tahap 2: Upload ke Hosting

Upload file ZIP hasil build ke hosting Anda:
- **FTP/SFTP**: Upload ke `public_html/` atau folder aplikasi
- **cPanel File Manager**: Upload dan extract di situ

### Tahap 3: Setup di Server

```bash
# Ekstrak jika upload ZIP
unzip pamsimas-*.zip -d /path/to/app

# Masuk ke direktori
cd /path/to/app

# Copy dan edit .env
cp .env.example .env
nano .env
```

### Tahap 4: Konfigurasi `.env`

Ubah sesuai konfigurasi hosting Anda:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_HOST=localhost
DB_DATABASE=pamsimas_db
DB_USERNAME=user_cpanel
DB_PASSWORD=password_mysql

# Generate APP_KEY baru jika perlu
# php artisan key:generate --force
```

### Tahap 5: Database & Migration

```bash
# Buat database via cPanel/phpMyAdmin
# Atau jalankan migrasi:
php artisan migrate --force

# (Optional) Seed data awal
php artisan db:seed --force
```

### Tahap 6: Permission & Testing

```bash
# Set permission
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Test aplikasi
curl -I https://domain-anda.com
```

---

## Troubleshooting

### Error 500 / White Screen
1. Cek `storage/logs/laravel.log`
2. Pastikan `.env` sudah sesuai
3. Pastikan `storage/` dan `bootstrap/cache/` writable (775)

### CSS/JS Tidak Muncul
1. Pastikan `public/build/` ada dan berisi file (hasil build)
2. Jika tidak ada, build ulang di lokal atau jalankan `npm run build` di server

### Database Connection Error
1. Cek konfigurasi di `.env`
2. Cek apakah database sudah dibuat di hosting
3. Cek user/password database di cPanel

### Permission Denied
```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

---

## Fitur Khusus

### WhatsApp Gateway

Jika ingin fitur WA berfungsi di server production:

1. **Node.js harus terinstall** di server
2. **Upload folder `wa-gateway/`** ke server (bukan di dalam folder Laravel)
3. **Edit config** di `wa-gateway/gateway.js`:
   - Ganti `WEBHOOK_URL` ke domain produksi
4. **Jalankan gateway** sebagai daemon:
   ```bash
   cd wa-gateway
   nohup node gateway.js > wa-gateway.log 2>&1 &
   ```
5. **Update `.env` Laravel**:
   ```env
   WA_GATEWAY_URL=https://domain-anda.com/api/send-wa
   WA_WEBHOOK_URL=https://domain-anda.com/api/api_wa
   WA_GATEWAY_NUMBER=6285157275866
   ```

---

## File yang Diupload

| Folder/File | Perlu? | Keterangan |
|-------------|--------|------------|
| `app/` | ✅ | Kode backend |
| `bootstrap/` | ✅ | Bootstrap Laravel |
| `config/` | ✅ | Konfigurasi |
| `database/migrations/` | ✅ | Struktur DB |
| `public/` | ✅ | Termasuk `build/` |
| `resources/` | ✅ | Blade views |
| `routes/` | ✅ | Route definitions |
| `storage/` | ✅ | Writable folder |
| `vendor/` | ⚠️ | Jika composer install tidak bisa di CLI hosting |
| `.env` | ✅ | Konfigurasi production |
| `artisan` | ✅ | Laravel CLI |
| `composer.json` | ⚠️ | Jika perlu install di server |
| `composer.lock` | ⚠️ | Jika perlu install di server |
| `deploy/` | ❌ | Tidak perlu diupload |

---

## Backup & Restore Database

### Export dari Lokal
```powershell
& 'D:\xampp\mysql\bin\mysqldump.exe' -u root pamsimas > backup_pamsimas.sql
```

### Import ke Hosting
```bash
mysql -u user_cpanel -p pamsimas_db < backup_pamsimas.sql
```
atau via phpMyAdmin di cPanel.

---

## Kontak & Bantuan

Jika ada masalah saat deployment:
1. Cek `storage/logs/laravel.log`
2. Pastikan semua dependency terinstall
3. Pastikan permission folder benar

---

*Dokumen ini diperbarui untuk deployment production.*
