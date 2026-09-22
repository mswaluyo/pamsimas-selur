# Dokumentasi: Deploy & Hotfix Langsung dari VSCode

Panduan ini mendokumentasikan alur kerja yang dipakai untuk men-deploy dan memperbaiki
website PAMSIMAS (`https://pamsimas.selur.my.id`, server HestiaCP @ `192.168.11.187`)
**tanpa keluar dari VSCode** — semua via terminal PowerShell + SSH/SCP.

---

## 1. Arsitektur & Prasyarat

| Komponen | Nilai |
|---|---|
| Server | Debian 12 + HestiaCP, IP `192.168.11.187` |
| Akses web publik | Cloudflare Tunnel (`cloudflared` service, tunnel ID `2280673a-...`) |
| Path project di server | `/home/admin/web/pamsimas.selur.my.id/public_html/` |
| User web / SSH | `admin` (web) / `root` (SSH) |
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

Semua operasi remote memakai pola ini (jalankan di terminal PowerShell VSCode):

```powershell
$pw   = ConvertTo-SecureString 'PASSWORD_ROOT' -AsPlainText -Force
$cred = New-Object System.Management.Automation.PSCredential('root', $pw)
$s = New-SSHSession -ComputerName 192.168.11.187 -Credential $cred -AcceptKey -ErrorAction Stop

# Jalankan perintah:
$r = Invoke-SSHCommand -SessionId $s.SessionId -TimeOut 120 -Command 'perintah-bash-di-server'
$r.Output   # hasil output
$r.Error    # stderr

Remove-SSHSession -SessionId $s.SessionId | Out-Null
```

> **Tips escaping**: perintah bash yang kompleks/berisi kutip ganda dikirim sebagai
> **base64** agar aman dari masalah quoting:
>
> ```powershell
> $script = @'
> # ...bash script bebas kutip...
> '@
> $b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($script))
> $r = Invoke-SSHCommand -SessionId $s.SessionId -TimeOut 300 -Command "echo $b64 | base64 -d | bash"
> ```

---

## 3. Pola Deploy File (Hotfix Langsung)

Alur standar setiap kali mengubah file project:

1. **Edit file di VSCode** (lokal, tetap tersimpan di git repo lokal).
2. **Upload** via `Set-SCPItem` ke `/tmp`, lalu `mv` ke posisi akhir:

   ```powershell
   Copy-Item 'd:\pamsimas.selur.my.id\resources\views\...' $env:TEMP\nama-file.php -Force
   Set-SCPItem -ComputerName 192.168.11.187 -Credential $cred -AcceptKey `
       -Path $env:TEMP\nama-file.php -Destination '/tmp' -ErrorAction Stop | Out-Null
   ```

   ```bash
   mv /tmp/nama-file.php /home/admin/web/pamsimas.selur.my.id/public_html/resources/views/.../
   ```

   > ⚠️ `Set-SCPItem -Destination` dengan path bersarang bermasalah — selalu upload
   > ke `/tmp` lalu pindahkan via SSH. Gunakan **nama file unik di /tmp** agar tidak
   > saling menimpa.

3. **Fix kepemilikan** (web berjalan sebagai `admin`):

   ```bash
   chown -R admin:admin /home/admin/web/pamsimas.selur.my.id/public_html/app \
                        /home/admin/web/pamsimas.selur.my.id/public_html/resources
   ```

4. **Rebuild cache view** (WAJIB setelah ubah `.blade.php`):

   ```bash
   cd /home/admin/web/pamsimas.selur.my.id/public_html
   sudo -u admin php8.3 artisan view:clear
   sudo -u admin php8.3 artisan view:cache   # harus "VIEW-CACHE-OK"
   ```

5. **Verifikasi** (bagian 4 & 5).

Setelah sesi hotfix selesai, perubahan lokal di-commit ke git sehingga ZIP deploy
berikutnya (`scripts/create-zip-hestiacp.ps1`) otomatis membawanya.

---

## 4. Checklist Verifikasi Setelah Deploy

```bash
# Aplikasi hidup:
curl -sS -o /dev/null -w "login=%{http_code}\n" -H "Host: pamsimas.selur.my.id" http://192.168.11.187/login
# Harus 200 (atau 302 untuk halaman ber-auth saat belum login)

