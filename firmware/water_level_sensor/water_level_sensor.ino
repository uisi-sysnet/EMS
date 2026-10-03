/*
 * EMS Water Level Sensor — Arduino Nano + SIM800L + JSN-SR04T
 * ============================================================
 * Measures the distance from the sensor down to the water surface and texts
 * a reading to the EMS GSM gateway every <interval> minutes. The interval is
 * set in the EMS dashboard; the gateway texts it here as "WLCFG,<minutes>",
 * this sketch saves it (survives power loss) and confirms with
 * "WLACK,<station>,<minutes>".
 *
 * SMS FORMAT (parsed by scripts/water_level_gsm.py)
 *   Reading:  WL1,<station_mn>,<seq>,<distance_cm>,<battery_mV>,<temp_tenths_C|NA>,<interval_min>
 *   Ack:      WLACK,<station_mn>,<interval_min>
 * The server turns distance into water level using the station's
 * installation height set in the dashboard, so the sensor never needs
 * reprogramming when the mounting height changes.
 *
 * WIRING
 * ------
 *   SIM800L          same as the gateway: TXD -> D2, RXD <- D3 via 1k/2k
 *                    divider, RST <- D4, VCC from a 3.7-4.2 V / 2 A supply
 *                    (Li-ion cell), 470-1000 uF capacitor at the module.
 *   JSN-SR04T        TRIG <- D5, ECHO -> D6, VCC 5 V, GND.
 *                    Mount it pointing straight down at the water.
 *   Battery voltage  battery + -- 100k --+-- 100k -- GND, middle -> A0
 *                    (set BATTERY_DIVIDER to match your resistors).
 *   DS18B20 (opt.)   DATA -> D7 with a 4.7k pull-up to 5 V. Set USE_DS18B20 1
 *                    and install the "OneWire" and "DallasTemperature" libraries.
 *   All grounds connected together.
 *
 * Fill in the three settings marked >>> below before uploading.
 * The SIM needs its PIN lock disabled and load / an unlimited-text promo:
 * this SIM pays for every reading it sends.
 */

#include <SoftwareSerial.h>
#include <EEPROM.h>
#include <avr/wdt.h>

// >>> Station code — must match "Station MN" in the dashboard (max 14 chars).
#define STATION_MN          "WLS001"
// >>> The GSM gateway's SIM number. Readings go here, and settings are only
//     accepted from here.
#define GATEWAY_NUMBER      "+639170000000"
// >>> Interval used until the dashboard sends one (minutes, 1-1440).
#define DEFAULT_INTERVAL    15

#define USE_DS18B20         0          // 1 = read a DS18B20 temperature probe on D7
#define BATTERY_DIVIDER     2.0f       // (R1 + R2) / R2 of the battery divider
#define ADC_REF_MV          5000.0f    // Nano ADC reference (5 V when USB/5V powered)

#define PIN_MODEM_RX        2
#define PIN_MODEM_TX        3
#define PIN_MODEM_RST       4
#define PIN_TRIG            5
#define PIN_ECHO            6
#define PIN_DS18B20         7
#define PIN_BATTERY         A0

#define MODEM_BAUD          9600UL
#define FIRST_SEND_MS       60000UL     // first reading 1 min after power-up
#define SEND_RETRY_MS       120000UL    // retry a failed send after 2 min
#define MAX_DISTANCE_CM     600         // JSN-SR04T range limit

#if USE_DS18B20
  #include <OneWire.h>
  #include <DallasTemperature.h>
  OneWire oneWire(PIN_DS18B20);
  DallasTemperature tempSensor(&oneWire);
#endif

SoftwareSerial modem(PIN_MODEM_RX, PIN_MODEM_TX);

// Settings saved in EEPROM.
struct Settings {
  uint16_t magic;
  uint16_t intervalMin;
  uint32_t seq;
};
const uint16_t SETTINGS_MAGIC = 0x574C;   // "WL"
Settings settings;

