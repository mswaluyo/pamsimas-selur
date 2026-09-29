// --- File Utama: Pamsimas_Hybrid.ino ---

// --- KONFIGURASI KEAMANAN ---
#define DEVELOPMENT_MODE                                                       \
  0 // 0 = Production (Aman, Cek Fingerprint), 1 = Development (Tidak Aman, Skip
    // Validasi)

// --- Library yang Dibutuhkan ---
#include <AceButton.h>
#include <ArduinoJson.h>
#include <EEPROM.h>
#include <ESP8266HTTPClient.h>
#include <ESP8266WiFi.h>
#include <ESP8266httpUpdate.h>
#include <LittleFS.h>
#include <WiFiClientSecure.h>
#include <time.h>
using namespace ace_button;

// --- Konfigurasi Wi-Fi ---
// CATATAN: nilai asli sengaja TIDAK disimpan di repo publik ini.
// Isi ssid/pass sesuai jaringan setempat sebelum build (jangan di-commit).
const char *ssid = "GANTI_SSID_WIFI";
const char *pass = "GANTI_SANDI_WIFI";

// --- Konfigurasi Server API ---
const char *server_domain = "pamsimas.selur.my.id";
const int server_port = 443;
const char *api_key = "P4mS1m4s-T1rt0-Arg0-2025";
const char *api_log_endpoint = "/api/log";
const char *api_status_endpoint = "/api/status";
const char *api_offline_log_endpoint = "/api/log-offline";
const char *api_update_endpoint = "/api/update";
const char *api_firmware_endpoint = "/api/firmware";
const char *api_health_endpoint = "/api/health";

char fingerprint[60] = "";
bool fingerprintFetched = false;

// --- Konfigurasi Perangkat ---
char deviceMacAddress[18];
const char *deviceName = "ESP8266-Hybrid";

// --- Konfigurasi Sensor & Aktuator ---
const int TRIGPIN = D6;
const int ECHOPIN = D5;
const int wifiLed = D7;
const int BuzzerPin = D8;
const int RelayPin = D0;
const int ButtonPin1 = D3;
const int ButtonPin2 = D4;

struct DeviceConfig {
  int device_mode; // 0: ACTUATOR, 1: MONITOR
  int on_duration;
  int off_duration;
  int full_tank_distance;
  int empty_tank_distance;
  int trigger_percentage;
  int min_run_time;
};

#define EEPROM_ADDR_DEVICE_CONFIG 18
#define EEPROM_ADDR_IS_REGISTERED 50
#define EEPROM_SIZE 64

float currentDistance = 0.0;
int waterLevelPer = 0;
#define SMOOTHING_WINDOW_SIZE 5
float distanceReadingsBuffer[SMOOTHING_WINDOW_SIZE];
int bufferIndex = 0;
bool bufferFilled = false;

DeviceConfig config;
bool relayStatus = false;
bool lastRelayStatus = false;
bool modeFlag = true;
char currMode[8] = "AUTO";
bool isRegistered = false;
bool versionReported = false;
unsigned long pumpStartTime = 0;
long pumpOnDuration = 300000;
long pumpOffDuration = 900000;

unsigned long sensorDebounceStartTime = 0;
bool isDebouncing = false;
int sensorDebounceDelay = 5;
// --- Status episode fault sensor (kebijakan ketersediaan air) ---
// Jangkauan ultrasonik terbatas 3 m, jadi "tidak ada gema" berarti permukaan
// air berada DI BAWAH jangkauan = tangki sedang butuh air. Dalam mode AUTO
// pompa justru diralat NYALA dan pengisian TIDAK dibatasi jumlah siklus
// (keputusan 29 Sep 2026: pemasangan sensor sudah terjaga, permukaan tidak akan
// merendam sensor dan bak punya peluap). Yang tetap melindungi mesin adalah
// safety cut-off durasi nyala maksimum (config on_duration) + masa istirahat
// (off_duration): pompa nyala - istirahat - nyala lagi sampai sensor membaca
// kembali. Sensor dianggap pulih dengan sendirinya saat air naik ke dalam
// jangkauan 3 m.
bool sensorFaultActive = false;      // sedang dalam episode fault
int sensorFaultStreak = 0;           // siklus berturut-turut tanpa gema
int sensorBlindFillCycles = 0;       // jumlah siklus isi buta selama episode ini (informasi)
unsigned long sensorFaultLastReport = 0;
// Penanda "masih buta" dikirim maks 1x per interval ini; tanpa batas siklus,
// episode buta bisa berjam-jam sehingga interval pendek akan membanjiri event.
const unsigned long SENSOR_FAULT_REPORT_INTERVAL_MS = 900000; // 15 menit
bool buzzerActive = false;
unsigned long buzzerStartTime = 0;
unsigned long buzzerDuration = 0;
bool timeSynchronized = false;
long timeZone = 7 * 3600;
bool isCoolingDown = false;
bool wasConnected = true;
unsigned long coolDownStartTime = 0;
bool isResumingFill = false;
unsigned long fullTankOverrideStartTime = 0;
bool isFullTankOverrideActive = false;