# Log error terakhir:
tail -20 storage/logs/laravel.log

# API dashboard:
curl -sS -H "Host: pamsimas.selur.my.id" http://192.168.11.187/api/dashboard-data | head -c 400

# Koneksi DB (klien mysql tidak ada di PATH — pakai PHP):
php8.3 -r "new PDO('mysql:host=127.0.0.1;dbname=admin_pamsimas_db','admin_pamsimas_user','...');echo 'DB OK';"
```

Login-test end-to-end via curl (untuk halaman ber-auth):

```bash
JAR=/tmp/cj.txt; rm -f $JAR
TOKEN=$(curl -sS -c $JAR -H "Host: pamsimas.selur.my.id" http://192.168.11.187/login \
        | grep -oP 'name="_token"[^>]*value="\K[^"]+' | head -1)
curl -sS -b $JAR -c $JAR -o /dev/null -w "loginpost=%{http_code}\n" \
     -H "Host: pamsimas.selur.my.id" \
     -d "_token=$TOKEN&username=admin&password=admin123" http://192.168.11.187/login
curl -sS -b $JAR -o /dev/null -w "dashboard=%{http_code}\n" \
     -H "Host: pamsimas.selur.my.id" http://192.168.11.187/
```

---

## 5. Debugging 500: Selalu Mulai dari Log

```bash
# Pesan error terbaru (1 baris = 1 error):
grep -E "production.ERROR" storage/logs/laravel.log | tail -3 | cut -c1-400
# Atau full stack:
tail -c 3000 storage/logs/laravel.log
```

Contoh kasus nyata yang sudah terjadi & solusinya:

| Gejala | Penyebab | Solusi |
|---|---|---|
| 500 `unexpected token "]"` di view | Regex JS berisi `{{ ... }}` diparse Blade | Bungkus `<script>` dengan `@verbatim ... @endverbatim` |
| 500, log tak bertambah, body kosong | Fatal error sebelum Laravel logging | Cek `php8.3 artisan view:cache` (gagal = sintaks blade), cek error FPM |
| Semua request DB `Access denied 1698 (root)` | `bootstrap/cache/config.php` cache lama | `php8.3 artisan config:clear` |
| Nilai `.env` benar tapi request 500 | Cache config usang | `config:clear`; **hindari `config:cache` di server ini** (anomali) |
| Halaman "Success!"/"Page Not Found" | Docroot Nginx salah | Lihat bagian 7 |

---

## 6. Database (MariaDB)

Klien `mysql` tidak tersedia di PATH server → gunakan script PHP PDO sementara:

```bash
cat > /tmp/q.php <<'PHP'
<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=admin_pamsimas_db;charset=utf8mb4',
               'admin_pamsimas_user', 'PASSWORD', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($pdo->query('SELECT ...') as $r) { print_r($r); }
PHP
sudo -u admin php8.3 /tmp/q.php && rm /tmp/q.php
```

Import data dari dump backup lama (`backup_pamsimas/.db_source/pamsimas_db.sql`):
ekstrak blok `INSERT INTO ...;` per tabel (hati-hati: backtick SQL = `[char]96` di
PowerShell), bungkus dengan `SET FOREIGN_KEY_CHECKS=0/1;`, jalankan via PHP PDO.
Sudah dipakai untuk: master tangki/pompa/sensor (`deploy/restore-master.sql`)
dan seeder template gauge (`deploy/gauge-templates.json`).

---

## 7. Infrastruktur yang Perlu Diketahui

- **Nginx hanya listen di `192.168.11.187:80`** (bukan 127.0.0.1). Cloudflare Tunnel
  origin-nya `http://localhost:80` → diteruskan oleh service systemd **`localhost80.service`**
  (socat `127.0.0.1:80 → 192.168.11.187:80`, auto-start,
  sumber: `/etc/systemd/system/localhost80.service`).
- **Docroot**: jangan set custom docroot `public` secara manual — template `laravel`
  Hestia sudah otomatis menambah `/public` (pernah menyebabkan docroot dobel `/public/public`).
- `/etc/hosts` server memetakan `pamsimas.selur.my.id → 127.0.0.1` (buatan Hestia),
  jadi tes dari server sendiri wajib `curl --resolve pamsimas.selur.my.id:443:IP_CLOUDFLARE`.
