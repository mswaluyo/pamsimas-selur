# Firmware Pamsimas_Hybrid (ESP8266 + Sensor/Pompa)

Source Arduino (6 file `.ino`) perangkat IoT PAMSIMAS Selur. Endpoint server yang dipakai:

| Endpoint | Fungsi di firmware |
|---|---|
| `GET /api/fingerprint` | handshake awal: **plain text SHA1** sertifikat edge (59 karakter `AB:CD:…`) untuk `client.setFingerprint()` |
| `GET /api/status?mac_address=<MAC>` | sinkronisasi mode/level air tiap siklus |
| `POST /api/log` | kirim log sensor & status pompa (X-API-Key) |
| `POST /api/log-offline` | unggah log yang tertahan saat offline (LittleFS) |
| `POST /api/update` | ambil perintah mode/ON-OFF dari server |
| `POST /api/health` | telemetri uptime, free heap, RSSI, reset reason |
| `GET /api/firmware?key=<API_KEY>` | cek/unduh firmware baru |

## Pinning SSL (perilaku sekarang)

- Server membalas **SHA1 sertifikat edge/CDN yang benar-benar dihubungi perangkat** dalam bentuk
  `AB:CD:…` (59 karakter, plain text) → firmware menyimpannya di `char fingerprint[60]` dan memakai
  `client.setFingerprint()`. Respons JSON (mis. dari `?format=json`) **akan ditolak** firmware
  karena panjangnya > 59 karakter.
- Respons **non-200** (mis. 503 saat probe gagal atau `FINGERPRINT_DISABLED=true`) membuat firmware
  memakai mode insecure (tanpa pinning) — perangkat tetap bisa mengirim data.
- Fingerprint diambil **sekali per boot** (`fetchServerFingerprint()` pada `setup()`); bila sertifikat
  edge berputar (rotasi Cloudflare), reboot perangkat agar mengambil nilai baru. Cek nilai terkini:
  `curl -s https://pamsimas.selur.my.id/api/fingerprint?format=json`.
- Usulan perbaikan firmware berikutnya: ambil ulang fingerprint saat handshake gagal, atau ganti ke
  validasi berbasis CA + hostname (`setTrustAnchors`) supaya tidak perlu reboot manual.

## Konfigurasi (WAJIB diisi sebelum build)

`Pamsimas_Hybrid.ino` sengaja **tidak memuat kredensial asli** (repo ini publik):

```cpp
const char *ssid = "GANTI_SSID_WIFI";
const char *pass = "GANTI_SANDI_WIFI";
const char *api_key = "...";  // = DEVICE_API_KEY di .env Laravel
```

Isi nilai sebenarnya lokal saja (jangan di-commit), lalu sesuaikan
`server_domain` bila bukan `pamsimas.selur.my.id`.
