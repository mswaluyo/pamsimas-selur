# Runbook Deployment PAMSIMAS — Ubuntu + aaPanel (Manual Upload)

Server produksi: Ubuntu 26.04 LTS · aaPanel · doc root `/www/wwwroot/pamsimas.selur.my.id/public`
User web server aaPanel: `www` · PHP: 8.3 (`/www/server/php/83`)

> ⚠️ **Repo ini PUBLIK.** Jangan menulis kredensial asli (sandi MySQL/root, API key, token)
> di dokumen mana pun di dalam repo. Semua kredensial di runbook ini memakai placeholder
> `<...>`; nilai sebenarnya diambil dari `.env` di server atau password manager.
> Contoh di bawah memakai `<SANDI_ROOT_MYSQL>` = sandi `root` MySQL server.

---

## 1. Prasyarat Server

| Komponen | Perintah cek | Target |
|---|---|---|
| PHP CLI | `php -v` | 8.2+ (terpasang 8.3) |
| Ekstensi PHP | `php -m \| grep -E "fileinfo\|mbstring\|gd\|curl\|zip\|pdo_mysql"` | semua muncul |
| Composer | `composer --version` | ≥ 2.2 (Laravel 12 butuh `composer-runtime-api ^2.2`) |
| Node.js | `node -v` | ≥ 18 (untuk build Vite & wa-gateway) |
| MySQL | `mysql --version` | 5.7+ / 8.0 |

> PHP 8.3 aaPanel default **tidak menyertakan `fileinfo`** → wajib dipasang via
> aaPanel → App Store → PHP 8.3 → Settings → Install Extensions → **fileinfo**,
> atau compile manual dari source `ext/fileinfo` (lihat bagian Troubleshooting).

---

## 2. Upload Proyek

Buat ZIP deploy dari lokal (otomatis membuang `node_modules/`, `vendor/`, `deploy/`, `scripts/`, `backup_pamsimas/`, `*.md`, dan `.env`; `.env.production` tetap ikut agar bisa di-copy menjadi `.env` di server):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\create-zip.ps1
```

Upload ZIP hasilnya ke:

```
/www/wwwroot/pamsimas.selur.my.id/
```

Lalu unzip via File Manager aaPanel. Struktur yang benar:

```
pamsimas.selur.my.id/
├── app/  bootstrap/  config/  database/  resources/  routes/  storage/
├── public/            <-- doc root website diarahkan ke folder INI
│   └── build/         <-- hasil npm run build (WAJIB ada)
├── wa-gateway/        <-- service Node.js terpisah (port 3000)
├── artisan  composer.json  composer.lock  package.json  vite.config.js
├── .env.production    <-- di-copy menjadi .env
└── .env               <-- hasil copy (JANGAN dari lokal)
```

**Setelah unzip, ubah document root website:**
aaPanel → Website → pamsimas.selur.my.id → **Site directory / Running directory → `/public`**

**Bootstrap server (opsional, sekali jalan):**
`scripts/setup-server.sh` tidak ikut di dalam ZIP (folder `scripts/` dikecualikan), jadi kirim manual lalu jalankan sebagai root:

```bash
scp scripts/setup-server.sh admin@SERVER-IP:/root/     # contoh SERVER-IP: 192.168.20.200
ssh admin@SERVER-IP "sudo bash /root/setup-server.sh"
```

Script ini idempotent: memeriksa ekstensi PHP & versi Composer, memperbaiki permission
`storage/` dan `bootstrap/cache`, menjalankan `migrate --seed`, lalu membersihkan cache.

---

## 3. Fix Database (error `SQLSTATE[HY000] [1045] Access denied`)

`.env.production` membawa kredensial server **lama** (`root` / `<SANDI_ROOT_MYSQL>`). Buat user baru:

```bash
ROOTPW=$(cat /www/server/panel/data/default_mysql_pwd)

