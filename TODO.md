# PAMSIMAS Selur - Audit Findings & Action Plan (TODO)

Dokumen ini memuat rangkuman hasil audit komprehensif terhadap arsitektur kode (*codebase*) dan *live site* (`https://pamsimas.selur.my.id/`). Temuan dikelompokkan berdasarkan tingkat keparahan (*Severity*) lengkap dengan analisis risiko, file terdampak, dan langkah rekomendasi perbaikan.

---

## Ringkasan Eksekutif & Status Audit

| Tingkat Keparahan | Jumlah Temuan | Deskripsi Risiko Utama |
|---|---|---|
| **Critical** | 3 | Kontrol perangkat keras IoT tanpa otentikasi, bypass CSRF / penghapusan terminal log publik, eksposur data operasional & PII warga tanpa otentikasi. |
| **High** | 4 | Konfigurasi `env()` langsung di controller memicu kegagalan saat `config:cache`, tidak adanya *rate limiting* / proteksi brute-force pada login, query unindexed/lambat pada polling dashboard 5 detik, inkonsistensi endpoint webhook WhatsApp. |
| **Medium** | 3 | Injeksi CSS via nilai dinamis tanpa sanitasi ketat di Template Gauge, penanganan fail-safe status pompa saat perangkat offline, dependensi kerja (*dirty working tree*) belum terorganisir ke commit. |
| **Low / Best Practice** | 3 | Redundansi kode JavaScript/CSS inline pada dashboard, audit logging aksi operasional manual, standardisasi respon error API IoT ESP32. |

---

## 1. Temuan Tingkat Kritis (CRITICAL)

### 1.1 Unauthenticated Device Command Execution & State Manipulation
- **File Terdampak**:
  - `routes/api.php` (`POST /api/device-command`)
  - `app/Http/Controllers/Api/DeviceApiController.php` (`command()`)
- **Deskripsi & Bukti**:
  - Endpoint `POST /api/device-command` dipetakan langsung ke controller tanpa proteksi middleware otentikasi (`auth:sanctum`, `EnsureAuthenticated`, atau session auth).
  - Verifikasi *live site* mengonfirmasi endpoint merespons input publik. Siapa pun di internet yang mengetahui atau menebak MAC address perangkat (yang juga bocor di endpoint publik) dapat mengirim payload JSON:
    ```json
    { "mac": "08:B6:1F:B1:3F:80", "action": "set_mode", "value": "MANUAL" }
    ```
    atau menyalakan/mematikan pompa fisik secara sepihak (`action: "set_pump"`).
- **Dampak Operasional/Keamanan**:
  - Pihak luar dapat menyalakan/mematikan pompa air desa tanpa izin, memicu kekeringan bak tandon atau kerusakan fisik motor pompa akibat *dry running* atau *overfill*.
- **Rekomendasi Perbaikan**:
  1. Pindahkan rute kontrol perangkat dari `routes/api.php` ke `routes/web.php` dengan middleware web session auth (`EnsureAuthenticated` / `role:Administrator,Operator`), **atau**
  2. Lindungi dengan middleware auth Sanctum / session cookie + token CSRF jika diakses melalui AJAX dashboard.
  3. Validasi hak akses pengguna (`session('user.role')`) sebelum mengeksekusi pengubahan status pompa.

### 1.2 Unauthenticated Dashboard Data & Sensitive Operational Exposure
- **File Terdampak**:
  - `routes/api.php` (`GET /api/dashboard/data`, `GET /api/system/detected-devices`, `GET /api/meter/last/{id}`)
  - `app/Http/Controllers/Api/DashboardApiController.php`
  - `app/Http/Controllers/Api/SystemApiController.php`