char modemLine[200];
uint8_t modemLen = 0;
unsigned long nextSendAt = 0;
uint8_t smsToHandle[31];   // slots announced by +CMTI, handled from loop()
uint8_t smsQueued = 0;

// ---------------------------------------------------------------- storage --
void loadSettings() {
  EEPROM.get(0, settings);
  if (settings.magic != SETTINGS_MAGIC || settings.intervalMin < 1 || settings.intervalMin > 1440) {
    settings.magic = SETTINGS_MAGIC;
    settings.intervalMin = DEFAULT_INTERVAL;
    settings.seq = 0;
    EEPROM.put(0, settings);
  }
}

void saveSettings() {
  EEPROM.put(0, settings);   // put() only writes bytes that changed
}

// ---------------------------------------------------------- modem helpers --
bool modemReadLine() {
  while (modem.available()) {
    char ch = modem.read();
    if (ch == '\r') continue;
    if (ch == '\n') {
      if (modemLen == 0) continue;
      modemLine[modemLen] = '\0';
      modemLen = 0;
      return true;
    }
    if (modemLen < sizeof(modemLine) - 1) modemLine[modemLen++] = ch;
  }
  return false;
}

void noteUrc() {
  // '+CMTI: "SM",3' — remember the slot; it's read from loop(), not here,
  // because we may be in the middle of another command.
  if (strncmp(modemLine, "+CMTI:", 6) == 0) {
    const char *comma = strchr(modemLine, ',');
    if (comma && smsQueued < sizeof(smsToHandle)) smsToHandle[smsQueued++] = (uint8_t)atoi(comma + 1);
  }
}

bool waitFor(const char *expect, unsigned long timeoutMs) {
  unsigned long start = millis();
  while (millis() - start < timeoutMs) {
    wdt_reset();
    if (modemReadLine()) {
      if (strncmp(modemLine, expect, strlen(expect)) == 0) return true;
      if (strcmp(modemLine, "ERROR") == 0 || strncmp(modemLine, "+CMS ERROR", 10) == 0 ||
          strncmp(modemLine, "+CME ERROR", 10) == 0) return false;
      noteUrc();
    }
  }
  return false;
}

bool at(const char *cmd, const char *expect = "OK", unsigned long timeoutMs = 2000) {
  modem.println(cmd);
  return waitFor(expect, timeoutMs);
}

void resetModem() {
  pinMode(PIN_MODEM_RST, OUTPUT);
  digitalWrite(PIN_MODEM_RST, LOW);
  delay(200);
  digitalWrite(PIN_MODEM_RST, HIGH);
  pinMode(PIN_MODEM_RST, INPUT);
  for (uint8_t i = 0; i < 10; i++) { wdt_reset(); delay(500); }
}

bool initModem() {
  for (uint8_t attempt = 0; attempt < 10; attempt++) {
    while (modem.available()) modem.read();
    if (at("AT", "OK", 1000)) break;
    if (attempt == 9) return false;
  }
  at("ATE0");
  at("AT+CMGF=1");
  at("AT+CSCS=\"GSM\"");
  at("AT+CPMS=\"SM\",\"SM\",\"SM\"", "OK", 5000);
  at("AT+CNMI=2,1,0,0,0");
  at("AT+CSDH=0");
  return true;
}

bool waitForNetwork(unsigned long timeoutMs) {
  unsigned long start = millis();
  while (millis() - start < timeoutMs) {
    modem.println(F("AT+CREG?"));
    if (waitFor("+CREG:", 2000)) {
      const char *comma = strchr(modemLine, ',');
      int stat = comma ? atoi(comma + 1) : 0;
      waitFor("OK", 1000);
      if (stat == 1 || stat == 5) return true;
    }
    for (uint8_t i = 0; i < 4; i++) { wdt_reset(); delay(500); }
  }
  return false;
}

