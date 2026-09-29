// Satu pembacaan ultrasonik dianggap valid hanya bila berada di rentang ini.
// Nilai 0 berarti pulseIn timeout (TIDAK ADA GEMA), bukan jarak 0 cm.
bool sensorReadingInBound(float d) { return d > 2.0 && d < 500.0; }

float calculateMovingAverage() {
  float sum = 0;
  int count = bufferFilled ? SMOOTHING_WINDOW_SIZE : bufferIndex;
  if (count == 0)
    return 0;
  for (int i = 0; i < count; i++)
    sum += distanceReadingsBuffer[i];
  return sum / count;
}

void measureAndSendData() {
  // Menggunakan mode eksplisit dari konfigurasi
  if (config.device_mode == 0) {
    Serial.println("SENSOR: Mode Actuator, melewati pembacaan sensor fisik.");
    return;
  }

  // REFERENSI LOGIKA DARI Pamsimas_esp8266.ino: Test reading cepat untuk
  // Fail-safe
  bool sensorError = false;
  float testDist = 0.0;
  for (int test = 0; test < 3; test++) {
    digitalWrite(TRIGPIN, LOW);
    delayMicroseconds(5);
    digitalWrite(TRIGPIN, HIGH);
    delayMicroseconds(15);
    digitalWrite(TRIGPIN, LOW);
    unsigned long duration = pulseIn(ECHOPIN, HIGH, 30000);
    testDist = (duration / 2.0) * 0.0343;
    if (duration == 0 || !sensorReadingInBound(testDist)) {
      sensorError = true;
      break;
    }
    delay(50);
  }

  if (sensorError) {
    sensorFaultStreak++;
    // KEBIJAKAN KEDAISAN AIR (29 Sep 2026): jarak ultrasonik terbatas 3 m, jadi
    // "tidak ada gema" berarti permukaan air di bawah jangkauan = tangki butuh
    // air. Dalam mode AUTO pompa diralat NYALA dan pengisian TIDAK dibatasi
    // jumlah siklus (pemasangan sensor sudah terjaga: permukaan tidak akan
    // merendam sensor dan bak punya peluap). Proteksi mesin tetap jalan:
    // safety cut-off (on_duration) + masa istirahat (off_duration) sehingga
    // polanya nyala - istirahat - nyala lagi sampai sensor membaca kembali.
    // Mode MANUAL/TIMED: relay tidak disentuh (keputusan operator/timer menang).
    bool inAuto = (strcmp(currMode, "AUTO") == 0);

    // Tanpa pembacaan, level dianggap 0 % (butuh air) agar logika AUTO
    // mempertahankan pompa, bukan 100 % yang justru menghentikan pengisian.
    if (inAuto)
      waterLevelPer = 0;

    // Anti-spam: laporan penuh saat MASUK episode fault, lalu penanda "masih
    // buta" maks 1x per SENSOR_FAULT_REPORT_INTERVAL_MS.
    bool announce = !sensorFaultActive;
    bool heartbeat = sensorFaultActive &&
                     (millis() - sensorFaultLastReport >=
                      SENSOR_FAULT_REPORT_INTERVAL_MS);

    Serial.printf("SENSOR: tidak ada gema (%.2f cm), siklus gagal ke-%d. ",
                  testDist, sensorFaultStreak);

    if (announce || heartbeat) {
      const char *newStatus = nullptr;
      sensorFaultActive = true;
      sensorFaultLastReport = millis();

      if (inAuto) {
        if (!relayStatus && !isCoolingDown) {
          relayStatus = true;
          pumpStartTime = millis();
          handlePumpStateChange(); // tulis pin + reset timestamp pumpStartTime
          controlBuzzer(500);
          newStatus = "ON";
          Serial.println("pompa NYALA (fail-safe ketersediaan air, isi tanpa "
                         "batas siklus).");
        } else {
          Serial.println(relayStatus
                             ? "pompa sudah menyala, dipertahankan."
                             : "masa istirahat mesin; pengisian lanjut pada "
                               "siklus berikutnya.");
        }
      } else {
        Serial.println("relay tidak diubah (mode non-AUTO).");
      }

      char eventMsg[112];
      snprintf(eventMsg, sizeof(eventMsg),
               "Sensor tidak terbaca (%d siklus, isi buta ke-%d) - pompa %s%s",
               sensorFaultStreak, sensorBlindFillCycles,
               relayStatus ? "ON" : "OFF", inAuto ? "" : " [mode non-AUTO]");
      // Offline log hanya saat jaringan putus; saat online dikirim langsung agar
      // tidak terduplikasi ketika /event_log.txt di-flush setelah reconnect.
      if (WiFi.status() != WL_CONNECTED) {
        logEventOffline(eventMsg);
      } else {
        sendControlCommand("report_event", eventMsg);
        if (newStatus != nullptr)
          sendControlCommand("set_status", newStatus);
      }
      sendSensorData(-1.0, testDist); // tertahan di LittleFS bila jaringan putus
    } else {
      Serial.println("tidak dilaporkan lagi (episode fault sudah tercatat).");
    }
    return;
  }

  // Bacaan valid lagi: tutup episode fault dan catat pemulihannya 1x.
  // Setelah ini level & kendali pompa kembali penuh ke logika AUTO/TIMED.
  if (sensorFaultActive) {
    Serial.printf("SENSOR: Pulih setelah %d siklus gagal (%d siklus isi buta).\n",
                  sensorFaultStreak, sensorBlindFillCycles);
    char eventMsg[112];
    snprintf(eventMsg, sizeof(eventMsg),
             "Sensor Pulih: normal kembali (%d siklus gagal, %d siklus isi buta)",
             sensorFaultStreak, sensorBlindFillCycles);
    if (WiFi.status() != WL_CONNECTED)
      logEventOffline(eventMsg);
    else
      sendControlCommand("report_event", eventMsg);
    sensorFaultActive = false;
    sensorBlindFillCycles = 0;
  }
  sensorFaultStreak = 0;

  float totalDist = 0;
  int valid = 0;
  for (int i = 0; i < 8; i++) {
    digitalWrite(TRIGPIN, LOW);
    delayMicroseconds(5);
    digitalWrite(TRIGPIN, HIGH);
    delayMicroseconds(15);
    digitalWrite(TRIGPIN, LOW);
    float d = pulseIn(ECHOPIN, HIGH, 30000);
    float single = (d / 2.0) * 0.0343;
    if (sensorReadingInBound(single)) {
      totalDist += single;
      valid++;
    }
    delay(50);
  }

  distanceReadingsBuffer[bufferIndex] =
      (valid > 0) ? (totalDist / valid) : currentDistance;
  bufferIndex = (bufferIndex + 1) % SMOOTHING_WINDOW_SIZE;
  if (bufferIndex == 0)
    bufferFilled = true;
  currentDistance = calculateMovingAverage();
  waterLevelPer = map((int)currentDistance, config.empty_tank_distance,
                      config.full_tank_distance, 0, 100);
  waterLevelPer = constrain(waterLevelPer, 0, 100);
  Serial.printf("SENSOR: Jarak Final: %.2f cm, Level: %d %%, RSSI: %d dBm\n",
                currentDistance, waterLevelPer,
                (WiFi.status() == WL_CONNECTED) ? WiFi.RSSI() : 0);
  sendSensorData(waterLevelPer, currentDistance);
}