mysql -u root -p"$ROOTPW" <<'SQL'
CREATE DATABASE IF NOT EXISTS pamsimas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pamsimas_user'@'localhost' IDENTIFIED BY 'GANTI_SANDI_KUAT';
GRANT ALL PRIVILEGES ON pamsimas_db.* TO 'pamsimas_user'@'localhost';
FLUSH PRIVILEGES;
SQL
```

> **Alternatif tanpa user baru — pakai `root` yang sudah ada.**
> `.env.production` sudah memuat `DB_USERNAME=root` + `DB_PASSWORD=<SANDI_ROOT_MYSQL>`, jadi kalau
> sandi root MySQL memang `<SANDI_ROOT_MYSQL>`, tidak ada yang perlu diubah sama sekali:
>
> ```bash
> PAMSIMAS_DB_USER=root PAMSIMAS_DB_PASS='<SANDI_ROOT_MYSQL>' bash /home/admin/setup-server.sh
> ```
>
> Kalau ditolak MySQL, samakan sandinya **dan** update file panel — kalau tidak,
> aaPanel kehilangan akses kelola database:
>
> ```bash
> mysql -u root -p"$(cat /www/server/panel/data/default_mysql_pwd)" \
>   -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '<SANDI_ROOT_MYSQL>'; FLUSH PRIVILEGES;"
> printf '%s' '<SANDI_ROOT_MYSQL>' > /www/server/panel/data/default_mysql_pwd && chmod 600 /www/server/panel/data/default_mysql_pwd
> ```

Update `.env`:

```bash
cd /www/wwwroot/pamsimas.selur.my.id
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=pamsimas_user/; s/^DB_PASSWORD=.*/DB_PASSWORD=GANTI_SANDI_KUAT/" .env
grep -E '^DB_' .env
```

> ⚠️ Setiap kali `.env` diubah, **wajib** rebuild config cache — kalau tidak, Laravel
> masih memakai nilai lama dari `bootstrap/cache/config.php`.

```bash
php artisan config:clear
php artisan config:cache
```

---

## 4. Install Dependency & Finalisasi

```bash
cd /www/wwwroot/pamsimas.selur.my.id

# 1) Dependency PHP (tanpa dev)
composer install --optimize-autoloader --no-dev

# 2) Pastikan .env ada
cp -n .env.production .env

# 3) App key (wajib - belum ada di .env.production)
php artisan key:generate

# 4) Struktur database
php artisan migrate --force

# 5) Optimasi produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6) Permission
chown -R www:www /www/wwwroot/pamsimas.selur.my.id
chmod -R 775 storage bootstrap/cache
chmod -R 755 public/build
```

Verifikasi:

```bash
php artisan about
php artisan migrate:status
curl -I http://127.0.0.1/ | head -3
tail -30 storage/logs/laravel.log
```

---

## 5. Queue Worker (Supervisor)

`.env`: `QUEUE_CONNECTION=database` → butuh worker (untuk kirim WA massal, OCR foto, dsb).

aaPanel → App Store → **Supervisor** → Add Daemon:

| Field | Nilai |
|---|---|
| Name | `pamsimas-queue` |
| Run User | `www` |
| Run Directory | `/www/wwwroot/pamsimas.selur.my.id` |
| Start Command | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| Process | `1` |
| Auto Start / Auto Restart | ON |

Verifikasi: `php artisan queue:work --once`, lalu `tail -f storage/logs/laravel.log`.

---

## 6. Scheduler (Cron)

aaPanel → **Cron** → Add Task → Type: **Shell Script**, Schedule: **setiap 1 menit**:

```bash
cd /www/wwwroot/pamsimas.selur.my.id && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. WhatsApp Gateway (wa-gateway — Node.js, port 3000)

Syarat: Node.js ≥ 18 + PM2 (`npm install -g pm2`).

