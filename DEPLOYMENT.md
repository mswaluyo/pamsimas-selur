# Panduan Deployment ke Hosting Sendiri

## Persiapan Aplikasi

### 1. Ubah Konfigurasi untuk Production

Edit file `.env` di lokal:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_CONNECTION=mysql
DB_HOST=localhost (atau host database dari hosting)
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_database

SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 2. Generate App Key Baru (jika perlu)

```bash
php artisan key:generate
```

### 3. Build Aset Frontend

```bash
npm install
npm run build
```

Output akan tersimpan di `public/build/`

### 4. Optimasi Composer

```bash
composer install --optimize-autoloader --no-dev
```

---

## Upload ke Hosting

### Struktur Folder yang Diupload

Upload folder berikut ke hosting (biasanya ke `public_html/` atau `htdocs/`):

```
pamsimas/
├── app/
├── bootstrap/
├── config/
├── database/
│   └── migrations/
├── public/
│   └── build/          # Aset yang sudah di-build
├── resources/
├── routes/
├── storage/            # HARUS bisa ditulis web server
│   ├── app/meter_photos/
│   ├── framework/
│   └── logs/
├── vendor/             # Hasil composer install
├── .env                # File environment (JANGAN di-share publik)
├── artisan
├── composer.json
├── composer.lock
└── ...
```

> **Catatan:** Folder `node_modules/` TIDAK perlu diupload.

---

## Konfigurasi Web Server (Apache/Nginx)

### Apache (.htaccess)

Pastikan `public/.htaccess` sudah ada dan `mod_rewrite` aktif:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Nama Domain

Jika aplikasi berada di subfolder (mis. `public_html/pamsimas/`), ubah `APP_URL` dan sesuaikan path.

---

## Database

### 1. Buat Database di Hosting

Melalui cPanel/phpMyAdmin:
- Buat database baru
- Buat user dan beri akses ke database tersebut
- Catat: nama database, username, password

### 2. Impor Struktur (Migration)

Jika hosting mendukung CLI:

```bash
php artisan migrate --force
```

Jika tidak ada akses CLI, gunakan phpMyAdmin:
- buka `database/migrations/*.php`
- ekstrak perintah CREATE TABLE-nya manually, atau
- siapkan SQL dump dari lokal (lebih mudah).

### 3. Siapkan Master Data

Jalankan seeder jika ada (kalau hosting support CLI):

```bash
php artisan db:seed --force
```

---

## Permission Folder (Sangat Penting)

Pastikan folder `storage/` dan `bootstrap/cache/` bisa ditulis oleh web server:

**cPanel File Manager:**
- Pilih folder `storage` → Permissions → 775 atau 777 (tergantung konfigurasi hosting)
- Pilih folder `bootstrap/cache` → Permissions → 775 atau 777

**Via SSH (jika ada akses):**
```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chown -R www-data:www-data storage/
```

---

## Test Deploy

1. Buka domain di browser: `https://domain-anda.com`
2. Jika muncul halaman Laravel (Wel****ome atau error), berarti routing berjalan.
3. Cek apakah ada error 500 (biasanya karena permission atau .env belum sesuai).
4. Buka `storage/logs/laravel.log` (via File Manager atau SSH) untuk diagnosa.

---

## Checklist Sebelum Go Live

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` sesuai domain
- [ ] Database terhubung dan migrations dijalankan
- [ ] `storage/` bisa ditulis web server
- [ ] `public/build/` ada (hasil npm run build)
- [ ] `vendor/` lengkap (composer install --no-dev)
- [ ] File `.env` tidak terekspos ke publik
- [ ] HTTPS aktif (SSL)
- [ ] Backup database rutin
- [ ] Timer/queue jika menggunakan antrean tugas

---

## Fitur Tambahan yang Butuh Perhatian Khusus

### 1. Queue (Jika Digunakan)

Jika aplikasi menggunakan queue (mis. antrean kirim WA), jalankan worker:

```bash
php artisan queue:work --daemon
```

Atau gunakan supervisord/cron untuk menjalankannya.

### 2. WhatsApp Gateway (wa-gateway)

Jika ingin fitur WA berjalan:
- Node.js harus terinstall di server hosting
- Jalankan `wa-gateway/gateway.js` sebagai daemon
- Pastikan webhook URL mengarah ke domain produksi

---

## Troubleshooting Umum di Hosting

| Gejala | Kemungkinan Penyebab | Solusi |
|--------|----------------------|--------|
| Error 500 | Permission, .env salah, vendor tidak lengkap | Cek log, perbaiki permission dan .env |
| CSS/JS tidak muncul | `npm run build` tidak dijalankan, `public/build/` tidak ada | Jalankan build ulang, upload ulang folder `public/build/` |
| Database error | Konfigurasi DB di .env salah, database tidak dibuat | Cek .env, buat database, jalankan migrasi |
| File upload gagal | Folder `storage/app/` tidak writable | Perbaiki permission folder storage |
| HTTPS error | SSL tidak terpasang / redirect HTTP ke HTTPS belum diatur | Pasang SSL lewat hosting, atau atur di .htaccess |
| Queue tidak jalan | Tidak ada worker yang berjalan | Jalankan queue:work atau atur cron/supervisor |

---

## Backup Rutin

1. Backup database (via phpMyAdmin atau `mysqldump`)
2. Backup folder `storage/app/meter_photos/` (foto meteran warga)
3. Simpan di lokasi aman / cloud storage

---

Jika Anda butuh detail lebih spesifik (mis. setting Nginx, konfigurasi PHP version, atau cara menggunakan cPanel), beri tahu saja tipe hosting Anda (shared hosting, VPS, dll.) dan saya bantu sesuaikan.
