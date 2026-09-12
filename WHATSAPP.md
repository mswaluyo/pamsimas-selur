# Panduan Integrasi WhatsApp (WA Gateway)

## Gambaran Umum

PAMSIMAS menyediakan fitur notifikasi & pelaporan meteran air via WhatsApp menggunakan **WhatsApp Gateway** berbasis Node.js + Baileys yang berada di direktori `wa-gateway/`.

Alur kerja:
1. Warga mengirim teks `AKTIVASI-PTA-XXXX` atau foto meteran ke nomor WA gateway.
2. Gateway (Node.js) menerima pesan dan meneruskannya ke webhook Laravel (`/api/api_wa` atau `/webhook/wa`).
3. Laravel memvalidasi, menyimpan foto ke `storage/app/meter_photos/`, menciptakan antrean `customer_validations`, dan mengirim balasan ke warga via gateway yang sama.

## Struktur

## Dependencies

### Backend (Laravel)
- Tidak ada dependency PHP baru yang wajib. Pastikan:
  - `storage/app/meter_photos` dapat ditulis oleh proses Laravel
  - Secret `WA_GATEWAY_SECRET` dikonfigurasi (lihat bagian Konfigurasi)

### Gateway (Node.js)
- Node.js >= 18 direkomendasikan.
- Install dependency di `wa-gateway/`:

```bash
cd wa-gateway
npm install
```

Dependency yang digunakan:
- `@whiskeysockets/baileys` — koneksi WhatsApp
- `qrcode-terminal` — menampilkan QR code saat pairing
- `axios` + `form-data` — HTTP client untuk mengirim pesan ke PHP & mengupload foto
- `pino` + `@hapi/boom` — logging & error handling

## Konfigurasi

### 1. Environment variable (.env)

Tambahkan atau sesuaikan di `.env` Laravel:

```
WA_GATEWAY_URL=http://127.0.0.1:3000/send-wa
WA_GATEWAY_SECRET=P4mS1m4s-T1rt0-Arg0-2025
WA_WEBHOOK_URL=http://127.0.0.1:8000/api/api_wa
WA_GATEWAY_NUMBER=6285157275866
```

| Variabel | Kegunaan |
|----------|----------|
| `WA_GATEWAY_URL` | URL HTTP POST untuk mengirim pesan keluar |
| `WA_GATEWAY_SECRET` | Rahasia bersama untuk autentikasi: gateway mengirim `X-Pamsimas-Key` dalam setiap request ke webhook Laravel |
| `WA_WEBHOOK_URL` | URL di Laravel yang akan dipanggil oleh gateway saat ada pesan masuk |
| `WA_GATEWAY_NUMBER` | Nomor WA gateway |

> Setelah mengubah `.env`, jalankan `php artisan config:clear`.

### 2. Config Laravel (`config/services.php`)

Pastikan bagian ini ada:

```php
'wa_gateway_url'      => env('WA_GATEWAY_URL', 'http://127.0.0.1:3000/send-wa'),
'wa_gateway_secret'   => env('WA_GATEWAY_SECRET', 'P4mS1m4s-T1rt0-Arg0-2025'),
## Menjalankan Gateway

1. Pastikan server Laravel berjalan (mis. `php artisan serve` di port 8000, atau production web server).
2. Di terminal lain, masuk ke `wa-gateway/` dan jalankan:

```bash
cd wa-gateway
node gateway.js
```

3. Saat pertama kali, QR code akan muncul di terminal. Scan dengan WhatsApp > Tiga titik > paired device > Scan QR.
4. Setelah terhubung, server Node.js akan mendengarkan di port **3000** dan siap menerima outbox dari Laravel via `WA_GATEWAY_URL`.

## API & Webhook

### Endpoint Laravel yang menerima pesan masuk (Webhook)
- `POST /api/api_wa`
- `POST /webhook/wa`

Header wajib: `X-Pamsimas-Key: <WA_GATEWAY_SECRET>`

Payload untuk **teks**:
- `phone` (string) — JID utuh, contoh `6281234567890@s.whatsapp.net` atau `6281234567890@lid`
- `type`: `text`
- `text` (string)

Payload untuk **foto**:
- `phone` (string)
- `type`: `photo`
- `photo` (file) — gambar JPEG

### Endpoint Laravel untuk mengirim pesan keluar
`WhatsAppService::send($phone, $message)` akan melakukan HTTP POST ke `WA_GATEWAY_URL` dengan:
- Body JSON: `{"phone": "...", "message": "..."}`
- Header: `X-Pamsimas-Key: <WA_GATEWAY_SECRET>`

### Endpoint Gateway (Node.js) untuk outbox
- `POST http://127.0.0.1:3000/send-wa` (atau host/port yang dikonfigurasi)
- Body: `{"phone": "6281234567890@s.whatsapp.net", "message": "Halo"}`
- Header: `X-Pamsimas-Key` sesuai rahasia
## Testing / Verifikasi End-to-End

1. **Pastikan gateway berjalan dan terhubung WhatsApp**  
   Terminal gateway akan menampilkan pesan `GATEWAY PAMSIMAS AKTIF` dan QR code hanya pada 최초 pairing.