- **Deskripsi & Bukti**:
  - Endpoint monitoring diekspos di `routes/api.php` tanpa otentikasi.
  - Pengujian live endpoint mengonfirmasi:
    - `/api/dashboard/data` membocorkan seluruh daftar MAC address perangkat ESP32, IP, status pompa, persentase air tandon, RSSI sinyal WiFi, dan parameter kalibrasi sensor ke publik.
    - `/api/system/detected-devices` membocorkan perangkat baru yang mencoba registrasi.
    - `/api/meter/last/{id}` membocorkan data meteran air pelanggan.
- **Dampak Operasional/Keamanan**:
  - Membuka informasi sensitif infrastruktur IoT desa dan data privasi meter warga tanpa batas akses.
- **Rekomendasi Perbaikan**:
  1. Terapkan middleware otentikasi session pada endpoint-endpoint yang melayani tampilan internal admin dashboard.
  2. Pastikan rute publik di `routes/api.php` **hanya** endpoint yang dikonsumsi langsung oleh perangkat ESP32 (`/api/device/data`, `/api/device/config`, `/api/health`, `/api/log-offline`, `/api/ota/*`) dan dilindungi oleh `EnsureDeviceApiKey`.

### 1.3 Unauthenticated Terminal Log Clearing & Log Tampering
- **File Terdampak**:
  - `routes/api.php` (`POST /api/terminal/clear`)
  - `app/Http/Controllers/Api/LogApiController.php` (`clear()`)
- **Deskripsi & Bukti**:
  - Endpoint `POST /api/terminal/clear` dapat dipanggil oleh siapa saja tanpa otentikasi untuk membersihkan file log transaksi/sistem (`device_raw.log` / database log).
- **Dampak Operasional/Keamanan**:
  - Pelaku dapat menghapus jejak digital (*anti-forensics*) setelah melakukan manipulasi status pompa atau injeksi data telemetry palsu.
- **Rekomendasi Perbaikan**:
  1. Batasi endpoint ini hanya untuk pengguna terotentikasi berstatus `Administrator`.
  2. Catat riwayat pembersihan log ke dalam tabel `event_logs` / `admin_logs` lengkap dengan ID pengguna dan alamat IP.

---

## 2. Temuan Tingkat Tinggi (HIGH)

### 2.1 Penggunaan Langsung `env()` di Dalam Controllers (Config Caching Hazard)
- **File Terdampak**:
  - `app/Http/Controllers/MeterController.php` (baris `env('FONNTE_TOKEN')`)
  - `app/Http/Controllers/PaymentController.php` (baris `env('FONNTE_TOKEN')`)
  - `app/Http/Controllers/WhatsAppWebhookController.php` (baris `env('WHATSAPP_VERIFY_TOKEN')`)
- **Deskripsi Masalah**:
  - Laravel meniadakan fungsi pembacaan file `.env` setelah perintah `php artisan config:cache` dijalankan pada lingkungan production. Panggilan langsung `env('KEY')` di luar file `config/*.php` akan mengembalikan `null`.
- **Dampak**:
  - Fitur pengiriman struk tagihan WhatsApp otomatis via Fonnte, verifikasi webhook WhatsApp, dan notifikasi darurat akan langsung mati total (*silent failure*) jika admin mengoptimalkan server dengan `config:cache`.
- **Rekomendasi Perbaikan**:
  1. Daftarkan konfigurasi pada `config/services.php`:
     ```php
     'fonnte' => [
         'token' => env('FONNTE_TOKEN'),
     ],
     'whatsapp' => [
         'webhook_token' => env('WHATSAPP_VERIFY_TOKEN'),
     ],
     ```
  2. Ganti seluruh pemanggilan `env('FONNTE_TOKEN')` dan `env('WHATSAPP_VERIFY_TOKEN')` menjadi `config('services.fonnte.token')` dan `config('services.whatsapp.webhook_token')`.

### 2.2 Ketiadaan Proteksi Brute-Force (*Rate Limiting*) pada Route Login
- **File Terdampak**:
  - `routes/web.php` (`POST /login`)
  - `app/Http/Controllers/AuthController.php` (`login()`)
