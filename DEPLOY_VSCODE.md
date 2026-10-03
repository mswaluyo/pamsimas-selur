# Dokumentasi: Deploy & Hotfix Langsung dari VSCode

Panduan ini mendokumentasikan alur kerja yang dipakai untuk men-deploy dan memperbaiki
website PAMSIMAS (`https://pamsimas.selur.my.id`, server HestiaCP @ `192.168.20.200`)
**tanpa keluar dari VSCode** — semua via terminal PowerShell + SSH/SCP.

---

## 1. Arsitektur & Prasyarat

| Komponen | Nilai |
|---|---|
| Server | Debian 12 (DietPi) + HestiaCP 1.10.5 |
| **IP server (statis)** | `192.168.20.200/24`, gateway `192.168.20.1`, DNS `1.1.1.1 / 8.8.8.8` |
| Akses web publik | Cloudflare Tunnel (`cloudflared`, tunnel ID `2280673a-...`) |
| Path project di server | `/home/admin/web/pamsimas.selur.my.id/public_html/` |
| User web / SSH | `admin` (web) / `root` (SSH, port 22, OpenSSH) |
| PHP | 8.3 (CLI & PHP-FPM pool `php8.3-fpm-pamsimas.selur.my.id.sock`) |
| Database | MariaDB `admin_pamsimas_db`, user `admin_pamsimas_user` |
| Docroot Nginx | `.../public_html/public` (template `laravel` Hestia otomatis menambah `/public`) |
| Composer | `php8.3 composer.phar` (composer CLI tidak terpasang global) |

Prasyarat di lokal (VSCode) — ekstensi PowerShell **Posh-SSH** (sekali saja):

```powershell
Install-Module Posh-SSH -Scope CurrentUser -Force
```

---

## 2. Pola Dasar: Sesi SSH dari Terminal VSCode

> **PENTING (sejak ganti ke OpenSSH 9.2)**: Posh-SSH gagal *key exchange* dengan
> OpenSSH 9.2 (`Key exchange negotiation failed`). Gunakan **ssh.exe native + askpass**:

```powershell
$ap = "$env:TEMP\askpass.cmd"
Set-Content -Path $ap -Value '@echo PASSWORD_ROOT' -Encoding ASCII
$env:SSH_ASKPASS=$ap; $env:SSH_ASKPASS_REQUIRE='force'; $env:DISPLAY=':0'
ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=NUL -p 22 root@192.168.20.200 "perintah"

# Kirim file:
scp -P 22 file root@192.168.20.200:/tmp/
```

> **Tips escaping**: bash kompleks dikirim sebagai **base64**:
> ```powershell
> $script = @'
> # ...bash script bebas kutip...
> '@
> $b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($script))
> ssh ... root@192.168.20.200 "echo $b64 | base64 -d | bash"
> ```

---

## 3. Pola Deploy File (Hotfix Langsung)

1. **Edit file di VSCode** (repo lokal).
2. **Upload** via `scp` ke `/tmp`, lalu `mv` ke posisi akhir (nama file unik di /tmp!).
3. **Fix kepemilikan**: `chown -R admin:admin <path app/resources>`.
4. **Rebuild cache view** (WAJIB setelah ubah `.blade.php`):
   ```bash
   cd /home/admin/web/pamsimas.selur.my.id/public_html
   sudo -u admin php8.3 artisan view:clear
   sudo -u admin php8.3 artisan view:cache   # harus "VIEW-CACHE-OK"
   ```
5. **Verifikasi** (bagian 4 & 5). Lalu commit ke git lokal.

> ⚠️ Jangan jalankan artisan sebagai **root** — file milik root memblokir write session/log.

---

## 4. Checklist Verifikasi Setelah Deploy

```bash
curl -sS -o /dev/null -w "login=%{http_code}\n" -H "Host: pamsimas.selur.my.id" http://192.168.20.200/login
tail -20 storage/logs/laravel.log
curl -sS -H "Host: pamsimas.selur.my.id" http://192.168.20.200/api/dashboard-data | head -c 400
```

Login-test end-to-end via curl:

```bash
JAR=/tmp/cj.txt; rm -f $JAR
TOKEN=$(curl -sS -c $JAR -H "Host: pamsimas.selur.my.id" http://192.168.20.200/login \
        | grep -oP 'name="_token"[^>]*value="\K[^"]+' | head -1)
curl -sS -b $JAR -c $JAR -o /dev/null -w "loginpost=%{http_code}\n" \
     -H "Host: pamsimas.selur.my.id" \
     -d "_token=$TOKEN&username=admin&password=admin123" http://192.168.20.200/login
curl -sS -b $JAR -o /dev/null -w "dashboard=%{http_code}\n" \
     -H "Host: pamsimas.selur.my.id" http://192.168.20.200/
```

---

## 5. Debugging 500: Selalu Mulai dari Log

```bash
grep -E "production.ERROR" storage/logs/laravel.log | tail -3 | cut -c1-400
tail -c 3000 storage/logs/laravel.log
```

