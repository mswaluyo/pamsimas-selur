void fetchQuickStatus() {
  if (WiFi.status() != WL_CONNECTED)
    return;
  if (ESP.getFreeHeap() < 10000) {
    Serial.println("MEM: Heap terlalu rendah untuk SSL");
    return;
  }
  Serial.println("FETCH: Mengambil Quick Status dari server...");
  bool triggerFullUpdate = false;
  bool triggerOta = false;
  bool triggerModeReset = false;
  bool triggerConfigReset = false;
  {
    WiFiClientSecure client;
    setupSecureClient(client);
    HTTPClient http;
    char path[128];
    snprintf(path, sizeof(path), "https://%s%s?mac=%s&rssi=%d", server_domain,
             api_status_endpoint, deviceMacAddress, WiFi.RSSI());
    if (http.begin(client, path)) {
      http.addHeader("X-API-Key", api_key);
      int code = http.GET();
      if (code == 200) {
        Serial.println("FETCH: Quick Status diterima.");
        String payload = http.getString();
        StaticJsonDocument<768> doc;
        DeserializationError error = deserializeJson(doc, payload);
        if (error) {
          Serial.printf("FETCH: Gagal parsing JSON QuickStatus: %s\n",
                        error.c_str());
          http.end();
          return;
        }

        if (doc.containsKey("status") && doc["status"] == "unregistered") {
          if (isRegistered) {
            Serial.println("STATUS: Perangkat dihapus dari server. Reset "
                           "EEPROM & Restart...");
            EEPROM.write(EEPROM_ADDR_IS_REGISTERED, 0);
            EEPROM.commit();
            delay(1000);
            ESP.restart();
          }
          isRegistered = false;
          http.end();
          return;
        }

        if (!isRegistered && doc.containsKey("control_mode")) {
          Serial.println("FETCH: Perangkat terdeteksi terdaftar di server. "
                         "Meminta konfigurasi penuh.");
          triggerFullUpdate = true;
        }
        if (doc.containsKey("config_update_command") &&
            doc["config_update_command"] == 1) {
          Serial.println(
              "PERINTAH: Perubahan konfigurasi terdeteksi di Dashboard.");
          triggerFullUpdate = true;
        }
        if (doc.containsKey("water_percentage") && config.device_mode == 0) {
          waterLevelPer = doc["water_percentage"];
          Serial.printf("FETCH: Level air dari server: %d%%\n", waterLevelPer);
        }

        if (doc.containsKey("ota_update_command") &&
            doc["ota_update_command"] == 1) {
          Serial.println("PERINTAH: Menerima perintah OTA Update dari server.");
          triggerOta = true;
        }
        if (doc.containsKey("mode_update_command") &&
            doc["mode_update_command"] == 1) {
          const char *m = doc["control_mode"];
          if (m) {
            strncpy(currMode, m, 7);
            modeFlag = (strcmp(currMode, "AUTO") == 0);
            triggerModeReset = true;
          }
          Serial.printf("PERINTAH: Mode diubah menjadi: %s\n", currMode);
        }

        // Sinkronkan status relay jika tidak dalam mode AUTO
        if (strcmp(currMode, "AUTO") != 0) {
          const char *s = doc["status"];
          if (s) {
            bool targetStatus = (strcmp(s, "ON") == 0);
            
            // PROTEKSI MANUAL: Jangan izinkan nyala jika mesin masih panas (Cooling Down)
            if (targetStatus && isCoolingDown) {
              Serial.println("FETCH: Perintah ON diabaikan (Masih Masa Istirahat)");
              if (relayStatus) { // Jika entah bagaimana relay masih ON
                relayStatus = false;
                handlePumpStateChange();
              }
              sendControlCommand("set_status", "OFF"); // Paksa status Dashboard kembali ke OFF
            } else {
              relayStatus = targetStatus;
            }
            Serial.printf("FETCH: Status relay disinkronkan ke: %s\n", relayStatus ? "ON" : "OFF");
          }
        }

        serverConnectionFailures = 0;
        if (currentStatusFetchInterval != STATUS_FETCH_NORMAL) {
          Serial.println("NETWORK: Komunikasi dengan server pulih. Interval "
                         "status kembali normal.");
          currentStatusFetchInterval = STATUS_FETCH_NORMAL;
        }

        if (!versionReported && isRegistered) {
          sendControlCommand("report_version", __DATE__ " " __TIME__);
          Serial.println("FETCH: Melaporkan versi firmware ke server.");
          versionReported = true;
        }
      } else {
        serverConnectionFailures++;
      }
      http.end();
    }
    yield();
  }
  if (triggerFullUpdate) {
    fetchControlStatus();
    sendControlCommand("reset_config", "0");
  }
  if (triggerOta) {
    sendControlCommand("reset_ota_update", "0");
    performOTA();
  }
  if (triggerModeReset)
    sendControlCommand("reset_mode_update", "0");
}