- **PHP CLI default = 8.5**; selalu gunakan eksplisit `php8.3` untuk artisan.
- Restart service bila perlu: `systemctl restart nginx php8.3-fpm cloudflared`.

---

## 8. SSH Remote dari VSCode via Cloudflare Tunnel

SSH ke server dari luar jaringan memakai jalur: **PC → cloudflared access → Cloudflare edge → tunnel → sshd (port 22)**.

### 8.1 Komponen
| Komponen | Lokasi | Fungsi |
|---|---|---|
| `cloudflared.exe` | `C:\cloudflared\cloudflared.exe` | Jembatan akses tunnel di PC |
| `SSH-Connections-cloudflare.bat` | `D:\pamsimas.selur.my.id\.vscode\` | Menjalankan jembatan di `localhost:2222` |
| `~/.ssh/config` (alias `ssh.selur.my.id`) | `C:\Users\<user>\.ssh\config` | ProxyCommand otomatis per koneksi SSH |
| Route tunnel | Zero Trust → pamsimas-server | `ssh.selur.my.id → ssh://localhost:22` (**port wajib 22, bukan 20**) |

### 8.2 Cara pakai (urutan)
1. Jalankan `D:\pamsimas.selur.my.id\.vscode\SSH-Connections-cloudflare.bat`
   → harus muncul `INF Start Websocket listener host=localhost:2222` tanpa error.
2. SSH — pilih salah satu:
   ```
   ssh root@ssh.selur.my.id     # via ProxyCommand (config ~/.ssh/config)
   ssh pamsimas-local           # via jembatan .bat (port 2222)
   ```
3. Di VS Code: extension **Remote-SSH** → connect ke host `ssh.selur.my.id`
   (Remote-SSH memakai `~/.ssh/config` yang sama, jadi bekerja tanpa .bat sekalipun).

### 8.3 DNS & Cloudflare (sudah benar, untuk referensi)
- DNS: `ssh.selur.my.id` → record **Tunnel pamsimas-server** (Proxied). Jangan ganti manual.
- Ingress route: Service **`ssh://localhost:22`** — pernah salah ketik `:20` → SSH gagal menjangkau sshd.
- SSH server di server = **dropbear**, listen `0.0.0.0:22`.

### 8.4 Troubleshooting
| Gejala | Penyebab | Solusi |
|---|---|---|
| `bind: Only one usage of each socket address (port 2222)` | Jembatan cloudflared lain masih hidup (duplikat) | Matikan: `Get-NetTCPConnection -LocalPort 2222 -State Listen \| %{ Stop-Process -Id $_.OwningProcess -Force }` lalu jalankan ulang .bat |
| `failed to connect to origin ... no such host` | DNS lokal gagal resolve `*.cfargotunnel.com` | Perbaiki DNS/CNAME record; atau pakai alias `pamsimas-local` saat bridge .bat aktif |
| Koneksi terbuka tapi SSH hang | Ingress port salah (`:20`) atau Access policy menolak | Cek Service `:22` + buat Access policy Allow email Anda |
| `ssh root@ssh.selur.my.id` timeout tanpa cloudflared | SSH mentah tak lewat tunnel | **Wajib** lewat `cloudflared access` (bat/ProxyCommand) — `*.cfargotunnel.com` tak resolve ke IP publik |
| Login password ditanya terus | Normal (dropbear + password auth) | Ketik password root; sarankan ganti ke key auth |

### 8.5 Alternatif cepat (di jaringan lokal yang sama)
```
ssh root@192.168.11.187
```
Tidak butuh cloudflared sama sekali — ini yang dipakai untuk seluruh hotfix di Bagian 9.

---

## 9. Riwayat Perbaikan (rekaman sesi ini)

