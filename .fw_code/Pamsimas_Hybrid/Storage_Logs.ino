bool loadConfigFromEEPROM() {
  Serial.println("EEPROM: Mencoba memuat konfigurasi dari EEPROM...");

  isRegistered = (EEPROM.read(EEPROM_ADDR_IS_REGISTERED) == 1);
  EEPROM.get(EEPROM_ADDR_DEVICE_CONFIG, config);

  // Hanya berikan default jika nilai benar-benar rusak (negatif)
  if (config.trigger_percentage < 0 || config.trigger_percentage > 100)
    config.trigger_percentage = 80;
  
  if (config.empty_tank_distance < 0)
    config.empty_tank_distance = 0;
  if (config.full_tank_distance < 0)
    config.full_tank_distance = 0;
  if (config.min_run_time < 0)
    config.min_run_time = 60;

  // Pastikan pengali adalah 1000 karena server mengirim dalam satuan DETIK
  pumpOnDuration = (long)(config.on_duration > 0 ? config.on_duration : 1800) * 1000;
  pumpOffDuration = (long)(config.off_duration > 0 ? config.off_duration : 900) * 1000;

  Serial.println("EEPROM: Konfigurasi dimuat:");
  Serial.printf("  - Mode Alat: %s\n", config.device_mode == 0 ? "ACTUATOR" : "MONITOR");
  Serial.printf("  - Timer Internal: %ld ms ON / %ld ms OFF\n", pumpOnDuration, pumpOffDuration);
  Serial.printf("  - Jarak Penuh: %d cm\n", config.full_tank_distance);
  Serial.printf("  - Jarak Kosong: %d cm\n", config.empty_tank_distance);
  Serial.printf("  - Ambang Batas: %d %%\n", config.trigger_percentage);
  return true;
}

void logDataOffline(float percentage, float cm, int rssi) {
  if (!timeSynchronized)
    return;
  File f = LittleFS.open("/sensor_log.txt", "a");
  if (f) {
    f.printf("%lu,%.0f,%.2f,%d\n", time(nullptr), percentage, cm, rssi);
    f.close();
  }
}

void logPumpStatusOffline(bool status) {
  if (!timeSynchronized)
    return;
  File f = LittleFS.open("/pump_log.txt", "a");
  if (f) {
    f.printf("%lu,%d\n", time(nullptr), status ? 1 : 0);
    f.close();
  }
}

void logEventOffline(const char *eventName) {
  if (!timeSynchronized)
    return;
  File f = LittleFS.open("/event_log.txt", "a");
  if (f) {
    f.printf("%lu,%s\n", time(nullptr), eventName);
    f.close();
  }
}