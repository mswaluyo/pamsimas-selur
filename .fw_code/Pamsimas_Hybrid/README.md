# Firmware Pamsimas_Hybrid (ESP8266 + Sensor/Pompa)

Source Arduino (6 file `.ino`) perangkat IoT PAMSIMAS Selur. Endpoint server yang dipakai:

| Endpoint | Fungsi di firmware |
|---|---|
| `GET /api/fingerprint` | handshake awal (ambil fingerprint server) |
| `GET /api/status?mac_address=<MAC>` | sinkronisasi mode/level air tiap siklus |
| `POST /api/log` | kirim log sensor & status pompa (X-API-Key) |
| `POST /api/log-offline` | unggah log yang tertahan saat offline (LittleFS) |
| `POST /api/update` | ambil perintah mode/ON-OFF dari server |
| `POST /api/health` | telemetri uptime, free heap, RSSI, reset reason |
| `GET /api/firmware?key=<API_KEY>` | cek/unduh firmware baru |

## Konfigurasi (WAJIB diisi sebelum build)

`Pamsimas_Hybrid.ino` sengaja **tidak memuat kredensial asli** (repo ini publik):

```cpp
const char *ssid = "GANTI_SSID_WIFI";
const char *pass = "GANTI_SANDI_WIFI";
const char *api_key = "...";  // = DEVICE_API_KEY di .env Laravel
```

Isi nilai sebenarnya lokal saja (jangan di-commit), lalu sesuaikan
`server_domain` bila bukan `pamsimas.selur.my.id`.
