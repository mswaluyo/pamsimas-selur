# PAMSIMAS - Google Apps Script IoT Collector
# ============================================
# Solusi 100% Gratis untuk Monitoring IoT Tanpa Server
# ============================================

## Cara Deploy (10 Menit)

### 1. Buat Google Spreadsheet
1. Buka https://sheets.google.com
2. Buat spreadsheet baru: "PAMSIMAS Data"
3. Copy **Spreadsheet ID** dari URL:
   `https://docs.google.com/spreadsheets/d/[SPREADSHEET_ID]/edit`

### 2. Deploy Google Apps Script
1. Buka https://script.google.com
2. **New Project**
3. Hapus kode default, paste isi file `Code.gs`
4. Ganti `SPREADSHEET_ID` dengan ID spreadsheet Anda
5. Ganti `API_KEY` dengan key rahasia Anda
6. Klik **Save** (floppy disk icon)
7. Klik **Deploy** > **New Deployment**
8. Type: **Web App**
9. Execute as: **Me**
10. Who has access: **Anyone**
11. Klik **Deploy**
12. **Copy URL** yang diberikan

### 3. Test
```powershell
# Test dengan curl
curl -X GET "https://script.google.com/macros/s/AKfycb.../exec?action=status"

# Test kirim data
curl -X POST "https://script.google.com/macros/s/AKfycb.../exec" ^
  -H "Content-Type: application/json" ^
  -d "{\"api_key\":\"pamsimas-secret-key-123\",\"device_id\":\"TEST_01\",\"sensor_type\":\"debit\",\"value\":25.5,\"unit\":\"L/menit\"}"
```

### 4. Setup ESP8266/ESP32
1. Buka Arduino IDE
2. Install library: **ArduinoJson**
3. Copy isi `esp8266-example.ino`
4. Sesuaikan:
   - `WIFI_SSID` dan `WIFI_PASSWORD`
   - `APPS_SCRIPT_URL` (URL dari langkah 2)
   - `DEVICE_ID` (unik untuk setiap station)
5. Upload ke ESP8266/ESP32

## Arsitektur Sistem

```
[ESP8266/ESP32] → HTTP POST → [Google Apps Script] → [Google Sheets]
     ↓                                                        ↓
  Sensor data                                            Database
  (debit, suhu)                                          (Gratis unlimited)
```

## Kelebihan
- ✅ 100% Gratis selamanya
- ✅ Tanpa kartu kredit
- ✅ Always On (server Google)
- ✅ Database unlimited (Google Sheets 10 juta sel)
- ✅ Bisa kirim email alert
- ✅ Bisa buat dashboard dengan Google Sites/Data Studio

## Keterbatangan
- ❌ Tidak bisa Python/OCR (butuh solusi terpisah untuk OCR)
- ❌ Execution time limit 6 menit
- ❌ Rate limit: 100.000 request/hari (cukup untuk IoT)

## Tips
- Setiap device punya `DEVICE_ID` unik
- Data tersimpan otomatis di Google Sheets
- Bisa export CSV kapan saja
- Bisa buat chart/dashboard langsung dari Sheets
- Untuk notifikasi WA, bisa integrasi dengan WhatsApp Gateway yang sudah ada

## Contoh Data di Spreadsheet

| Timestamp | Device ID | Sensor Type | Value | Unit | Battery | Signal |
|-----------|-----------|-------------|-------|------|---------|--------|
| 2024/01/15 10:30 | STATION_01 | debit_air | 25.5 | L/menit | 3.7 | -45 |
| 2024/01/15 10:35 | STATION_01 | debit_air | 27.2 | L/menit | 3.7 | -50 |
| 2024/01/15 10:30 | STATION_02 | debit_air | 18.3 | L/menit | 3.9 | -42 |