| Gejala | Penyebab | Solusi |
|---|---|---|
| 500 `unexpected token "]"` di view | Regex JS berisi `{{ ... }}` diparse Blade | Bungkus `<script>` dengan `@verbatim ... @endverbatim` |
| 500, log tak bertambah, body kosong | Fatal error sebelum Laravel logging | `php8.3 artisan view:cache` (gagal = sintaks blade), cek error FPM |
| DB `Access denied 1698 (root)` | `bootstrap/cache/config.php` cache lama | `php8.3 artisan config:clear` |
| **Semua hostname situs** (`pamsimas.` & `ssh.`) membalas **HTTP 530** body `error code: 1033`, SSH mati di `websocket: bad handshake`, padahal DNS & edge Cloudflare sehat (`https://www.cloudflare.com/cdn-cgi/trace` = 200) | **Server/connector cloudflared tidak hidup** (terkonfirmasi 29 Sep 2026: **listrik padam, server mati total**) — penyebab lain: cloudflared crash, tunnel di-rename/dihapus di Zero Trust, kredensial connector berganti. 1033 = tunnel error, bukan error origin — Laravel & FPM sebenarnya sehat | Akses server lewat jalur lain (LAN `192.168.20.200` atau konsol hosting/HestiaCP) → `systemctl restart cloudflared`, `journalctl -u cloudflared -n 50 --no-pager`, cek `cloudflared tunnel list` + `cloudflared tunnel ingress show <tunnel>`. Dampak selama mati: dashboard/API tidak terjangkau dan **perangkat ESP berhenti lapor** tetapi tetap jalan lokal dengan konfigurasi terakhir; begitu connector hidup lagi, konfigurasi tertunda **tidak hilang** (flag `config_update_command` bertahan sampai ack — Task #51) |
| `.env` benar tapi request 500 | Cache config usang | `config:clear`; **hindari `config:cache` di server ini** |
| Halaman "Success!"/"Page Not Found" | Docroot Nginx salah | Lihat bagian 7 |
| Serial perangkat: `SENSOR: ERROR KRITIS - Jarak tidak valid (0.00 cm)` + event `EMERGENCY: Sensor Error - Pompa Dimatikan` berulang, `sensor_logs` terisi `water_percentage = -1`, `water_level_cm = 0.00` | **`0.00` = `pulseIn` timeout (tidak ada gema)**, bukan jarak 0 cm — hampir selalu karena jarak sensor→permukaan mendekati/melewati jangkauan HC-SR04 (±2,5–3 m riil) atau `empty_tank_distance` (= `tanks.height`) lebih besar dari jarak pasang sensor. Setting tinggi bak **tidak** mengubah fisika pantulan | Ukur meteran jarak muka sensor→dasar bak, samakan dengan `tanks.height`/`empty_tank_distance`; perbaiki pemasangan (JSN-SR04T 4,5 m / sensor diturunkan / pipa tenang / kabel probe pendek / kap 470–1000 µF / baca saat pompa OFF). Firmware (build 29 Sep 2026 ke atas): tanpa gema = air di bawah jangkauan → dalam mode AUTO level dianggap 0 % dan pompa **diralat NYALA** (fail-safe ketersediaan air), **tanpa batas siklus isi buta** — yang tersisa hanya proteksi mesin (`on_duration` nyala → `off_duration` istirahat → nyala lagi) sampai air naik ke dalam jangkauan dan event `Sensor Pulih` muncul; laporan anti-spam (1 event + penanda "masih buta" maks 1×/15 menit). Pastikan peluap/pelampung mekanis berfungsi. Detail: `TODO.md` §7.6 |

---

## 6. Database (MariaDB)

Klien `mysql` tidak ada di PATH server → pakai script PHP PDO sementara:

```bash
cat > /tmp/q.php <<'PHP'
<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=admin_pamsimas_db;charset=utf8mb4',
               'admin_pamsimas_user', 'PASSWORD', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($pdo->query('SELECT ...') as $r) { print_r($r); }
PHP
sudo -u admin php8.3 /tmp/q.php && rm /tmp/q.php
```

Import dari dump backup (`backup_pamsimas/.db_source/pamsimas_db.sql`): ekstrak blok
`INSERT INTO ...;` (backtick SQL = `[char]96` di PowerShell), bungkus
`SET FOREIGN_KEY_CHECKS=0/1;`, jalankan via PDO. File hasil: `deploy/restore-master.sql`,
`deploy/restore-customers.sql`, `deploy/gauge-templates.json`.

---

## 7. Infrastruktur yang Perlu Diketahui

- **IP statis server** `192.168.20.200/24` (gw `192.168.20.1`) — config:
  `/etc/network/interfaces.d/eth0.conf` (DietPi, bukan netplan).
- **Nginx hanya listen di `192.168.20.200:80`** (bukan 127.0.0.1). Cloudflare Tunnel
  origin `http://localhost:80` → diteruskan service **`localhost80.service`**
  (socat `127.0.0.1:80 → 192.168.20.200:80`).
- **Docroot**: template `laravel` Hestia otomatis menambah `/public` — JANGAN set
  custom docroot manual (pernah dobel `/public/public`).
- `/etc/hosts` server memetakan `pamsimas.selur.my.id → 127.0.0.1` — tes dari server
  wajib `curl --resolve ...` atau `curl -H "Host: ..."`.
- **PHP CLI default = 8.5**; selalu eksplisit `php8.3`.
- phpMyAdmin via panel: proxy di `/usr/local/hestia/nginx/conf/nginx.conf`
  (**bisa tertimpa saat update HestiaCP**; backup: `nginx.conf.bak-pma`).
- SSH server = **OpenSSH** (`/etc/ssh/sshd_config.d/hestia.conf`); dropbear disabled.

---

## 8. SSH Remote dari VSCode via Cloudflare Tunnel

Jalur: **PC → cloudflared access → edge → tunnel → sshd (22)**.

1. Jalankan `D:\pamsimas.selur.my.id\.vscode\SSH-Connections-cloudflare.bat`
   (harus muncul `Start Websocket listener host=localhost:2222` tanpa error).
2. `ssh root@ssh.selur.my.id` (ProxyCommand di `~/.ssh/config`), atau
   `ssh -p 2222 root@localhost` saat .bat aktif.
3. Lokal (LAN): `ssh root@192.168.20.200`.

Troubleshooting: port 2222 terpakai →
`Get-NetTCPConnection -LocalPort 2222 -State Listen | %{ Stop-Process -Id $_.OwningProcess -Force }`

---

## 9. Riwayat Perbaikan (rekaman sesi)

| # | Masalah | Perbaikan | File |
|---|---|---|---|
| 1 | `.env.production` `WA_GATEWAY_URL` salah (404) | → `http://127.0.0.1:3000/send-wa` | `.env.production` |
| 2 | Cache config lama (`root`/`pamsimas_db`) | `config:clear` | — |
| 3 | Docroot dobel `public/public` | Reset; andalkan template `laravel` Hestia | — |
| 4 | User MySQL belum ada | Buat DB + user | — |
| 5 | Menu Perangkat Terdeteksi tidak ada | Section + tombol Daftarkan (MAC prefill) + sidebar | `DeviceController.php`, `devices/index.blade.php`, `_form.blade.php`, `layouts/app.blade.php` |
| 6 | Master tangki/pompa/sensor kosong | Import dump backup | `deploy/restore-master.sql` |
| 7 | Master data tidak bisa diedit | Edit (modal) + Hapus per baris | `SettingController.php`, `settings/{tanks,pumps,sensors}.blade.php` |
| 8 | Tangki tanpa dimensi P×L / ⌀ | Field dinamis kotak/tabung | `SettingController.php`, `settings/tanks.blade.php` |
| 9 | Registrasi perangkat input manual | Auto-fill dari master data (`fillFromMasterData`) | `DeviceController.php`, `_form.blade.php` |
| 10 | Template gauge tak bisa diganti | Dashboard render template aktif; API kirim `water_percentage`; 5 template di-seed | `DashboardController.php`, `dashboard/index.blade.php` |
| 11 | Dashboard 500 (regex `{{ }}` diparse Blade) | `@verbatim` di blok script | `dashboard/index.blade.php` |
| 12 | Sidebar berantakan | Section konsisten via helper `$navSection`/`$navItem` | `layouts/app.blade.php` |
| 13 | Error 1033 Cloudflare Tunnel | CNAME tunnel lama `f75adb78-...` → tunnel aktif `2280673a-...` | Cloudflare DNS |
| 14 | Nginx tak listen di localhost | Service `localhost80.service` (socat) | — |
| 15 | SSH remote via tunnel gagal | Ingress `:20`→`:22`; matikan duplikat bridge 2222 | Cloudflare, `~/.ssh/config` |
| 17 | Hestia File Manager "Unknown error" | dropbear tak dukung `restrict`/`internal-sftp` → ganti OpenSSH 22 | `/etc/ssh/sshd_config.d/hestia.conf` |
| 18 | Ganti router, web down | IP statis `192.168.20.200/24` + Hestia sys-ip + socat di-update | `/etc/network/interfaces.d/eth0.conf` |
| 19 | nginx `failed` + phpMyAdmin 502 pasca ganti IP | `conf.d/192.168.20.6.conf` → `192.168.20.200.conf`; proxy_pass diperbaiki | `/etc/nginx/conf.d/`, hestia nginx.conf |
| 20 | phpMyAdmin 404 via panel 8083 | Tambah `location ^~ /phpmyadmin/` (proxy ke nginx utama) | `/usr/local/hestia/nginx/conf/nginx.conf` |
| 21 | Icon web kosong | favicon.ico + logo.png dari backup; layout & login pakai logo | `public/favicon.ico`, `public/img/logo.png`, views |
| 22 | `%` ganda di gauge dashboard | `updateGauge` fallback cukup isi angka (`%` dari template) | `dashboard/index.blade.php` |
| 23 | Avatar user tampil aneh | Inisial: 2 kata → huruf pertama masing-masing; 1 kata → 1 huruf | `layouts/app.blade.php` |
| 24 | Database pelanggan kosong | Import 134 pelanggan dari backup | `deploy/restore-customers.sql` |
| 25 | Tombol kontrol di kartu gauge tak berfungsi | `sendCommand()` di JS + endpoint web `POST /api/device-command` (session + CSRF) | `routes/web.php`, `Api/DeviceApiController.php`, `dashboard/index.blade.php` |
| 26 | Kartu gauge belum serupa sistem lama | Header LED pompa/badge timer/bar sinyal, label pompa + kipas berputar, footer tombol AUTO-MANUAL & Nyalakan/Matikan, label MAC; `@stack('styles')` di layout | `dashboard/index.blade.php`, `layouts/app.blade.php` |
| 27 | `set_pump`/`set_mode` via API key selalu "sesi berakhir" | Route `/api/*` tanpa session → kontrol dashboard dipindah ke route web; firmware tetap `/api/update` | `routes/web.php` |
| 28 | Info ganda di kartu gauge (nama pompa 2×, level 2×, caption online) | Kartu ditata ulang: LED online + sinyal (atas), **nama bak** = judul template, **nama pompa** 1×, tombol AUTO/MANUAL kiri & ON/OFF kanan (nonaktif saat AUTO), MAC; `ensureSlots` rebuild bila komposisi perangkat berubah | `dashboard/index.blade.php` |
| 29 | Kartu gauge terlalu besar | Grid disamakan dengan backup: `repeat(auto-fill, minmax(280px,1fr))` + gap 20px (10px di HP), kartu `rounded-lg` (8px) & padding 20px, `.gauge-title` gaya backup (600/nowrap/ellipsis) | `dashboard/index.blade.php` |
| 30 | Posisi/warna tombol mode & pompa belum seperti backup | `.gauge-actions` = `space-between` (mode kiri, pompa kanan) padding 8px 12px, border-top `#e0e0e0`, bg `#f9f9f9`, radius 4px; tombol: biru AUTO `#3498db`, abu MANUAL/offline `#7f8c8d`, hijau ON `#27ae60`, merah OFF `#e74c3c`, `:disabled` opacity .6 | `dashboard/index.blade.php` |
| 31 | Klik gauge = pop up, harusnya halaman detail (backup) | Modal + `openGaugeDetail()` dihapus; klik kartu → `window.location.href = '/devices/show/{id}'` via `slot.dataset.detailUrl` (tombol aksi tetap kirim perintah) | `dashboard/index.blade.php` |
| 32 | Halaman detail 500 "Call to member function format() on string" | `record_time`/`timestamp` bukan objek tanggal: tambah `$casts` datetime di `SensorLog`/`PumpLog` (+`last_offline_sync` di `Device`), Blade pakai `Carbon::parse(...)` aman | `SensorLog.php`, `PumpLog.php`, `Device.php`, `devices/show.blade.php` |
| 33 | Detail perangkat tanpa gauge/grafik (JS crash: dev-gauge-slot tak ada + 4 variabel dc* tak terdefinisi); halaman /logs/* error (relasi device() tak ada di model) | Sisip slot gauge + buka grid di show.blade.php, definisikan dcTankName/dcPumpName/dcInitPct/dcTplActDevId + guard null; tambah device() di PumpLog/SensorLog/EventLog => /logs/pumps|sensors|events|admin & /devices/show/* semua 200 | devices/show.blade.php, PumpLog.php, SensorLog.php, EventLog.php |
| 34 | 7 kartu statistik Detail Perangkat tampil 3 baris × 2 kolom; utility `lg:grid-cols-6`/`sm:grid-cols-3` tak ada di bundle (build CSS basi) & file tak pernah terunggah (tunnel SFTP 2222 mati) | Stat cards dikonversi `grid-auto-flow:column` + `grid-auto-columns:minmax(74px,1fr)` (7 kartu → 1 baris, CSS inline view mandiri dari Tailwind); `npm run build` → `app-BxmBOLVx.css`; deploy via **scp + askpass** (key `id_rsa_pem` sudah tidak terdaftar di server — cek dengan `PubkeyAcceptedAlgorithms=+ssh-rsa`); `view:clear` + `view:cache` wajib | `devices/show.blade.php`, `layouts/app.blade.php`, `DeviceController.php`, `public/build/` |
| 35 | Blok "Aset & Sumber Data" berada di kartu Detail Kolom Kanan, harusnya di bawah gauge setelah MAC | Blok dipindah ke `#gauge-container` setelah kartu gauge (label MAC `.gauge-mac` elemen terakhir kartu → Aset setelahnya); ditaruh **di luar** `#gauge-card-*` karena `renderGaugeCardStructure()` mengisi `innerHTML` kartu; lebar `max-width:320px` sejajar gauge; blok "Timer & Ambang Batas" jadi terakhir di kartu kanan (`margin-bottom:0`); verifikasi curl: POS-ASET 22771 < POS-DETAIL 23436, ASET-COUNT=1 | `devices/show.blade.php` |
| 36 | Grafik "Riwayat Level Air & Pompa" tidak muncul (API history error) | `DashboardApiController::history()` pakai `PumpLog::` tapi import `use App\Models\PumpLog;` hilang → Class "Api\PumpLog" not found → HTTP500 di `/api/device/history` (terbaca di laravel.log) → fetch grafik gagal, canvas kosong; fix: tambah import; audit file lain pakai FQCN/namespace aman; verifikasi: `php8.3 -l` OK, API 500→**200** JSON | `app/Http/Controllers/Api/DashboardApiController.php` |
| 37 | Tombol mode & on/off di detail berbeda dari dashboard (label panjang "Matikan/Nyalakan Pompa", tanpa warna offline, ada window.confirm) | Disamakan dengan dashboard: label pompa **ON/OFF**, palet `.btn-blue/.btn-gray/.btn-green/.btn-red` (+hover persis dashboard; abu saat offline), tooltip deskriptif, disable `!online \|\| mode !== 'MANUAL'`, klik **langsung kirim** tanpa konfirmasi; layout footer `flex:1` dipertahankan | `devices/show.blade.php` |
| 38 | Gauge (dashboard + detail) belum punya timer lama nyala/mati pompa | Badge timer **count-up** (bukan countdown): **hijau** = lama waktu nyala (ON), **abu** = lama waktu mati (OFF); sumber waktu `pump_status_since` di `/api/dashboard-data` (baris PumpLog transisi terakhir — log hanya ditulis saat status berubah, diverifikasi di `DeviceApiController::writePumpLog`) + fallback lokal bila null; init detail juga dari server (`pumpStatusSince`/`pumpStatus` di DeviceController) akurat saat reload; badge disuntik JS di header gauge (antara LED online & sinyal), tick tiap 1 detik | `dashboard/index.blade.php`, `devices/show.blade.php`, `Api/DashboardApiController.php`, `DeviceController.php` |
| 39 | Halaman detail `devices/show` belum rapi: kartu grafik menempel grid atas (`.controller-detail-grid` tanpa `margin-bottom`; selector `.card + .card` tak berlaku setelah div), inline style pada blok Aset & Timer, judul kartu tak seragam (emoji sebagian), stat-card radius 8 + shadow `0 2px 4px` beda keluarga dengan kartu lain (radius 12 + `0 4px 15px`), ikon kipas pakai emoji + class `.is-spinning` tidak konsisten dengan dashboard (lingkaran conic-gradient + `.spin`), padding-bottom scrollbar aktif meski tanpa scrollbar, padding gauge 30/20 vs kartu 20, checkbox Auto tanpa `accent-color`, 2 dobel blank line | Rapikan `devices/show.blade.php` saja (struktur/urutan section tidak diubah): `margin-bottom:20px` pada `.controller-detail-grid`; inline style → CSS `:last-child` + `#gauge-container .info-block`; judul seragam "⚙️ Detail Konfigurasi" / "📈 Riwayat Level Air & Pompa" / "🕓 Log Kejadian Terakhir"; stat-card diseragamkan radius 12 + shadow kartu; fan disalin persis dashboard (conic-gradient hijau saat ON, class `.spin`, emoji 🌀 dihapus dari JS); `padding-bottom:6px` hanya di `max-width:767px`; padding gauge 20px; `accent-color:#3498db`; **TEMUAN PENTING — root proyek remote = `/home/admin/web/pamsimas.selur.my.id/public_html` (bukan `/var/www/pamsimas.selur.my.id` yang tidak ada; semua verifikasi pertama jadi baseline); `/srv/jail/admin/home/admin/...` = bind-mount inode sama, cukup 1 file**; verifikasi: LINT1, VIEW-CACHE0, MTIME baru, SHOW200, DASH200, HISTORY200, marker 10/10, zero-check 0/0, div 56/56, ERROR sejak 12:00 = 0 → **RAPIK-VERIFY-OK** | `devices/show.blade.php`, `DEPLOY_VSCODE.md` |
| 40 | Logika status ON tetap tampil saat device mati/offline — terutama **grafik** (pita ON terakhir ditarik ke `window_end`=sekarang karena PumpLog tak pernah menulis OFF setelah device mati) & **timer badge** (hijau count-up selamanya); se-famili: stat "Status Pompa (24j)" tetap ON & "Durasi (24j)" menghitung jam offline sebagai nyala (kasus nyata device 3: OFFLINE sejak 02:29 tapi `status=ON`) | Prinsip: **offline → status terakhir TIDAK berlaku, dianggap OFF, dihitung/ditutup di `last_update` (kontak terakhir)**: (1) `history()` — offline & `last_update` ≥ window start → sintetis `pumps[] = OFF@last_update` (menutup pita; `buildAnnotations` tak lagi tarik ke `window_end`); `last_update` < window start → `initial_pump_status='OFF'`; (2) timer dashboard — `onP = is_online && status==='ON'`, sumber saat offline = `last_update_ts`, title "Perangkat offline — pompa dianggap mati…"; (3) timer detail — branch online/offline di `applyDeviceState` (`pumpOffSince=last_update_ts`, `lastPumpState='OFF'`) + init CFG `isOnline`/`lastUpdateTs` (`bootOnline`); (4) stat Status Pompa server+client di-gate `isOnline`; (5) `DeviceController` — blok ON 24j ditutup di `last_update` saat offline. Tombol ON/OFF sengaja tetap status terakhir (disabled + tooltip offline = last-known); saat reconnect timer kembali ke `pump_status_since` (gap offline bisa ikut terhitung bila status tak berubah — batasan disadari). Verifikasi: `php8.3 -l` OK×2, VIEWS-OK, LOGIN=302→SHOW3/SHOW2/DASH=200, HIST 200×3; device3: `isOnline:false`, stat server `>OFF`, marker 10, zero-check 0/0; H3-24h **LAST=`OFF@2026-09-24 02:29:27`** = last_update (INIT=ON, 37 entri), H3-1h INIT=OFF+pumps[], H2 INIT=OFF (device online tak berubah), ERRS=90 baseline → **OFFSTATE-VERIFY-OK** | `Api/DashboardApiController.php`, `devices/show.blade.php`, `dashboard/index.blade.php`, `DeviceController.php`, `DEPLOY_VSCODE.md` |
| 41 | Ukuran tombol mode (AUTO) & pompa (ON/OFF) di kartu gauge **beda antara dashboard vs detail** — padding/font sudah sama (hasil #37-#39) tapi tombol detail tampak jauh lebih lebar | Penyebab: `.btn-action` di `devices/show` punya **`flex:1`** (tiap tombol melebar mengisi ½ kartu) sedangkan dashboard tanpa `flex:1` (tombol selebar konten + `space-between`); ditambah `.gauge-actions` detail `margin-top:8px` vs dashboard `10px`. Fix: samakan **byte-per-byte** 2 aturan di `show.blade` = dashboard: **hapus `flex:1`**, `margin-top:8px→10px`, urutkan properti persis dashboard (acuan = dashboard/sistem lama; padding `5px 10px`, font `.8rem` sudah cocok). Verifikasi: lokal `GA-EQUAL=True` & `BTN-EQUAL=True` (identik persis), `OLD-FLEX1=0`, `NEW-GA=1`; deploy 1 file (`scp` OK, `php8.3 -l` OK, `VIEWS-OK`); e2e login sesi **`_token`** (bukan `token`) → `LOGIN=302`, `SHOW2=200` & `DASH=200`; di **kedua** halaman render `BTN-NEW=1`+`GA-NEW=1`, `S2-OLD-FLEX=0`; log Laravel 0 error baru (1 ERROR lama tgl-11 saja) → **BTN-SIZE-VERIFY-OK** | `devices/show.blade.php`, `DEPLOY_VSCODE.md` |
| 42 | "🕓 Log Kejadian Terakhir" (detail device) hanya berisi kejadian pompa/mode/sync — **tidak ada log koneksi** (saat perangkat putus/sambung tidak pernah tercatat); proyek tidak punya scheduler/cron sehingga deteksi offline tak bisa berjalan periodik | (1) `EventLog::logDisconnect()` — helper **idempoten**: tulis event `event_type='Koneksi'` pesan "Koneksi terputus — perangkat offline" dengan **jangkar `event_time` = `last_update`** (kontak terakhir) → tidak dobel walau dipanggil berulang; (2) middleware `EnsureDeviceApiKey::noteConnection()` — berjalan untuk semua endpoint `device.api` (`/api/log\|status\|update\|device/update\|health\|log-offline`), saat request firmware masuk & perangkat ternyata offline: tulis 'terputus' (bila belum) + "Koneksi tersambung — perangkat online (offline {durasi})"; skip bila ada sesi login (tombol dashboard memakai `/api/device-command` di luar grup ini); try/catch best-effort; (3) `DeviceController::show()` — perangkat offline saat halaman dibuka → `logDisconnect()` sebelum query `$eventLogs` agar baris 'terputus' langsung tampil; (4) blade `show`: cabang ikon/warna baru **📶 `log-success`** (`tersambung`) & **📴 `log-warning`** (`terputus`) sebelum boot/nyala/mati. Pemutusan tercatat saat halaman dibuka **atau** otomatis saat reconnect berikutnya (backdated ke `last_update`) — tanpa scheduler. Verifikasi: `php8.3 -l` OK×3, VIEWS-OK, mtime 4 file baru; e2e login sesi (`_token`) LOGIN=302 → **SHOW3=200** dengan `PAGE-TERPUTUS=1`, `• Koneksi=1`, `=1`; K3: BASE=0 → **AFTER=1** → GET ulang **IDEM=1**; simulasi reconnect (`last_update`−10 menit via tinker + `POST /api/update set_status` nilai tak berubah) → POST1=200 → **K3SIM=3** (terputus@T0 + terputus@T1 gap-close + "tersambung…(offline 10 menit)") → POST2 **tetap 3** (idempoten); cleanup hapus 2 baris test + restore T0 → **K3FINAL=1**; device2 online: SHOW2=200 & K2=0 (tak ada event palsu; laporan live device jalan pasca-deploy `last=14:32`); ERROR log sejak deploy = **0** (31 error `PumpLog not found` semua <09:00 = baseline lama) → **CONNLOG-VERIFY-OK** | `app/Models/EventLog.php`, `app/Http/Middleware/EnsureDeviceApiKey.php`, `app/Http/Controllers/DeviceController.php`, `resources/views/devices/show.blade.php`, `DEPLOY_VSCODE.md` |
| 43 | `/settings/tariff` menimpa `indicator_settings` (1 baris) **tanpa jejak** — tak tercatat kapan / bulan berapa / oleh siapa / dari nilai berapa tarif berubah; riwayat perubahan tarif tidak dapat diaudit | (1) Migrasi baru `2024_01_01_000005` → tabel **`tariff_histories`** (`water_price`/`admin_fee` nilai baru, `old_water_price`/`old_admin_fee`, `changed_by`, `created_at` = kapan & bulan berubah); (2) model `TariffHistory` (pola `$timestamps=false` + `created_at` eksplisit ala `AdminLog`, casts float/datetime); (3) `SettingController::updateTariff()` — baca nilai lama via `getSettings()` dulu, **toleransi 0.005**: nilai sama → flash "Tidak ada perubahan nilai tarif." & **tidak menulis baris** (anti-duplikat), ada perubahan → update + `TariffHistory::create` (old→new, `changed_by=session('user.username')`) + `AdminLog::create` 'Ubah Tarif' (konsisten konvensi audit trail); `tariff()` kirim 50 histori terbaru (`orderByDesc id`); (4) view `settings/tariff` — kartu "🕓 Histori Perubahan Tarif" tabel pola `logs/admin`: kolom Waktu (`d-m-Y H:i`), **Bulan Berubah** (badge `bg-indigo-100` "September 2026" — array bulan Indonesia manual karena locale `en`), Harga Air & Biaya Admin (`line-through` nilai lama → **bold** nilai baru hanya bila berubah), Oleh; empty-state "Belum ada perubahan tarif tercatat."; tagihan lama aman karena `invoices` menynapshot tarif per baris. Verifikasi: `php8.3 -l` OK×3, `migrate --path …000005` DONE, `view:clear` OK, TBL=Y ROWS=0 (WP=3000 AF=5000); e2e login LOGIN=302 → GET1 `H=1 EMPTY=1`, WP0=3000 AF0=5000 AG0=0 → POST1 302 `F=1 ROWS=1 STRIKE=1 BULAN=1 IN=3100` → **POST2 nilai sama `F="Tidak ada perubahan" ROWS tetap 1`** → POST3 (admin+100) `F=1 ROWS=2 STRIKE=2 BULAN=2`, AG=2 → POST4 restore `F=1 ROWS=3 BULAN=3` → cleanup `CLEANUP=3\|3\|3000\|5000` (hapus 3 baris test + 3 AdminLog uji, tarif asli kembali) → GETFINAL `EMPTYF=1 INFINAL=3000`; ERROR hari ini `ERR0=ERR1=31` (0 baru pasca-deploy) → **TARIFF-HISTORY-VERIFY-OK** | `database/migrations/2024_01_01_000005_create_tariff_histories_table.php`, `app/Models/TariffHistory.php`, `app/Http/Controllers/SettingController.php`, `resources/views/settings/tariff.blade.php`, `DEPLOY_VSCODE.md` |
| 44 | Tampilan `/settings/tariff` tidak konsisten dengan halaman Pengaturan lain (`tanks`/`pumps`/`sensors`): 2 kartu center-stack (`max-w-lg` form + `max-w-3xl` histori, judul+deskripsi) bukan pola grid tabel-kiri/form-kanan | `tariff.blade.php` direstrukturisasi ikut pola **persis** halaman lain: grid `grid-cols-1 gap-5 lg:grid-cols-3` — **kiri `lg:col-span-2`** kartu tabel `overflow-x-auto rounded-xl bg-white shadow` berisi tabel **Histori Perubahan Tarif** (Waktu · badge Bulan Berubah · Harga Air & Admin `line-through` lama → **bold** baru · Oleh; empty-state `colspan=5`; judul kartu & deskripsi lama dihapus agar murni pola tabel tanks/pumps), **kanan** kartu form `rounded-xl bg-white p-6 shadow` — judul "Tarif Air" + deskripsi BillingService + 2 input `w-full rounded-lg border border-slate-300 px-3 py-2 text-sm` (value nilai saat ini + placeholder unit) + tombol **full-width** `rounded-lg bg-sky-600 py-2` "Simpan Tarif"; struktur pemrosesan (controller/model/migrasi/toleransi anti-duplikat) **tidak berubah sama sekali**. Verifikasi: marker lokal 18 positif & legacy (`mx-auto max-w-lg`, `max-w-3xl`, judul kartu) = 0; deploy scp **4190=4190 RC=0**, `view:clear` OK; e2e login 302 → GET tariff **200** `GRID=1 SPAN=1 TBL=1 FORM=1 BTN=1 EMPTY=1 INWP=3000 NOHEAD=0 NOLEG=0`, form action = URL absolut `route()` (POST nilai sama 302 → `F-NOMOVE=1`, `ROWS=0`, tarif tak berubah); GET `/settings/tanks` pembanding 200 `GRID=1 SPAN=1` (pola identik); DB `histories=0`, `AdminLog=0`, WP/AF `3000/5000`; ERROR hari ini `ERR0=ERR1=31` (0 baru) → **TARIFF-UI-VERIFY-OK** | `resources/views/settings/tariff.blade.php`, `DEPLOY_VSCODE.md` |
| 45 | Endpoint data operasional terbuka ke publik tanpa login (audit `TODO.md` Critical 1.2 / 1.3): `GET /api/dashboard/data`, `/api/dashboard-data`, `/api/device/history`, `/api/system/detected-devices`, `/api/detected-devices`, `/api/terminal/events`, `POST /api/terminal/clear`, `GET /api/meter/last/{id}` membalas **200** bagi anonim (bocor MAC/IP/RSSI/status pompa; log bisa dihapus siapa pun); `GET /api/system/cleanup` juga publik padahal menghapus log >90 hari + `OPTIMIZE TABLE`; `POST /login` tanpa pembatas percobaan | Route data UI **dipindah dari `routes/api.php` ke grup `auth.session` di `routes/web.php` dengan URI tetap sama** (fetch() dashboard & halaman detail tetap jalan karena cookie sesi terkirim otomatis; POST tetap kena CSRF grup web); `EnsureAuthenticated` kini membalas **401 JSON** untuk jalur `api/*` tanpa sesi (bukan redirect HTML agar parser AJAX tidak salah baca); maintenance `system/cleanup` pindah ke middleware baru **`device.key`** = `EnsureDeviceApiKeyStrict` (X-API-KEY **wajib valid**, tanpa fallback firmware lama — bila dipanggil cron tambahkan `?api_key=<DEVICE_API_KEY>`); `POST /login` diberi **`throttle:5,1`**; `/api/fingerprint` sengaja **tetap publik** (handshake firmware `Network_SSL.ino`) dan endpoint perangkat (`/api/log\|status\|update\|device/update\|health\|log-offline`) tidak berubah | Verifikasi lokal (PHP 8.2 XAMPP + PHPUnit 11.5): `php -l` OK×6; `route:list --json` → 8 endpoint UI `[web, auth.session]`, cleanup `[api, device.key]`, `/api/status` `[api, device.api]`; `artisan serve` + curl anonim → 8 endpoint UI **401** (`{"status":"error","message":"Unauthorized — silakan login terlebih dahulu."}`), `/api/fingerprint` **200**, `POST /api/device-command` & `POST /api/terminal/clear` tanpa token **419**, cleanup tanpa key **401** sedangkan dengan key lolos middleware (500 hanya karena MySQL XAMPP lokal mati — `SQLSTATE[HY000] [2002]`); PHPUnit **7 tes / 30 asersi OK** (uji throttle login → **429** pada percobaan ke-6; `ExampleTest` disesuaikan → `/` = redirect `/login`) | `routes/api.php`, `routes/web.php`, `app/Http/Middleware/EnsureAuthenticated.php`, `app/Http/Middleware/EnsureDeviceApiKeyStrict.php`, `bootstrap/app.php`, `tests/Feature/ApiEndpointSecurityTest.php`, `tests/Feature/ExampleTest.php`, `TODO.md` |
| 46 | Deploy Task #45 ke production & verifikasi live (jalur **`ssh.selur.my.id`** via cloudflared access + `ssh.exe`/`scp.exe -O` + askpass; LAN `192.168.20.200:22` tidak terjangkau dari laptop, port lokal 2222 sudah dipakai listener cloudflared yang berjalan). Server: HestiaCP, PHP **8.3.33**, host `pamsimas.selur.my.id` | Alur: (1) **backup pra-deploy** `tar czf /root/backup-t45/t45-pre.tar.gz` (6 file, 21.184 B); (2) staging lokal **LF + UTF-8 tanpa BOM** → `scp -O` 7 file ke `/tmp/t45`; (3) **integritas `md5sum` lokal = `/tmp/t45` = posisi akhir** (7/7 MATCH); (4) `mv` ke posisi akhir + `chown admin:admin` (file `devices/show.blade.php` tadinya milik `www-data`); (5) `php8.3 -l` OK×5, `artisan route:clear` OK, `artisan view:clear` OK (tidak ada `bootstrap/cache/routes-*.php`, jadi rute baru langsung aktif) | **Verifikasi live:** *anonim* → 8 endpoint UI **401** (body `{"status":"error","message":"Unauthorized — silakan login terlebih dahulu."}`), `/api/fingerprint` **200**, `/api/status` **422**, `/` **302**, `/login` **200**, `POST /api/device-command` & `POST /api/terminal/clear` tanpa token **419**, `/api/system/cleanup` tanpa key **401**; *sesi login (admin, `_token` dari halaman login)* → LOGIN 302 → GET `/` `/devices` `/devices/show/2` `/devices/detected` `/settings/tariff` `/logs/events` `/monitoring` semua **200** → `/api/dashboard-data` **200** (`stats.total_devices=2, online_devices=1`, `server_time` jalan) → `/api/device/history?device_id=2&range=1h` **200** → `POST /api/device-command` dengan token dari halaman dashboard + MAC tak dikenal = **404** `{"status":"error","message":"Perangkat tidak ditemukan."}` (jalur sah tetap bekerja, state tidak berubah; token pra-login memang 419 karena `session()->regenerate()` merotasi CSRF); `storage/logs/laravel.log` **tidak bertambah** (mtime & ERROR terakhir tetap `2026-09-24 08:45`, total ERROR=90 baseline) → **TASK45-DEPLOY-VERIFY-OK** | `routes/api.php`, `routes/web.php`, `bootstrap/app.php`, `app/Http/Middleware/EnsureAuthenticated.php`, `app/Http/Middleware/EnsureDeviceApiKeyStrict.php`, `resources/views/dashboard/index.blade.php`, `resources/views/devices/show.blade.php` |
| 47 | Repo GitHub `mswaluyo/pamsimas-selur` ternyata **PUBLIK** (`private: false`), padahal (a) `.fw_code/` firmware baru memuat **sandi Wi-Fi plaintext**, (b) `DEPLOY_AAPANEL.md` (7 tempat) + `scripts/setup-server.sh` memuat **sandi root MySQL/server asli**, (c) `.env.example`/`WHATSAPP.md`/`DEPLOY_AAPANEL.md` memuat `DEVICE_API_KEY` & `WA_GATEWAY_SECRET` asli | (1) `.fw_code` di-commit dengan `ssid`/`pass` **di-redact** ke placeholder `GANTI_SSID_WIFI`/`GANTI_SANDI_WIFI` + `README.md` (7 endpoint firmware + cara isi konfigurasi) ⇒ sandi Wi-Fi **tidak pernah terpublikasi**; (2) sandi server di `DEPLOY_AAPANEL.md` (baris 74, 88, 89, 92, 100, 101, 270) & `scripts/setup-server.sh` (13-15) → placeholder `<SANDI_ROOT_MYSQL>` + peringatan "repo publik" di kepala dokumen; (3) histori pekerjaan dirapikan jadi 6 commit atomic (working tree bersih, `git diff HEAD` kosong) dan dipush ke `origin/main` | Verifikasi: `bash -n scripts/setup-server.sh` RC=0; grep workspace → sisa kemunculan sandi **hanya** di `.env.production` (**gitignored**, tidak ter-publish); setelah push, file mentah di GitHub: `DEPLOY_AAPANEL.md` sandi=**0**/placeholder=**8**, `setup-server.sh` sandi=**0**/placeholder=**2**; GitHub HEAD = `d15cab3`; `git status` menunjukkan `main...origin/main` **in sync**; catatan lanjutan + jadwal rotasi kredensial dipindahkan ke `TODO.md` **bagian 7 (Backlog Akhir, pasca-stabil)** | `.fw_code/Pamsimas_Hybrid/**`, `DEPLOY_AAPANEL.md`, `scripts/setup-server.sh`, `TODO.md`, `DEPLOY_VSCODE.md` |
| 49 | Log boot firmware: `SECURITY: Fingerprint dari server tidak valid` + **setiap** request HTTPS menyusul `WARNING: Fingerprint belum tersedia, menggunakan mode tidak aman` (tanpa pinning SSL) — penyebabnya `/api/fingerprint` membalas **JSON** (260+ karakter) padahal firmware menyimpan respons ke `char fingerprint[60]` dan hanya menerima **21–59 karakter** (`Network_SSL.ino::fetchServerFingerprint`), nilai itu lalu dipakai `client.setFingerprint()` | Endpoint dikembalikan ke kontrak sistem lama: **plain text SHA1 sertifikat** `"AB:CD:…"` (59 karakter). Sertifikat yang dilaporkan = sertifikat **edge yang benar-benar dihadapi perangkat**, diambil service baru `SslFingerprintService`: resolusi IP publik via **DNS-over-HTTPS** (cloudflare-dns.com → dns.google → dns.quad9) karena hostname publik dipetakan `127.0.0.1` di `/etc/hosts` server → koneksi `ssl://<IP>:443` dengan **SNI** hostname → verifikasi nama host dari SAN/CN (dukungan wildcard `*.selur.my.id`) → `openssl_x509_fingerprint(sha1)` → format `strtoupper(chunk_split(,2,':'))`; hasil sukses di-cache 10 menit (gagal 30 detik). JSON tetap tersedia bila diminta eksplisit (`?format=json` atau `Accept: application/json`) **tanpa** data internal server (php/os/host dihapus dari respons lama); probe gagal / host belum diset → **HTTP 503** sehingga firmware otomatis memakai mode insecure (perangkat tidak ikut gagal); kill-switch darurat `FINGERPRINT_DISABLED=true`, override host `FINGERPRINT_HOST` (default dari `APP_URL`); route diberi `throttle:30,1` karena tiap permintaan melakukan koneksi TLS keluar | Verifikasi: `php -l` OK×5; PHPUnit **12 tes / 48 asersi OK** (kontrak plain-text 59 char + regex `^([0-9A-F]{2}:){19}[0-9A-F]{2}$`, JSON hanya bila diminta, 503 saat probe gagal/kill-switch/host kosong); lokal `artisan serve` + `FINGERPRINT_HOST=pamsimas.selur.my.id` → GET **200** `53:0C:FD:23:8E:95:45:C2:54:25:C6:2A:1A:52:28:30:34:B5:CA:D6` (len **59**), `?format=json` lengkap (`sha256=21:60:E6:…`, issuer `WE1`, valid `2026-09-17 → 2026-12-16`, target `172.67.164.2`), `FINGERPRINT_DISABLED=true` → **503**; deploy scp **md5 lokal = /tmp = posisi akhir 4/4 MATCH** (backup `/root/backup-t49.tar.gz`), `php8.3 -l` OK×4, `route:clear`+`view:clear` OK, `chown admin:admin`; **live**: dari laptop **dan** dari server (`curl -H 'Host: pamsimas.selur.my.id' http://127.0.0.1/api/fingerprint`) → HTTP **200**, plain text **identik 59 karakter**, JSON OK; `storage/logs/laravel.log` tidak bertambah (ERROR terakhir tetap 2026-09-24 08:45) → **FP-CONTRACT-VERIFY-OK** | `app/Services/SslFingerprintService.php` (baru), `app/Http/Controllers/Api/SystemApiController.php`, `config/services.php`, `routes/api.php`, `.env.example`, `tests/Feature/ApiFingerprintTest.php` (baru), `tests/Feature/ApiEndpointSecurityTest.php`, `.fw_code/Pamsimas_Hybrid/README.md` |
| 50 | Log firmware berisi dua respons **HTTP 400** — `reset_config` & `reset_mode_update` (hal serupa juga akan terjadi untuk `reset_ota_update`/`reset_restart`) karena `POST /api/update` hanya mengenal action `set_status/set_mode/set_manual_status/report_version/report_event/set_pump`; sisanya dijawab 400 `Unknown action` | `DeviceApiController::update()` kini menerima ack flag one-shot dari firmware: `reset_config` -> `config_update_command=false`, `reset_mode_update` -> `mode_update_command=false`, `reset_restart` -> `restart_command=false`, `reset_ota_update` -> dibalas 200 (skema baru belum punya kolom OTA); semuanya lewat helper baru `ackFirmwareFlag()` yang menyentuh `last_update` + mengembalikan status flag terkini (idempoten — flag juga sudah di-reset oleh `status()` saat nilai itu dikirim); action tak dikenal tetap 400 dan MAC tak dikenal tetap dibalas `unregistered` | Verifikasi: `php -l` OK; PHPUnit **16 tes / 71 asersi OK** (4 tes baru di `tests/Feature/DeviceAckActionTest.php`: ack->200 & flag bersih, action ngawur->400, MAC tak dikenal->unregistered, `/api/status` kirim menit x 60 lalu reset flag); deploy scp md5 **92bf43fb... MATCH** + backup `/root/backup-t50.tar.gz` + `php8.3 -l` OK + `chown admin:admin`; **live** POST `/api/update` (MAC `C4:D8:D5:13:A6:17` + `X-API-KEY`) -> `reset_config`, `reset_mode_update`, `reset_ota_update`, `reset_restart` semuanya **HTTP 200** (status=success, mode_update_command=0, config_update_command=0, restart_command=0) sedangkan `aksi_ngawur` tetap **400**; `GET /api/status?mac=...` -> **200** `on_duration=660, off_duration=600, full_tank_distance=25, empty_tank_distance=225, trigger_percentage=80, min_run_time=60, sensor_debounce=5, report_interval=3` -> **identik dengan blok [KONFIGURASI DARI SERVER] pada log perangkat**; `laravel.log` tidak bertambah (ERROR=90, mtime tetap 2026-09-24 08:45) -> **DEVICE-ACK-VERIFY-OK** | `app/Http/Controllers/Api/DeviceApiController.php`, `tests/Feature/DeviceAckActionTest.php` (baru), `DEPLOY_VSCODE.md`, `TODO.md` |
| 51 | Konfigurasi dari dashboard tidak selalu sampai ke firmware: (a) flag `config_update_command`/`mode_update_command` **di-reset oleh `GET /api/status` begitu nilai dikirim**, jadi kalau satu permintaan gagal (link ESP8266 lossy, perangkat offline/reboot saat admin menyimpan, HTTP error) perubahan hilang permanen dan perangkat tetap pakai nilai lama; (b) sinkronisasi nilai turunan (`syncFromMasterData`) hanya ada di `DeviceController` (duplikasi logika, tidak dipakai jalur lain); (c) mengubah **master data** di Pengaturan → Tangki/Pompa/Sensor sama sekali tidak memicu kirim ulang, sehingga edit di sana tidak pernah sampai ke perangkat yang memakainya | (1) `syncFromMasterData()` **dipindah ke model `Device`** sebagai satu-sumber kebenaran (tangki → `empty_tank_distance`; pompa → `on/off_duration` menit dengan `ceil(seconds/60)`; sensor → `full_tank_distance` + `trigger_percentage`); (2) `SettingController::updateTank/updatePump/updateSensor` memanggil sync untuk **semua perangkat** pemakai master itu + menaikkan `config_update_command=1` + catat `admin_logs` aksi **`Sync Perangkat`**; (3) `DeviceController::update` memakai method model yang sama, selalu menaikkan flag, dan flash berubah jadi "… diperbarui & dikirim ke perangkat."; (4) **`DeviceApiController::status()` tidak lagi menurunkan flag** — satu-satunya yang menurunkan adalah **ack firmware** di `POST /api/update` (`reset_config`/`reset_mode_update`, idempoten + dicatat ke `event_logs` "Perangkat menerapkan konfigurasi baru."), sehingga flag bertahan sampai benar-benar diterima | Verifikasi lokal: `php -l` OK×6; **PHPUnit 21 tes / 99 asersi OK** — `tests/Feature/ConfigPropagationTest.php` (baru, 5 tes: edit perangkat ganti tangki menyinkronkan + flag + `admin_logs`; ubah pompa/tangki/sensor master terkirim ke perangkat; master tanpa perangkat tidak menulis log; `ceil` 601 dtk → 11 menit) dan `DeviceAckActionTest` diubah menjadi **flag harus BERTAHAN setelah `/api/status` dan baru turun setelah ack**. Deploy: staging LF/no-BOM → `scp -O` 4 file, **md5 lokal = `/tmp/t51` = posisi akhir 4/4 MATCH**, backup `/root/backup-t51.tar.gz` (10.306 B), `php8.3 -l` OK×4, `chown admin:admin`, `view:clear` OK. **Verifikasi live lewat UI asli** (login admin, `_token` halaman): *edit perangkat #2 nilai identik* → POST **302**, flash baru muncul, `admin_logs` `Ubah Perangkat` 23:14:13 → **perangkat nyata** ack: `event_logs` "Perangkat menerapkan konfigurasi baru." **23:14:17** + "Perangkat menerapkan perubahan mode." 23:14:19 → flag `0` di snapshot berikutnya; *edit pompa #2 no-op (ON 660s/OFF 600s)* → POST **302** + flash "dikirim ke perangkat", `admin_logs` `Sync Perangkat → C4:D8:D5:13:A6:17` **02:08:29** → ack perangkat **02:08:34** (5 dtk) → flag `0` dan **tidak ada event berulang** pada snapshot +14 dtk (tidak ada loop kirim) → **CONFIG-PROPAGATION-VERIFY-OK**; `laravel.log` tidak bertambah. Catatan: ±02:10 WIB edge Cloudflare sempat gangguan (web `530 error code: 1033`, SSH `websocket: bad handshake`) — infra, tidak terkait kode; verifikasi di atas selesai sebelum kejadian | `app/Models/Device.php`, `app/Http/Controllers/DeviceController.php`, `app/Http/Controllers/SettingController.php`, `app/Http/Controllers/Api/DeviceApiController.php`, `tests/Feature/ConfigPropagationTest.php` (baru), `tests/Feature/DeviceAckActionTest.php`, `DEPLOY_VSCODE.md`, `TODO.md` |
| 52 | Form master data (Pompa / Tangki / Sensor / Tarif) & form User **tidak punya label** — field angka (`on_duration`/`off_duration`, tinggi tangki, jarak sensor, tarif, tahun) tidak jelas artinya; modal **Edit Tangki/Sensor** terpotong di layar kecil tanpa bisa di-scroll | Label `<label for>` dipasang pada **7 view** (`settings/pumps|tanks|sensors|tariff`, `templates/index`, `users/create|edit`) mengikuti konvensi `devices/_form.blade.php` (label di atas input, `for` == `id`, prefix `add-` untuk form Tambah) + teks bantu: durasi ON/OFF (detik), tinggi tangki (range HC-SR04 3–400 cm), jarak sensor Full/Empty; modal Edit Tangki & Sensor diberi `max-h-[90vh] overflow-y-auto`; commit `17f7296` → `origin/main`. **Deploy** (server BUKAN repo git → hotfix scp): daftar beda dihitung dengan **MD5 konten LF-normalized** vs `git ls-tree -r HEAD` → hasil **hanya 7 file** itu yang beda, sisanya (app/, routes/, config/, view lain) sudah sinkron → `tar -czf` 7 file → scp `/tmp/deploy.tgz` → `tar -xzf -C public_html` → `chown admin:admin` + `chmod 644` → `view:clear` + `view:cache` = **VIEW-CACHE-OK** (54 view ter-compile, string label baru terbaca di `storage/framework/views/*`) → **MD5 server == MD5 repo 7/7 MATCH**; backup pra-deploy `/tmp/backup-ui-20260929-153806` (7 file); `laravel.log` tidak bertambah; cek **eksternal** `https://pamsimas.selur.my.id/login` = **200** | `resources/views/settings/pumps|tanks|sensors|tariff.blade.php`, `resources/views/templates/index.blade.php`, `resources/views/users/create|edit.blade.php`, `.vscode/deploy-setup-key.ps1` (baru) |
| 53 | Pemilihan **Tipe Perangkat** menyesatkan (`MONITOR (sensor saja)` / `ACTUATOR (pompa saja, tanpa sensor)`) → perangkat ber-sensor terpasang sebagai **ACTUATOR**, sehingga log serial menampilkan `SENSOR: Mode Actuator, melewati pembacaan sensor fisik.` dan level air mandek di `Level air dari server: 27 %` (data lama) padahal seluruh parameter sensor terlihat "sudah diset" | Label opsi diganti **`MONITOR - sensor + pompa (fungsi ganda)`** / **`ACTUATOR - pompa saja (tanpa baca sensor)`** + **hint dinamis** di bawah `select` (JS toggle `hint-type-monitor`/`hint-type-actuator`; script diletakkan **di luar** `@if(!$isEdit)` agar ikut tampil di form edit); halaman detail: `Sumber Data Monitor` (logika `sensor_id ? … : …` — menyesatkan) → **`Sumber Level Air`** yang mengikuti `device_type`, plus keterangan singkat pada `Tipe Perangkat`. **Deploy**: 2 view, staging LF/no-BOM, `tar -cf` → scp → **`tar -xf`** (bukan `-xzf` — tar bawaan Windows tidak meng-gzip sendiri, percobaan pertama gagal `Not in gzip format`) → `chown admin:admin` + `chmod 644` → `view:clear` + `view:cache` OK (54 view) → **MD5 2/2 MATCH** (`6e1c7ad2…`/`cdfdefb7…`), frasa lama = 0 dan frasa baru terbaca di `storage/framework/views/*`; backup `/tmp/backup-typehint*` (3×); cek eksternal `/login` **200**. **Temuan sampingan**: kelas Tailwind baru **tidak ada** di bundle Vite (`mt-1.5`, `space-y-1`, `leading-relaxed` = 0) → dipakai hanya kelas yang sudah ada (`mt-1`, `hidden`, …), dicatat di §10. Commit `f8aadb5` → `63cd74f` → `1ee5c33` → `c9bf061` (push `origin/main`); pada **`c9bf061`** paragraf penjelasan di bawah `select` **beserta JS toggle `hint-type-*` dihapus atas permintaan operator** (label opsi dianggap cukup) — deploy ulang 1 file, **MD5 `b897ed4d…` MATCH**, `hint-type` = 0 di sumber & view ter-compile, `autoFill()` & tombol "Daftarkan Perangkat" tetap utuh | `resources/views/devices/_form.blade.php`, `resources/views/devices/show.blade.php`, `TODO.md` (§7.7), `DEPLOY_VSCODE.md` (§10) |
| 54 | `devices.report_interval` dengan label *"Interval Lapor (detik)"* disalahartikan sebagai laju **pelaporan sensor**; di firmware nilainya justru jadi `currentStatusFetchInterval` (**poll `/api/status`**), sedangkan laju kirim data sensor = konstanta `dataSendInterval = 3000 ms` (`Pamsimas_Hybrid.ino:123`) — angkanya kebetulan sama-sama 3 sehingga tampak "mengikuti firmware" | Field **dihapus** dari form Registrasi & Edit Perangkat + validasi `report_interval` dilepas dari `DeviceController::update()` (kiriman form lama kini diabaikan); nilai **tetap 3 detik** (default kolom DB = default firmware `STATUS_FETCH_NORMAL`); `/api/status` tetap mengirim `report_interval` dari DB supaya kontrak API utuh (tanpa flash ulang). **Verifikasi deploy**: staging LF/no-BOM, `php8.3 -l` controller OK, `tar -xf` → `chown admin:admin` + `chmod 644` → `view:clear` + `view:cache` OK → **MD5 2/2 MATCH** (`67064c0e…` view, `98130b82…` controller); `Interval Lapor` & `report_interval` = **0** di view sumber dan **0 file** di `storage/framework/views/*`; field lain utuh (MAC, Tipe Perangkat, Mode Kontrol, Jarak Penuh/Kosong, Trigger, tombol Daftarkan/Simpan, `autoFill()`); DB: device #2 & #3 `report_interval = 3`; backup `/tmp/backup-interval-20260930-013117`; cek eksternal `/login` **200**. Commit `7149e32`; analisis lengkap → `TODO.md` §7.8 | `resources/views/devices/_form.blade.php`, `app/Http/Controllers/DeviceController.php`, `TODO.md` (§7.8) |
| 55 | Riwayat (sensor/pompa/kejadian) tampak **7 jam lebih muda** dari jam operator. Port Laravel kehilangan dua hal yang ada di sistem lama (`backup_pamsimas/.env: TIMEZONE=Asia/Jakarta` + `core/Database.php:57-62: SET time_zone='+07:00'`): `config/app.php` masih `'UTC'` dan koneksi `mysql` tanpa kunci `timezone` (sesi MySQL = SYSTEM = UTC), sementara semua view mencetak stempel mentah | `config/app.php` → `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')`; `config/database.php` (blok `mysql` & `mariadb`) → `'timezone' => env('DB_TIMEZONE', '+07:00')` (didukung Laravel 12: `MySqlConnector.php:110-111`); `.env.example` + dokumen ini. `.env` server **tidak** diubah (default sudah WIB ⇒ mudah dibalik). Data lama **tidak** dimigrasi: kolom `TIMESTAMP` otomatis dibaca WIB; kolom `DATETIME` agregat digeser sekali `+7 HOUR` (`minute_sensor_logs` 7.524 baris, `hourly_sensor_logs` 135 baris) dengan `ORDER BY <kolom> DESC` karena PK `(device_id, timestamp)`. **Verifikasi**: `config=Asia/Jakarta` + sesi MySQL `+07:00`, `now()=20:38:25` vs `date` server `13:38:25 UTC`; jalur view (Eloquent cast+format) → event 19:26:35, pump 20:27:51, sensor 30-09-2026 09:09:52, device 20:38:23 + `online=YA` (sebelumnya 02:09/13:27/12:26); agregat menit 09:09:00 **cocok** dengan raw; error laravel 94→94; perangkat tetap `/api/status` 200 tiap 3 dtk; `/login` 200; MD5 `0dd01448…`/`63bd6164…`; backup config `/tmp/backup-tz-20261003-133648`; `config:clear` + `view:clear`/`view:cache` OK. Commit `3187443`; analisis → `TODO.md` §7.10 | `config/app.php`, `config/database.php`, `.env.example`, `TODO.md` (§7.10) |
| 56 | Grafik halaman perangkat **tidak menampilkan jeda OFF 10 menit** (pompa tampak nyala ~40 menit sekali siklus). Penyebab: port Laravel menulis ulang `devices.status` + `pump_logs` dari data level pada **setiap** poll `/api/status` (`applyAutoControl()`), sehingga ~4 detik setelah perangkat melaporkan OFF (safety cut-off) server menulis ON lagi; saat perangkat benar-benar menyala lagi laporannya tidak menghasilkan log baru (nilai sama) ⇒ jeda istirahat raib dari riwayat. Sistem lama tidak pernah menimpa status dari level | `applyAutoControl()` kini **hanya menghitung perintah usulan** (`pump_command`, ambang disamakan dengan firmware: `pct <= trigger` ⇒ ON, `pct >= 98` ⇒ OFF) dan **tidak menulis** `status`/`pump_logs`; status hanya berubah dari `/api/update` `set_status` (laporan perangkat). **Verifikasi**: uji A/B dengan perangkat dummy di dalam transaksi DB + `rollback` → `status OFF → OFF` (tidak ditimpa), `pump_logs` baru **0**, `event_logs` baru **0**, respons tetap `pump_command=ON, status=OFF, water_percentage=0, source_ready=1`; setelah rollback `devices = 2` (bersih); MD5 `000547f84e7f87ffd37ce5990bfff158`; `php8.3 -l` OK; error laravel 94 → 94; `/api/status` perangkat tetap 200 tiap 3 dtk; backup `/tmp/backup-power-20261003-140243`. Catatan: **riwayat lama direkonstruksi** (21:40 WIB) — dari 187 event phantom `Pompa ON (AUTO) @ x%`: 156 digeser ke ON nyata (`OFF + off_duration`, pesan diberi tanda `(rekonstruksi)`), 11 dikembalikan (siklus pasca-reboot), 20 dilewati; cadangan `event_logs_bak_20261003_214008` (2.050 baris) & `pump_logs_bak_20261003_214008` (1.221 baris). **Bukti live**: pengamat server menangkap `21:38:01 OFF` (laporan perangkat) → `21:38:12 status_db=OFF` tanpa ON palsu → `21:48:02 ON` (laporan perangkat), jeda **10,0** menit dan durasi nyala **30,1** menit per siklus | `app/Http/Controllers/Api/DeviceApiController.php`, `TODO.md` (§7.11) |


---

## 10. Zona Aman & Zona Bahaya

**Aman:** deploy `.blade.php`/controller/model + `view:clear`; `migrate --force`;
`db:seed --force`; `php8.3 -l <file>` sebelum upload.

**Hindari / hati-hati:**
- ❌ `php artisan config:cache` — anomali di server ini.
- 🔑 **Akses SSH saat ini:** kunci publik `~/.ssh/id_ed25519.pub` (mesin Windows) sudah
  terdaftar di `/root/.ssh/authorized_keys` STB → `ssh -o UserKnownHostsFile=NUL -p 2222
  root@127.0.0.1` login **tanpa sandi** selama `SSH-Connections-cloudflare.bat` aktif.
  Daftarkan ulang (mesin/perangkat baru): `powershell -File .vscode\deploy-setup-key.ps1`
  (butuh sandi root 1× lewat askpass). Kunci lama `id_rsa_pem` **tidak** diterima server.
  Catatan: folder `.vscode/` di-`.gitignore`, jadi helper ini **hanya ada di mesin lokal** —
  salin manual ke mesin lain.
- ⚠️ **Jangan** verifikasi situs dengan `curl https://pamsimas.selur.my.id/...` **dari dalam
  STB** — selalu `000`/`400` (origin tidak melayani loopback lewat Cloudflare). Cek dari
  mesin eksternal: `Invoke-WebRequest https://pamsimas.selur.my.id/login` (harap `200`).
- ⚠️ Skrip `.sh` hasil scp dari Windows **ber-CRLF** → normalkan `tr -d '\r' < x.sh > x.lf`
  lalu `bash x.lf`. `sed -i 's/\r$//'` pernah memangkas karakter dan merusak skrip;
  file ber-CRLF juga membuat `read` membawa `\r` → path tak ketemu ("MISSING" palsu).
- ⚠️ Daftar file yang beda server↔repo: bandingkan **MD5 konten setelah `tr -d '\r'`**
  (bukan `md5sum` mentah, bukan sha1 git) — view di server ber-CRLF, di repo ber-LF.
- ⚠️ **Kelas Tailwind baru tidak otomatis muncul**: CSS disajikan dari bundle Vite yang sudah dibangun
  (`public_html/public/build/assets/app-*.css`, saat ini `app-BxmBOLVx.css`). Kelas yang belum pernah
  dipakai view mana pun **tidak ada** di bundle, jadi menambahkannya di `.blade.php` berdampak kosong
  bila `public/build` tidak dibangun ulang — contoh nyata (Task #53): `mt-1.5`, `space-y-1`,
  `leading-relaxed` **tidak ada**; sedangkan `mt-1`, `hidden`, `text-xs`, `font-semibold`,
  `text-slate-500`, `text-slate-700`, `text-amber-700` **ada**. Cek cepat dari lokal sebelum deploy:
  `Select-String -Path public\build\assets\app-*.css -Pattern '\.mt-1\.5\{'`. Bila kelas baru memang
  wajib: `npm run build` → unggah folder `public/build/` (termasuk `manifest.json`) ke server, lalu
  bersihkan cache view.
- ❌ Set custom docroot manual dua kali (menumpuk).
- 🗑️ Panduan server lama **`DEPLOY_AAPANEL.md` dihapus** (platform AAPANEL sudah ditinggalkan,
  kini HestiaCP di STB) — dokumen ini + `DEPLOYMENT.md` satu-satunya acuan. Riwayat pemakaiannya
  tetap terbaca di Task #47 (tabel §9) dan histori git (`git log --diff-filter=D -- DEPLOY_AAPANEL.md`).
  Dokumen deploy di `public_html/` **tidak** dapat diakses publik (`curl .../DEPLOY_AAPANEL.md`
  → **404**, docroot = `public_html/public`) — diverifikasi ulang saat Task #52.
- ⚠️ Task #49 (kontrak `/api/fingerprint`): sertifikat edge Cloudflare yang kini dipin firmware =
  SHA1 `53:0C:FD:23:8E:95:45:C2:54:25:C6:2A:1A:52:28:30:34:B5:CA:D6` (valid s/d **2026-12-16**).
  Firmware mengambil nilai ini **sekali per boot**; bila Cloudflare merotasi sertifikat, perangkat
  akan gagal handshake HTTPS sampai di-reboot (=ambil fingerprint baru). Darurat tanpa deploy:
  set `FINGERPRINT_DISABLED=true` di `.env` server + `config:clear`, lalu reboot perangkat →
  firmware kembali memakai mode insecure (seperti sebelum #49). Cek nilai terkini kapan saja:
  `curl -s https://pamsimas.selur.my.id/api/fingerprint?format=json`.
- ⚠️ Task #51 (propagasi konfigurasi): `GET /api/status` **tidak lagi** menurunkan
  `config_update_command`/`mode_update_command` — hanya ack firmware (`reset_config` / `reset_mode_update`
  di `POST /api/update`) yang menurunkannya, supaya perubahan tidak hilang saat satu pengiriman gagal.
  Konsekuensi: perangkat ber-firmware lama (pra-#50, tidak mengirim ack) akan membuat flag menempel `1`
  dan konfigurasi dikirim berulang — aman (nilai tetap benar), tetapi `event_logs` tidak mencatat ack.
  Selain itu menyimpan **master data** (Pengaturan → Tangki/Pompa/Sensor) kini **langsung mendorong
  nilai ke perangkat** pemakainya (`admin_logs` aksi `Sync Perangkat`): jangan koreksi data master
  "diam-diam" saat perangkat sedang beroperasi — lihat catatan data master di `TODO.md` bagian 7.3/7.4.
- ⚠️ Artisan wajib sebagai `admin` (`sudo -u admin`), bukan root.
- ⚠️ JSON dari PowerShell 5.1 mengandung **BOM** — strip sebelum `json_decode`.
- ⚠️ Posh-SSH tak kompatibel OpenSSH 9.2 → pakai `ssh.exe`/`scp.exe` + askpass.
- ⚠️ Setelah mengubah `routes/*.php` atau `bootstrap/app.php`: jalankan `sudo -u admin php8.3 artisan route:clear`
  (kalau pernah ada `bootstrap/cache/routes-*.php`, perubahan rute/middleware tidak akan terbaca). Jangan pakai `route:cache`.
- ⚠️ Task #45 (proteksi endpoint data): file yang harus diunggah = `routes/api.php`, `routes/web.php`,
  `bootstrap/app.php`, `app/Http/Middleware/EnsureAuthenticated.php`, `app/Http/Middleware/EnsureDeviceApiKeyStrict.php` (baru)
  → lalu `route:clear` + `view:clear`. Jika ada cron/script yang memanggil `/api/system/cleanup`,
  tambahkan `?api_key=<DEVICE_API_KEY>` (atau header `X-API-KEY`) karena endpoint itu kini **wajib key valid**.

## 11. Runbook: Pemulihan Setelah Listrik Padam (server mati total)

**Gejala saat server mati / connector cloudflared tidak jalan:** `pamsimas.selur.my.id` dan
`ssh.selur.my.id` sama-sama membalas **HTTP 530** body `error code: 1033`; SSH `ssh root@ssh.selur.my.id`
berhenti di `websocket: bad handshake`. Pembeda dengan masalah Cloudflare: `https://pamsimas.selur.my.id/cdn-cgi/trace`
**tetap 200** (DNS + routing edge sehat) → masalah ada di **sisi server**, bukan Cloudflare.
Terverifikasi 29 Sep 2026: penyebabnya **listrik padam, server mati total**.

**Dampak ke perangkat di lapangan (dari source `.fw_code/Pamsimas_Hybrid` — semua ini otomatis):**
- Jaringan/server putus → firmware mencatat *"NETWORK: Koneksi terputus. Beralih ke mode AUTO sebagai
  fallback"* (`Pamsimas_Hybrid.ino:228`) → pompa **tetap terkontrol lokal** memakai konfigurasi terakhir
  yang tersimpan di **EEPROM** (`EEPROM.put(EEPROM_ADDR_DEVICE_CONFIG, …)`, hanya ditulis saat berubah).
- Data selama padam **tidak hilang**: tertahan di LittleFS — `/sensor_log.txt`, `/pump_log.txt`,
  `/event_log.txt` — lalu dikirim berangsur (per kloter) lewat `POST /api/log-offline` (`sendOfflineLogs()`)
  begitu server hidup; file dihapus setelah terkirim.
- Konfigurasi yang belum sempat diakui perangkat **tidak hilang**: `config_update_command` bertahan sampai
  ack `reset_config` (Task #51) → terkirim pada polling pertama setelah server hidup.
- Fingerprint SSL diambil **sekali per boot**; reboot server tidak mengubah sertifikat edge → perangkat
  aman, tidak perlu diapa-apakan. (Bila Cloudflare yang merotasi sertifikat, lihat catatan Task #49.)

**Urutan pemulihan setelah listrik menyala:**
1. Nyalakan server (bila tidak auto-on: set BIOS `AC Back / Restore on AC Power Loss = Power On`).
2. `systemctl is-active nginx php8.3-fpm mariadb cloudflared` → `systemctl restart <yang inactive>`.
   Perintah artisan tetap wajib `sudo -u admin php8.3 …` (bukan root).
3. `journalctl -u cloudflared -n 30 --no-pager` → harus ada baris **"Registered connection"**;
   lalu `curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: pamsimas.selur.my.id' http://127.0.0.1/api/health` → **200**.
4. Dari luar (bukti tunnel pulih):
   `curl -s -o NUL -w '%{http_code}\n' https://pamsimas.selur.my.id/api/health` → **200** dan
   `curl -s https://pamsimas.selur.my.id/api/fingerprint` → **59 karakter** (`^([0-9A-F]{2}:){19}[0-9A-F]{2}$`).
5. Perangkat reconnect **sendiri** (polling 3 dtk): cek `devices.last_update` < 2 menit; bila
   `config_update_command` masih `1`, itu normal sampai perangkat ack (`event_logs`
   "Perangkat menerapkan konfigurasi baru.").
6. Backlog LittleFS terkirim bertahap → `sensor_logs` akan "melompat" naik beberapa menit (normal),
   dan `storage/logs/laravel.log` biasanya bertambah error koneksi dari firmware saat restart.

**Cegah berulang:** lihat `TODO.md` bagian **7.5** (UPS untuk server + router, auto-power-on BIOS,
`Restart=always` untuk `cloudflared`, watchdog + peringatan WhatsApp).


---

*Dibuat dari sesi deploy 21–22 Sep 2026 (router baru: server @ `192.168.20.200`).
Perbarui tabel riwayat (Bagian 9) setiap sesi hotfix berikutnya.*
