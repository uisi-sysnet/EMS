/*
 * EMS Water Level Sensor — Arduino Nano + SIM800L + DFRobot URM06-PULSE
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
 *   Power-on: WLON,<station_mn>,<interval_min>   (once after each power-up/reset)
 * The server turns distance into water level using the station's
 * installation height set in the dashboard, so the sensor never needs
 * reprogramming when the mounting height changes.
 *
 * WIRING
 * ------
 *   SIM800L          same as the gateway: TXD -> D8, RXD <- D9 via 1k/2k
 *                    divider, GND common, VCC from a 3.7-4.2 V / 2 A supply
 *                    (Li-ion cell), 470-1000 uF capacitor at the module.
 *                    RST is not used (a hung modem is restarted with AT+CFUN=1,1).
 *   TTL-to-RS485     RO -> D0 (RX), DI <- D1 (TX), DE + RE tied together <- D4,
 *   (MAX485 module)  VCC 5 V, GND. A/B to the RS485 bus; 120 ohm terminator at
 *                    each end of the bus. Unplug the module (or its RO wire)
 *                    while uploading: D0/D1 are also the Nano's USB serial.
 *   URM06-PULSE      Trig <- D2, Echo -> D3, VCC 6-12 V (5 V works but is
 *   (DFRobot SEN0151) not recommended), GND. Range 20 cm - 10 m. Mount it
 *                    pointing straight down at the water, at least 20 cm
 *                    above the highest expected level. The Nano can run from
 *                    the same 7-12 V supply on its VIN pin.
 *   Battery voltage  battery + -- 100k --+-- 100k -- GND, middle -> A0
 *                    (set BATTERY_DIVIDER to match your resistors).
 *   DS18B20 (opt.)   DATA -> D7 with a 4.7k pull-up to 5 V. Set USE_DS18B20 1
 *                    and install the "OneWire" and "DallasTemperature" libraries.
 *   All grounds connected together.
 *
 * MODBUS RTU SLAVE (RS485, 9600 8N1, read-only)
 *   Functions 03 (holding) and 04 (input registers) return the same map.
 *   Register (0-based)                     Unit
 *     0  distance to the water             cm, 0 = no echo
 *     1  battery                           mV
 *     2  temperature                       0.1 C (signed), -32768 = not fitted
 *     3  reporting interval                minutes
 *     4  SMS sequence number (low 16 bits)
 *     5  age of this measurement           s (65535 = not measured yet)
 *   Measured every second, and before each SMS. While the station is busy
 *   sending an SMS (up to about a minute) it doesn't answer Modbus requests.
 *
 * Fill in the four settings marked >>> below before uploading.
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
#define GATEWAY_NUMBER      "+639995787215"
// >>> Interval used until the dashboard sends one (minutes, 1-1440).
#define DEFAULT_INTERVAL    3
// >>> Modbus slave address on the RS485 bus (1-247, different for every station).
#define MODBUS_ID           1
#define MODBUS_BAUD         9600UL
#define MEASURE_MS          1000UL     // live reading for USB and Modbus (one measurement takes under 1 s)

#define SERIAL_DEBUG        1          // 1 = print each measurement on the USB Serial Monitor (9600)
#define USE_DS18B20         0          // 1 = read a DS18B20 temperature probe on D7
#define BATTERY_DIVIDER     2.0f       // (R1 + R2) / R2 of the battery divider
#define ADC_REF_MV          5000.0f    // Nano ADC reference (5 V when USB/5V powered)

#define PIN_MODEM_RX        8          // Nano receives on D8 (from SIM800L TXD)
#define PIN_MODEM_TX        9          // Nano transmits on D9 (to SIM800L RXD, via divider)
#define PIN_RS485_DE        4          // DE + RE of the RS485 module (HIGH = transmit)
#define PIN_TRIG            2
#define PIN_ECHO            3
#define PIN_DS18B20         7
#define PIN_BATTERY         A0

#define MODEM_BAUD          9600UL
#define FIRST_SEND_MS       60000UL     // first reading 1 min after power-up
#define SEND_RETRY_MS       120000UL    // retry a failed send after 2 min
#define MIN_DISTANCE_CM     20          // URM06-PULSE range: 20 cm - 10 m
#define MAX_DISTANCE_CM     1000

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
bool powerOnPending = true;   // WLON not delivered yet; retried with each reading

// Latest measurement, shared by the SMS reading and the Modbus registers.
const int16_t TEMP_NA = -32768;
long lastDistanceCm = 0;           // 0 = no echo
long lastBatteryMv = 0;
int16_t lastTempTenths = TEMP_NA;
unsigned long lastMeasuredAt = 0;
unsigned long lastEchoUs = 0;      // raw echo pulse of the last ping, for the USB printout
uint8_t lastGoodPings = 0;         // pings (of 5) that agreed on the distance
bool measuredOnce = false;

uint8_t mbFrame[64];               // Modbus request being received
uint8_t mbLen = 0;
unsigned long mbLastByteUs = 0;

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

// RST isn't wired (D4 drives the RS485 module), so restart the modem by
// command. shortcut: can't revive a modem that stops answering AT at all;
// wire RST to a free pin (e.g. D5) if that happens in the field.
void resetModem() {
  modem.println(F("AT+CFUN=1,1"));
  idle(10000);
}

// Waits without going quiet: the live reading (USB) and Modbus keep running.
void idle(unsigned long ms) {
  unsigned long start = millis();
  while (millis() - start < ms) {
    wdt_reset();
    modbusPoll();
    if (millis() - lastMeasuredAt >= MEASURE_MS) measure();
  }
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
    idle(2000);
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
  if (!prompt) { modem.write(27); smsResult(F("modem gave no > prompt"), text); return false; }

  modem.print(text);
  modem.write(26);
  if (!waitFor("+CMGS:", 60000)) { smsResult(F("network did not accept it"), text); return false; }
  waitFor("OK", 2000);
  smsResult(NULL, text);
  return true;
}

// Shows every SMS attempt on the USB Serial Monitor. fail = NULL means it was sent.
void smsResult(const __FlashStringHelper *fail, const char *text) {
#if SERIAL_DEBUG
  if (fail) { Serial.print(F("SMS FAILED (")); Serial.print(fail); Serial.print(F("): ")); }
  else Serial.print(F("SMS SENT OK: "));
  Serial.println(text);
  Serial.flush();
#endif
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
    // URM06-PULSE: a LOW pulse over 50 us on Trig starts one measurement
    // (Trig idles HIGH; held LOW it would re-measure every 100 ms). Echo is a
    // HIGH pulse of 1 us per mm; 15000 us means the sensor failed to measure.
    digitalWrite(PIN_TRIG, LOW);
    delayMicroseconds(60);
    digitalWrite(PIN_TRIG, HIGH);
    // The echo pulse comes only after the sensor's own measuring cycle (it repeats
    // every 100 ms), so allow 200 ms; a shorter wait reads the previous ping's pulse.
    unsigned long us = pulseIn(PIN_ECHO, HIGH, 200000UL);
    lastEchoUs = us;
    long cm = (long)((us + 5UL) / 10UL);
    if (us > 0 && us < 15000UL && cm >= MIN_DISTANCE_CM && cm <= MAX_DISTANCE_CM) samples[good++] = cm;
    delay(100);
  }
  lastGoodPings = good;
  if (good < 3) return -1;                // mostly failed pings: report no echo, not a stray value
  for (uint8_t i = 1; i < good; i++) {   // insertion sort
    long v = samples[i];
    int8_t j = i - 1;
    while (j >= 0 && samples[j] > v) { samples[j + 1] = samples[j]; j--; }
    samples[j + 1] = v;
  }
  // At least 3 pings must agree (within 5 cm or 2 %) — noise gives scattered values.
  long median = samples[good / 2];
  long tol = max(5L, median / 50);
  uint8_t agree = 0;
  for (uint8_t i = 0; i < good; i++) if (labs(samples[i] - median) <= tol) agree++;
  lastGoodPings = agree;
  return agree >= 3 ? median : -1;
}

long readBatteryMv() {
  long sum = 0;
  for (uint8_t i = 0; i < 8; i++) { sum += analogRead(PIN_BATTERY); delay(2); }
  return (long)((sum / 8.0f) * ADC_REF_MV / 1023.0f * BATTERY_DIVIDER);
}

// Temperature in tenths of a degree, or TEMP_NA without a probe.
int16_t readTemperatureTenths() {
#if USE_DS18B20
  tempSensor.requestTemperatures();
  float c = tempSensor.getTempCByIndex(0);
  if (c > -55.0f && c < 125.0f) return (int16_t)(c * 10.0f + (c >= 0 ? 0.5f : -0.5f));
#endif
  return TEMP_NA;
}

// Takes a fresh reading into the shared values (SMS and Modbus).
void measure() {
  long distance = readDistanceCm();
  lastDistanceCm = distance < 0 ? 0 : distance;   // 0 = no echo; the server stores it as "no reading"
  lastBatteryMv = readBatteryMv();
  lastTempTenths = readTemperatureTenths();
  lastMeasuredAt = millis();
  measuredOnce = true;
#if SERIAL_DEBUG
  // USB and RS485 share D1, but DE is LOW here so the text never reaches the bus.
  // flush() empties the buffer so none of it is still sending when a Modbus reply sets DE HIGH.
  Serial.print(F("Distance: "));
  if (lastDistanceCm == 0) Serial.print(F("no echo"));
  else { Serial.print(lastDistanceCm); Serial.print(F(" cm")); }
  // 0 us = no pulse on D3 (wiring/power), 15000 = sensor error, under 200 = closer than 20 cm.
  Serial.print(F(" (")); Serial.print(lastGoodPings); Serial.print(F("/5 pings agree, last echo "));
  Serial.print(lastEchoUs); Serial.print(F(" us)"));
  Serial.print(F("  Battery: ")); Serial.print(lastBatteryMv); Serial.print(F(" mV  Temp: "));
  if (lastTempTenths == TEMP_NA) Serial.println(F("NA"));
  else Serial.println(lastTempTenths / 10.0, 1);
  Serial.flush();
#endif
}

// ---------------------------------------------------------------- actions --
bool sendReading() {
  measure();
  char temp[8];
  if (lastTempTenths == TEMP_NA) snprintf(temp, sizeof(temp), "NA");
  else snprintf(temp, sizeof(temp), "%d", lastTempTenths);

  settings.seq++;
  char text[100];
  snprintf(text, sizeof(text), "WL1,%s,%lu,%ld,%ld,%s,%u",
           STATION_MN, (unsigned long)settings.seq, lastDistanceCm, lastBatteryMv, temp, settings.intervalMin);

  bool ok = false;
  if (!waitForNetwork(30000)) smsResult(F("no network"), text);
  else ok = sendSms(GATEWAY_NUMBER, text);
  if (ok) saveSettings();          // persist seq only after a successful send
  else settings.seq--;
  return ok;
}

// Tells the gateway this station just powered on: WLON,<station_mn>,<interval_min>.
bool sendPowerOn() {
  char text[48];
  snprintf(text, sizeof(text), "WLON,%s,%u", STATION_MN, settings.intervalMin);
  if (!waitForNetwork(30000)) { smsResult(F("no network"), text); return false; }
  return sendSms(GATEWAY_NUMBER, text);
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

// ------------------------------------------------------- Modbus RTU slave --
uint16_t modbusCrc(const uint8_t *buf, uint8_t len) {
  uint16_t crc = 0xFFFF;
  for (uint8_t i = 0; i < len; i++) {
    crc ^= buf[i];
    for (uint8_t b = 0; b < 8; b++) crc = (crc & 1) ? (crc >> 1) ^ 0xA001 : crc >> 1;
  }
  return crc;
}

void modbusSend(uint8_t *buf, uint8_t len) {   // appends the CRC
  uint16_t crc = modbusCrc(buf, len);
  buf[len++] = crc & 0xFF;
  buf[len++] = crc >> 8;
  digitalWrite(PIN_RS485_DE, HIGH);
  Serial.write(buf, len);
  Serial.flush();                    // wait until the last byte has left
  digitalWrite(PIN_RS485_DE, LOW);
}

uint16_t modbusRegister(uint16_t addr) {
  switch (addr) {
    case 0: return (uint16_t)lastDistanceCm;
    case 1: return (uint16_t)lastBatteryMv;
    case 2: return (uint16_t)lastTempTenths;
    case 3: return settings.intervalMin;
    case 4: return (uint16_t)(settings.seq & 0xFFFF);
    default: {
      if (!measuredOnce) return 0xFFFF;
      unsigned long age = (millis() - lastMeasuredAt) / 1000UL;
      return age > 0xFFFE ? 0xFFFE : (uint16_t)age;
    }
  }
}
const uint16_t MODBUS_REGISTERS = 6;

// Answers one complete request frame (address, function, ..., CRC).
void modbusHandle(const uint8_t *req, uint8_t len) {
  if (len < 8 || req[0] != MODBUS_ID) return;              // not for us (or broadcast)
  if (modbusCrc(req, len - 2) != (uint16_t)(req[len - 2] | (req[len - 1] << 8))) return;

  uint8_t resp[5 + 2 * MODBUS_REGISTERS + 2];
  resp[0] = MODBUS_ID;
  uint8_t fn = req[1];
  uint16_t start = (req[2] << 8) | req[3];
  uint16_t count = (req[4] << 8) | req[5];
  if (fn != 3 && fn != 4) { resp[1] = fn | 0x80; resp[2] = 1; modbusSend(resp, 3); return; }   // illegal function
  if (count < 1 || count > MODBUS_REGISTERS || start + count > MODBUS_REGISTERS) {
    resp[1] = fn | 0x80; resp[2] = 2; modbusSend(resp, 3); return;                          // illegal address
  }
  resp[1] = fn;
  resp[2] = count * 2;
  for (uint16_t i = 0; i < count; i++) {
    uint16_t v = modbusRegister(start + i);
    resp[3 + 2 * i] = v >> 8;
    resp[4 + 2 * i] = v & 0xFF;
  }
  modbusSend(resp, 3 + count * 2);
}

// RTU frames end with a silence of 3.5 characters (about 4 ms at 9600 baud).
void modbusPoll() {
  while (Serial.available()) {
    uint8_t b = Serial.read();
    if (mbLen < sizeof(mbFrame)) mbFrame[mbLen++] = b;
    mbLastByteUs = micros();
  }
  if (mbLen > 0 && micros() - mbLastByteUs > 4000UL) {
    modbusHandle(mbFrame, mbLen);
    mbLen = 0;
  }
}

// After a long blocking step (SMS, measurement) any buffered request is
// stale: the master has already timed out, so drop it instead of answering late.
void modbusDropStale() {
  while (Serial.available()) Serial.read();
  mbLen = 0;
}

// -------------------------------------------------------------- main loop --
void setup() {
  wdt_disable();
  pinMode(PIN_TRIG, OUTPUT);
  digitalWrite(PIN_TRIG, HIGH);    // URM06-PULSE triggers on LOW; idle HIGH
  pinMode(PIN_ECHO, INPUT);
  pinMode(PIN_RS485_DE, OUTPUT);
  digitalWrite(PIN_RS485_DE, LOW);   // listen on the RS485 bus
  Serial.begin(MODBUS_BAUD);
  modem.begin(MODEM_BAUD);
#if USE_DS18B20
  tempSensor.begin();
#endif
  wdt_enable(WDTO_8S);

  loadSettings();
#if SERIAL_DEBUG
  Serial.print(F("\nEMS water level station ")); Serial.print(F(STATION_MN));
  Serial.print(F("  Modbus ID ")); Serial.print(MODBUS_ID);
  Serial.print(F("  interval ")); Serial.print(settings.intervalMin); Serial.println(F(" min"));
  Serial.flush();
#endif
  measure();                       // first reading right away, before the slow modem start
#if SERIAL_DEBUG
  Serial.println(F("SIM800L: starting (up to 1 min)..."));
  Serial.flush();
#endif
  bool modemOk = initModem();
  if (!modemOk) {
    resetModem();
    modemOk = initModem();
  }
  bool networkOk = modemOk && waitForNetwork(60000);
#if SERIAL_DEBUG
  Serial.println(!modemOk ? F("SIM800L: not answering (check D8/D9 wiring and its power)")
                 : networkOk ? F("SIM800L: OK, registered on the network")
                 : F("SIM800L: OK, but no network (check SIM, PIN lock, antenna)"));
  Serial.flush();
#endif
  if (modemOk) handleStoredSms();
  if (networkOk && sendPowerOn()) powerOnPending = false;
  modbusDropStale();
  nextSendAt = millis() + FIRST_SEND_MS;
}

void loop() {
  wdt_reset();
  modbusPoll();
  if (modemReadLine()) noteUrc();

  if (smsQueued > 0) {
    while (smsQueued > 0) handleSms(smsToHandle[--smsQueued]);
    modbusDropStale();
  }

  if ((long)(millis() - nextSendAt) >= 0) {
    if (powerOnPending && sendPowerOn()) powerOnPending = false;
    if (sendReading()) {
      nextSendAt = millis() + (unsigned long)settings.intervalMin * 60000UL;
    } else {
      // Recover the modem if it stopped answering, then retry soon.
      if (!at("AT", "OK", 2000)) { resetModem(); initModem(); }
      nextSendAt = millis() + SEND_RETRY_MS;
    }
    modbusDropStale();
  } else if (millis() - lastMeasuredAt >= MEASURE_MS) {
    // No modbusDropStale() here: a request that came in during this short
    // measurement is still answered within the master's 3 s timeout.
    measure();
  }
}