```bash
cd /www/wwwroot/pamsimas.selur.my.id/wa-gateway
npm install
pm2 start gateway.js --name pamsimas-wa
pm2 save
pm2 startup systemd -u www --hp /home/www   # ikuti perintah yang dicetak PM2
```

Scan QR WhatsApp (sekali saja), tampilkan QR di terminal:

```bash
pm2 logs pamsimas-wa --lines 200
```

Kredensial tersimpan di `wa-gateway/auth_pamsimas/` — **jangan dihapus**, kalau hilang harus scan QR ulang.

Konfigurasi `.env` terkait:

```env
WA_GATEWAY_URL=https://pamsimas.selur.my.id/api/send-wa
WA_GATEWAY_SECRET=P4mS1m4s-T1rt0-Arg0-2025
WA_WEBHOOK_URL=https://pamsimas.selur.my.id/api/api_wa
WA_GATEWAY_NUMBER=6285157275866
```

> Setelah mengubah nilai `WA_*` di `.env`: `php artisan config:cache` ulang dan `pm2 restart pamsimas-wa`.

---

## 8. Cloudflare (DNS + SSL) — urutan yang benar

**a. DNS** (Cloudflare Dashboard → DNS → Records):

| Type | Name | Content | Proxy |
|---|---|---|---|
| A | `pamsimas` | `<IP publik VPS>` | Proxied (oranye) untuk produksi; DNS-only (abu) saat debugging |

**b. SSL/TLS mode:** Cloudflare → SSL/TLS → Overview → pilih **Full** atau **Full (Strict)**.
❌ JANGAN **Flexible** → menyebabkan redirect loop (`https → http → https`).

- **Full (Strict)** → pasang juga SSL Let's Encrypt di aaPanel (Website → SSL → Let's Encrypt).
- **Full** → boleh Self-Signed SSL di aaPanel.

**c. Tambahan:** SSL/TLS → Edge Certificates → **Always Use HTTPS** = ON.

---

## 9. Troubleshooting (error yang sudah pernah muncul)

| Error | Penyebab | Solusi |
|---|---|---|
| `cp: cannot create regular file '.env': Permission denied` | Dijalankan sebagai `admin`, bukan root | `sudo -i` lalu ulangi |
| `chown: Operation not permitted` | Bukan root | `sudo -i` lalu ulangi |
| `Failed opening required .../vendor/autoload.php` | `vendor/` tidak diikutkan ZIP | `composer install --optimize-autoloader --no-dev` |
| `Your lock file does not contain a compatible set of packages` (`composer-runtime-api`) | Composer server < 2.2 | `composer self-update` atau pasang composer.phar terbaru ke `/usr/local/bin` |
| `league/flysystem-local requires ext-fileinfo` | PHP 8.3 aaPanel tanpa `fileinfo` | aaPanel → PHP 8.3 → Install Extensions → fileinfo (atau compile dari source `ext/fileinfo`) |
| `Module "zip" is already loaded` | Duplikat `extension=zip` di php.ini | Aman diabaikan; boleh dibersihkan di `php-cli.ini`/`php.ini` |
| `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'` | Kredensial DB server lama di `.env` | Buat user DB baru + update `.env` + `php artisan config:cache` |
| Halaman blank/500 padahal migrate sukses | Permission atau config cache basi | `chown -R www:www storage bootstrap/cache; chmod -R 775 ...` lalu `php artisan config:clear` |
| CSS/JS tidak muncul | `public/build/` tidak ada / doc root salah | Pastikan `public/build/manifest.json` ada & doc root = `public/` |
| QR WA tidak muncul di log PM2 | Log terpotong / belum restart | `pm2 logs pamsimas-wa --lines 200` atau jalankan manual sekali `node gateway.js` |

---

## 10. Urutan Perintah Ringkas (copy-paste)

### Opsi cepat (satu perintah)