bool sendSms(const char *number, const char *text) {
  char cmd[40];
  snprintf(cmd, sizeof(cmd), "AT+CMGS=\"%s\"", number);
  modem.println(cmd);

  unsigned long start = millis();
  bool prompt = false;
  while (millis() - start < 5000 && !prompt) {
    wdt_reset();
    while (modem.available()) {
      if (modem.read() == '>') { prompt = true; break; }
    }
  }
  if (!prompt) { modem.write(27); return false; }

  modem.print(text);
  modem.write(26);
  if (!waitFor("+CMGS:", 60000)) return false;
  waitFor("OK", 2000);
  return true;
}

// Pulls the n-th double-quoted field out of a header line.
bool quotedField(const char *line, uint8_t n, char *out, uint8_t outSize) {
  const char *p = line;
  for (uint8_t i = 0; i <= n; i++) {
    p = strchr(p, '"');
    if (!p) return false;
    const char *end = strchr(p + 1, '"');
    if (!end) return false;
    if (i == n) {
      uint8_t len = min((uint8_t)(end - p - 1), (uint8_t)(outSize - 1));
      memcpy(out, p + 1, len);
      out[len] = '\0';
      return true;
    }
    p = end + 1;
  }
  return false;
}

// Same rule as the server: 09XXXXXXXXX / 63... / +63... compare equal.
void normalizeNumber(const char *in, char *out, uint8_t outSize) {
  char digits[20];
  uint8_t n = 0;
  for (const char *c = in; *c && n < sizeof(digits) - 1; c++) {
    if (*c >= '0' && *c <= '9') digits[n++] = *c;
  }
  digits[n] = '\0';
  if (n == 11 && digits[0] == '0') snprintf(out, outSize, "+63%s", digits + 1);
  else snprintf(out, outSize, "+%s", digits);
}

// ------------------------------------------------------------- measurement --
long readDistanceCm() {
  // Median of 5 pings rejects the odd echo off a wave or the pipe wall.
  long samples[5];
  uint8_t good = 0;
  for (uint8_t i = 0; i < 5; i++) {
    wdt_reset();
    digitalWrite(PIN_TRIG, LOW);
    delayMicroseconds(5);
    digitalWrite(PIN_TRIG, HIGH);
    delayMicroseconds(20);           // JSN-SR04T needs a longer trigger than HC-SR04
    digitalWrite(PIN_TRIG, LOW);
    unsigned long us = pulseIn(PIN_ECHO, HIGH, 40000UL);
    long cm = (long)(us / 58UL);
    if (us > 0 && cm > 0 && cm <= MAX_DISTANCE_CM) samples[good++] = cm;
    delay(80);
  }
  if (good == 0) return -1;
  for (uint8_t i = 1; i < good; i++) {   // insertion sort
    long v = samples[i];
    int8_t j = i - 1;
    while (j >= 0 && samples[j] > v) { samples[j + 1] = samples[j]; j--; }
    samples[j + 1] = v;
  }
  return samples[good / 2];
}

long readBatteryMv() {
  long sum = 0;
  for (uint8_t i = 0; i < 8; i++) { sum += analogRead(PIN_BATTERY); delay(2); }
  return (long)((sum / 8.0f) * ADC_REF_MV / 1023.0f * BATTERY_DIVIDER);
}

// Temperature in tenths of a degree, written into `out` ("281" or "NA").
void readTemperature(char *out, uint8_t outSize) {
#if USE_DS18B20
  tempSensor.requestTemperatures();
  float c = tempSensor.getTempCByIndex(0);
  if (c > -55.0f && c < 125.0f) {
    snprintf(out, outSize, "%d", (int)(c * 10.0f + (c >= 0 ? 0.5f : -0.5f)));
    return;
  }
#endif
  snprintf(out, outSize, "NA");
}