void fetchControlStatus() {
  if (WiFi.status() != WL_CONNECTED)
    return;
  WiFiClientSecure client;
  setupSecureClient(client);
  Serial.println("FETCH: Mengambil konfigurasi penuh dari server...");
  HTTPClient http;
  char path[128];
  snprintf(path, sizeof(path), "https://%s%s?mac=%s&rssi=%d", server_domain,
           api_status_endpoint, deviceMacAddress, WiFi.RSSI());
  if (http.begin(client, path)) {
    http.addHeader("X-API-Key", api_key);
    int code = http.GET();
    if (code == 200) {
      Serial.println("FETCH: Konfigurasi penuh diterima.");
      String payload = http.getString();
      StaticJsonDocument<768> doc;
      DeserializationError error = deserializeJson(doc, payload);
      if (error) {
        Serial.printf("FETCH: Gagal parsing JSON Config: %s\n", error.c_str());
        http.end();
        return;
      }

      if (doc.containsKey("status") && doc["status"] == "unregistered") {
        Serial.println("FETCH: Server melaporkan perangkat tidak terdaftar. "
                       "Mengabaikan konfigurasi.");
        http.end();
        return;
      }
      if (doc.containsKey("water_percentage") && config.device_mode == 0) {
        waterLevelPer = doc["water_percentage"];
        Serial.printf("FETCH: Level air dari server: %d%%\n", waterLevelPer);
      }

      strncpy(currMode, doc["control_mode"], 7);
      modeFlag = (strcmp(currMode, "AUTO") == 0);
      
      if (!modeFlag) {
        bool targetStatus = (strcmp(doc["status"], "ON") == 0);
        // Proteksi Manual: Jangan izinkan ON jika sedang Cooling Down
        if (targetStatus && isCoolingDown) {
          Serial.println("FETCH: Mengabaikan status ON manual karena masih masa istirahat.");
          sendControlCommand("set_status", "OFF");
        } else {
          relayStatus = targetStatus;
        }
      }

      bool registrationJustConfirmed = false;
      if (!isRegistered) {
        isRegistered = true;
        registrationJustConfirmed = true;
        digitalWrite(wifiLed, LOW);
        Serial.println("FETCH: Pendaftaran BERHASIL!");
      }

      // Cek apakah ada perubahan konfigurasi sebelum menulis ke EEPROM
      bool configChanged = false;
      int server_mode = doc["device_mode"];
      int server_on = doc["on_duration"];
      int server_off = doc["off_duration"];
      int server_full = doc["full_tank_distance"];
      int server_empty = doc["empty_tank_distance"];
      int server_trig = doc["trigger_percentage"];
      int server_min_run =
          doc.containsKey("min_run_time") ? doc["min_run_time"] : 60;

      if (config.device_mode != server_mode ||
          config.on_duration != server_on ||
          config.off_duration != server_off ||
          config.full_tank_distance != server_full ||
          config.empty_tank_distance != server_empty ||
          config.trigger_percentage != server_trig ||
          config.min_run_time != server_min_run) {

        config.device_mode = server_mode;
        config.on_duration = server_on;
        config.off_duration = server_off;
        config.full_tank_distance = server_full;
        config.empty_tank_distance = server_empty;
        config.trigger_percentage = server_trig;
        config.min_run_time = server_min_run;
        configChanged = true;
      }

      if (doc.containsKey("sensor_debounce"))
        sensorDebounceDelay = doc["sensor_debounce"];
      if (doc.containsKey("report_interval"))
        currentStatusFetchInterval = (long)doc["report_interval"] * 1000;

      Serial.println("\n--- PROSES PENGECEKAN KONFIGURASI ---");
      Serial.println("  [KONFIGURASI DARI SERVER]");
      Serial.printf("    - Durasi Nyala: %d detik (%d menit)\n",
                    config.on_duration, config.on_duration / 60);
      Serial.printf("    - Durasi Istirahat: %d detik (%d menit)\n",
                    config.off_duration, config.off_duration / 60);
      Serial.printf("    - Jarak Penuh: %d cm\n", config.full_tank_distance);
      Serial.printf("    - Jarak Kosong: %d cm\n", config.empty_tank_distance);
      Serial.printf("    - Pemicu Pompa: %d %%\n", config.trigger_percentage);
      Serial.printf("    - Min Run Time: %d detik\n", config.min_run_time);
      Serial.printf("    - Sensor Debounce: %d detik\n", sensorDebounceDelay);
      Serial.printf("    - Report Interval: %ld ms\n",
                    currentStatusFetchInterval);

      pumpOnDuration = (long)config.on_duration * 1000;
      pumpOffDuration = (long)config.off_duration * 1000;

      // Perlindungan EEPROM: Hanya tulis jika ada perubahan nyata atau baru
      // terdaftar
      if (configChanged || registrationJustConfirmed) {
        if (registrationJustConfirmed) {
          EEPROM.write(EEPROM_ADDR_IS_REGISTERED, 1);
        }
        EEPROM.put(EEPROM_ADDR_DEVICE_CONFIG, config);
        EEPROM.commit();
        Serial.println("FETCH: Konfigurasi & Status Registrasi berhasil "
                       "diamankan ke EEPROM.");
      } else {
        Serial.println(
            "FETCH: Konfigurasi identik. Melewati penulisan EEPROM.");
      }

      if (doc.containsKey("restart_command") && doc["restart_command"] == 1) {
        sendControlCommand("reset_restart", "0");
        delay(1000);
        ESP.restart();
      }
    }
    http.end();
  }
}