unsigned long lastDataSendTime = 0;
unsigned long lastReconnectAttempt = 0;
unsigned long lastStatusFetchTime = 0;
unsigned long lastHealthSendTime = 0;
int serverConnectionFailures = 0;
const int MAX_SERVER_FAILURES = 10;
const long dataSendInterval = 3000;
const long STATUS_FETCH_NORMAL = 3000;
const long STATUS_FETCH_MAX = 60000;
long currentStatusFetchInterval = STATUS_FETCH_NORMAL;
const long healthSendInterval = 60000;
const long reconnectInterval = 10000;

ButtonConfig config1;
AceButton button1(&config1);
ButtonConfig config2;
AceButton button2(&config2);

// --- PROTOTIPE FUNGSI (PENTING UNTUK COMPILATION) ---
void setupSecureClient(WiFiClientSecure &client);
void fetchServerFingerprint();
bool loadConfigFromEEPROM();
void connectToWiFi(bool isInitialBoot);
void syncTime();
void fetchQuickStatus();
void fetchControlStatus();
void logDataOffline(float percentage, float cm, int rssi);
void logPumpStatusOffline(bool status);
void logEventOffline(const char *eventName);
void sendSensorData(float percentage, float cm);
void sendControlCommand(const char *action, const char *value);
void sendOfflineLogs();
void sendHealthStatus();
void performOTA();
void measureAndSendData();
void runUniversalPumpLogic();
void handlePumpStateChange();
void controlBuzzer(int duration);
void button1Handler(AceButton *button, uint8_t eventType, uint8_t buttonState);
void button2Handler(AceButton *button, uint8_t eventType, uint8_t buttonState);

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n\nDEVICE: Booting Hybrid Node...");
  EEPROM.begin(EEPROM_SIZE);

  if (!LittleFS.begin()) {
    Serial.println("FS: LittleFS Error/Corrupted! Mencoba format ulang...");
    if (LittleFS.format()) {
      Serial.println(
          "FS: Format berhasil. Sistem file siap digunakan kembali.");
      LittleFS.begin();
    } else {
      Serial.println("FS: Gagal total saat format LittleFS. Hardware flash "
                     "mungkin rusak.");
    }
  }

  loadConfigFromEEPROM();

  strcpy(currMode, "AUTO");
  modeFlag = true;
  strncpy(deviceMacAddress, WiFi.macAddress().c_str(),
          sizeof(deviceMacAddress));

  pinMode(ECHOPIN, INPUT);
  pinMode(TRIGPIN, OUTPUT);
  pinMode(wifiLed, OUTPUT);
  pinMode(RelayPin, OUTPUT);
  pinMode(BuzzerPin, OUTPUT);
  pinMode(ButtonPin1, INPUT_PULLUP);
  pinMode(ButtonPin2, INPUT_PULLUP);
  digitalWrite(wifiLed, HIGH);
  digitalWrite(RelayPin, LOW);

  config1.setEventHandler(button1Handler);
  config2.setEventHandler(button2Handler);
  button1.init(ButtonPin1);
  button2.init(ButtonPin2);
  connectToWiFi(true);

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("SETUP: WiFi Terhubung. Memulai sinkronisasi awal...");
    fetchServerFingerprint();
    yield();
    syncTime();
    yield();
    if (isRegistered) {
      Serial.println(
          "SETUP: Perangkat terdaftar. Mengirim laporan 'boot' ke server.");
      sendControlCommand("set_mode", "AUTO");
      delay(500);
      yield();
      sendControlCommand("set_status", "OFF");
      delay(500);
      yield();
      sendControlCommand("report_event", "boot");
      delay(500);
      yield();
    }
    if (config.device_mode == 1) { // Hanya kirim ping sensor jika ini Monitor
      Serial.println("SETUP: Mengirim ping deteksi awal sensor ke server "
                     "(persen=0, cm=0)...");
      sendSensorData(0.0, 0.0);
      delay(500);
      yield();
    }
    Serial.println(
        "SETUP: Melakukan sinkronisasi status awal dengan server...");
    fetchControlStatus();
    sendControlCommand("reset_config", "0");
    sendControlCommand("reset_mode_update", "0");
    delay(1000);
    yield(); // Ini akan mengatur isRegistered
    if (isRegistered) {
      Serial.println("SETUP: Mengirim log offline yang tersimpan.");
      sendOfflineLogs();
      yield();
    }
  }
  wasConnected = (WiFi.status() == WL_CONNECTED);
}