- **Deskripsi Masalah**:
  - Route `POST /login` belum dipasangi middleware `throttle` (misal `throttle:5,1` atau `throttle:login`).
  - Verifikasi HTTP response pada live site menunjukkan tidak ada rate limiting headers pada endpoint login.
- **Dampak**:
  - Potensi serangan brute force atau *credential stuffing* terhadap akun `Administrator`, `Operator`, dan `Kasir` sangat tinggi.
- **Rekomendasi Perbaikan**:
  1. Pasang middleware `throttle:5,1` pada rute `POST /login` di `routes/web.php`:
     ```php
     Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
     ```
  2. Implementasikan penguncian sementara akun jika terjadi kegagalan berturut-turut.

### 2.3 Inkonsistensi Route Webhook WhatsApp & Endpoint Zombie
- **File Terdampak**:
  - `routes/api.php`
  - `routes/web.php`
- **Deskripsi Masalah**:
  - Terdapat rute ganda: `routes/api.php` (`GET|POST /api/webhook/wa`) vs `routes/web.php` (`GET|POST /api_wa`).
  - Terdapat endpoint yang tidak terdefinisi (misal pemanggilan `/api/system/cleanup` menghasilkan 404 pada live site).
- **Dampak**:
  - Webhook provider pihak ketiga (Fonnte) dapat gagal terkirim jika konfigurasi webhook mengarah ke URL yang tidak konsisten.
- **Rekomendasi Perbaikan**:
  1. Satukan endpoint webhook secara resmi di bawah `routes/api.php` pada `/api/webhook/wa`.
  2. Berikan redirect atau deprecation handler untuk rute warisan `/api_wa`.
  3. Bersihkan route zombie/orphaned.

### 2.4 Beban Polling Dashboard (5 Detik) pada Query Database Unindexed
- **File Terdampak**:
  - `resources/views/dashboard/index.blade.php` (`setInterval(refresh, 5000)`)
  - `app/Http/Controllers/Api/DashboardApiController.php` (`data()`)
- **Deskripsi Masalah**:
  - Dashboard browser melakukan polling setiap 5 detik ke `/api/dashboard/data`.
  - Controller mengeksekusi agregasi tabel logs. Jika tabel mencapai puluhan ribu baris tanpa indexing yang tepat pada `(device_id, record_time)`, CPU database MariaDB/MySQL akan terbebani berat.
- **Dampak**:
  - Penurunan performa server (*high CPU load*) saat beberapa staf membuka dashboard bersamaan.
- **Rekomendasi Perbaikan**:
  1. Tambahkan composite index pada tabel logs: `(device_id, record_time DESC)`.
  2. Implementasikan caching singkat via Laravel Cache (`Cache::remember('dashboard_data', 3, ...)`).


---

## 3. Temuan Tingkat Menengah (MEDIUM)

### 3.1 Potensi Kerentanan CSS Injection pada Template Gauge
- **File Terdampak**:
  - `app/Http/Controllers/Api/TemplateApiController.php`
  - `resources/views/templates/index.blade.php`
  - `resources/views/dashboard/index.blade.php`
- **Deskripsi Masalah**:
  - Template gauge mengizinkan penyimpanan styling dan SVG dinamis. Nilai CSS dimasukkan ke dalam elemen DOM browser melalui JavaScript tanpa sanitasi ketat.
- **Dampak**:
  - Jika akun operator berhasil disusupi, pelaku dapat mengubah template gauge untuk merusak tampilan dashboard atau menyisipkan *CSS exfiltration techniques*.
- **Rekomendasi Perbaikan**:
  1. Validasi sintaks styling dan SVG template gauge menggunakan parser/sanitizer yang ketat sebelum disimpan ke database.

### 3.2 Penanganan Fail-Safe Status Pompa Saat Perangkat ESP32 Offline
- **File Terdampak**:
  - `app/Http/Controllers/Api/DeviceApiController.php`
  - `resources/views/dashboard/index.blade.php`
  - `resources/views/devices/show.blade.php`