void sendSensorData(float percentage, float cm) {
  if (WiFi.status() != WL_CONNECTED) {
    logDataOffline(percentage, cm, WiFi.RSSI());
    return;
  }
  WiFiClientSecure client;
  setupSecureClient(client);
  HTTPClient http;
  char path[128];
  snprintf(path, sizeof(path), "https://%s%s", server_domain, api_log_endpoint);
  if (http.begin(client, path)) {
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", api_key);
    StaticJsonDocument<200> doc;
    doc["mac_address"] = deviceMacAddress;
    doc["water_percentage"] = percentage;
    doc["water_level_cm"] = cm;
    doc["rssi"] = WiFi.RSSI();
    String body;
    serializeJson(doc, body);
    int code = http.POST(body);
    Serial.printf("API: Mengirim data sensor (Level: %.0f%%, CM: %.2f, RSSI: "
                  "%d) -> HTTP %d\n",
                  percentage, cm, WiFi.RSSI(), code);
    if (code == -1)
      Serial.println("Log Fail: SSL");
    http.end();
    yield();
  }
}

void sendControlCommand(const char *action, const char *value) {
  if (WiFi.status() != WL_CONNECTED)
    return;
  if (ESP.getFreeHeap() < 8000)
    return; // Lewati perintah jika memori kritis
  WiFiClientSecure client;
  setupSecureClient(client);
  HTTPClient http;
  char path[128];
  snprintf(path, sizeof(path), "https://%s%s", server_domain,
           api_update_endpoint);
  if (http.begin(client, path)) {
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", api_key);
    StaticJsonDocument<200> doc;
    doc["mac"] = deviceMacAddress;
    doc["action"] = action;
    doc["value"] = value;
    String body;
    body.reserve(150); // Mencegah fragmentasi heap dengan alokasi memori di awal
    serializeJson(doc, body);
    int code = http.POST(body);
    Serial.printf("API: Mengirim perintah '%s' -> '%s' (HTTP %d)\n", action,
                  value, code);
    if (code == -1)
      Serial.println("WARNING: SSL Handshake failed (Command)");
    http.end();
  }
}