void runUniversalPumpLogic() {
  unsigned long currentMillis = millis();

  // --- 1. GLOBAL SAFETY CUT-OFF ---
  if (relayStatus) {
    if (currentMillis - pumpStartTime >= (unsigned long)pumpOnDuration) {
      Serial.printf("SAFETY: Durasi Maksimum (%ld menit) tercapai. Force OFF!\n", pumpOnDuration/60000);
      
      relayStatus = false;
      isCoolingDown = true;
      coolDownStartTime = currentMillis;
      
      if (sensorFaultActive) {
        // Pengisian tanpa ukuran level ("buta") lanjut terus sampai sensor membaca
        // lagi; yang melindungi mesin adalah masa istirahat (off_duration) di bawah.
        sensorBlindFillCycles++;
        Serial.printf("SAFETY: Siklus isi buta ke-%d selama sensor tidak terbaca.\n",
                      sensorBlindFillCycles);
      }
      if (waterLevelPer < 95)
        isResumingFill = true;

      if (WiFi.status() == WL_CONNECTED) {
        sendControlCommand("set_status", "OFF");
        sendControlCommand("report_event", "Safety Cut-off: Durasi Maksimal");
      }
      
      handlePumpStateChange();
      return; 
    }
  }

  // --- 2. LOGIKA MASA ISTIRAHAT ---
  if (isCoolingDown) {
    if (currentMillis - coolDownStartTime >= (unsigned long)pumpOffDuration) {
      isCoolingDown = false;
      Serial.println("SAFETY: Masa istirahat mesin selesai. Siap beroperasi kembali.");
    } else {
      return; // Jangan jalankan logika apapun selama masa istirahat
    }
  }

  if (strcmp(currMode, "AUTO") == 0) {
    // LOGIKA BARU: ACTUATOR OTONOM SAAT OFFLINE
    if (config.device_mode == 0) { // Jika ini adalah Actuator
      if (!wasConnected) {         // Dan sedang offline
        // Jalankan pompa berdasarkan timer on_duration dan off_duration
        // Abaikan waterLevelPer dari server karena tidak ada
        if (relayStatus && (millis() - pumpStartTime >= pumpOnDuration)) {
          Serial.println("LOGIKA: [ACTUATOR OFFLINE] Durasi nyala selesai, "
                         "mematikan pompa.");
          relayStatus = false;
          handlePumpStateChange();
          sendControlCommand("report_event",
                             "Pompa Mati: Durasi Maksimum Tercapai (Offline)");
          // sendControlCommand("set_status", "OFF"); // Tidak bisa kirim ke
          // server saat offline
          isCoolingDown = true;
          coolDownStartTime = millis();
          controlBuzzer(1000);
        } else if (!relayStatus &&
                   (millis() - coolDownStartTime >= pumpOffDuration ||
                    !isCoolingDown)) {
          // Jika tidak sedang cooling down atau durasi istirahat selesai,
          // nyalakan lagi
          Serial.println("LOGIKA: [ACTUATOR OFFLINE] Durasi istirahat selesai, "
                         "menyalakan pompa.");
          relayStatus = true;
          pumpStartTime = millis();
          handlePumpStateChange();
          sendControlCommand("report_event",
                             "Pompa Nyala: Durasi Istirahat Selesai (Offline)");
          // sendControlCommand("set_status", "ON"); // Tidak bisa kirim ke
          // server saat offline
          isCoolingDown = false;
          controlBuzzer(200);
        }
        return; // Keluar dari logika AUTO berbasis level air jika Actuator
                // offline
      }
    }

    // LOGIKA AUTO BERBASIS LEVEL AIR (Hanya untuk Monitor atau Actuator Online)
    // ... (Logika yang sudah ada di bawah ini tetap sama) ...
    if (isResumingFill && !relayStatus) {
      Serial.println(
          "LOGIKA: [AUTO] Melanjutkan pengisian setelah istirahat...");
      relayStatus = true;
      pumpStartTime = millis();
      sendControlCommand("set_status", "ON");
      isResumingFill = false;
      controlBuzzer(200);
    }
    // FIX: Gunakan <= sesuai referensi logika yang Anda inginkan agar pemicu
    // akurat
    else if (waterLevelPer <= config.trigger_percentage && !relayStatus) {
      Serial.println("LOGIKA: [AUTO] Level air rendah, menyalakan pompa.");
      relayStatus = true;
      pumpStartTime = millis();
      sendControlCommand("set_status", "ON");
      controlBuzzer(500);
    } else if (waterLevelPer >= 98 && relayStatus) {
      long minRunTimeMs = (long)config.min_run_time * 1000;
      if (millis() - pumpStartTime < minRunTimeMs) {
        Serial.printf("LOGIKA: [AUTO] Sensor penuh (>=98%%), tapi diabaikan "
                      "(Minimum Run Time %d detik belum tercapai).\n",
                      config.min_run_time);
        isDebouncing = false;
        return;
      }
      if (!isDebouncing) {
        isDebouncing = true;
        sensorDebounceStartTime = millis();
        Serial.println(
            "LOGIKA: [AUTO] Terdeteksi penuh, memverifikasi gelombang...");
      } else if (millis() - sensorDebounceStartTime >
                 (sensorDebounceDelay * 1000)) {
        Serial.println(
            "LOGIKA: [AUTO] Tangki penuh (Stabil), mematikan pompa.");
        relayStatus = false;
        handlePumpStateChange();
        char eventMsg[64];
        snprintf(eventMsg, sizeof(eventMsg),
                 "Pompa Mati: Tangki Penuh (Auto) - Terdeteksi %d%%",
                 waterLevelPer);
        sendControlCommand("report_event", eventMsg);
        sendControlCommand("set_status", "OFF");
        isCoolingDown = true;
        coolDownStartTime = millis();
        controlBuzzer(500);
      }
    }
  } else if (strcmp(currMode, "TIMED") == 0) {
    if (relayStatus && millis() - pumpStartTime >= pumpOnDuration) {
      Serial.println("LOGIKA: [TIMED] Durasi nyala selesai, mematikan pompa.");
      relayStatus = false;
      sendControlCommand("set_status", "OFF");
    } else if (!relayStatus && millis() - pumpStartTime >= pumpOffDuration) {
      Serial.println("LOGIKA: [TIMED] Durasi mati selesai, menyalakan pompa.");
      relayStatus = true;
      pumpStartTime = millis();
      sendControlCommand("set_status", "ON");
    }
  }
  handlePumpStateChange();
}

void handlePumpStateChange() {
  if (relayStatus != lastRelayStatus) {
    digitalWrite(RelayPin, relayStatus ? HIGH : LOW);
    if (relayStatus) {
      Serial.println("RELAY: Pin relay diaktifkan (HIGH).");
      Serial.printf("  -> DEBUG: Mode = %s, Status Pompa = ON\n", currMode);
    } else {
      Serial.println("RELAY: Pin relay dinonaktifkan (LOW).");
      Serial.printf("  -> DEBUG: Mode = %s, Status Pompa = OFF\n", currMode);
    }
    if (relayStatus)
      pumpStartTime = millis();
    lastRelayStatus = relayStatus;
  }
}