- **Deskripsi Masalah**:
  - Saat perangkat ESP32 mengalami pemadaman listrik atau hilang sinyal WiFi (`is_online = false`), status terakhir di database tetap `'ON'` sampai batas waktu timeout tertentu.
  - Jika pompa sedang menyala saat perangkat mati mendadak, dashboard perlu memberikan indikasi jelas bahwa status pompa adalah *unconfirmed* atau *presumed off*.
- **Rekomendasi Perbaikan**:
  1. Tambahkan status derivasi pada backend: jika `last_update < now() - 2 minutes`, tandai `pump_operational_state = UNKNOWN / OFFLINE`.
  2. Nonaktifkan tombol toggle pompa pada dashboard ketika status koneksi perangkat offline.

### 3.3 Penataan Uncommitted Changes pada Working Tree Lokal
- **File Terdampak**:
  - `DEPLOY_VSCODE.md`
  - `app/Http/Controllers/Api/DashboardApiController.php`
  - `app/Http/Controllers/Api/DeviceApiController.php`
  - `app/Http/Controllers/DeviceController.php`
  - `app/Http/Controllers/SettingController.php`
  - `app/Http/Middleware/EnsureDeviceApiKey.php`
  - `app/Models/Device.php`, `EventLog.php`, `PumpLog.php`, `SensorLog.php`, `TariffHistory.php`
  - `database/migrations/2024_01_01_000005_create_tariff_histories_table.php`
  - `resources/views/devices/*`, `resources/views/layouts/app.blade.php`, `resources/views/settings/tariff.blade.php`
- **Deskripsi Masalah**:
  - Terdapat modifikasi signifikan dan file migrasi baru yang belum di-commit ke Git repo. Status cabang lokal berada dalam kondisi *dirty*.
- **Rekomendasi Perbaikan**:
  1. Lakukan review menyeluruh terhadap `git diff`.
  2. Pisahkan perubahan fitur (sejarah tarif, telemetry hardware, perbaikan middleware) ke dalam atomic git commits.

---

## 4. Temuan Tingkat Rendah & Praktik Terbaik (LOW)

### 4.1 Redundansi Script dan Gaya Tampilan Inline
- **File Terdampak**:
  - `resources/views/dashboard/index.blade.php`
  - `resources/views/devices/show.blade.php`
- **Deskripsi**:
  - Terdapat duplikasi logika styling CSS dan fungsi penghitung durasi pompa (*count-up timer*) di antara halaman dashboard dan detail perangkat.
- **Rekomendasi**:
  - Ekstraksi fungsi pendukung JS (`formatDuration`, `renderTimer`, `levelBar`) ke dalam file modul JS tersendiri.

### 4.2 Logging Audit Aksi Operator Manual
- **File Terdampak**:
  - `app/Http/Controllers/Api/DeviceApiController.php` (`command()`)
- **Deskripsi**:
  - Event log saat ini mencatat string umum `"Mode diubah ke MANUAL via dashboard"` tanpa menyertakan ID akun operator yang mengeksekusi aksi.
- **Rekomendasi**:
  - Rekam `user_id` / `username` dari sesi login ke dalam `event_logs` / `admin_logs` agar audit akuntabilitas operator dapat ditelusuri jika terjadi insiden air meluap.

### 4.3 Standardisasi Respon Error API IoT ESP32
- **File Terdampak**:
  - `app/Http/Controllers/Api/DeviceApiController.php`
- **Deskripsi**:
  - Format respon error pada beberapa rute berbeda antara format `{ status: 'error', message: '...' }` dan standar Laravel HTTP validation `{ message: '...', errors: { ... } }`.
- **Rekomendasi**:
  - Tetapkan format response konsisten agar parser JSON pada firmware Arduino/ESP32 tidak mengalami kegagalan parsing (*silent crash*).