2. **Test kirim teks aktivasi dari nomor warga**  
   Kirim `AKTIVASI-PTA-XXXX` ke nomor WA gateway.

   Cek di Laravel:
   - Lihat `event_logs` → filter `WA Aktivasi` atau `WA Inbox`.
   - Cek `customers` → kolom `lid` terisi.
   - Warga akan mendapat balasan WhatsApp dari gateway.

3. **Test kirim foto meteran**  
   Kirim foto ke nomor WA gateway dari nomor yang sudah teraktivasi.

   Cek di Laravel:
   - File foto ada di `storage/app/meter_photos/Y/m/`.
   - Record baru di `customer_validations` dengan `status = MENUNGGU`.

4. **Test kirim pesan dari backend**  
   Dari tinker/artisan atau controller yang memakai `WhatsAppService::send(...)`:

   ```bash
   php artisan tinker
   >>> \App\Services\WhatsAppService::send('6281234567890@s.whatsapp.net', 'Tes dari PAMSIMAS');
   ```

   Cek di terminal gateway: log `Outbox Sukses kirim`.

5. **Cek log untuk troubleshooting**  
   - Laravel: `storage/logs/laravel.log`
   - Gateway: output terminal Node.js

## Troubleshooting

- **Gateway gagal mengirim ke Laravel / Laravel gagal memproses**  
  - Pastikan `WA_GATEWAY_SECRET` di `.env` Laravel sama dengan yang diteruskan gateway dalam header `X-Pamsimas-Key`.
  - Pastikan `WA_WEBHOOK_URL` mengarah ke URL yang bisa diakses dari mesin tempat gateway berjalan.
  - Cek apakah Laravel server menjawab dengan 401 — biasanya karena mismatch secret.

- **Foto tidak sampai / error timeout**  
  - Pastikan `storage/app/meter_photos` writable.
  - Gateway menggunakan timeout 45 detik saat mengirim foto. Jika proses OCR/validasi berat, pertimbangkan untuk menggunakan antrean (queue) di masa depan.

- **QR code tidak muncul**  
  - Jalankan di environment dengan terminal yang mendukung ANSI color dan QR rendering.
  - Pastikan versi Node.js cukup baru.

- **Ganti format JID**  
  - Gateway mengirim `phone` dengan format yang diterima Baileys: bisa `@s.whatsapp.net` atau `@lid`. Backend harus bisa menerima kedua format (sudah didukung di `WhatsAppWebhookController` dan `Customer.lid`).

## Catatan Deployment

- Di production, jalankan gateway sebagai layanan terpisah (systemd/PM2/etc) agar survive reconnect.
- Gunakan HTTPS untuk webhook jika gateway mengakses Laravel dari luar localhost.
- Pastikan `WA_GATEWAY_SECRET` adalah rahasia yang kuat dan tidak disebarkan.

## Referensi

- `wa-gateway/gateway.js` — implementasi gateway Node.js
- `app/Http/Controllers/WhatsAppWebhookController.php` — handler webhook Laravel
- `app/Services/WhatsAppService.php` — layanan pengiriman pesan keluar
- `config/services.php` dan `.env` — konfigurasi secret & URL


'wa_webhook_url'      => env('WA_WEBHOOK_URL', 'http://127.0.0.1:8000/api/api_wa'),
'wa_gateway_number'   => env('WA_GATEWAY_NUMBER'),
```

### 3. Konfigurasi Gateway (`wa-gateway/`)

Gateway membaca `WEBHOOK_URL` atau `WA_WEBHOOK_URL` dari lingkungan. Contoh menjalankan:

**Windows (PowerShell):**
```powershell
$env:WEBHOOK_URL = "http://127.0.0.1:8000/api/api_wa"
$env:WA_WEBHOOK_URL = "http://127.0.0.1:8000/api/api_wa"
$env:NOMOR_GATEWAY_STB = "6285157275866"
cd wa-gateway; node gateway.js
```

**Linux/macOS:**
```bash
export WEBHOOK_URL=http://127.0.0.1:8000/api/api_wa
export WA_WEBHOOK_URL=http://127.0.0.1:8000/api/api_wa
export NOMOR_GATEWAY_STB=6285157275866
cd wa-gateway && node gateway.js
```

Jika `WEBHOOK_URL` tidak diset, gateway akan fallback ke `http://127.0.0.1:8000/api/api_wa`.

### 4. Direktori foto meteran

Pastikan direktori berikut ada dan dapat ditulis:
- `storage/app/meter_photos/`

Jika menggunakan link storage publik:
```bash
php artisan storage:link
```

| Komponen | Lokasi | Peran |
|----------|--------|-------|
| Node.js WA Gateway | `wa-gateway/` | Menjalankan koneksi WhatsApp (Baileys), menerima inbox, meneruskan ke webhook PHP |
| Webhook Controller | `app/Http/Controllers/WhatsAppWebhookController.php` | Menerima & memproses pesan masuk dari gateway |
| Layanan WA | `app/Services/WhatsAppService.php` | Mengirim pesan keluar via HTTP ke gateway |
| Konfigurasi | `.env`, `config/services.php` | Rahasia, URL gateway, webhook URL |