// ---------------------------------------------------------------- actions --
bool sendReading() {
  long distance = readDistanceCm();
  if (distance < 0) distance = 0;   // 0 = no echo; the server stores it as-is
  long battery = readBatteryMv();
  char temp[8];
  readTemperature(temp, sizeof(temp));

  settings.seq++;
  char text[100];
  snprintf(text, sizeof(text), "WL1,%s,%lu,%ld,%ld,%s,%u",
           STATION_MN, (unsigned long)settings.seq, distance, battery, temp, settings.intervalMin);

  if (!waitForNetwork(30000)) return false;
  bool ok = sendSms(GATEWAY_NUMBER, text);
  if (ok) saveSettings();          // persist seq only after a successful send
  else settings.seq--;
  return ok;
}

// Reads SMS `idx`; applies "WLCFG,<minutes>" from the gateway; deletes it.
void handleSms(uint8_t idx) {
  char cmd[16];
  snprintf(cmd, sizeof(cmd), "AT+CMGR=%u", idx);
  modem.println(cmd);
  if (!waitFor("+CMGR:", 5000)) return;

  char senderRaw[24] = "", sender[24], gateway[24];
  quotedField(modemLine, 1, senderRaw, sizeof(senderRaw));
  normalizeNumber(senderRaw, sender, sizeof(sender));
  normalizeNumber(GATEWAY_NUMBER, gateway, sizeof(gateway));

  char body[170] = "";
  if (waitFor("", 3000)) strncpy(body, modemLine, sizeof(body) - 1);   // first body line
  waitFor("OK", 2000);

  snprintf(cmd, sizeof(cmd), "AT+CMGD=%u", idx);   // delete every SMS (promos too)
  at(cmd, "OK", 5000);

  if (strcmp(sender, gateway) != 0) return;         // settings only from the gateway
  if (strncmp(body, "WLCFG,", 6) != 0) return;

  long minutes = atol(body + 6);
  if (minutes < 1 || minutes > 1440) return;

  bool changed = settings.intervalMin != (uint16_t)minutes;
  settings.intervalMin = (uint16_t)minutes;
  saveSettings();

  char ack[48];
  snprintf(ack, sizeof(ack), "WLACK,%s,%u", STATION_MN, settings.intervalMin);
  sendSms(GATEWAY_NUMBER, ack);

  // Apply right away: next reading one new interval from now.
  if (changed) nextSendAt = millis() + (unsigned long)settings.intervalMin * 60000UL;
}

// Handles SMS that arrived while the sensor was off or busy.
void handleStoredSms() {
  bool present[31] = {false};
  modem.println(F("AT+CMGL=\"ALL\",1"));
  unsigned long start = millis();
  while (millis() - start < 8000) {
    wdt_reset();
    if (!modemReadLine()) continue;
    if (strcmp(modemLine, "OK") == 0 || strcmp(modemLine, "ERROR") == 0) break;
    if (strncmp(modemLine, "+CMGL:", 6) == 0) {
      int idx = atoi(modemLine + 6);
      if (idx >= 1 && idx <= 30) present[idx] = true;
    }
  }
  for (uint8_t idx = 1; idx <= 30; idx++) if (present[idx]) handleSms(idx);
}

// -------------------------------------------------------------- main loop --
void setup() {
  wdt_disable();
  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);
  digitalWrite(PIN_TRIG, LOW);
  modem.begin(MODEM_BAUD);
#if USE_DS18B20
  tempSensor.begin();
#endif
  wdt_enable(WDTO_8S);

  loadSettings();
  if (!initModem()) {
    resetModem();
    initModem();
  }
  waitForNetwork(60000);
  handleStoredSms();
  nextSendAt = millis() + FIRST_SEND_MS;
}

void loop() {
  wdt_reset();
  if (modemReadLine()) noteUrc();

  while (smsQueued > 0) handleSms(smsToHandle[--smsQueued]);

  if ((long)(millis() - nextSendAt) >= 0) {
    if (sendReading()) {
      nextSendAt = millis() + (unsigned long)settings.intervalMin * 60000UL;
    } else {
      // Recover the modem if it stopped answering, then retry soon.
      if (!at("AT", "OK", 2000)) { resetModem(); initModem(); }
      nextSendAt = millis() + SEND_RETRY_MS;
    }
  }
}