void loop() {
  unsigned long currentMillis = millis();
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(
        "NETWORK: Koneksi terputus. Beralih ke mode AUTO sebagai fallback.");
    digitalWrite(wifiLed, HIGH);
    if (wasConnected) {
      strcpy(currMode, "AUTO");
      modeFlag = true;
      wasConnected = false;
    }
    if (currentMillis - lastReconnectAttempt >= reconnectInterval) {
      connectToWiFi(false);
      lastReconnectAttempt = currentMillis;
    }
  } else {
    if (!wasConnected) {
      wasConnected = true;
      Serial.println(
          "NETWORK: Koneksi pulih. Akan menyinkronkan status dengan server.");
      digitalWrite(wifiLed, LOW);
      syncTime();
      delay(1000);
      sendControlCommand("report_event", "network_recovered");
      delay(1000);
      sendControlCommand("set_mode", "AUTO");
      delay(1000);
      sendOfflineLogs();
      delay(1000);
      fetchControlStatus();
      Serial.println("NETWORK: Sinkronisasi setelah pulih selesai.");
    }
  }

  // LOGIKA PENDAFTARAN: Jika belum terdaftar, kunci semua operasi fisik
  if (!isRegistered) {
    // Blink LED saat mode tunggu
    if (wasConnected &&
        currentMillis - lastStatusFetchTime >= currentStatusFetchInterval) {
      digitalWrite(wifiLed,
                   !digitalRead(wifiLed)); // Blink LED saat mode tunggu
      Serial.printf("STATUS: Perangkat belum terdaftar. Polling server setiap "
                    "%ld ms...\n",
                    currentStatusFetchInterval);
      fetchQuickStatus();
      lastStatusFetchTime = currentMillis;
    }
    if (relayStatus) {
      relayStatus = false;
      digitalWrite(RelayPin, LOW);
      Serial.println(
          "STATUS: Perangkat belum terdaftar. Memastikan pompa OFF.");
    }
    return;
  }

  // PERBAIKAN: Logika fallback baru jika server tidak dapat dihubungi
  if (serverConnectionFailures >= MAX_SERVER_FAILURES &&
      strcmp(currMode, "AUTO") != 0) {
    Serial.println("SERVER: Tidak dapat dihubungi berulang kali. Memaksa "
                   "beralih ke mode AUTO.");
    strcpy(currMode, "AUTO");
    modeFlag = true;
    // logEventOffline("server_unreachable_fallback"); // Tidak ada di Hybrid
    serverConnectionFailures = 0; // Reset setelah beralih
  }

  button1.check();
  if (strcmp(currMode, "MANUAL") == 0)
    button2.check();
  if (currentMillis - lastDataSendTime >= dataSendInterval) {
    measureAndSendData();
    lastDataSendTime = currentMillis;
  }
  if (currentMillis - lastHealthSendTime >= healthSendInterval) {
    sendHealthStatus();
    lastHealthSendTime = currentMillis;
  }

  runUniversalPumpLogic();
  if (wasConnected &&
      currentMillis - lastStatusFetchTime >= currentStatusFetchInterval) {
    fetchQuickStatus();
    lastStatusFetchTime = currentMillis;
  }

  if (buzzerActive && (currentMillis - buzzerStartTime >= buzzerDuration)) {
    digitalWrite(BuzzerPin, LOW);
    buzzerActive = false;
  }
}