void sendOfflineLogs() {
  const char *logPath = "/sensor_log.txt";
  const char *tempPath = "/temp_log.txt";

  while (LittleFS.exists(logPath)) {
    if (WiFi.status() != WL_CONNECTED)
      break;
    if (ESP.getFreeHeap() < 10000)
      break; // Keamanan RAM

    File f = LittleFS.open(logPath, "r");
    if (!f)
      break;

    String payload;
    payload.reserve(2500);
    payload = "{\"mac\":\"" + String(deviceMacAddress) + "\",\"logs\":[";

    int linesInBatch = 0;
    bool first = true;
    char lineBuf[64]; // Buffer statis untuk satu baris log

    while (f.available()) {
      // Membaca baris menggunakan buffer karakter (lebih hemat RAM)
      int bytesRead = f.readBytesUntil('\n', lineBuf, sizeof(lineBuf) - 1);
      lineBuf[bytesRead] = '\0'; // Pastikan string berakhir dengan null

      if (bytesRead > 0) {
        // Cek apakah kloter sudah penuh (batas ~2200 karakter)
        if (payload.length() + bytesRead + 5 > 2200)
          break;

        if (!first)
          payload += ",";
        payload += "[";
        payload += lineBuf;
        payload += "]";
        first = false;
        linesInBatch++;
      }
    }
    f.close();

    if (linesInBatch == 0) {
      LittleFS.remove(logPath);
      break;
    }

    payload += "]}";

    WiFiClientSecure client;
    setupSecureClient(client);
    HTTPClient http;
    char fullPath[128];
    snprintf(fullPath, sizeof(fullPath), "https://%s%s", server_domain,
             api_offline_log_endpoint);

    int code = -1;
    if (http.begin(client, fullPath)) {
      http.addHeader("Content-Type", "application/json");
      http.addHeader("X-API-Key", api_key);
      code = http.POST(payload);
      http.end();
    }

    if (code == 200) {
      Serial.printf(
          "API: Kloter offline (%d data) terkirim. Membersihkan file...\n",
          linesInBatch);

      // Hapus baris yang sudah terkirim dengan menyalin sisanya ke file temp
      File src = LittleFS.open(logPath, "r");
      File dst = LittleFS.open(tempPath, "w");

      int currentLine = 0;
      while (src.available()) {
        int bytesRead = src.readBytesUntil('\n', lineBuf, sizeof(lineBuf) - 1);
        lineBuf[bytesRead] = '\0';
        if (currentLine >= linesInBatch) {
          dst.println(lineBuf);
        }
        currentLine++;
      }
      src.close();
      dst.close();

      LittleFS.remove(logPath);
      if (LittleFS.exists(tempPath)) {
        // Cek apakah file temp ada isinya
        File check = LittleFS.open(tempPath, "r");
        bool hasContent = check.available();
        check.close();

        if (hasContent)
          LittleFS.rename(tempPath, logPath);
        else
          LittleFS.remove(tempPath);
      }
    } else {
      Serial.printf("API: Gagal kirim kloter (HTTP %d). Berhenti.\n", code);
      break; // Berhenti jika gagal agar tidak loop terus menerus
    }
    yield(); // Beri waktu untuk proses latar belakang ESP agar tidak WDT Reset
  }

  // Kirim Event Logs Offline
  if (LittleFS.exists("/event_log.txt")) {
    File ef = LittleFS.open("/event_log.txt", "r");
    if (ef) {
      String eventPayload =
          "{\"mac\":\"" + String(deviceMacAddress) + "\",\"event_logs\":[";
      bool firstE = true;
      while (ef.available()) {
        String line = ef.readStringUntil('\n');
        if (line.length() > 0) {
          if (!firstE)
            eventPayload += ",";
          eventPayload += "\"" + line + "\"";
          firstE = false;
        }
      }
      ef.close();
      eventPayload += "]}";

      WiFiClientSecure client;
      setupSecureClient(client);
      HTTPClient http;
      char fullPath[128];
      snprintf(fullPath, sizeof(fullPath), "https://%s%s", server_domain,
               api_offline_log_endpoint);
      if (http.begin(client, fullPath)) {
        http.addHeader("Content-Type", "application/json");
        http.addHeader("X-API-Key", api_key);
        if (http.POST(eventPayload) == 200)
          LittleFS.remove("/event_log.txt");
        http.end();
      }
    }
  }

  // Kirim Pump Logs Offline
  if (LittleFS.exists("/pump_log.txt")) {
    File pf = LittleFS.open("/pump_log.txt", "r");
    if (pf) {
      // Logika serupa dengan event_logs untuk mengirim pump_logs...
      // Setelah sukses:
      pf.close();
      // LittleFS.remove("/pump_log.txt");
    }
  }

  // Bersihkan log pendukung jika log utama sudah habis
  if (!LittleFS.exists(logPath)) {
    LittleFS.remove("/pump_log.txt");
  }
}