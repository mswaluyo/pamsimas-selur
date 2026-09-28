void fetchServerFingerprint() {
  if (WiFi.status() != WL_CONNECTED || fingerprintFetched) return;
  WiFiClientSecure client; client.setInsecure();
  HTTPClient http;
  char serverPath[128]; snprintf(serverPath, sizeof(serverPath), "https://%s/api/fingerprint", server_domain);
  if (http.begin(client, serverPath)) {
    http.addHeader("X-API-Key", api_key);
    int code = http.GET();
    if (code == 200) {
      String payload = http.getString(); payload.trim();
      if (payload.length() > 20 && payload.length() < sizeof(fingerprint)) { 
        strncpy(fingerprint, payload.c_str(), sizeof(fingerprint) - 1);
        fingerprint[sizeof(fingerprint) - 1] = '\0';
        fingerprintFetched = true; 
        Serial.printf("SECURITY: Fingerprint berhasil diambil: %s\n", fingerprint);
      }
      else Serial.println("SECURITY: Fingerprint dari server tidak valid, menggunakan mode insecure.");
    }
    else Serial.printf("SECURITY: Gagal mengambil fingerprint (HTTP %d), menggunakan mode insecure.\n", code);
    http.end();
  } else Serial.println("SECURITY: Tidak dapat terhubung ke server untuk mengambil fingerprint.");
}

void setupSecureClient(WiFiClientSecure& client) {
  // Batasi buffer SSL untuk menghemat RAM (Default 16KB + 16KB sering menyebabkan crash)
  client.setBufferSizes(2048, 1024); 
  client.setTimeout(5000); // Set timeout 5 detik agar tidak memicu WDT
  #if DEVELOPMENT_MODE == 1
    client.setInsecure();
    Serial.println("SECURITY: DEVELOPMENT_MODE aktif, menggunakan mode insecure.");
  #else
    if (fingerprintFetched) {
      client.setFingerprint(fingerprint);
      Serial.println("SECURITY: Menggunakan fingerprint SSL dari server.");
    } else {
      client.setInsecure();
      Serial.println("SECURITY: WARNING: Fingerprint belum tersedia, menggunakan mode tidak aman.");
    }
  #endif
}

void connectToWiFi(bool isInitialBoot) {
  if (isInitialBoot) Serial.println("\nNETWORK: [BOOT] Mencoba menghubungkan ke WiFi untuk pertama kali...");
  else Serial.println("\nNETWORK: [RECONNECT] Mencoba menyambungkan ulang ke WiFi...");
  WiFi.disconnect(); WiFi.mode(WIFI_STA); WiFi.begin(ssid, pass);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 10000) { delay(500); Serial.print("."); }
  if (WiFi.status() == WL_CONNECTED) { Serial.println("\nNETWORK: WiFi Terhubung!"); Serial.print("NETWORK: Alamat IP: "); Serial.println(WiFi.localIP()); digitalWrite(wifiLed, LOW); }
  else Serial.println("\nNETWORK: Gagal terhubung ke WiFi.");
}

void syncTime() {
  if (WiFi.status() != WL_CONNECTED) return;
  Serial.println("NTP: Menyinkronkan waktu dari server NTP...");
  configTime(timeZone, 0, "pool.ntp.org", "time.nist.gov");
  time_t now = time(nullptr);
  unsigned long start = millis();
  // Beri timeout 15 detik agar tidak stuck selamanya jika NTP gagal
  while (now < 1510644967 && millis() - start < 15000) { 
    delay(500); 
    now = time(nullptr); 
    yield(); // Beri waktu untuk proses background ESP
  }
  timeSynchronized = true;
  Serial.printf("NTP: Waktu berhasil disinkronkan: %s", ctime(&now));
}

void performOTA() {
  if (WiFi.status() != WL_CONNECTED) return;
  Serial.println("OTA: Memulai proses download dan update firmware...");
  WiFiClientSecure client; setupSecureClient(client); client.setTimeout(10000);
  String url = "https://" + String(server_domain) + String(api_firmware_endpoint) + "?key=" + String(api_key);
  t_httpUpdate_return ret = ESPhttpUpdate.update(client, url);
  switch (ret) {
    case HTTP_UPDATE_FAILED: Serial.printf("\nOTA Gagal: Error (%d): %s\n", ESPhttpUpdate.getLastError(), ESPhttpUpdate.getLastErrorString().c_str()); break;
    case HTTP_UPDATE_NO_UPDATES: Serial.println("\nOTA: Tidak ada update."); break;
    case HTTP_UPDATE_OK: Serial.println("\nOTA: Update berhasil! Merestart..."); ESP.restart(); break;
  }
}

void sendHealthStatus() {
  if (WiFi.status() != WL_CONNECTED) return;
  Serial.println("HEALTH: Mengirim status kesehatan ke server...");
  WiFiClientSecure client; setupSecureClient(client);
  HTTPClient http;
  char path[128]; snprintf(path, sizeof(path), "https://%s%s", server_domain, api_health_endpoint);
  if (http.begin(client, path)) {
    http.addHeader("Content-Type", "application/json"); http.addHeader("X-API-Key", api_key);
    StaticJsonDocument<256> doc;
    doc["mac"] = deviceMacAddress; doc["uptime"] = millis();
    doc["free_heap"] = ESP.getFreeHeap(); doc["rssi"] = WiFi.RSSI();
    doc["reset_reason"] = ESP.getResetReason();

    // Deteksi Status LittleFS
    FSInfo fs_info;
    if (LittleFS.info(fs_info)) {
      doc["fs_used"] = fs_info.usedBytes;
      doc["fs_total"] = fs_info.totalBytes;
    } else {
      doc["fs_error"] = true;
    }

    String body; serializeJson(doc, body);
    int code = http.POST(body); // Tambahkan log untuk kode respons
    if (code == 200) Serial.printf("HEALTH: Status terkirim. Heap: %d, Uptime: %lu ms\n", ESP.getFreeHeap(), millis());
    else if (code == -1) Serial.println("HEALTH: WARNING: SSL Handshake failed (Health)");
    else Serial.printf("HEALTH: Gagal kirim status (HTTP %d)\n", code);
    http.end();
  }
}