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



