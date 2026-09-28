void controlBuzzer(int duration) {
  buzzerDuration = duration; buzzerStartTime = millis();
  digitalWrite(BuzzerPin, HIGH); buzzerActive = true;
}

void sendCommandAndBuzz(const char* action, const char* value, int buzzerDuration) {
  sendControlCommand(action, value); controlBuzzer(buzzerDuration);
}

void button1Handler(AceButton* button, uint8_t eventType, uint8_t buttonState) {
  if (eventType == AceButton::kEventReleased) {
    Serial.println("TOMBOL: Tombol 1 (Mode) ditekan.");
    const char* nextMode = (strcmp(currMode, "AUTO") == 0) ? "MANUAL" : "AUTO";
    sendCommandAndBuzz("set_mode", nextMode, 200);
  }
}

void button2Handler(AceButton* button, uint8_t eventType, uint8_t buttonState) {
  if (eventType == AceButton::kEventReleased) {
    Serial.println("TOMBOL: Tombol 2 (ON/OFF) ditekan.");
    const char* nextStatus = relayStatus ? "OFF" : "ON";
    sendCommandAndBuzz("set_manual_status", nextStatus, 500);
    relayStatus = !relayStatus;
    handlePumpStateChange();
  }
}