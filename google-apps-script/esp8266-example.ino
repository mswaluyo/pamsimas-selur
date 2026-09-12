/**
 * PAMSIMAS - ESP8266/ESP32 Example Code
 * ======================================
 * Kirim data sensor ke Google Apps Script
 * 
 * Library yang dibutuhkan:
 * - ESP8266WiFi.h (untuk ESP8266)
 * - ESP32WiFi.h (untuk ESP32)
 * - HTTPClient.h
 * - ArduinoJson.h (install via Library Manager)
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>

// ============================================================
// KONFIGURASI
// ============================================================
const char* WIFI_SSID = "NamaWiFiAnda";
const char* WIFI_PASSWORD = "PasswordWiFiAnda";

// URL Google Apps Script (ganti dengan URL deploy Anda)
const char* APPS_SCRIPT_URL = "https://script.google.com/macros/s/AKfycb.../exec";

const char* API_KEY = "pamsimas-secret-key-123";
const char* DEVICE_ID = "STATION_01";

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n=== PAMSIMAS IoT Node ===");
  
  // Connect WiFi
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
}

// ============================================================
// LOOP
// ============================================================
void loop() {
  // Baca sensor (contoh: simulasi debit air)
  float debit = random(10, 150) / 10.0; // Ganti dengan bacaan sensor asli
  float battery = analogRead(A0) * (3.3 / 1024.0);
  int signal = WiFi.RSSI();
  
  // Kirim data
  sendData(debit, battery, signal);
  
  // Tunggu 5 menit (300000 ms)
  // Untuk hemat baterai, gunakan deep sleep
  delay(300000);
}

// ============================================================
// KIRIM DATA KE GOOGLE APPS SCRIPT
// ============================================================
void sendData(float debit, float battery, int signal) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected!");
    return;
  }
  
  HTTPClient http;
  WiFiClientSecure client;
  client.setInsecure(); // Skip SSL verification untuk Google
  
  http.begin(client, APPS_SCRIPT_URL);
  http.addHeader("Content-Type", "application/json");
  
  // Buat JSON payload
  StaticJsonDocument<256> doc;
  doc["api_key"] = API_KEY;
  doc["device_id"] = DEVICE_ID;
  doc["sensor_type"] = "debit_air";
  doc["value"] = debit;
  doc["unit"] = "L/menit";
  doc["battery"] = battery;
  doc["signal"] = signal;
  
  String payload;
  serializeJson(doc, payload);
  
  Serial.println("Sending: " + payload);
  
  int httpCode = http.POST(payload);
  
  if (httpCode > 0) {
    String response = http.getString();
    Serial.println("HTTP Code: " + String(httpCode));
    Serial.println("Response: " + response);
  } else {
    Serial.println("HTTP Error: " + http.errorToString(httpCode));
  }
  
  http.end();
}