---

## 5. Checklist Rencana Aksi (Action Plan Checklist)

- [x] **Fase 1: Keamanan Darurat (Critical)** — ✅ *selesai & terverifikasi live di production (Task #45/#46, 28 Sep 2026 17:17)*
  - [x] Pasang proteksi auth pada `POST /api/device-command`. *(sudah ada sejak Task #25 — diverifikasi ulang: anonim = 419 CSRF, rute ada di grup `auth.session`)*
  - [x] Pasang proteksi auth pada `POST /api/terminal/clear`. *(dipindah ke `routes/web.php` grup `auth.session` → anonim 419/401)*
  - [x] Pasang proteksi auth pada `GET /api/dashboard/data`, `/api/system/detected-devices`, `/api/meter/last/{id}`. *(plus `/api/dashboard-data`, `/api/device/history`, `/api/detected-devices`, `/api/terminal/events` — semua kini 401 bagi anonim)*
  - [x] Pasang rate limiting `throttle:5,1` pada route `POST /login` di `routes/web.php`.
  - [x] **Tambahan audit:** `GET /api/system/cleanup` (hapus log >90 hari + `OPTIMIZE TABLE`) kini wajib `X-API-KEY` valid via middleware baru `device.key`; `/api/fingerprint` tetap publik karena dipakai handshake firmware `Network_SSL.ino`.
  - [x] Verifikasi: PHPUnit `tests/Feature/ApiEndpointSecurityTest.php` (7 tes / 30 asersi OK) + `artisan serve` lokal: 8 endpoint UI = 401, fingerprint = 200, POST tanpa token = 419, cleanup tanpa key = 401.

- [ ] **Fase 2: Konfigurasi & Stabilitas (High)**
  - [ ] Tambahkan entri Fonnte & WhatsApp webhook ke `config/services.php`.
  - [ ] Refactor seluruh pemanggilan `env()` di `MeterController`, `PaymentController`, `WhatsAppWebhookController` menjadi `config()`.
  - [ ] Uji coba eksekusi `php artisan config:cache` tanpa merusak fungsionalitas pengiriman pesan WhatsApp.
  - [ ] Hapus/satukan rute ganda webhook WhatsApp (`/api_wa` vs `/api/webhook/wa`).
  - [ ] Tambahkan index pada tabel `sensor_logs` dan `pump_logs`.

- [ ] **Fase 3: Refactoring & Git Grooming (Medium)**
  - [ ] Review dan commit uncommitted working tree changes secara terstruktur.
  - [ ] Migrasikan tabel `tariff_histories` pada database staging/production.
  - [ ] Perkuat fail-safe tampilan status pompa perangkat saat status koneksi terputus (*offline*).

- [ ] **Fase 4: Optimasi & Kebersihan Kode (Low)**
  - [ ] Modularisasi JavaScript timer dan gauge rendering dari Blade view ke Vite assets.
  - [ ] Tambahkan perekaman user ID pada setiap perintah manual kontrol pompa.
  - [ ] Jalankan pengujian menyeluruh end-to-end sebelum deployment production.

---

## 6. Temuan Tambahan — Kebocoran Kredensial di Repo Publik (28 Sep 2026)

Repo `github.com/mswaluyo/pamsimas-selur` bersifat **PUBLIK** (`private: false`).

### 6.1 ✅ Sudah dikerjakan (scrub, Task #47)
- [x] `.fw_code/` (source firmware) di-commit dengan `ssid`/`pass` **di-redact** menjadi placeholder
      `GANTI_SSID_WIFI` / `GANTI_SANDI_WIFI` + `README.md` → sandi Wi-Fi **tidak pernah terpublikasi**.
- [x] `DEPLOY_AAPANEL.md` (7 tempat) & `scripts/setup-server.sh` (contoh perintah) memuat **sandi root
      MySQL/server asli** → diganti placeholder `<SANDI_ROOT_MYSQL>` + peringatan di bagian atas dokumen.
      Verifikasi: file mentah di GitHub kini memuat **0** kemunculan sandi tersebut.
- [x] `git commit` terstruktur: working tree bersih, 7 commit dipush ke `origin/main`
      (`8a2d79c` tariff, `8d57e02` security endpoint, `79824a8` telemetri, `0e40ab5` docs, `8d50294` firmware, `d15cab3` scrub, `0d9c674` catatan).

### 6.2 ⚠️ Tindak lanjutnya
Belum ditangani → dipindahkan ke **Bagian 7 (Backlog Akhir)** di ujung dokumen ini, agar dikerjakan
pada satu gelombang setelah aplikasi bebas bug.

---

## 7. Backlog Akhir — Pekerjaan Pasca-Stabil 🔒

> Dikerjakan **setelah aplikasi bebas bug** (Fase 1–4 di bagian 5 selesai & terverifikasi live).
> Sifatnya "sekali kerja harus tuntas": menyentuh kredensial server, firmware perangkat, dan riwayat
> git — jadi butuh jendela waktu khusus + uji ulang menyeluruh, bukan hotfix harian.

### 7.1 Kredensial & keamanan (tindak lanjut temuan bagian 6)
- [ ] **Rotasi sandi server** — sandi `root` MariaDB & `root` SSH asli sudah terpublikasi di riwayat
      git repo publik. Urutan aman: (1) pastikan punya akses alternatif (sesi SSH/sudo & panel yang
      masih aktif) sebelum mengganti; (2) ganti sandi root MariaDB + tulis ulang
      `/www/server/panel/data/default_mysql_pwd`; (3) ganti sandi `root` SSH + perbarui askpass/klien;
      (4) update `.env`/`.env.production` di server (keduanya gitignored); (5) uji login panel, login
      aplikasi + dashboard, koneksi DB, cron/queue.
- [ ] **Rotasi `DEVICE_API_KEY`** — ganti di `.env` server (dan default `config/services.php`), lalu
      **flash ulang firmware ESP8266** (`api_key` di `Pamsimas_Hybrid.ino`) + sesuaikan `wa-gateway`
      bila memakai nilai yang sama. Verifikasi: `/api/log`, `/api/status`, `/api/update` dari perangkat nyata.
- [ ] **Rotasi `WA_GATEWAY_SECRET`** — samakan di `.env` Laravel, `.env` wa-gateway, dan dokumentasi
      (placeholder saja). Verifikasi: webhook `/api/api_wa` menerima pesan uji.
- [ ] **Ganti sandi akun aplikasi bawaan seeder** (`admin123` / `kasir123`) sebelum dipakai lebih luas.
- [ ] **Ubah repo GitHub menjadi private** (Settings → General → Danger Zone) — pengaman tercepat.
- [ ] **Bersihkan riwayat git** (`git filter-repo`/BFG) agar sandi hilang dari `git log`, lalu
      force-push — dikerjakan setelah rotasi sandi (opsional bila repo sudah private).

### 7.2 Sisa audit teknis (ringkasan Fase 2–4 di bagian 5)
- [ ] **High**: lengkapi `config/services.php` + refactor `env()` → `config()`, konsolidasi rute webhook
      WhatsApp (`/api_wa` vs `/api/webhook/wa`) & secret-nya, tambah index `sensor_logs`/`pump_logs`,
      uji `php artisan config:cache` tanpa memutus pengiriman WhatsApp.
- [ ] **Medium**: fail-safe status pompa saat perangkat offline, migrasi `tariff_histories` di
      staging/production (sudah jalan di production lewat `migrate --path`).
- [ ] **Low**: modularisasi JS timer/gauge ke Vite assets, rekam `user_id` operator pada setiap
      perintah manual, uji end-to-end menyeluruh sebelum rilis berikutnya.