| # | Masalah | Perbaikan | File |
|---|---|---|---|
| 1 | `.env.production` `WA_GATEWAY_URL` salah (404) | Arahkan ke `http://127.0.0.1:3000/send-wa` | `.env.production` |
| 2 | Cache config lama (`root`/`pamsimas_db`) | `config:clear` | — (runtime) |
| 3 | Docroot dobel `public/public` | Reset custom docroot; andalkan template `laravel` Hestia | — (Hestia) |
| 4 | User MySQL belum ada | Buat DB + user sesuai kredensial Hestia | — (DB) |
| 5 | Menu "Perangkat Terdeteksi" tidak ada + MAC tak bisa didaftarkan | Section baru di daftar perangkat + tombol *Daftarkan* (MAC prefill) + link sidebar | `DeviceController.php`, `devices/index.blade.php`, `devices/_form.blade.php`, `layouts/app.blade.php` |
| 6 | Master tangki/pompa/sensor kosong | Import dari dump backup | `deploy/restore-master.sql` |
| 7 | Master data tidak bisa diedit | Tambah tombol Edit (modal) + Hapus per baris | `SettingController.php`, `settings/{tanks,pumps,sensors}.blade.php` |
| 8 | Tangki tak punya field dimensi (P×L / ⌀) | Field dinamis kotak/tabung + logika simpan dimensi | `SettingController.php`, `settings/tanks.blade.php` |
| 9 | Registrasi perangkat minta input manual | Auto-fill dari master data (server + JS) | `DeviceController.php` (`fillFromMasterData`), `devices/_form.blade.php` |
| 10 | Template gauge tak bisa diganti | Dashboard render template aktif; `water_percentage` ditambah ke API; 5 template lama di-seed | `DashboardController.php`, `dashboard/index.blade.php`, DB `gauge_templates` |
| 11 | Dashboard 500 (regex `{{ }}` diparse Blade) | `@verbatim` di blok script | `dashboard/index.blade.php` |
| 12 | Sidebar berantakan | Section konsisten (Utama/IoT/Layanan/Pengaturan/Riwayat/Sistem) via helper `$navSection`/`$navItem` | `layouts/app.blade.php` |
| 13 | Error 1033 Cloudflare Tunnel | DNS CNAME menunjuk tunnel lama `f75adb78-...` → ganti ke tunnel aktif `2280673a-...` | — (Cloudflare DNS) |
| 14 | Nginx tak listen di localhost (origin tunnel gagal) | Service `localhost80.service` (socat 127.0.0.1:80 → 192.168.11.187:80) | — (server) |
| 15 | SSH remote `ssh root@ssh.selur.my.id` gagal | Port ingress `:20` → `:22`, matikan duplikat bridge (port 2222 terpakai), DNS CNAME diperbaiki ke tunnel aktif | Cloudflare Zero Trust, `~/.ssh/config`, `.vscode/SSH-Connections-cloudflare.bat` |
| 16 | Sidebar berantakan (lanjutan #12) | Judul section untuk semua grup via helper `$navSection`/`$navItem` | `layouts/app.blade.php` |
| 17 | HestiaCP File Manager (`/fm/`) "Unknown error" | SSH server **dropbear** tak mendukung opsi key `restrict` & `internal-sftp` yang ditulis Hestia FM → ganti ke `openssh-server` (port 22), dropbear di-disable. Key FM (`hst-filemanager-key`) kini login SFTP sukses | `/etc/ssh/sshd_config.d/hestia.conf`, systemd `ssh` vs `dropbear` |

---

## 10. Zona Aman & Zona Bahaya

**Aman dilakukan langsung:**
- Deploy `.blade.php`, controller, model → + `view:clear` (+ `view:cache`).
- `migrate --force` untuk migrasi baru, `db:seed --force`.
- `php8.3 -l <file>` untuk cek sintaks PHP sebelum upload.

**Hindari / hati-hati:**
- ❌ `php artisan config:cache` — anomali di server ini (cache menghasilkan nilai lama).
- ❌ Mengubah docroot via `v-change-web-domain-docroot` dua kali (menumpuk).
- ⚠️ `composer` di server pakai `php8.3 composer.phar`; jalankan sebagai `admin`.
- ⚠️ Semua `artisan` wajib `sudo -u admin` atau sebagai user `admin` — jangan root
  (membuat file milik root yang memblokir penulisan session/log).
- ⚠️ File JSON/teks yang dibuat `Set-Content -Encoding UTF8` (PowerShell 5.1) mengandung
  **BOM** — strip BOM sebelum `json_decode` di PHP.

---

*Dokumentasi dibuat dari sesi deploy 21–22 Sep 2026. Perbarui tabel riwayat (Bagian 8)
setiap sesi hotfix berikutnya.*