```bash
sudo -i
/etc/init.d/mysqld start                 # MySQL wajib jalan lebih dulu

# (a) pakai user DB khusus aplikasi:
bash /home/admin/setup-server.sh

# (b) ATAU pakai user root MySQL yang sudah ada, sandi root tidak diubah:
PAMSIMAS_DB_USER=root PAMSIMAS_DB_PASS='<SANDI_ROOT_MYSQL>' bash /home/admin/setup-server.sh
```

Sandbox script itu: cek ekstensi PHP, versi Composer, MySQL, database+user,
`.env`, `composer install`, `key:generate`, `migrate`, `db:seed`, cache, permission, verifikasi.
Upload dulu kalau belum ada (`scp scripts/setup-server.sh admin@SERVER-IP:/home/admin/`).

### Opsi manual (kalau ingin dikontrol sendiri)

```bash
# --- 0. Masuk root. User 'admin' TIDAK boleh menulis ke folder aplikasi ---
sudo -i
cd /www/wwwroot/pamsimas.selur.my.id

# --- 1. Pastikan MySQL jalan (aaPanel mengelolanya lewat init.d, bukan systemd) ---
/etc/init.d/mysqld start
mysqladmin -u root -p"$(cat /www/server/panel/data/default_mysql_pwd)" status | head -1

# --- 2. Bersihkan sisa ZIP & file diagnosa (tidak ikut ke git) ---
rm -f pamsimas-aapanel-*.zip diag-cfg*.php diag-config*.php

# --- 3. Lihat database yang SUDAH ADA dulu - jangan dihapus ---
mysql -u root -p"$(cat /www/server/panel/data/default_mysql_pwd)" -e "SHOW DATABASES;"

# --- 4. Database + user aplikasi (idempotent, aman diulang) ---
mysql -u root -p"$(cat /www/server/panel/data/default_mysql_pwd)" -e "
CREATE DATABASE IF NOT EXISTS pamsimas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pamsimas_user'@'localhost' IDENTIFIED BY 'GANTI_SANDI_KUAT';
ALTER USER 'pamsimas_user'@'localhost' IDENTIFIED BY 'GANTI_SANDI_KUAT';
GRANT ALL PRIVILEGES ON pamsimas_db.* TO 'pamsimas_user'@'localhost';
FLUSH PRIVILEGES;"

# --- 5. .env dari .env.production, diarahkan ke user DB baru ---
cp -n .env.production .env
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/; s/^DB_HOST=.*/DB_HOST=127.0.0.1/; s/^DB_PORT=.*/DB_PORT=3306/; s/^DB_DATABASE=.*/DB_DATABASE=pamsimas_db/; s/^DB_USERNAME=.*/DB_USERNAME=pamsimas_user/; s/^DB_PASSWORD=.*/DB_PASSWORD=GANTI_SANDI_KUAT/" .env
grep -E '^DB_' .env

# --- 6. Dependency, app key, migrasi, seed ---
export COMPOSER_ALLOW_SUPERUSER=1
composer install --optimize-autoloader --no-dev
php artisan key:generate --force
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

# --- 7. Optimasi produksi + permission ---
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www:www /www/wwwroot/pamsimas.selur.my.id
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod -R 755 public/build

# --- 8. Verifikasi ---
php artisan about | head -15
php artisan migrate:status | tail -5
curl -sS -o /dev/null -w "http=%{http_code}\n" -H 'Host: pamsimas.selur.my.id' http://127.0.0.1/
tail -20 storage/logs/laravel.log
```

> `check-db.php` hanya untuk setup **SQLite** (memakai `sqlite_master`/`PRAGMA`),
> jadi tidak berguna di server MySQL ini. Untuk mereset sandi user admin di MySQL:
>
> ```bash
> HASH=$(php -r "echo password_hash('admin123', PASSWORD_BCRYPT);")
> mysql -u pamsimas_user -p'GANTI_SANDI_KUAT' pamsimas_db \
>   -e "UPDATE users SET password='$HASH' WHERE username='admin';"
> ```

