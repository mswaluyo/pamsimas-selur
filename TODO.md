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
- [ ] **Firmware (usulan, butuh ubah `.ino`)**: refresh fingerprint SSL saat handshake gagal / sebelum
      rotasi sertifikat edge — kini pin SHA1 diambil **sekali per boot** (#49), sehingga bila Cloudflare
      merotasi sertifikat perangkat perlu di-reboot. Alternatif jangka panjang: validasi berbasis CA +
      hostname (`setTrustAnchors`) agar tidak bergantung pada pin. Kill-switch server sementara:
      `FINGERPRINT_DISABLED=true` di `.env` + `config:clear`, lalu reboot perangkat.

### 7.3 Catatan data master (hasil pemeriksaan 28 Sep 2026, Task #50)

- [ ] **Konfirmasi relasi perangkat ↔ tangki/pompa/sensor.** Device id 2 (`C4:D8:D5:13:A6:17`, MONITOR)
      memakai `tank_id=1` "Pamsimas Ngasinan" (tinggi 400) tetapi `pump_id=2`/`sensor_id=2` "Mbaran"
      (tinggi tangki 225, `full_tank_distance=25`, trigger 80). Nilai yang dikirim ke perangkat
      (`full=25`, `empty=225`, trigger 80) konsisten dengan data **Mbaran**, bukan Ngasinan.
- [ ] Device id 3 (ACTUATOR, `CC:50:E3:52:F3:B6`) memakai `tank_id=2` "Mbaran" tetapi `pump_id=4`
      "Pompa Kendal" (master `on/off_duration_seconds` = 1800/600) — pastikan memang pompa yang benar.
- [ ] `devices.delay_seconds` **tidak ada** di skema, jadi `/api/status` selalu mengirim `delay_seconds=0`.
      Firmware saat ini tidak memakai field itu (aman), tetapi master `pumps.delay_seconds`
      (20/30/40/185 detik) belum pernah sampai ke perangkat — bila nanti firmware memakai cooling-delay,
      sumbernya harus dari `pumps`.

### 7.4 Tindak lanjut propagasi konfigurasi (Task #51, 28–29 Sep 2026)

- [ ] **Koreksi relasi perangkat ↔ master sekarang berdampak langsung.** Setelah #51, menyimpan
      master data di Pengaturan otomatis mengirim ulang konfigurasi ke perangkat pemakainya. Contoh
      nyata: perangkat #2 memakai `tank_id=1` (Ngasinan, tinggi 400) tetapi `empty_tank_distance=225`
      (nilai Mbaran). Begitu tangki #1 disimpan, kode akan memaksa `empty_tank_distance` → **400** dan
      perangkat memakai tinggi 400 cm. **Jadwalkan bersama operator**: tentukan sumber kebenaran
      (ganti `tank_id` perangkat #2 ke Mbaran, atau betulkan tinggi tangki) sebelum menyimpan.
- [ ] **Perangkat ber-firmware pra-#50** tidak mengirim ack → `config_update_command` menempel `1`
      dan konfigurasi terkirim ulang tiap polling. Pantau `event_logs` (tidak muncul pesan
      "Perangkat menerapkan konfigurasi baru.") dan flash firmware bila perlu.
- [ ] `pumps.delay_seconds` belum ikut tersinkron (kolom `devices.delay_seconds` tidak ada di skema).
      Bila firmware mulai memakai cooling-delay, tambahkan kolom + ikutkan di `Device::syncFromMasterData()`.
- [ ] Opsional: tampilkan badge "menunggu perangkat menerapkan" di daftar perangkat saat
      `config_update_command = 1`, agar admin tahu perubahan belum diakui perangkat.
- [ ] **Watchdog connector cloudflared** (penyebab insiden 29 Sep 2026 ±02:10: `pamsimas.` dan `ssh.`
      sama-sama **530 / error code 1033** selama >15 menit, tidak ada jalur remote untuk memulihkan
      karena SSH juga lewat tunnel). Usulan: systemd unit timer di server yang mengecek
      `curl -s -o /dev/null -w '%{http_code}' https://pamsimas.selur.my.id/api/health` setiap 1–2 menit,
      `systemctl restart cloudflared` bila bukan 200, dan kirim peringatan lewat webhook WhatsApp yang
      sudah ada (`/api_wa`) supaya operator tahu perangkat berhenti lapor.

### 7.5 Ketahanan daya & tunnel (insiden listrik padam, 29 Sep 2026)

- [ ] **UPS untuk server + router** (≥ 20 menit + auto-shutdown rapi). Selama server mati, dashboard/API
      tidak terjangkau dan perangkat berhenti lapor — pompa tetap jalan lokal (mode AUTO fallback),
      data tertahan di LittleFS perangkat. Runbook pemulihan: `DEPLOY_VSCODE.md` bagian **11**.
- [ ] **Auto power-on setelah listrik kembali**: set BIOS/UEFI `AC Back` / `Restore on AC Power Loss` =
      **Power On**. Tanpa ini server tidak bisa dinyalakan dari jauh (tidak ada Wake-on-LAN jarak jauh).
- [ ] **Semua service ikut hidup saat boot**: `systemctl is-enabled nginx php8.3-fpm mariadb cloudflared`
      → `systemctl enable` yang belum; drop-in unit `cloudflared`: `Restart=always`, `RestartSec=5`,
      `After=network-online.target` + `Wants=network-online.target` (supaya connector ikut naik bersama jaringan).
- [ ] **Watchdog connector** (butuh di server): timer 1–2 menit cek `/api/health`, `systemctl restart
      cloudflared` bila bukan 200 + kirim peringatan lewat webhook WhatsApp (`/api_wa`). Ini menolong saat
      connector **crash**, bukan saat listrik padam — untuk padam, yang berguna adalah auto-power-on +
      peringatan "server tidak merespons > N menit" yang **ditulis perangkat** (firmware sudah mencatat
      event offline ke LittleFS, tinggal dikirim sebagai `event_type` peringatan).
- [ ] **Jalur darurat kedua**: web **dan** SSH kini satu-nasib lewat tunnel yang sama — saat connector mati
      tidak ada cara remote untuk memulihkan. Usulkan salah satu: port-forward sementara di router
      (`2222 → 192.168.20.200:22`, ditutup saat normal) atau **WireGuard di router** sebagai jalur tetap.
- [ ] **Setelah server hidup**, jalankan checklist `DEPLOY_VSCODE.md` §11: `api/health` 200,
      `/api/fingerprint` 59 karakter, `devices.last_update` < 2 menit, `config_update_command` turun setelah
      ack, dan backlog LittleFS (`/sensor_log.txt` dll.) terkirim lewat `/api/log-offline`.

### 7.6 Jangkauan sensor ultrasonik vs tinggi bak 400 cm (temuan 29 Sep 2026)

**Gejala** (log serial perangkat, mode AUTO, pompa sempat ON):

```
SENSOR: Jarak Final: 298.93 cm, Level: 27 %, RSSI: -55 dBm
SENSOR: ERROR KRITIS - Jarak tidak valid (0.00 cm). Sensor RUSAK/RUSAK! Pompa DARURAT MATI.
API: ... 'report_event' -> 'EMERGENCY: Sensor Error - Pompa Dimatikan' (HTTP 200)
```

- [ ] **Pahami dulu artinya `0.00 cm`**: `pulseIn(ECHOPIN, HIGH, 30000)` mengembalikan `0` saat
      **timeout = tidak ada gema sama sekali**, bukan jarak 0 cm. Rumus `(duration/2)*0.0343` lalu
      mencetak `0.00`. Jadi pesan "Sensor RUSAK" = *echo hilang*, bukan komponen rusak.
- [ ] **Penyebab utama = jarak fisik di tepi jangkauan.** `Jarak Final: 298.93 cm` berarti gema pulang
      ±17,4 ms dari batas 30 ms. HC-SR04 (kolom `sensors.sensor_type`) di spec sanggup 4 m tetapi di
      lapangan andal hanya s/d ±2,5–3 m (gema lemah, divergensi beam ±30–40 cm) → kehilangan 1–2 echo
      itu **normal**, bukan kerusakan.
- [ ] **`empty_tank_distance` diambil dari `tanks.height`** (`Device::syncFromMasterData()`: tinggi
      tangki → `empty_tank_distance`), dan itu hanya benar **bila sensor terpasang tepat di bibir atas
      bak**. Ukur dengan meteran dari **muka sensor ke dasar bak** saat kosong: kalau hasilnya mis. 305 cm,
      maka tinggi 400 cm membuat level salah (27 % padahal nyaris kosong) **dan** bak kosong tidak akan
      pernah terbaca → selalu dianggap "sensor rusak" → pompa tidak pernah diizinkan nyala.
      Alternatif bersih: buat field baseline khusus di `sensors` (mis. `empty_tank_distance`, fallback ke
      `tanks.height`) supaya tinggi bak untuk volume tidak merangkap sebagai baseline ultrasonik, lalu
      ikutkan di `syncFromMasterData()` + form Sensor.
- [ ] **Tindakan hardware (pilih/kombinasi):** ganti ke **JSN-SR04T versi 4,5 m (waterproof)**; atau
      turunkan posisi sensor / pakai **pipa tenang (standpipe)** agar jarak kerja ±1–2 m; pendekkan kabel
      probe (kabel panjang & kecil membunuh sinyal HC-SR04); **kapasitor 470–1000 µF** dekat sensor dengan
      rel 5 V terpisah; usahakan pengukuran saat **pompa OFF** (derau kontakor + riak/oli/busa permukaan
      membuat gema hilang).
- [x] **Firmware: laporan fault jadi anti-spam.** Event `report_event`, `set_status`, buzzer, dan baris
      sensor `-1 %` kini **edge-triggered**: hanya saat masuk episode fault, lalu penanda berulang maksimal
      1× per 2 menit (`SENSOR_FAULT_REPORT_INTERVAL_MS`) — bukan tiap `report_interval` seperti sebelumnya
      (dulu `event_logs` terisi tiap 3 detik). Counter `sensorFaultStreak` ditampilkan di pesan serial.
- [x] **Firmware: kebijakan dibalik menjadi KEDAISAN AIR — pengisian buta TANPA batas siklus
      (29 Sep 2026, jangkauan riil terkonfirmasi maksimum 3 m).** "Tidak ada gema" diartikan
      **permukaan air di bawah jangkauan = tangki butuh air**, jadi dalam mode AUTO `waterLevelPer`
      dianggap **0 %** dan pompa **diralat NYALA**. Batas siklus yang sempat dibuat (2 siklus) **dihapus**
      atas keputusan operator: pemasangan sensor sudah terjaga (permukaan tidak akan merendam sensor) dan
      bak punya peluap, jadi luber tidak mungkin. Yang tetap melindungi mesin hanya proteksi yang sudah ada:
      safety cut-off durasi nyala maksimum (`on_duration`) + masa istirahat (`off_duration`), sehingga
      polanya **nyala → istirahat → nyala** berulang sampai air naik ke dalam jangkauan dan sensor membaca
      lagi (saat itu event `Sensor Pulih: normal kembali (N siklus gagal, M siklus isi buta)` dikirim dan
      kendali kembali penuh ke AUTO). Mode **MANUAL/TIMED tidak menyentuh relay**. Laporan tetap anti-spam:
      1 event saat masuk episode fault + penanda "masih buta" maks **1×/15 menit**
      (`SENSOR_FAULT_REPORT_INTERVAL_MS` = 900000 — episode buta kini bisa berjam-jam, jadi interval
      diperlebar agar `event_logs` tidak banjir), dan `sensor_logs` hanya menerima sentinel `-1` pada
      moment yang sama.
- [x] **Perbaikan konvensi log:** `logEventOffline()` kini hanya dipanggil **saat jaringan putus**; saat
      online event dikirim langsung (`report_event`). Sebelumnya keduanya dipanggil bersamaan sehingga
      event dobel ketika `/event_log.txt` di-flush pada boot/reconnect (`sendOfflineLogs()` hanya jalan di
      dua moment itu).
- [ ] **Setelah hardware beres**: catat `Jarak Final` maksimum yang masih stabil, samakan nilai itu dengan
      `tanks.height` / `empty_tank_distance`, lalu pantau 1–2 hari bahwa event `Sensor Pulih` tidak muncul
      lagi dan `sensor_logs` tidak berisi `water_percentage = -1`.
- [ ] **`on_duration` / `off_duration` sekarang = pola nyala-istirahat pompa saat buta** (bukan lagi batas
      total pengisian). Atur `on_duration` ± waktu isi dari tanda 3 m sampai penuh agar pompa tidak sering
      terpotong, dan `off_duration` sesuai spesifikasi duty-cycle pompa. **Pastikan peluap/pelampung
      mekanis berfungsi** — setelah batas siklus dilepas, itu satu-satunya penahan pengisian bila sensor
      mati total selagi bak sudah penuh.
- [ ] Opsional (**belum** dikerjakan): saring baris `water_percentage = -1` dari grafik riwayat dashboard
      agar tidak dianggap level 0 %, dan tampilkan badge "level tidak terukur — pengisian buta aktif" di
      dashboard saat event terakhir device adalah `Sensor tidak terbaca`.
- [x] **Keterbacaan tipe perangkat di form diperbaiki (30 Sep 2026).** Lihat bagian **7.7**.

### 7.7 Salah pilih Tipe Perangkat = sensor tidak pernah dibaca (30 Sep 2026)

**Gejala** (log serial perangkat yang dikira punya sensor, mode AUTO, pompa ON):

```
[PROSES PENGECEKAN KONFIGURASI ...] semua parameter sensor terkirim (Jarak Penuh 25 cm, Jarak Kosong 225 cm, ...)
FETCH: Konfigurasi identik. Melewati penulisan EEPROM.
SENSOR: Mode Actuator, melewati pembacaan sensor fisik.
FETCH: Level air dari server: 27 %
```

**Penyebab** (bukan sensor rusak): perangkat terdaftar sebagai **ACTUATOR**, padahal
memakai sensor ultrasonik. `measureAndSendData()` keluar lebih dulu saat `device_mode == 0`
(`.fw_code/Pamsimas_Hybrid/Pump_Sensor_Logic.ino:17-20`) sehingga **HC-SR04 tidak pernah dibaca**; level air
hanya diambil dari `water_percentage` server (`API_Communication.ino:60-62`, `163-165`). Padahal log
konfigurasi tetap menampilkan seluruh parameter sensor — parameternya terkirim, tapi tidak dipakai untuk
membaca — sehingga mudah disalahartikan sebagai "sensor aktif".

**Aturan praktis (dokumen ini; belum ada validasi di server):**

| Tipe | Peran | Sensor fisik | Relay pompa |
|---|---|---|---|
| `MONITOR` | **Fungsi ganda**: membaca & melapor level air tiap *Interval Lapor* **dan** menggerakkan relay pompa sendiri (pada mode AUTO relay ikut logika level air) | **Ya** (HC-SR04, `Pamsimas_Hybrid.ino:218`) | **Ya** — `Pump_Sensor_Logic.ino:245-307` + `digitalWrite(RelayPin)` |
| `ACTUATOR` | Mengeksekusi nyala/mati pompa + timer ON/OFF saat link putus | **Tidak** (dilewati firmware, `Pump_Sensor_Logic.ino:17-20`) | Ya; level air diambil dari MONITOR satu tangki |

Satu papan MCU punya pin relay yang sama (`const int RelayPin = D0`, `Pamsimas_Hybrid.ino:49`) untuk kedua
peran — jadi MONITOR memang bisa "sensor sekaligus pompa" (berguna bila pemasangan hanya satu papan),
sementara ACTUATOR murni penggerak pompa tanpa andil sensor.

- MONITOR → `device_mode = 1`, ACTUATOR → `device_mode = 0`; nilai dikirim dari
  `device_type` (`DeviceApiController.php:184`) — **bukan** dari `sensor_id`. Perangkat boleh
  `device_type = ACTUATOR` sambil tetap punya `sensor_id` (master data dipakai untuk ambang), sehingga
  `sensor_id` **bukan** penentu mode.
- "Interlock satu bak": log level dari MONITOR langsung memicu `applyAutoControl()` pada ACTUATOR
  `tank_id` yang sama (`DeviceApiController.php:129-137`). Kalau tidak ada MONITOR di tangki itu,
  level air ACTUATOR **beku** di laporan terakhir dan pompa tidak bekerja sesuai pemicu.
- **Perbaikan UI (sudah dideploy):** label opsi form `Tipe Perangkat` kini menyebut perannya secara eksplisit
  — `MONITOR - sensor + pompa (fungsi ganda)` dan `ACTUATOR - pompa saja (tanpa baca sensor)`
  (`resources/views/devices/_form.blade.php`). Baris "Sumber Data Monitor" → "Sumber Level Air"
  (`devices/show.blade.php:209`) juga dibuat jujur soal siapa yang membaca sensor.
  Catatan: paragraf penjelasan panjang di bawah `select` (beserta JS toggle `hint-type-*`) sempat dipasang lalu
  **dihapus atas permintaan operator** (`c9bf061`, 30 Sep 2026) — label opsi dianggap cukup jelas.
- [ ] **Validasi server** (usul): saat `device_type = ACTUATOR` tanpa MONITOR lain di `tank_id` yang sama,
      tampilkan peringatan (bukan error) di form + halaman detail. Konfirmasi dulu dengan operator karena
      perangkat single-board mungkin sengaja di-set ACTUATOR.
- [ ] **Cek cepat saat debug log serial:** baris `SENSOR: Mode Actuator, melewati pembacaan sensor fisik.`
      = perangkat dalam mode ACTUATOR. Kalau seharusnya punya sensor, perbaiki `device_type` di
      Pengaturan → Perangkat (tidak perlu flash ulang; `config_update_command` +
      `mode_update_command` sudah dinaikkan oleh `DeviceController::update()` dan diturunkan lagi
      setelah perangkat ack `reset_config`).

### 7.8 "Interval Lapor (detik)" ternyata interval *poll*, bukan interval lapor (30 Sep 2026)

**Temuan (perbandingan kode server ↔ firmware):**

| Sisi | Fakta |
|---|---|
| Server | `devices.report_interval` (default 3, migrasi `2024_01_01_000001`) dikirim apa adanya di `/api/status` (`DeviceApiController.php:203`) |
| Firmware | `API_Communication.ino:221-222`: `currentStatusFetchInterval = doc["report_interval"] * 1000` → dipakai untuk **poll `/api/status`**, bukan untuk mengirim data sensor |
| Firmware | Interval kirim data sensor = **konstanta** `dataSendInterval = 3000 ms` (`Pamsimas_Hybrid.ino:123`, dipakai di `:311-314`); default poll `STATUS_FETCH_NORMAL = 3000` (`:124`) — karena angkanya kebetulan sama, nilainya tampak "mengikuti firmware" |
| Firmware | Yang dicetak `- Report Interval: 3000 ms` juga `currentStatusFetchInterval` (`API_Communication.ino:235-236`) — salah label di sisi firmware |

**Konsekuensi:** menaikkan nilai itu memperlambat **respons perintah** (pump_command, config/mode update + ack, restart/OTA) dan melambatkan penyegaran `water_percentage` bagi ACTUATOR — tetapi **tidak** mengubah laju pelaporan sensor (tetap 3 dtk), tidak mengubah status online (`Device::isOnline()` = 300 dtk, `Device.php:54-57`; heartbeat `/api/health` 60 dtk, `Pamsimas_Hybrid.ino:127`), dan tidak mengubah agregasi menit/jam (`aggregate()`, `DeviceApiController.php:635-653`).

**Tindakan (sudah dideploy, commit `7149e32` → lihat `DEPLOY_VSCODE.md` §9 Task #54):**
- Field **"Interval Lapor (detik)" dihapus** dari form Registrasi & Edit Perangkat
  (`resources/views/devices/_form.blade.php`).
- Validasi `report_interval` dilepas dari `DeviceController::update()` sehingga kiriman
  form lama pun tidak bisa mengubah nilainya; nilai tetap **3 detik** (default kolom DB =
  default firmware). Data saat ini sudah seragam: device #2 `C4:D8:D5:13:A6:17` (MONITOR) dan
  #3 `CC:50:E3:52:F3:B6` (ACTUATOR) sama-sama `report_interval = 3`.
- `/api/status` tetap mengirim `report_interval` (dari DB) agar kontrak API tidak berubah.
- [ ] **Backlog opsional** bila operator ingin benar-benar bisa mengatur **laju lapor sensor**:
      opsi B (firmware memakai nilai server untuk `dataSendInterval`) atau opsi C (pisah dua field:
      lapor data + poll perintah; perlu migrasi DB + flash ulang). Saran rentang bila dikerjakan:
      **3–300 detik** — jangan di bawah 3 detik karena satu siklus pengukuran saja sudah ±0,5–0,6 dtk
      (8 bacaan × `delay(50)`, `Pump_Sensor_Logic.ino:133-146`) dan `setTimeout` SSL 5 dtk.

### 7.9 Analisa: perilaku ACTUATOR saat **sumber level air offline** (3 Okt 2026)

**Definisi.** "Sumber" = perangkat **MONITOR se-tangki** yang memasok level air
(label UI `Sumber Level Air`, `devices/show.blade.php:209`). ACTUATOR tidak punya
sensor sendiri (`sensor_id = NULL`; `measureAndSendData()` langsung `return` untuk
`device_mode == 0`, `Pump_Sensor_Logic.ino:17-20`), jadi seluruh keputusan AUTO-nya
bergantung pada data MONITOR — lewat server, bukan langsung.

**Kondisi nyata saat analisa dibuat (bukan simulasi):**

| Objek | Keadaan |
|---|---|
| MONITOR #2 `C4:D8:D5:13:A6:17` | **OFFLINE sejak 30 Sep 2026 02:09:49** (± 82 jam / 3,4 hari). Tidak ada kontak `/api/*` sama sekali (heartbeat 60 dtk pun tidak) |
| Log terakhir MONITOR #2 | `record_time = 2026-09-30 02:09:52`, `pct = 0`, `cm = 255,82` (melebihi `empty_tank_distance` 225 cm) |
| ACTUATOR #3 `CC:50:E3:52:F3:B6` | **ONLINE** (poll 3 dtk), `mode = AUTO`, `status = ON`, `sensor_id = NULL`, `on_duration = 30 mnt`, `off_duration = 10 mnt`, `trigger = 70%`, firmware `Jul 20 2026 09:14:35` |
| Respons `/api/status` #3 (curl loopback) | `water_percentage: 0`, **`source_ready: 1`**, `pump_command: "ON"`, `on_duration: 1800`, `off_duration: 600` |

**Rantai keputusan (server → firmware):**
1. `DeviceApiController::resolveWaterInfo()` (baris 47-62) mengambil **log terakhir
   MONITOR se-tangki tanpa memeriksa umur data** → pct = 0 (data 3,4 hari lalu).
   Tidak ada satu pun pemakaian `isOnline()` untuk data level (hanya badge UI).
2. `status()` (baris 177, 186-189) mengirim `water_percentage: 0` +
   **`source_ready: 1` hardcode** (komentar baris 187-188: "pompa tetap bisa
   dikendalikan meski monitor hilang").
3. `applyAutoControl()` (baris 71-88) memakai `pct < trigger` → **status ON** dan
   menulis `pump_logs` "Pompa ON (AUTO) @ 0%". Server tidak pernah tahu angka 0 itu
   sudah basi.
4. Firmware `fetchQuickStatus()` (baris 60-63) menyalin `water_percentage` ke
   `waterLevelPer` (khusus `device_mode == 0`); relay dari server hanya disinkronkan
   di mode non-AUTO (baris 82) ⇒ **di AUTO keputusan relay murni milik firmware**
   dengan angka basi tadi.
5. `runUniversalPumpLogic()` AUTO: `waterLevelPer <= trigger` → ON; OFF hanya bila
   `waterLevelPer >= 98` (tidak akan pernah) **atau** safety cut-off
   (`pumpOnDuration`). Setelah cut-off: `isResumingFill = true` bila `waterLevelPer < 95`
   (`Pump_Sensor_Logic.ino:182-183`) → istirahat `off_duration` → **isi lagi**, berulang.
6. `source_ready` **tidak dibaca firmware sama sekali** (tidak ada di `*.ino`), jadi
   walau server mengirim 0/1, perilaku tidak berubah tanpa flash ulang.
**Perilaku terukur (`pump_logs`/`event_logs` #3, 24 jam terakhir):**
- 41 siklus ON, rata-rata **ON 34,6 mnt / OFF 0,2 mnt** (angka OFF adalah artefak, lihat R2).
  Pola event: `Pompa OFF (AUTO) — laporan perangkat` → `Safety Cut-off: Durasi Maksimal`
  → **4 detik** kemudian `Pompa ON (AUTO) @ 0%`.
- Siklus fisik sebenarnya = **30 mnt nyala + 10 mnt istirahat** (`on_duration` = 1800 s,
  `off_duration` = 600 s) ⇒ "isi buta" hampir 24 jam/hari, 3,4 hari berturut-turut,
  tanpa satu pun alarm di dashboard.
- Episode tidak stabil 10:51-12:00: boot berulang (10:51:15, 11:00:53, 11:26:39,
  11:31:59, 11:41:49, 11:45:34; `reset_reason = Power On`) — tiap kali tepat **2-3 detik
  setelah relay turun** (safety cut-off) ⇒ indikasi **brownout saat kontaktor pompa lepas**
  (catu ESP sebaris beban pompa, tanpa snubber/PSU terpisah).
- `duration_seconds` di `pump_logs` selalu **0** (kolom tidak pernah diisi kode).

**Risiko/celah yang teridentifikasi:**
- **R1 — Isi buta tak terbatas tanpa peringatan.** Data beku `< 95%` ⇒ ACTUATOR mengisi
  terus (siklus 30/10) selamanya; proteksi hanya dua timer itu. Monitor yang mati tidak
  bisa melihat air naik ⇒ **risiko luber** (tergantung pelampung fisik). Pengisian baru
  berhenti sendiri bila data beku **>= 95%** (kondisi `isResumingFill` tidak terpenuhi).
- **R2 — Server menimpa laporan OFF perangkat.** `applyAutoControl()` menulis
  `status = ON` ~4 detik setelah firmware melaporkan cut-off/OFF ⇒ di DB & dashboard pompa
  tampak **ON padahal relay sedang istirahat 10 menit**, dan `pumpStatusSince` (badge timer)
  jadi **10 menit lebih awal** dari kenyataan (`DashboardController.php:112-119`,
  `DashboardApiController.php:46-50`). Ada dua pengendali AUTO (server & firmware) untuk
  aktuator yang sama.
- **R3 — Ambang OFF tidak konsisten.** Server OFF di `pct >= 99`
  (`DeviceApiController.php:79`); firmware OFF di `pct >= 98` (`Pump_Sensor_Logic.ino:264`).
  Pita 98-98,99% bisa membuat status DB berbeda dari relay. Ambang ON juga beda: `<=`
  (firmware) vs `<` (server).
- **R4 — ACTUATOR tanpa MONITOR = pompa "ON" permanen.** `tankMonitor()` `null` ⇒
  fallback ke log ACTUATOR sendiri yang tidak pernah ada ⇒ `pct = 0` selamanya (belum ada
  validasi/peringatan di UI — butir backlog §7.8).
- **R5 — Tidak ada indikator kesegaran data di UI.** Gauge/dashboard menampilkan
  `water_percentage: 0` + "Online" untuk #3 tanpa membedakan "tangki kosong" dan "sumber
  mati 3,4 hari" (`DashboardApiController.php:31-42` hanya fallback nilai, tanpa umur data;
  `devices/show.blade.php:209` masih teks statis).
- **R6 — Reboot saat relay turun** menghapus `isCoolingDown` & `pumpStartTime` (variabel RAM)
  ⇒ jendela proteksi 30 menit **mulai ulang dari nol** dan pompa langsung distart ulang,
  memperbanyak start/stop motor.
- **R7 — ACTUATOR yang benar-benar offline** (WiFi mati) memakai cabang timer
  (`Pump_Sensor_Logic.ino:205-243`) dengan pola 30/10 yang sama ⇒ **safeguard-nya identik**;
  mematikan WiFi perangkat tidak menambah proteksi apa pun terhadap sumber yang mati.
  Di cabang itu `sendControlCommand("report_event", ...)` tetap dipanggil walau offline
  (komentar "tidak bisa kirim ke server" hanya pada `set_status`) ⇒ setiap transisi
  menunggu timeout TLS.

**Rekomendasi (belum diimplementasikan — butuh keputusan kebijakan):**
- **Opsi A (tanpa flash).** Tandai level basi di server: bila `record_time` log monitor
  lebih tua dari **600 detik**, kirim `source_ready = 0` + tambah `source_age_seconds`;
  `applyAutoControl()` tidak boleh memaksa ON dari data basi (paling sedikit: tulis
  `event_logs` "Sumber level air basi (MONITOR #x offline)"), dan tampilkan badge merah di
  dashboard/gauge. ACTUATOR tanpa MONITOR ⇒ `source_ready = 0` sejak awal.
  Efek lapangan: hentikan isi buta #3 sampai monitor dicek.
- **Opsi B (A + firmware, butuh flash).** Firmware membaca `source_ready`/`source_age_seconds`:
  bila basi ⇒ AUTO tidak mempertahankan pengisian otomatis (masuk "safety rest", buzzer +
  `report_event`), atau batasi **N siklus isi buta** lalu berhenti sampai monitor pulih.
  Sekaligus: samakan ambang 98 vs 99, isi `duration_seconds`, catat `reset_reason` per boot.
- **Opsi C (operasional, tanpa kode).** Periksa MONITOR #2 hari ini (catatan terakhirnya
  `cm = 255,82` di luar batas kosong 225 cm lalu hilang total) dan selama sumber belum
  pulih **pindahkan #3 ke MANUAL / cabut relay** — di MANUAL `applyAutoControl()` berhenti
  (`control_mode !== 'AUTO'`) dan pompa hanya mengikuti dashboard (proteksi cooling-down tetap
  aktif).
- **Sekunder:** pisahkan catu daya/beban relay (R6) atau tambahkan snubber; isi
  `duration_seconds`; simpan `reset_reason` per kejadian `boot`.

> **UPDATE 3 Okt 2026 (lihat §7.12):** untuk bak **Pamsimas Mbaran**, R1 (risiko luber),
> R4 (ACTUATOR tanpa MONITOR), dan **Opsi C** dinyatakan **TIDAK BERLAKU** — operator
> sengaja mematikan MONITOR #2 agar ACTUATOR #3 berjalan **otonom AUTO** karena debit air
> masih kurang (pengisian menerus memang diinginkan). **Opsi A/B dibatalkan.**
> Yang tetap berlaku: R2/R3 sudah diperbaiki (§7.11), R6 (catu daya/brownout) masih
> relevan, dan `on_duration`/`off_duration` menjadi satu-satunya proteksi siklus.

### 7.10 Riwayat meleset 7 jam: aplikasi Laravel memakai UTC, seharusnya WIB (3 Okt 2026)

**Gejala (laporan operator).** Setelah perangkat di-flash, riwayat (sensor/pompa/kejadian)
tidak cocok dengan jam sekarang.

**Bukti jam dinding operator = WIB, bukan UTC:**
- Mesin kerja operator: `2026-10-03 20:35:07 +07:00`, zona `SE Asia Standard Time` =
  *(UTC+07:00) Bangkok, Hanoi, Jakarta*, `BaseUtcOffset 07:00:00`, tanpa DST; selisih vs
  UTC tepat 7,000000 jam.
- Firmware: `long timeZone = 7 * 3600;` (`Pamsimas_Hybrid.ino:109`) — preset lokal +7.
- Sistem lama: `backup_pamsimas/.env` → `TIMEZONE=Asia/Jakarta`;
  `backup_pamsimas/public/index.php:86` & `core/Database.php:15` →
  `date_default_timezone_set('Asia/Jakarta')`; `core/Database.php:57-62` →
  `SET time_zone='+07:00'` dengan komentar "agar query berbasis waktu (NOW, DATE_SUB) akurat".
- Port Laravel **kehilangan** keduanya: `config/app.php:68` `'UTC'`, koneksi `mysql` tanpa
  kunci `timezone` → sesi MySQL `SYSTEM` = UTC. Semua stempel memakai `now()`
  (`DeviceApiController.php:121,336,401`) dan semua view mencetak nilai mentah
  (`logs/sensors.blade.php:28`, `logs/events.blade.php:27`, `logs/pumps.blade.php:28`,
  `devices/show.blade.php:228,285`) → riwayat tampil 7 jam lebih muda.

**Perbaikan (mengikuti sistem lama) — sudah live:**

| Berkas | Perubahan |
|---|---|
| `config/app.php` | `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')` |
| `config/database.php` (blok `mysql` & `mariadb`) | `'timezone' => env('DB_TIMEZONE', '+07:00')` — didukung Laravel 12 (`vendor/laravel/framework/.../MySqlConnector.php:110-111`) |
| `.env.example` | `APP_TIMEZONE=Asia/Jakarta`, `DB_TIMEZONE=+07:00` (opsional; default config sudah WIB, sehingga `.env` server **tidak** diubah = mudah dibalik) |

**Kenapa data lama tidak perlu diubah:** kolom `TIMESTAMP` (`sensor_logs.record_time`,
`pump_logs.timestamp`, `event_logs.event_time`, `devices.last_update`) disimpan sebagai
instan UTC; begitu sesi MySQL `+07:00`, pembacaan otomatis WIB (terbukti: `sensor_logs.max`
02:09:52 → **09:09:52**; event 13:27 → **20:27**). Kolom `DATETIME` agregat **tidak** ikut
terkonversi → digeser sekali `+7 HOUR`: `minute_sensor_logs` 7.524 baris,
`hourly_sensor_logs` 135 baris (4 tabel agregat lain kosong). UPDATE wajib
`ORDER BY <kolom> DESC` karena PK `(device_id, timestamp)` — tanpa itu MySQL bentrok
"Duplicate entry" saat memproses baris demi baris.

**Verifikasi deploy (3 Okt 2026, semuanya lulus):** `config('app.timezone') = Asia/Jakarta`
dan sesi MySQL `+07:00` dengan `now() = 20:38:25` selagi `date` server
`13:38:25 UTC` (= beda tepat 7 jam); jalur yang sama dipakai view
(Eloquent cast + `format`) → event `03-10-2026 19:26:35`, pump `20:27:51`,
sensor `30-09-2026 09:09:52`, device `20:38:23` + `online = YA`;
agregat menit `2026-09-30 09:09:00` **cocok** dengan rata-rata `sensor_logs` pada menit itu
(selisih pada baris jam adalah efek normal "rata-rata dari rata-rata menit", bukan geseran);
`laravel.log` error 94 → 94 (tidak bertambah); perangkat tetap polling
`/api/status` HTTP 200 tiap 3 detik; `/login` 200; MD5 config terpasang
`0dd01448aaad768b24b8a9a042a0631d` (app.php) & `63bd61644cea74a7be2df79f04829e03`
(database.php); cadangan config `/tmp/backup-tz-20261003-133648`.

**Catatan penting:**
- `APP_TIMEZONE` dan `DB_TIMEZONE` **wajib sejalan**. Kalau hanya salah satu diubah,
  `Device::isOnline()` (ambang 300 dtk) dan timer dashboard meleset 7 jam.
- Tabel cadangan agregat pra-geser **tidak dipertahankan** (terhapus saat dedup tabel
  cadangan ganda). Amankan karena `minute/hourly_sensor_logs` adalah turunan
  `sensor_logs` (mentah, utuh 108.327 baris) dan geseran reversibel dengan `-7 HOUR`.
- Firmware **tidak** perlu di-flash ulang; `server_time` (epoch UTC) tidak berubah.
- **Rollback:** kembalikan 2 berkas config dari `/tmp/backup-tz-…` → `config:clear`
  → (opsional) geser agregat `-7 HOUR` dengan `ORDER BY <kolom> ASC`.
- Temuan menyertai saat analisa ini: pasca-flash kedua perangkat sudah memakai firmware
  `Sep 29 2026 20:38:26`, tetapi **MONITOR #2 masih belum mengirim data**
  (`sensor_logs` berhenti di 30 Sep; 0 request `/api/log` di access log; `uptime`
  hanya 128 detik saat kontak terakhir 19:26 WIB) dan **ACTUATOR #3 reboot tiap 1-2 menit**
  (`uptime = 120.001 ms`, event `boot` berulang, `reset_reason = Power On`) — lihat §7.9
  (R6 brownout) dan Opsi C untuk tindakan lapangan.

### 7.12 Keputusan operator: bak **Pamsimas Mbaran** sengaja berjalan OTONOM AUTO tanpa sensor (3 Okt 2026)

**Pernyataan operator (3 Okt 2026).**
1. Perbaikan riwayat/grafik §7.11 sudah sesuai harapan.
2. **Risiko luber tidak berlaku**: kenyataannya **debit air masih kurang**, sehingga pengisian
   menerus memang diinginkan.
3. **MONITOR #2 dimatikan atas permintaan operator** (bukan kerusakan/kabel putus) agar
   ACTUATOR #3 berjalan **otonom di mode AUTO**.

**Konsekuensi yang disengaja (dan diterima):**
- Level acuan #3 tidak pernah sahih ⇒ server mengirim `water_percentage = 0` (basi) +
  `source_ready = 1` ⇒ firmware AUTO #3 mempertahankan pengisian.
- Siklus nyata: **ON = `on_duration` (30 menit) → istirahat `off_duration` (10 menit)**,
  berulang terus. Dua timer inilah **satu-satunya proteksi** (tidak ada proteksi berbasis
  level). Karena itu `on_duration`/`off_duration` di master data **wajib** diisi wajar.
- Pompa #2 (Pompa Mbaran) tidak bertenaga selama perangkat #2 mati ⇒ praktis hanya
  Pompa Kendal (#4, lewat #3) yang mengisi bak ini.

**Yang TIDAK dilakukan (dicabut dari rencana):**
- Opsi A/B §7.9 (menandai sumber basi lalu **menghentikan** pompa / `source_ready = 0`)
  **dibatalkan** — bertentangan dengan keputusan ini. Jangan diimplementasikan tanpa
  persetujuan baru dari operator.
- Rekomendasi §7.9 R1/R4 & Opsi C (pindahkan #3 ke MANUAL, cabut relay) **dinyatakan tidak
  berlaku** untuk bak Pamsimas Mbaran.

**Yang masih layak dipertimbangkan (opsional, tidak mengubah perilaku):**
- Label UI yang jujur: tampilkan "sumber level mati — mode otonom (siklus 30 mnt ON /
  10 mnt OFF)" alih-alih angka `0%` yang tampak seperti pembacaan nyata
  (`DashboardApiController::data()` tetap mengirim `water_percentage = 0`).
- Bila kelak debit sudah cukup: hidupkan kembali MONITOR #2 → AUTO otomatis kembali
  berbasis level (tanpa perubahan kode).
- Jangan sampai **dua pompa** mengisi bak yang sama secara bersamaan ketika #2 dihidupkan
  kembali (periksa penugasan `pump_id` #2 vs #3 di master data).

**Status teknis pendukung:** siklus 30,1 mnt ON / 10,0 mnt OFF terverifikasi live
(§7.11); laporan relay diterima server sebagai `set_status` perangkat, dan sejak
perbaikan §7.11 server tidak lagi menimpanya.

### 7.11 Grafik tidak menampilkan istirahat 10 menit — server menimpa laporan OFF perangkat (3 Okt 2026)

*(Perbaikan teknis di website; keputusan operator yang menyertainya ada di §7.12 di atas.)*

**Gejala (laporan operator).** Di grafik halaman perangkat ACTUATOR tidak terlihat jeda OFF
10 menit; seolah pompa nyala terus (_ON_ ~40 menit sekali siklus).

**Sebab.** Port Laravel menulis ulang `devices.status` + `pump_logs` dari data level pada
**setiap** poll `/api/status` (tiap 3 detik) di `applyAutoControl()`. Urutan kejadiannya:
1. Perangkat mencapai safety cut-off `on_duration` → relay OFF → kirim `/api/update`
   `set_status OFF` (+ `report_event` "Safety Cut-off: Durasi Maksimal").
2. Server mencatat OFF, lalu **~4 detik** kemudian (poll berikutnya, `pct` masih 0 < trigger)
   server menulis **ON** lagi + log `Pompa ON (AUTO) @ 0%`.
3. Masa istirahat mesin (`off_duration` = 10 menit) berjalan di firmware, tetapi di DB
   status sudah ON sehingga saat perangkat benar-benar menyala lagi, laporannya **tidak
   menghasilkan log baru** (nilai sama) — jeda 10 menit itu raib dari riwayat.
   Pada zoom 6 jam, 4 detik ≈ 0,05 piksel ⇒ praktis tak terlihat.

**Sistem lama tidak begini.** `backup_pamsimas/app/Controllers/Api/DeviceApiController.php`
tidak pernah menulis `status` dari level; status hanya berubah dari laporan perangkat.
Jadi ini regresi porting, bukan perilaku asli.

**Perbaikan (live sejak 3 Okt 2026 21:02 WIB / commit berikutnya):**
`applyAutoControl()` tidak lagi menyimpan apa pun — hanya **menghitung perintah usulan**
`pump_command` dengan ambang yang sama seperti firmware (`pct <= trigger` ⇒ ON,
`pct >= 98` ⇒ OFF). Perubahan `devices.status`/`pump_logs` **hanya** dari `/api/update`
action `set_status` (laporan perangkat). Efek: riwayat & grafik menampilkan 30 menit nyala
+ 10 menit istirahat sesuai kenyataan, dan badge timer memakai transisi yang benar.

**Verifikasi (uji A/B terkontrol, tanpa efek samping).** Perangkat dummy (ACTUATOR, AUTO,
`status = OFF`, level sumber 0%) dipanggil `/api/status` di dalam transaksi DB lalu
di-`rollback`: hasilnya `status DB OFF → OFF` (tidak ditimpa), `pump_logs` baru **0**,
`event_logs` baru **0**, sedangkan respons tetap `pump_command = ON`, `status = OFF`,
`water_percentage = 0`, `source_ready = 1`. Setelah rollback `devices = 2` (bersih).
MD5 terpasang `000547f84e7f87ffd37ce5990bfff158`, `laravel.log` error 94 → 94,
perangkat tetap `GET /api/status` HTTP 200 tiap 3 detik.

**Sisa yang belum ditangani (masih terbuka):**
- **Riwayat lama sudah direkonstruksi (3 Okt 2026 21:40 WIB).** Dari 187 event phantom
  `Pompa ON (AUTO) @ x%`: **156** digeser ke ON nyata = (waktu OFF perangkat +
  `off_duration`, pesan diberi tanda `(rekonstruksi)`), **11 dikembalikan** ke waktu asli
  karena siklusnya dimulai setelah **reboot** (perangkat menyala segera, tanpa menunggu
  istirahat), dan **20 dilewati** (perpotongan waktu terlalu rapat saat reboot beruntun).
  Sisa 31 baris `Pompa ON (AUTO) @ x%` memang ON asli/interupsi reboot sehingga dibiarkan.
  Cadangan sebelum perubahan: `event_logs_bak_20261003_214008` (2.050 baris) dan
  `pump_logs_bak_20261003_214008` (1.221 baris) — bisa dibandingkan/dipulihkan.
- **Bukti live pasca-fix:** pengamat mencatat `21:37:27 status_db=ON` → perangkat lapor
  OFF `21:38:01` → `21:38:12 status_db=OFF` **tanpa** ON palsu (sebelumnya selalu muncul
  3-4 detik setelah OFF), lalu perangkat lapor ON kembali. Hasil akhir siklus nyata:
  `20:57:57 OFF → 21:07:57 ON` (jeda **10,0** menit) dan `21:38:01 OFF (laporan perangkat)
  → 21:48:02 ON (laporan perangkat)` (jeda **10,0** menit) dengan durasi nyala **30,1**
  menit per siklus — grafik kini menampilkan 30 menit ON + 10 menit OFF sesuai kenyataan.

### 7.13 Bug render Blade: `@else` menempel teks di kartu "Aset & Sumber Data" (3 Okt 2026)

**Gejala (laporan operator).** Di halaman detail perangkat MONITOR, baris *Sumber Level Air*
menampilkan teks mentah `@else` dan **kedua cabang tampil sekaligus**:

> Sensor ultrasonik pada perangkat ini (relay ikut logika AUTO) (Sensor Mbaran)**@else**Dari
> perangkat MONITOR satu tangki (perangkat ini pompa saja) — Bak Pamsimas Mbaran

**Sebab.** `resources/views/devices/show.blade.php:209` menulis `@elseDari perangkat …` tanpa
pemisah. Compiler Blade menangkap nama direktif secara *greedy* (`[A-Za-z0-9_]+`) sehingga
yang terbaca adalah direktif tak dikenal **`elseDari`** → dibiarkan apa adanya sebagai teks,
`@if` tetap aktif, dan isi cabang `else` ikut tercetak. Bukan masalah data, bukan masalah
perangkat — murni salah tulis direktif.

**Perbaikan.** Isi cabang `else` dipindah ke echo Blade sehingga karakter setelah `@else`
bukan huruf:
`…@else{{ 'Dari perangkat MONITOR satu tangki (perangkat ini pompa saja)' }}@endif &mdash; …`

**Audit menyeluruh pola serupa** (semua `resources/views/**/*.blade.php`):
- `@else(?!if)[A-Za-z]` → **1 temuan** (baris 209, sudah diperbaiki).
- `@endif[A-Za-z]`, `@endforeach[A-Za-z]`, `@endforelse[A-Za-z]`, `@empty[A-Za-z]`,
  `@endwhile[A-Za-z]`, `@endphp[A-Za-z]` → **0 temuan**.
- `@endfor[A-Za-z]` → 34 "temuan" **palsu** (cocok dengan `endfor` di dalam `endforeach`).
- `@endif&mdash;` / `@endif<` aman: direktifnya dibatasi karakter non-huruf sehingga tetap
  dikompilasi benar.

**Verifikasi deploy:** MD5 terpasang `41d5a377c3fd861f887e6f314502bc4f` (local = server),
`view:clear` + `view:cache` OK, `elseDari` = 0 di sumber dan 0 di
`storage/framework/views/*`; render baris asli (diambil dari berkas terpasang) dengan data
nyata → **#2 MONITOR**: "Sensor ultrasonik pada perangkat ini (relay ikut logika AUTO)
(Sensor Mbaran) — Bak Pamsimas Mbaran"; **#3 ACTUATOR**: "Dari perangkat MONITOR satu tangki
(perangkat ini pompa saja) — Bak Pamsimas Mbaran"; literal `@else` tidak ada di keduanya.
Cadangan view: `/tmp/backup-view-20261003-150920`.

**Catatan gaya penulisan Blade (cegah terulang):** setelah direktif **tanpa argumen**
(`@else`, `@endif`, `@endforeach`, `@empty`, `@endwhile`) selalu beri spasi/newline; jangan
menyambungnya langsung ke kata (mis. `@elseDari`), karena akan dibaca sebagai direktif baru.

### 7.14 Penyederhanaan label "Sumber Level Air" — cukup nama sensor terdaftar (3 Okt 2026)

**Usulan operator.** Di halaman detail perangkat, baris *Sumber Level Air* cukup menyebutkan
**sumbernya saja = nama sensor yang terdaftar**; tidak perlu kalimat panjang
("Sensor ultrasonik pada perangkat ini (relay ikut logika AUTO) …", "… (perangkat ini pompa
saja) — Bak …"). Peran perangkat sudah dijelaskan baris **Tipe Perangkat**
("MONITOR (sensor + pompa, fungsi ganda)" / "ACTUATOR (pompa saja, tanpa baca sensor)") dan
nama bak sudah ada pada baris **Tangki**, jadi keduanya berulang.

**Perubahan (`resources/views/devices/show.blade.php`, blok `@php` + satu baris `<li>`):**
- **MONITOR** → nama sensor miliknya sendiri (`$device->sensor?->sensor_name`).
- **ACTUATOR** → nama sensor milik perangkat **MONITOR se-tangki** (resolusi sama dengan
  interlock `DeviceApiController::tankMonitor()`), karena ACTUATOR tidak punya sensor sendiri
  (`sensor_id = NULL` pada #3).
- Tidak ada sensor terdaftar ⇒ teks `Belum ada sensor terdaftar` (bukan kalimat panjang).
- Sufiks `— Bak <nama tangki>` dihapus karena sudah ada baris *Tangki*.

**Verifikasi.** MD5 terpasang `85d8caeef7350042be8fe793facf62e7` (lokal = server),
`view:clear` + `view:cache` OK; blok 14 baris **diambil langsung dari berkas terpasang** lalu
dirender dengan data nyata → **#2 MONITOR**: `Sensor Mbaran`; **#3 ACTUATOR**: `Sensor Mbaran`
(diambil dari MONITOR se-tangki). Cadangan view `/tmp/backup-view-20261003-151527`.

### 7.15 Grafik "muncul dari bawah" setiap live refresh (3 Okt 2026)

**Gejala (laporan operator).** Grafik riwayat di halaman detail perangkat tampak
**muncul/tumbuh dari bawah setiap live refresh** (tiap 5 detik). Yang diharapkan: garis
cukup **bertambah panjang**; animasi masuk hanya saat halaman di-reload.

**Sebab.** `updateChart()` (`resources/views/devices/show.blade.php`) memanggil
`chart.update()` — animasi Chart.js aktif — **dan** mengganti `chart.options` secara utuh
(`chart.options = options`) pada setiap refresh. Chart.js memperlakukan opsi/deret yang
diganti sebagai keadaan baru sehingga memutar ulang animasi masuk (tumbuh dari garis dasar).

**Perbaikan.** Chart diperbarui **di tempat** dan **tanpa animasi**:
hanya bagian yang dinamis yang diubah — `chart.data.datasets`, anotasi
(`chart.options.plugins.annotation.annotations`), `scales.x.time.unit`,
`scales.y.beginAtZero`, `scales.y.min` — lalu `chart.update('none')`. Chart hanya dibuat
sekali (`new Chart(...)`), sehingga animasi tumbuh-dari-bawah terjadi **hanya** saat chart
pertama dibuat (reload halaman / pindah perangkat).

**Verifikasi (harness Node, A/B — bukan sekadar baca kode).** Blok grafik
(`function boxAnnotation` … sebelum `async function fetchChartData`) diekstrak dari berkas,
`Chart` di-stub yang mencatat konstruksi + argumen `update()`:

| Berkas | Konstruksi Chart | Mode `update()` |
|---|---|---|
| Sebelum (cadangan server) | 1 | `(default = beranimasi)` × 3 |
| Sesudah (lokal) | 1 | `none` × 3 |
| Sesudah (**unduhan dari server**) | 1 | `none` × 3 |

Dataset & anotasi tetap diperbarui (`pumpBoxLast`, `triggerLine`), dan `scales.x.time.unit`
ikut berubah saat rentang diganti (`live` → `1440` ⇒ `hour`). Deploy: MD5
`1fc484240d93ddb421952b155e9b85c5` (lokal = server), `view:clear` + `view:cache` OK,
`update('none')` ada dan `chart.options = options` sudah **0**; cadangan
`/tmp/backup-view-20261003-152305`.

**Efek samping yang disengaja:** mengganti rentang (60/1h/1d) dan toggle *auto-scale* kini
juga instan tanpa animasi — konsisten dengan permintaan ("animasi hanya saat reload").

### 7.16 "Waktu Nyala" 1000× terlalu besar — `uptime` milidetik dianggap detik (4 Okt 2026)

**Gejala (laporan operator).** Kartu *Detail Konfigurasi* menampilkan
**ACTUATOR #3 "308 hari 7 jam 29 menit"** dan **MONITOR #2 "265 hari 19 jam 53 menit"**,
padahal kedua perangkat baru di-flash 3 Okt — mustahil.

**Sebab.** Firmware mengirim `uptime` sebagai **MILIDETIK** (`millis()`):
`.fw_code/Pamsimas_Hybrid/Network_SSL.ino:90` → `doc["uptime"] = millis();` — dan seluruh
firmware sistem lama juga begitu (bahkan berkomentar "Uptime dalam milidetik",
`backup_pamsimas/.fw_code/Pamsimas_esp8266/Pamsimas_esp8266.ino:1201`). Tampilan
memperlakukannya sebagai **DETIK**:
- server (`show.blade.php:238`) → `floor($device->uptime / 3600)` "jam";
- klien (`show.blade.php` → `fmtUptime(d.uptime)` dari `/api/dashboard-data`) → dibagi 86400
  sebagai "hari" (itulah kenapa angka hari muncul, dengan format berbeda dari sisi server).
Nilai ms/1000 = 1000 detik/… ⇒ hasil **1000× lebih besar**.

**Perbaikan (di sisi tampilan; DB & API tetap milidetik).** `devices.uptime` **tidak** diubah
supaya tetap kompatibel dengan sistem lama (kolom & API yang sama). Yang disesuaikan:
1. Blade: blok `@php` menghitung `$uptimeSec = intdiv($device->uptime, 1000)` lalu memformat
   persis seperti `fmtUptime()` (hari hanya bila > 0, jam bila ada hari/jam, menit selalu).
2. JS: `setText('val-uptime', fmtUptime(Math.floor((Number(d.uptime) || 0) / 1000)))`.

**Verifikasi.**
- Node (harness `fmtUptime` yang diekstrak dari berkas — sebelum vs sesudah):
  | Perangkat | Nilai | Sebelum (salah) | Sesudah (benar) |
  |---|---|---|---|
  | #2 | 23.153.589 ms | `267 hari 23 jam 33 menit` | `6 jam 25 menit` |
  | #3 | 26.818.149 ms | `310 hari 9 jam 29 menit` | `7 jam 26 menit` |
  | contoh | 95.000.000 ms | — | `1 hari 2 jam 23 menit` |
- Render sisi server (blok asli diambil dari berkas terpasang): #2 `uptime = 23.216.395 ms`
  → **`6 jam 26 menit`**; #3 `26.939.434 ms` → **`7 jam 28 menit`** — format identik dengan
  sisi JS sehingga angka tidak "melompat" saat poll pertama.
- Deploy: MD5 `650b0919de889691af42c368d6ed430b` (lokal = server), `view:clear`+`view:cache` OK,
  backup `/tmp/backup-view-20261003-222356`.

**Kontrak data (penting):** `devices.uptime` = **milidetik** (`millis()` perangkat). Setiap
konsumen tampilan baru **wajib** membagi 1000 (atau gunakan 1 helper bersama). Bila kelak ada
firmware yang mengirim detik, sesuaikan di sini.

**Temuan operasional menyertai (4 Okt 2026 pagi):**
- **MONITOR #2 sudah ONLINE dan mengirim data lagi** sejak 3 Okt 22:06 (58 request `/api/log`
  di log akses; `sensor_logs` 4 Okt sudah 4.583 baris); level **≈66,6 %** (`cm ≈ 92,8`),
  naik dari 0 % pada 3 Okt ⇒ pengisian selama 3,4 hari itu memang mengisi bak.
  Konsekuensinya **keputusan §7.12 (mode otonom tanpa sensor) kini tidak lagi berlaku** —
  ACTUATOR #3 kembali memakai data level yang sahih (`source_ready`/level segar).
- Siklus kedua perangkat kini bersih tanpa ON palsu: #2 `OFF 04:45:59 → ON 04:56:01`
  (istirahat 10,0 mnt) `→ OFF 05:07:05` (nyala 11,1 mnt) `→ ON 05:17:06`; #3
  `OFF 04:25:37 → ON 04:35:40` (10,0 mnt) `→ OFF 05:05:41` (30,0 mnt) `→ ON 05:15:42`.
- `reset_reason` #3 = **`Exception`** (reboot terakhir karena *crash*/panic, bukan power-on)
  sekitar 3 Okt 21:55; **stabil 7,5 jam** sejak itu, heap 31 KB. #2 = `External System`
  (reboot ~22:56, kemungkinan saat operator memasang kembali perangkat monitor).
  Keduanya reboot di rentang waktu yang sama (21:55–23:00) — patut dicatat sebagai jeda
  gangguan daya/pemasangan, bukan pola reboot berulang.

### 7.17 "Log Kejadian Terakhir" dibuat satu log = satu baris (4 Okt 2026)

**Permintaan operator.** Daftar log informasi di halaman detail perangkat agar **1 log = 1
baris** supaya mudah diperiksa dan dibandingkan.

**Sebelum.** Tiap entri memakai 2 baris: pesan (tebal) di atas, lalu waktu • tipe di bawahnya
(`<div class="log-content">` berisi `.log-message` + `.log-timestamp`), padding 12/15px, ikon
32px ⇒ hanya ~5-6 entri terlihat dan waktu antar-entri sulit dibandingkan.

**Sesudah.** Satu baris fleksibel (`<li class="log-item">` berisi `<span>` saja, tanpa `<div>`):
| Kolom | Lebar | Gaya |
|---|---|---|
| ikon status | 22×22 px | lingkaran berwarna sesuai jenis (power/success/warning) |
| waktu | tetap **128 px** | monospace + `tabular-nums` (`04-10-2026 05:15:42`) agar rapi sejajar |
| tipe | tetap **84 px** | uppercase, abu-abu (`PUMP`, `INFO`, `KONEKSI`) |
| pesan | `flex:1` | dipotong `…` bila panjang, teks lengkap via tooltip `title` |

Padding diringkas jadi 6/12px + highlight saat hover ⇒ ±13 entri terlihat tanpa scroll.
Halaman log lain (`logs/events`, `logs/pumps`, `logs/sensors`, `logs/admin`) memang sudah
berupa tabel (satu baris per entri) sehingga tidak diubah.

**Verifikasi.** Blok `<ul class="log-list">…</ul>` (baris 297-318) diambil dari berkas
terpasang lalu dirender dengan data nyata: **#3 → 6 event = 6 `<li class="log-item">` dengan
0 `<div>`**; **#2 → 6 = 6, 0 `<div>`**; setiap entri tercetak satu baris, mis.
`04-10-2026 05:15:42  Pump  Pompa ON (AUTO) — laporan perangkat`. Deploy: MD5
`54aa831df9263d70c5139c7a0f48f1b0` (lokal = server), `view:clear` + `view:cache` OK,
backup `/tmp/backup-view-20261003-224224`.

- `applyAutoControl()` kini murni saran; interlock `source_ready` bawaan sistem lama
  (`source_ready == 0` ⇒ firmware lama mematikan pompa) belum dipulihkan: port ini masih
  mengirim `source_ready = 1` hardcode dan firmware Hybrid belum membacanya (butuh
  perubahan firmware + flash). Lihat §7.9 Opsi A/B.

### 7.18 Log durasi **nyala/mati** pompa dihitung dari transisi (tanpa ubah database) (4 Okt 2026)

**Permintaan operator.** "Tanpa mengubah database, tambahkan log durasi nyala dan mati" —
operator ingin langsung melihat **berapa lama pompa menyala** dan **berapa lama istirahat**
pada log, tanpa menambah kolom/tabel.

**Akar masalah.** Kolom `pump_logs.duration_seconds` **sudah ada** tetapi **tidak pernah diisi**
(firmware tidak mengirimnya, server pun tidak menghitungnya — sudah dicatat di §7.9/§7.16),
sehingga halaman *Riwayat Log Pompa* selalu menampilkan **`0`** pada kolom *"Durasi (dtk)"*
alias informasi durasi sebenarnya hilang. Karena itu **DB tidak diubah** (sesuai permintaan):
durasi dihitung **saat render** dari selisih waktu antar-transisi yang sudah tersimpan.

**Cara hitung (helper baru `app/Support/PumpDuration.php`).** Untuk tiap transisi, durasi =
selisih `pump_logs.timestamp` dengan **transisi sebelumnya pada perangkat yang sama**
(dikelompokkan per `device_id`, diurutkan menaik — penting karena halaman *Riwayat Log Pompa*
mencampur semua perangkat dalam satu tabel):
| Baris | Durasi yang ditampilkan | Label |
|---|---|---|
| `pump_status = OFF` | selisih ke transisi **ON** sebelumnya = **lama pompa menyala** | `nyala 00:30:02` |
| `pump_status = ON` | selisih ke transisi **OFF** sebelumnya = **lama istirahat/mati** | `mati 00:10:01` |

- `PumpDuration::format()` → `HH:MM:SS` (+ `Xd ` bila lebih dari sehari);
- `PumpDuration::mapFromLogs($logs)` → peta `id` ⇒ `['id','waktu','dari','detik','teks']`,
  `'waktu'` (`Y-m-d H:i:s`) dipakai untuk **mencocokkan entri `event_logs` bertipe `Pump`**
  di halaman detail (waktu kejadian & waktu `pump_logs` memang identik — satu `now()`);
- baris transisi paling tua di satu halaman paginasi tidak punya pembanding di halaman itu ⇒
  helper mengambil **satu SELECT tambahan** (`previousRow()`: transisi terakhir sebelum baris
  itu pada perangkat yang sama) supaya durasi **selalu terisi**, tidak `—`;
- hanya **SELECT**, tidak ada `INSERT/UPDATE/ALTER` ⇒ **skema & data tidak berubah** (§3/§10).

**Tampilan.**
1. `resources/views/logs/pumps.blade.php` — header `Durasi (dtk)` → **`Durasi`**; sel diisi
   chip `nyala 00:30:02` / `mati 00:10:01` (Tailwind: `rounded-full bg-slate-100 … font-mono`)
   dari `PumpDuration::mapFromLogs(collect($logs->items()))`.
2. `resources/views/devices/show.blade.php` — daftar *Log Kejadian Terakhir* (satu baris/entri,
   §7.17) diberi **chip durasi di ujung kanan** (`.log-dur`, `margin-left:auto`) khusus entri
   `event_type = Pump`, dicocokkan lewat waktu kejadian; entri lain (`Info`, `Koneksi`, …) tetap
   bersih tanpa chip. Tooltip menjelaskan arti (`Durasi nyala sebelum pompa dimatikan`).

**Verifikasi.**
- Helper (10 transisi terakhir **#3**, data nyata):
  `… 02:35:28 OFF → mati 00:10:02` · `03:05:29 ON → nyala 00:30:01` · `03:15:32 OFF → mati 00:10:03`
  · `03:45:37 ON → nyala 00:30:05` … — **berpasangan ganjil-genap & sesuai** `on_duration`
  #3 = 30 menit / `off_duration` = 10 menit.
- Tabel *Riwayat Log Pompa* (50 baris pertama, **semua perangkat** dicampur): **50 chip durasi,
  0 sel masih bernilai `0`** — mis. `C4:D8:D5:13:A6:17` (#2) `OFF → nyala 00:11:03` dan
  `CC:50:E3:52:F3:B6` (#3) `OFF → nyala 00:30:02` ⇒ pengelompokan per perangkat terbukti benar.
- Daftar log halaman detail (blok `@php` + `<ul class="log-list">` dari **berkas terpasang**,
  dirender dengan `eventLogs`/`pumpLogs` nyata): **#3 → 4 chip dari 6 entri**, **#2 → 4 chip
  dari 6 entri**, keduanya `OFF → nyala 00:30:02` (#3) / `nyala 00:11:03` (#2) dan
  `ON → mati 00:10:01`; entri `Info Safety Cut-off` **tanpa chip** (benar).
- Deploy: 3 berkas (`app/Support/PumpDuration.php` baru + 2 view), staging LF/no-BOM,
  `php8.3 -l` OK, **MD5 3/3 MATCH** (`cd60e7011c0ab01806d2cb6476f18d2e`,
  `4f4d43416b186002667f086b83e9a8dd`, `831036fcd50a67489b69b3035af8f853`),
  autoload **PSR-4 tanpa classmap** (kelas baru langsung dikenali — cek `App\Support\Permission`
  di `vendor/composer/autoload_classmap.php`), `view:clear` + `view:cache` OK (54 view ter-cache;
  hasil kompilasi memuat `log-dur` & `PumpDuration`), backup `/tmp/backup-dur-20261003-225437`.
- Catatan operasional: `ssh -p 2222 root@127.0.0.1` memunculkan
  **`WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED!`** ⇒ sebelum menyentuh berkas, host
  dipastikan lewat `hostname` (`pamsimas.selur.my.id`) **dan** MD5 view terpasang masih sama
  dengan baseline §7.17 (`54aa831d…`) — dipakai opsi `-o UserKnownHostsFile=/dev/null -o
  StrictHostKeyChecking=no` untuk sesi ini.

**Verifikasi awal yang menyesatkan (dicatat supaya tidak terulang):** harness pertama mengambil
potongan template **mulai dari baris `<ul class="log-list">`** sehingga blok `@php` pemetaan
durasi (yang berada **di atas** `<ul>`) tidak ikut dirender ⇒ hasil `0 chip` (padahal view
benar). Slice harus dimulai dari **baris `@php`** blok tersebut.

### 7.19 Badge tipe perangkat **MON / ACT** pada kartu gauge (4 Okt 2026)

**Permintaan operator.** Kartu gauge (dashboard **dan** halaman detail perangkat) perlu penanda
tipe perangkat agar langsung terlihat mana **MONITOR** dan mana **ACTUATOR** — singkatnya
**MON** / **ACT**

**Sumber data (tanpa perubahan API/DB).** `device_type` sudah dikirim `/api/dashboard-data`
(`DashboardApiController.php:55`) dan **kedua** halaman memakai endpoint itu. Halaman detail juga
mengeksposnya sejak boot: `window.DEVICE_CONFIG.deviceType = @json($device->device_type)`.

**Tampilan.** Badge diletakkan di **header kartu gauge** (`gauge-header-container`), berdampingan
dengan indikator online/offline. Indikator + badge dibungkus grup **`.hdr-left`** supaya jarak
tetap rapi meski header memakai `justify-content:space-between`; urutan header jadi:
**[dot online] [MON./ACT.] … [badge timer pompa] [kekuatan sinyal]**.
| Tipe | Label | Warna |
|---|---|---|
| `MONITOR` (sensor + pompa, fungsi ganda) | **MON** | indigo (`#eef2ff` / `#4338ca`) |
| `ACTUATOR` (pompa saja, tanpa baca sensor) | **ACT** | oranye (`#fff7ed` / `#c2410c`) |
| tipe lain / kosong | *tanpa badge* | — |

Tooltip menjelaskan tipe lengkapnya. CSS memakai aturan khusus halaman (`.device-type-badge`) di
blok `<style>` masing-masing — **bukan kelas Tailwind baru** — sesuai catatan §10 (kelas Tailwind
baru belum tentu ada di bundle Vite).

**Perubahan kode.**
1. `resources/views/dashboard/index.blade.php` — helper `deviceTypeInfo()` + `deviceTypeBadgeHtml()`;
   `cardHeader(d)` membungkus indikator online + badge di `.hdr-left`; `updateSlot()` menyegarkan
   badge dari `dev.device_type` pada setiap poll (3 dtk) sehingga perubahan tipe ikut tampil.
2. `resources/views/devices/show.blade.php` — `CFG.deviceType` baru; helper yang sama;
   `renderGaugeCardStructure()` menyisipkan badge di header kartu; `applyDeviceState()`
   menyegarkan badge bila API mengirim `device_type`.

**Verifikasi (harness Node; kode diambil langsung dari berkas, bukan salinan tangan).**
37 pemeriksaan **lulus**, dijalankan dua kali: pada berkas **lokal** dan pada berkas **hasil
unduhan dari server** — hasil identik:
- sintaks kedua blok `@verbatim` valid (`new vm.Script`) → tidak ada JS yang rusak;
- `cardHeader({device_type:'MONITOR'})` → ada `class="hdr-left"`, `class="device-type-badge is-mon"`,
  teks `MON`, tooltip benar; `ACTUATOR` → `is-act` + `ACT`; tipe tak dikenal/kosong → **tanpa
  badge**; indikator online + 4 bar sinyal tetap utuh (tidak ada regresi header);
- ekspresi `header.innerHTML` halaman detail (diekstrak dari berkas lalu dievaluasi) menghasilkan
  hasil sama dan tetap memuat `data-pump-led`, `data-pump-timer` (`--:--:--`), `data-signal`;
- penyisipan badge timer dashboard (`hdr.insertBefore(tBadge, sigEl)`) tidak berubah sehingga
  posisinya tetap sebelum indikator sinyal;
- 4 selektor CSS ada di **kedua** halaman; `device_type` ada di API; `CFG.deviceType` ada di detail.
- Pratinjau header nyata dari berkas terpasang: **#2** `C4:D8:D5:13:A6:17` (MONITOR) →
  `…<span class="device-type-badge is-mon" data-device-type title="Tipe perangkat: MONITOR — sensor + pompa (fungsi ganda)">MON</span>…`
  dan **#3** `CC:50:E3:52:F3:B6` (ACTUATOR) → `…is-act …>ACT</span>…` — **identik** antara berkas
  server & lokal. Data DB: #2 `MONITOR` (`sensor_id` 2), #3 `ACTUATOR` (`sensor_id` `-`).
- Deploy: MD5 `44635c61e3569b9437198ba6c300968d` (dashboard) & `a2c3ae4dd5e26d1ead54b92edf838e75`
  (detail) — **lokal = server**; `view:clear` + `view:cache` OK; **2** view terkompilasi memuat
  `device-type-badge`; backup `/tmp/backup-badge-20261004-005117`.

**Revisi (4 Okt 2026, permintaan operator: "tidak perlu di beri '.'").** Titik di akhir label
dihapus → `MON.` / `ACT.` menjadi **`MON` / `ACT`** (komentar kode & dokumen ikut disesuaikan;
tooltip tetap menyebut tipe lengkapnya). Deploy ulang 2 view: MD5
`d18203858612458d7da9216f67460707` (dashboard) & `ee1a3839d32d3f0c9d724d2d51da4f3f` (detail) —
**lokal = server**; `view:clear` + `view:cache` OK; view terkompilasi yang **masih** memuat
`>MON.<`/`>ACT.<` = **0 & 0**, yang memuat `device-type-badge` = **2**, sehingga tidak ada sisa
label bertitik; backup `/tmp/backup-badge2-20261004-005510`. Harness diperluas menjadi **37**
pemeriksaan (4 di antaranya negatif, memastikan `>MON.<`/`>ACT.<` memang tidak ada) — lulus pada
berkas lokal **dan** salinan hasil unduhan server; pratinjau header nyata kini `>MON</span>` (#2)
dan `>ACT</span>` (#3).

### 7.20 Ikon seluruh aplikasi dikonversi ke tema **Font Awesome 6.4.2** (seperti backup) (4 Okt 2026)

**Permintaan operator.** *"Ubah ikon-ikonnya menjadi tema seperti backup."* Sistem lama
(`backup_pamsimas`) memakai **Font Awesome** (`<i class="fas fa-...">`), sedangkan port Laravel ini
masih memakai **emoji** (📡 🛢️ 💧 ⚙️ …) sehingga tampilan tidak satu tema.

**Tema & rujukan.** Ditambahkan CDN yang sama dengan `backup_pamsimas/app/Views/layouts/main.php:34`
→ `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css`, lalu **78 emoji**
di **10 berkas view** diganti ikon FA yang **diambil dari kosakata ikon backup**:

| Lokasi | Emoji | Ikon FA (rujukan backup) |
|---|---|---|
| nav Dashboard / Perangkat / Terdeteksi / Monitoring | 📊 📡 🔎 🖥️ | `fa-tachometer-alt` (`sidebar.php:16`), `fa-microchip` (`:62`), `fa-search` (`:56`), `fa-server` (`:155`) |
| nav Kasir Meter / Pembayaran / Pelanggan / Tarif | 🔍 💰 👥 💵 | `fa-file-invoice-dollar` (`:33`), `fa-money-bill-wave` (`:27`), `fa-address-book` (`:45`), `fa-hand-holding-usd` (`:39`) |
| nav Tangki / Pompa / Sensor / Tampilan / Template Gauge | 🛢️ ⚙️ 📶 🎨 🧩 | `fa-database` & `fa-fan` (`dashboard/index.php:26,35`), `fa-satellite-dish` (`show.php:98`), `fa-palette`, `fa-magic` |
| nav Log Pompa / Sensor / Event / Admin + Pengguna + Keluar + ☰ | 📜 📈 🔔 🗂️ 👤 🚪 ☰ | `fa-history` (`sidebar.php:131`), `fa-chart-line` (`:125`), `fa-list-check`, `fa-shield-alt`, `fa-users-cog`, `fa-sign-out-alt` (`:164`), `fa-bars` |
| toast sukses / gagal | ✅ ⚠️ | `fa-check-circle` / `fa-exclamation-triangle` (pola `app-core.js:37`) |
| kartu statistik dashboard | 📡 🛢️ 💰 🔍 | `fa-wifi` / `fa-database` / `fa-money-bill-wave` / `fa-file-invoice-dollar` (`dashboard/index.php:17,26`) |
| kartu statistik **detail** | 💧 ⚙️ 🎛️ 📶 📡 🔄 ⏱️ | `fa-tint`, `fa-power-off`, `fa-sliders-h`, `fa-wifi`, `fa-wifi`, `fa-sync`, `fa-stopwatch` (`devices/show.php:11-59`) |
| judul section detail | ⚙️ 📈 🕓 | `fa-cogs` / `fa-chart-line` / `fa-history` (`show.php:131`) |
| ikon log kejadian | ℹ️ 📶 📴 ⚡ ⏻ | `fa-info-circle`, `fa-wifi`, `fa-unlink`, `fa-bolt`, `fa-power-off` (`show.php:137-141`) |
| kipas pompa (CSS `conic-gradient`) | `.fan-icon` | `<i class="fas fa-fan" data-fan>` + kelas **`.fa-spin`** (`dashboard-live.js:531-539`) |
| kasir & meter: tab, tombol massal, hapus, badge status | 1️⃣2️⃣3️⃣ ✅ 🗑 🎉 ✔ ⏳ ✖ | `fa-list-check`, `fa-keyboard`, `fa-table`, `fa-check-double`, `fa-trash`, `fa-check-circle`, `fa-check`, `fa-hourglass-half`, `fa-times` |
| pelanggan / pembayaran / tarif | 📣 💡 ✓ → | `fa-bullhorn`, `fa-info-circle`, `fa-check`, `fa-arrow-right` |

**Titik implementasi penting.**
1. Ikon disimpan sebagai **nama kelas** lalu dirender di luar `{{ }}` (`<i class="fas {{ $x }}"></i>`)
   supaya **tidak di-escape** Blade — sama seperti backup (`show.php:137-141`).
2. CSS pendukung memakai aturan biasa (bukan kelas Tailwind baru, lihat §10):
   `#sidebar nav a > i.fas { width:1.15em; text-align:center; flex:none; }` agar label menu tetap
   sejajar; `.pump-info-label i[data-fan]` + `.fa-spin` menggantikan `@keyframes fanSpin`.
3. **Sengaja tidak diubah** (bukan ikon): `→` pada teks/komentar ("kartu → halaman detail"),
   `⌀` (simbol diameter di `settings/tanks`), `m³`, `±`, `×`, `·`, `—`.

**Verifikasi.**
- **Lokal**: uji-kering dulu (semua 78 pola cocok) sebelum eksekusi; pindai ulang seluruh view →
  karakter non-ASCII tersisa hanya `—`(49) `→`(13) `³` `·` `©` `±` `×` `⌀` = **0 emoji ikon**;
  cek sintaks semua blok JS (6 berkas, 9 blok, Blade dijadikan placeholder) **valid**;
  harness badge gauge lama tetap **37/37** (tidak ada regresi).
- **Server**: MD5 **10/10 MATCH** (contoh: `d5a7e6c89ffc3e211acbaf8405441537` layout,
  `a29de28e3c8b24e9361407f1f07c1e51` detail, `f7313ca18deb0a9f05f3510f8938b314` meter);
  `view:clear` + `view:cache` OK; **60 ikon `<i class="fas`** di view terkompilasi & **0 emoji**.
  Verifier PHP: render **sidebar dengan sesi `role=Administrator`** → **21 ikon nav** (semua menu
  di atas ada), render **halaman detail #3 dengan data nyata** → 13 ikon kartu/judul/log + kipas
  `fa-fan`/`fa-spin` + chip durasi §7.18 tetap ada, **45/46** lulus.
- **Uji pemetaan ikon log** (5 kejadian disuntikkan ke tampilan, tanpa mengubah data DB) →
  **7/7 lulus**: `tersambung→fa-wifi`, `terputus→fa-unlink`, `boot→fa-bolt`, `nyala/mati→fa-power-off`.
- Backup: `/tmp/backup-fa-20261004-053714` (10 berkas).

**Temuan sampingan.**
- Satu "kegagalan" awal (`fa-bolt` tidak muncul) bukan bug: **20 log terakhir #3 tidak memuat
  kejadian boot** → diverifikasi dengan menyuntikkan contoh kejadian (lihat di atas).
- Cabang log `nyala`/`mati` hanya memicu bila **pesan** memuat kata itu; pesan nyata firmware/server
  memakai **"Pompa ON/OFF (AUTO)"** sehingga ikonnya jatuh ke `fa-info-circle` — **perilaku lama
  yang sudah ada sebelum konversi ini** (sebelumnya juga jatuh ke emoji ℹ️); tidak diubah di tugas ini.
- Checker sintaks awal memberi positif palsu karena blok `<script>` memuat penanda `@verbatim`
  → ditangani dengan membuang penanda tersebut sebelum diperiksa.

### 7.21 Menu sidebar "Perangkat Terdeteksi" dihapus (4 Okt 2026)

**Permintaan operator.** *"Perangkat Terdeteksi pada sidebar dihilangkan saja karena di
https://pamsimas.selur.my.id/devices sudah ada."*

**Mengapa aman.** `DeviceController::index()` sudah mengirim `detected` (baris 29-30) dan
`devices/index.blade.php` menampilkan bagian **"Perangkat Terdeteksi Otomatis"** (baris 60-110,
termasuk tombol hapus → `devices.detected.delete`) ⇒ daftarnya tetap terlihat di `/devices`.
Rute `GET /devices/detected` (`routes/web.php:44`) **tidak dihapus** — hanya tautan sidebar-nya.

**Perubahan (`resources/views/layouts/app.blade.php`, 1 baris jadi 4).**
1. `$navItem(route('devices.detected'), …, '<i class="fas fa-search"></i> Perangkat Terdeteksi', …)`
   **dihapus**, diganti komentar Blade yang menjelaskan alasan penghapusan.
2. `request()->routeIs(...)` pada menu **Perangkat** diperluas →
   `devices.index, devices.show, devices.edit, devices.detected, devices.create`, supaya saat
   membuka `/devices/detected` atau halaman daftar perangkat baru, menu **Perangkat** tetap
   disorot (sebelumnya disorot oleh menu yang kini dihapus).
3. `$detectedCount` ikut tak dirujuk lagi — variabel itu **tidak pernah diisi siapa pun** (muncul
   hanya 1× di seluruh repo, dengan fallback `?? 0`) sehingga badge-nya memang selalu kosong;
   tidak ada kode lain yang perlu dibersihkan.

**Verifikasi — 11/11 lulus.**
- Render sidebar (sesi `role=Administrator`): teks "Perangkat Terdeteksi" **tidak ada** di HTML,
  `fa-search` **tidak ada**, jumlah ikon FA sidebar **21 → 20**, menu Perangkat tetap tertaut ke
  `/devices`, menu Dashboard/Monitoring tetap utuh.
- View terkompilasi: `routeIs('devices.detected','devices.create')` **ada** pada menu Perangkat;
  `$navItem(route('devices.detected'))` = **0**; `$detectedCount` = **0**; judul & aksi hapus
  bagian terdeteksi di `/devices` **masih ada**.
- `/devices` dirender dengan data nyata → bagian "Perangkat Terdeteksi Otomatis" muncul (entri
  DB kosong → empty-state "Tidak ada perangkat terdeteksi", wajar); rute `devices.detected` dan
  URL aksi `…/devices/detected/{id}/delete` masih terbentuk benar.
- Deploy: MD5 `aff599ce527c21e26ea79b3381e58fff` (**lokal = server**), `view:clear`+`view:cache`
  OK, `/login` **200** & `/` **302**, backup `/tmp/backup-menu-20261004-054908`.
- Dua asersi awal gagal karena **cara uji**, bukan karena kode: `routeIs(...)` tidak muncul di
  HTML hasil render (harus dicek di view terkompilasi) dan `@forelse` tidak me-render tombol hapus
  saat `detected` kosong → diverifikasi ulang (`vfy_menu2.php`) dan lulus semuanya.


### 7.22 Kartu statistik dashboard: kotak ikon disamakan gaya backup (4 Okt 2026)

**Permintaan operator.** Menunjuk 4 kartu di dashboard (Perangkat Online, Total Tangki, Tagihan
Belum Bayar, Meter Menunggu Validasi): *"icon ini … disamakan"*. Dikonfirmasi lewat pertanyaan →
operator memilih: **warna/kotak ikonnya disamakan gaya backup**, glyph ikon **tetap** karena sudah
sama dengan backup.

**Fakta awal (penting).** Sebelum perubahan ini keempat kartu **sudah memakai Font Awesome**
(hasil Task #64 — tidak ada emoji; dirender ulang dari server: `fa-wifi`, `fa-database`,
`fa-money-bill-wave`, `fa-file-invoice-dollar`). Yang beda dengan backup hanya **kotaknya**:
gradien Tailwind (`bg-gradient-to-br from-sky-500 to-cyan-600`, 56px, radius 2xl).

**Sebelum → Sesudah (mengikuti `backup_pamsimas/public/css/style.css:249-275`):**
| Kartu | Glyph (tetap) | Warna kotak |
|---|---|---|
| Perangkat Online | `fa-wifi` | gradien sky→cyan → **hijau `#27ae60`** (`bg-green`, warna kartu *Online* backup) |
| Total Tangki | `fa-database` | gradien violet→purple → **oranye `#f39c12`** (`bg-orange`, warna kartu *Tangki* backup) |
| Tagihan Belum Bayar | `fa-money-bill-wave` | gradien amber→orange → **biru `#3498db`** (`bg-blue`) |
| Meter Menunggu Validasi | `fa-file-invoice-dollar` | gradien emerald→teal → **ungu `#6f42c1`** (`bg-purple`) |

Spesifikasi backup yang disalin persis: **lingkaran 50px** (`border-radius:50%`), ikon **24px
putih**, dan efek `scale(1.05)` saat kartu di-hover (pengganti `group-hover:scale-105`). CSS
didefinisikan sendiri di `@push('styles')` (`.stat-tile` + 5 varian warna) — **bukan** kelas
Tailwind baru (lihat §10) supaya warnanya dijamin tampil.

**Verifikasi — 15/15 lulus** (render ulang `dashboard.index` di server dengan data nyata):
- 4 kotak `class="stat-tile …"` dengan pasangan warna/ikon persis seperti tabel di atas;
- CSS ada di HTML hasil render: `width:50px; height:50px; border-radius:50%`, `font-size:24px`,
  `#27ae60`, `#f39c12`, `#3498db`, `#6f42c1`; sisa `bg-gradient-to-br {{` pada kartu = **0**;
- Font Awesome 6.4.2 tetap dimuat, halaman **tanpa emoji**, total `<i class="fas` = 8;
- MD5 `f6a21700003ff91a61a5dcdd90471ccb` (**lokal = server**), `view:clear`+`view:cache` OK,
  `/login` **200** & `/` **302**, backup `/tmp/backup-dash2-20261004-060050`;
- cek sintaks blok JS (checker lokal) tetap valid.

**Catatan:** bila operator masih melihat tampilan lama, kemungkinan cache browser → cukup
**reload (F5)**; sumber (origin) sudah menyajikan tampilan baru.

**Catatan perbaikan dokumen.** §7.21 semula salah sisip (masuk ke tengah §7.20) karena penyisipan
memakai nomor baris yang sudah usang setelah §7.20 ditambahkan — blok §7.21 (baris 1105–1141 waktu
itu) dipindahkan ke akhir berkas sehingga urutan §7.18 → §7.21 kembali benar.

### 7.23 Mobile: 4 kartu statistik dashboard jadi SATU baris (4 Okt 2026)

**Permintaan operator.** *"Pada tampilan mobile buat agar menjadi 1 baris (4 icon)."*

**Sebelum.** `<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">` → di ponsel
menjadi **4 baris bertumpuk** (satu kartu per baris), tiap kartu lebar penuh.

**Sesudah — mengikuti pola backup** (`backup_pamsimas/public/css/responsive.css:45,57-60` yang
memang menyusun kartu statistiknya **4-dalam-1-baris** di layar kecil):
1. Grid diganti **`.stat-grid`** = `grid-template-columns:repeat(4, 1fr)` (**selalu 4 kolom**,
   `gap` 16px) → di lebar berapa pun urutannya tetap 1 baris × 4 kartu (backup memakai pola sama:
   `dashboard.css:4` `repeat(5,1fr)` untuk desktop, `repeat(4,1fr)` di layar kecil).
2. Di `@media (max-width:767px)` (breakpoint yang dipakai backup, `dashboard.css:25`):
   - `gap` 5px; kartu jadi `flex-direction:column` + `padding:8px 4px` (dirapatkan, rata tengah),
   - ikon dikecilkan ke **35px / font 16px** (persis `responsive.css:59`),
   - **judul disembunyikan** (`display:none` — persis `responsive.css:45`), nilai tetap tampil
     (font `.78rem`) → **4 ikon + 4 angka muat dalam satu baris**,
   - label judul tetap tersedia lewat atribut **`title`** pada kartu (tooltip / tekan-tahan).
3. Kartu diberi class `stat-tile-card`, judul/nilai diberi class `stat-card-title`/
   `stat-card-value` (nama mengikuti kelas backup) sebagai sasaran CSS — sengaja **bukan** kelas
   Tailwind baru (lihat §10), semua aturan CSS ditulis sendiri di `@push('styles')`.

**Verifikasi — 19/19 lulus** (render `dashboard.index` di server dengan data nyata):
- `.stat-grid` 1×, class grid lama (`grid-cols-1 … sm:grid-cols-2 …`) **0**, CSS
  `grid-template-columns:repeat(4, 1fr)` ada;
- media query `@media (max-width:767px)` ada dengan isi lengkap: `flex-direction:column`,
  `width:35px; height:35px; font-size:16px;`, `.stat-card-title { display:none;`,
  `.stat-grid { gap:5px;`;
- markup: 4× `stat-tile-card`, 4× `stat-card-title`, 4× `stat-card-value`, dan 4× atribut `title`
  (Perangkat Online, Total Tangki, Tagihan Belum Bayar, Meter Menunggu Validasi);
- regresi #66 tetap utuh: hijau+`fa-wifi`, oranye+`fa-database`, biru+`fa-money-bill-wave`,
  ungu+`fa-file-invoice-dollar`; Font Awesome 6.4.2 tetap dimuat; halaman tanpa emoji;
- MD5 `8fdd5be2f6ac2e69240f212d4e305b4b` (**lokal = server**), `view:clear`+`view:cache` OK,
  `/login` **200** & `/` **302**, backup `/tmp/backup-mobile-20261004-060824`.
- 3 asersi awal gagal murni **typo pada skrip uji** (lupa `;` sebelum `}` saat mencocokkan string
  CSS) — setelah skrip dikoreksi → lulus semuanya.

**Ruang lingkup.** Hanya kartu statistik dashboard; tampilan ≥768px tidak berubah selain kini
semuanya berbentuk 4 kolom dalam satu baris (sebelumnya 2 kolom di rentang 640–1279px).

### 7.24 /monitoring: badge tipe perangkat (MON/ACT) di bagian "Perangkat IoT" (4 Okt 2026)

**Permintaan operator.** Menunjuk `https://pamsimas.selur.my.id/monitoring` → kartu *Perangkat
IoT* (MAC · Online · Tangki · Status/Mode · Update): **"tambahkan tipe perangkat"**.

**Data & dampak.** `MonitoringController::overview()` sudah mengirim `Device::with('tank')->get()`
sehingga `$d->device_type` tersedia di view — **tidak ada perubahan controller, API, atau DB**.

**Tampilan (konsisten dengan §7.19).** Badge **`MON` / `ACT`** (label **tanpa titik** sesuai revisi)
diletakkan tepat **di samping MAC** (dibungkus grup `<span class="flex items-center gap-2">`
bersama MAC, indikator Online/Offline tetap di kanan), dengan `title="Tipe perangkat: MONITOR|ACTUATOR"`.
Tipe di luar `MONITOR`/`ACTUATOR` atau kosong → **tanpa badge** (aturan sama dengan kartu gauge).

| Tipe | Badge | Warna |
|---|---|---|
| `MONITOR` | **MON** | indigo `#eef2ff` / `#4338ca` |
| `ACTUATOR` | **ACT** | oranye `#fff7ed` / `#c2410c` |
| lain/kosong | *tanpa badge* | — |

CSS `.device-type-badge` + varian `.is-mon`/`.is-act` **disalin identik** ke `@push('styles')`
halaman ini (halaman ini sebelumnya tidak punya blok style sendiri).

**Verifikasi — 19/19 lulus** (render `monitoring.overview` di server dengan `Device::with('tank')`):
- **#2** `C4:D8:D5:13:A6:17` (MONITOR) → `device-type-badge is-mon` + `>MON<` + tooltip `MONITOR`;
  **#3** `CC:50:E3:52:F3:B6` (ACTUATOR) → `is-act` + `>ACT<` + tooltip `ACTUATOR`;
- badge muncul **2/2** perangkat; label **tanpa titik** (`>MON.<`/`>ACT.<` = 0); 3 aturan CSS ada;
- **regresi utuh**: baris `Tangki:`/`Status:`/`Update:` masing-masing 2×, kartu *Sistem/Database/
  Performa* tetap memakai `fa-server`/`fa-database`/`fa-tachometer-alt`, indikator Online tetap,
  Font Awesome 6.4.2 tetap dimuat, halaman tanpa emoji;
- MD5 `eba2fb49fd74f1edd3983d292af9a5a7` (**lokal = server**), `view:clear`+`view:cache` OK,
  `/login` **200**, `/` & `/monitoring` **302** (redirect login tanpa sesi), backup
  `/tmp/backup-mon-20261004-061835`.

### 7.25 Tampilan mobile halaman detail perangkat: analisa → perbaikan P1–P3 (4 Okt 2026)

**Permintaan.** (1) *"tolong analisa tampilan mobile pada detail device, jangan ubah dulu"* →
(2) setelah analisa dikirim, *"baik kerjakan"* → scope yang dikerjakan: temuan **P1, P2, P3**
(P4–P6 menunggu persetujuan, lihat "Sisa pekerjaan").

#### Hasil analisa (perhitungan CSS, tanpa mengubah berkas)

| Temuan | Fakta | Level |
|---|---|---|
| **P1** baris log meluber | lebar konten = `viewport - 106`; baris satu-liris butuh ~384px (ikon 22 + waktu 128 + tipe 84 + 4 gap + chip ~110) -> meluber saat **viewport <490px (semua HP)**; `.log-message` yang fleksibel (`min-width:0`) menyusut ke **0** lalu chip keluar tepi -> **pesan (§7.17) & durasi (§7.18) tak terlihat** | P1 |
| **P2** header grafik | `.chart-card-container` = `grid-template-columns:1fr auto` **tanpa media query mobile** → kontrol meluber / label tombol mengepak | P2 |
| **P3** target sentuh | `.btn-sm` & `.gauge-actions .btn-action` ≈26–30px (pedoman ≥44px) | P3 |
| P4 boros ruang vertikal | `.card` padding 20px (backup mobile 12px), `#gauge-container` `min-height:450px`, judul halaman tidak disembunyikan | P4 (nanti) |
| P5 pola kartu statistik | detail = 7 kartu scroll horizontal; dashboard sudah 4-kolom (§7.23); backup mobile = `repeat(4,1fr)` + judul disembunyikan | P5 (nanti) |
| P6 tooltip | `title` tidak muncul di layar sentuh | P6 (nanti) |

Yang sudah baik: viewport meta ada; `.controller-detail-grid` 1 kolom di mobile; header wrap; log-list
scroll vertikal; `.gauge-card` max-width 320px; canvas width 100%.

#### Temuan saat pengukuran nyata — **akar masalah yang tidak terlihat dari analisa statis**

Chrome headless tersedia di-mesin, sehingga HTML **hasil render server** diukur nyata pada
**360px** (lewat CDP `Emulation.setDeviceMetricsOverride`; `--window-size` dipaksa minimum 500px,
jadi emulasi CDP dipakai). Hasilnya: **halaman ini sebenarnya 606px lebar** dan menghasilkan
scroll horizontal **seluruh halaman**. Penyebabnya:

```
body > div.flex.min-h-screen (345) > div.flex.min-h-screen.flex-1.flex-col (606) > main (606)
```
Pembungkus `flex-1` **dan** `<main>` adalah *flex item* dengan `min-width:auto` → dipaksa selebar
**min-content** anaknya, yaitu **baris 7 kartu statistik = 566px** (+ padding `main` 40px = 606px).
Bukti: sesudah `min-width:0` disuntikkan langsung dari konsol, overflow halaman hilang total
(`606>360` → `NO`), dan baris statistik pun berganti jadi **scroll internal** (`566>305`).

#### Perubahan (2 view)

1. `resources/views/layouts/app.blade.php` — **akar masalah**:
   `.flex.min-h-screen.flex-1, main { min-width: 0; }` (+ komentar penjelas). Baris statistik tetap
   bisa di-scroll sendiri karena sudah memakai `overflow-x:auto`; **desktop tidak terpengaruh**.
2. `resources/views/devices/show.blade.php` — tiga blok baru di blok `<style>`:
   - **P1** `@media (max-width:640px)`: `.log-item { flex-wrap:wrap; gap:6px 10px; padding:7px 10px }`,
     `.log-time { flex:0 0 auto; font-size:.7rem }`, `.log-type { flex:0 0 auto; font-size:.62rem }`,
     `.log-dur { font-size:.62rem; padding:1px 6px }` → baris jadi **2 baris**: (ikon+waktu+tipe) /
     (pesan + chip durasi).
   - **P2** `@media (max-width:767px)`: `.chart-card-container { grid-template-columns:1fr }`,
     `.chart-controls-container { grid-column:1 / -1; grid-row:auto }`, `.btn-group { flex-wrap:wrap }`,
     `.chart-canvas-container { grid-column:1 / -1 }`.
   - **P3** `@media (max-width:767px)`: `.btn-sm { padding:9px 12px; font-size:.78rem }`,
     `.gauge-actions .btn-action { padding:9px 14px }`, `.auto-scale-wrapper input { 18px }`,
     label padding 9px. Aturan dasar desktop (waktu 128px, tipe 84px, grid `1fr auto`) **dipertahankan**.

#### Verifikasi

- **Struktural 23/23** (render `devices.show` perangkat #3 di server): ketiga blok media ada,
  aturan desktop tetap utuh, chip durasi & badge MON/ACT tetap ada, FA 6.4.2 termuat, tanpa emoji.
- **Pengukuran nyata @360px (A/B, HTML asli hasil render server, Chrome headless + CDP):**
  | Metrik | Sebelum (hanya perbaikan layout) | Sesudah (P1–P3 + layout) |
  |---|---|---|
  | Overflow halaman | 736>360 (tanpa fix layout) → `NO` (dengan fix layout) | **`NO`** |
  | Overflow daftar log | **388>248** (scroll horizontal) | **`NO`** |
  | Lebar pesan log | **0px (tidak terlihat)** | **228px** |
  | Chip durasi | **di luar area** (`NO 423>305`) | **terlihat** |
  | Tinggi baris log | 35px (1 baris) | 62–85px (2 baris) |
  | Tinggi tombol grafik | 48px (label wrap) | **39px** |
  | Baris statistik | melebar bersama halaman | **scroll internal** `566>305` |
  | Emulasi HP (`mobile:true`) | viewport melebar **606px** | viewport tepat **360px** |
- **Screenshot 360px diperiksa visual**: kartu statistik 1 baris (scroll), kontrol grafik menumpuk
  rapi (Live/1 Jam/6 Jam/24 Jam + Auto), dan tiap entri log menampilkan waktu + tipe + **pesan +
  chip durasi** ("mati 00:30:02"). "Template gauge belum tersedia." & "Library grafik … tidak
  dapat dimuat." muncul karena harness uji menghidrasi `<template>`/Chart.js tanpa CDN — bukan cacat
  produksi.
- Deploy: MD5 `01916939b9350979b982cd6b5bce1571` (layout) & `46ecdbafc3777398b7e8692145a753ca`
  (detail) — **lokal = server**, `view:clear`+`view:cache` OK, backup
  `/tmp/backup-resp-20261004-071731` (hanya detail) & `/tmp/backup-resp2-20261004-073521` (dua berkas).

#### Sisa pekerjaan (menunggu persetujuan)

- **P4** `.card { padding:12–14px }` + `#gauge-container { min-height:~360px }` di mobile; opsional
  sembunyikan `h1` seperti backup.
- **P5** kartu statistik: tetap scroll (konsisten dengan §7.23 via 4 kolom) atau ikut pola backup.
- **P6** tampilkan teks `MONITOR`/`ACTUATOR` di mobile agar tidak bergantung pada `title`.
### 7.26 P4–P6 responsif mobile diterapkan (4 Okt 2026)

**Permintaan.** *"terapkan rencana perubahan"* → melanjutkan item yang menunggu persetujuan di §7.25:
**P4** (ruang vertikal), **P5** (pola kartu statistik), **P6** (tipe perangkat terbaca tanpa tooltip).
Semua perubahan **hanya di `resources/views/devices/show.blade.php`** (P1–P3 + akar masalah sudah
selesai di §7.25) — **tidak ada perubahan controller, API, atau DB**.

#### P4 — ruang vertikal lebih hemat (`@media (max-width:767px)`)
| Aturan | Sebelum | Sesudah |
|---|---|---|
| `.card` padding | 20px | **12px** (ikut backup `responsive.css:49`) |
| `.card + .card` margin-top | 20px | 12px |
| `.controller-detail-grid` gap | 20px | 12px |
| `#gauge-container` padding / tinggi min | 20px / **450px** | 12px / **360px** |
| `.chart-canvas-container` tinggi | 300px | **220px** |
| `.page-header h1` | 1.35rem | **1.05rem** |

Judul halaman **diperkecil, tidak disembunyikan** (backup menyembunyikannya di `responsive.css:45`)
supaya konteks perangkat ("Detail Perangkat — Pompa Kendall") tetap terbaca di HP.

#### P5 — kartu statistik: 7 kartu jadi 2 baris tanpa scroll
`@media (max-width:767px)`: `grid-auto-flow:row` + `grid-template-columns:repeat(4, minmax(0,1fr))` +
`gap:5px` + `overflow-x:visible`, kartu `padding:8px 4px`, ikon 32px, judul .58rem, nilai .8rem.
Ini **selaras dengan dashboard** (§7.23, 4 kartu per baris) dan menghilangkan scroll horizontal
yang sebelumnya wajib di baris statistik. Judul kartu **tetap tampil** (clamp 2 baris) — informasi
label (Level Air, Status Pompa, Mode Operasi, …) dianggap lebih berguna daripada badge angka ala backup.

#### P6 — tipe perangkat tampil penuh di layar sentuh
`deviceTypeInfo()` kini mengembalikan `full` (`MONITOR`/`ACTUATOR`) dan `deviceTypeBadgeHtml()`
merender **dua label**: `<span class="dtype-short">MON</span><span class="dtype-full">MONITOR</span>`.
CSS dasar `.dtype-full { display:none }`; pada `@media (max-width:767px)` keduanya ditukar
(`.dtype-short{display:none}`, `.dtype-full{display:inline}`) → **HP menampilkan "MONITOR"/"ACTUATOR"**
(terbaca tanpa harus tekan-tahan `title`), desktop tetap ringkas **MON/ACT** seperti §7.19.
Scope: hanya badge gauge di halaman detail (halaman dashboard & `/monitoring` tetap MON/ACT).

#### Verifikasi

- **Struktural 36/36** (render `devices.show` perangkat #3 di server): blok P4/P5/P6 lengkap,
  aturan dasar desktop utuh, chip durasi & badge tipe tetap ada, FA 6.4.2, tanpa emoji.
- **Pengukuran nyata @360 & @320px** (Chrome headless + CDP, HTML asli hasil render server; A/B
  hanya P4–P6 yang dibedakan, P1–P3 dipertahankan di kedua varian):
  | Metrik | Tanpa P4–P6 | Dengan P4–P6 |
  |---|---|---|
  | Overflow halaman | `NO` | `NO` |
  | Kartu statistik | scroll `566>320`, 320×108 (**1 baris**) | **`NO`**, 320×196 (**2 baris**) |
  | Ukuran kartu | 74×102 | **76×93** (ikon 34→32px, padding 10/6→8/4) |
  | `#gauge-container` | 450px, padding 20px | **360px**, padding 12px |
  | Kanvas | 300px | **220px** |
  | `h1` | 21.6px | **16.8px** |
  | Log (tetap dari §7.25) | 2 baris, pesan 258px | 2 baris, pesan **274px** @360 / **234px** @320 |
  @320px: tanpa overflow sama sekali (stat 280×196, kartu 66×93) ✔
- **Desktop 1280px identik sebelum/sesudah** (padding 14/10, ikon 36, gauge 589, kanvas 300,
  h1 21.6px, tombol 30px) ⇒ P4–P6 benar-benar hanya memengaruhi layar kecil ✔
- **P6 terukur langsung**: `dtype short=none full=inline` pada 360/320px, dan
  `short=inline full=none` pada 1280px ✔
- **Screenshot 360px diperiksa visual**: 7 kartu statistik dalam 2 baris, badge **ACTUATOR** terbaca,
  tombol AUTO/ON lebih besar, kartu lebih ringkas, log 2 baris + chip durasi ("mati 00:30:02").
- Deploy: MD5 `7dc170ba11c875998e7709e4119622f9` (**lokal = server**), `view:clear`+`view:cache` OK,
  backup `/tmp/backup-p456-20261004-074900`.

**Catatan operasional.** Pengukuran memakai Chrome headless + CDP `Emulation.setDeviceMetricsOverride`
(`--window-size` dipaksa minimum 500px). Skrip uji ada di luar repo (`%TEMP%\tmpcss`: `build_probe.js`,
`cdp_measure.js`) dan dihapus setelah dipakai; tidak ada dependensi npm yang ditambahkan.
### 7.27 Ambang "tampilan HP" digeser ke 480px (4 Okt 2026)

**Permintaan operator.** *"coba buat 480 untuk tampilan hp agar lebih luas"* — ambang media query
responsif dipindahkan ke **480px**: layar **≤480px** memakai tata letak ringkas (HP), sedangkan
**481px ke atas** kembali ke tata letak lega (kartu lebih besar, gauge 450px, kanvas 300px,
judul 21.6px, baris log satu baris) sehingga layar yang lebih lebar terasa lebih lapang.

#### Perubahan (`resources/views/devices/show.blade.php`)

| Blok | Sebelum | Sesudah |
|---|---|---|
| P2 header grafik, P3 target sentuh, P4 ruang vertikal, P5 kartu statistik, P6 badge tipe | `@media (max-width:767px)` | **`max-width:480px`** |
| P1 baris log 2-baris | `@media (max-width:640px)` | **tetap `640px`** (lihat pengecualian di bawah) |
| `.stat-cards-container` padding-bottom (aturan lama) | 767px | 480px |
| `min-width:768px` & `min-width:1200px` (peningkatan desktop) | — | **tidak diubah** |

**Pengecualian berbasis data untuk P1 (log tetap 640px).** Pengukuran nyata pada lebar tepat
481px menunjukkan **pesan log menyusut ke `0px`**: satu-laris butuh ≈384px (ikon 22 + waktu 128 +
tipe 84 + 4 gap + chip ~110) sedangkan lebar konten hanya 375px (`481 − 106`). Karena itu aturan
dua baris untuk log **dipertahankan sampai 640px**, sementara blok lain memakai 480px.

#### Verifikasi — 38/38 struktural + pengukuran nyata (Chrome headless/CDP, HTML hasil render server)

| Lebar | Tata letak | Overflow halaman | Log | Kartu statistik | Gauge | Kanvas | Badge tipe |
|---|---|---|---|---|---|---|---|
| **430px** (HP) | mobile | **tidak ada** | 2 baris, pesan **246px** | 2 baris **tanpa scroll** (390×185) | 405px / pad 12px | 220px | **ACTUATOR** penuh |
| **480px** | mobile | tidak ada | 2 baris, pesan **296px** | 440×175 tanpa scroll | 405 / 12 | 220 | penuh |
| **481px** | **lega** | tidak ada | 2 baris, pesan **281px** | 1 baris scroll (566>441) | 450 / 20 | 300 | **ACT** ringkas |
| **600px** | lega | tidak ada | 2 baris, pesan **301px** | 566>560 | 450 / 20 | 300 | ACT |

Screenshot 430px diperiksa visual: 7 kartu statistik (masih 7 saat ini — lihat §7.28), gauge +
badge **ACTUATOR**, kontrol grafik menumpuk, log 2 baris dengan chip durasi ✔

- Deploy: MD5 `00ea06b8817d1b315a2998e11259a6ac` **lokal = server**, `view:clear`+`view:cache` OK.
- Backup: `/tmp/backup-480-20261004-080000` (baseline) & `/tmp/backup-480b` (sebelum pengecualian P1).
- **Catatan operasional:** perintah backup pertama gagal karena PowerShell menelan `$(date …)`
  (`mkdir: invalid option -- 'a'`) sehingga baseline dibuat ulang sesudahnya; versi sebelum perubahan
  tetap tersedia di git (commit `7cb9db5`).
### 7.28 Tiga kartu statistik dihapus dari halaman detail perangkat (4 Okt 2026)

**Permintaan operator.** Pada bagian `stat-cards-container` halaman detail perangkat, hapus kartu
**Level Air**, **Status Pompa (24j)**, dan **Mode Operasi**.

**Alasan (terlihat dari halaman):** ketiga nilai tersebut sudah tampil di tempat lain pada halaman
yang sama — persentase level ada di gauge + grafik, status pompa ada di indikator LED/header gauge
dan tombol pompa, mode kontrol ada di tombol **AUTO** pada kartu gauge — sehingga baris statistik
cukup memuat informasi yang benar-benar terpisah.

**Perubahan (`resources/views/devices/show.blade.php`, −31/+11 baris).**
| Dihapus | Dipakai di |
|---|---|
| kartu **Level Air** (`stat-water-icon`, `stat-water-value`) | gauge (persentase) & grafik riwayat |
| kartu **Status Pompa (24j)** (`stat-pump-icon`, `stat-pump-value`) | header gauge (LED) & tombol pompa |
| kartu **Mode Operasi** (`stat-mode-value`) | tombol AUTO pada kartu gauge |

Tersisa **4 kartu**: Konektivitas · Sinyal WiFi · Frekuensi Nyala · Durasi (24j). Penomoran
komentar kartu (`{{-- n. … --}}`) disusun ulang 1–4 dan komentar kontainer/CTO CSS diperjelas.

**Keamanan JS.** Semua pemanggilan `setText('stat-*')` sudah null-safe (`setText` memeriksa
elemen ada/tidak) dan perubahan `className` dibungkus `if (wi)` / `if (pi)` / `if (ci)` ⇒
menghapus elemen tersebut **tidak** membuat error JavaScript; `applyDeviceState()` hanya melompatinya.
Keempat id yang tersisa (`stat-conn-*`, `stat-signal-value`, `stat-cycle-value`,
`stat-duration-24h-value`) tetap ter-update live seperti sebelumnya.

**Efek samping yang desirable.** Dengan 4 kartu, baris statistik **cukup muat satu baris pada
semua lebar** sehingga scroll horizontal yang sebelumnya muncul di 481–600px (`566>441`) **hilang**,
dan pada HP baris statistik kini **1 baris** (tinggi 88px) — bukan lagi 2 baris (185px).

#### Verifikasi

- **Struktural 44/44** (render `devices.show` perangkat #3 di server): hanya 4 `class="stat-card"`,
  ketiga id yang dihapus tidak ada, 4 id live-update tersisa utuh, penjaga `if (wi)/if (pi)/if (ci)`
  masih ada, semua aturan responsif §7.25–§7.27 & aturan desktop tetap, FA termuat, tanpa emoji.
- **Pengukuran nyata (Chrome headless + CDP):**
  | Lebar | Kartu statistik | Log | Gauge | Kanvas | Badge tipe |
  |---|---|---|---|---|---|
  | 430px | **390×88 (1 baris)**, kartu 94×82, tanpa scroll | 2 baris, pesan **251px** | 405/pad12 | 220 | ACTUATOR penuh |
  | 480px | **440×88 (1 baris)** | 2 baris, pesan **301px** | 405/pad12 | 220 | penuh |
  | 481px | 441×91 (**tanpa scroll**, sebelumnya 566>441) | 2 baris, pesan **286px** | 450/pad20 | 300 | ACT ringkas |
  | 600px | 560×91 | 2 baris, pesan 301px | 450/pad20 | 300 | ACT |
  Overflow halaman & daftar log: **tidak ada** di semua lebar ✔
- **Screenshot 430px diperiksa visual**: 4 kartu dalam satu baris, gauge + badge ACTUATOR, kontrol
  grafik satu baris, log 2 baris dengan chip durasi ✔
- Deploy: MD5 `d65cb67dcaa0c2293680a571cbcb068e` **lokal = server**, `view:clear`+`view:cache` OK,
  backup `/tmp/backup-72`.

**Catatan:** data *Waktu Nyala* (uptime) tidak pernah tampil di kartu statistik sejak port awal —
hanya di *Detail Konfigurasi* (`val-uptime`), sehingga penghapusan ini tidak menambah satu pun
informasi yang hilang.

### §7.29 Mode Ringkas HP (Redmi Note 11) — "terlalu besar & boros"

**Keluhan operator:** di HP (Redmi Note 11, lebar CSS **393px** di DPR 2,75) tampilan web
terasa **membesar**: ikon & tulisan besar, banyak ruang kosong, layar sempit tapi isinya sedikit.
Perbaikan: perkecil ukuran elemen di ambang `≤480px` — **bukan** mengganti breakpoint.

**Lapis 1 — kerangka aplikasi (`layouts/app.blade.php`, impacts semua halaman):**
| Aspek | Sebelum | Sesudah |
|---|---|---|
| `#app-header` tinggi | 64px | **48px** |
| `#app-main` padding | 20px | **10px** |
| `#app-footer` | 12px 20px, .75rem | **7px 10px, .66rem** |

`id` baru dipakai agar spesifikasi mengalahkan utility class Tailwind
(`#app-main { padding: 10px }` > `.p-5`).

**Lapis 2 — isi halaman detail (`devices/show.blade.php`):**
| Unsur | Sebelum | Sesudah |
|---|---|---|
| Padding kartu | 12px | **9px 10px** |
| `h1` / judul kartu | 1.05rem | **.95 / .92rem** |
| Daftar detail | .9rem | **.78rem** |
| Ikon kartu statistik | 32px | **26px** |
| Judul / nilai kartu | .58 / .8rem | **.5 / .7rem** |
| Gauge | pad 12, min-height 360, kartu 320 | **pad 8/10, min-height 0, kartu 260** |
| Kanvas grafik | 220px | **165px** |
| Baris log | 85px, .8rem | **50px, .72rem** |
| Daftar log maks | 400px | **320px** |

Blok `MODE RINGKAS HP` diletakkan **paling akhir** `<style>` dengan spesifikasi yang sama
sehingga menimpa aturan P1–P6 (urutan sumber sama-sama menang, yang terakhir ditulis).

**Hasil ukur nyata Chrome headless/CDP (A/B, varian sebelum vs sesudah):**
| Lebar | Tinggi halaman | Gauge | Kanvas | Kartu statistik | Baris log | Ikon | h1 |
|---|---|---|---|---|---|---|---|
| **393px** | **2187 → 1701px (−22%)** | 405 → **317** | 220 → **165** | 353×99 → **373×74** | 85 → **50** | 32 → **26** | 16.8 → **15.2** |
| 430px | 2176 → **1672px** | 405 → **317** | 220 → **165** | 390×88 → **410×74** | 62 → **50** | 32 → **26** | 15.2 |
| 481px | 2394 → 2394 (tetap) | 450 | 300 | 441×91 | 62 | 34 | 21.6 |
| 600px | 2324 → 2324 (tetap) | 450 | 300 | 560×91 | 60 | 34 | 21.6 |

- Overflow halaman & daftar log: **tidak ada** di semua lebar.
- Pesan log pada 393px tetap **253px** (aman, tidak menyusut).
- **481px ke atas identik** ⇒ tablet & desktop tidak berubah sama sekali.

**Cara tuning cepat:** ubah hanya nilai dalam blok `MODE RINGKAS HP` (≤480px) untuk halaman
detail, atau blok `#app-header/#app-main/#app-footer` untuk seluruh aplikasi. Setelah ubah,
`view:clear` + `view:cache` di server.
