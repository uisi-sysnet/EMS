/*
 * EMS GSM Gateway — Arduino Nano + SIM800L
 * =========================================
 * Receives water level readings from the sensors by SMS and passes them to
 * the EMS server over USB serial (scripts/water_level_gsm.py), and sends the
 * SMS the server asks for (interval settings for the sensors).
 *
 * The Nano keeps no state of its own: each SMS stays on the SIM until the
 * server has saved it and answers ACK, so nothing is lost while the server
 * is down or rebooting.
 *
 * WIRING
 * ------
 *   SIM800L TXD  -> Nano D2   (SoftwareSerial RX)
 *   SIM800L RXD  <- Nano D3   through a voltage divider: D3 -- 1k --+-- SIM800L RXD
 *                                                                  |
 *                                                                 2k
 *                                                                  |
 *                                                                 GND
 *   SIM800L RST  <- Nano D4   (optional; lets the Nano reset a hung modem)
 *   SIM800L GND  -- Nano GND  -- power supply GND (common ground!)
 *   SIM800L VCC  -- 3.7-4.2 V supply that can deliver 2 A peaks
 *                   (a Li-ion cell, or a buck converter set to 4.0 V).
 *                   NOT the Nano's 5V or 3V3 pin: the module resets every
 *                   time it transmits if the supply sags.
 *   Put a 470-1000 uF capacitor across SIM800L VCC/GND, close to the module.
 *   Nano USB     -- EMS server (shows up as /dev/ttyUSB0 or /dev/serial/by-id/...)
 *
 * The SIM needs its PIN lock disabled. Receiving SMS is free; sending
 * (interval settings) uses the gateway SIM's load.
 *
 * SERIAL PROTOCOL (one line each, '|' separated) — see water_level_gsm.py
 *   Nano -> server:  +READY|<fw>   +SMS|<idx>|<sender>|<timestamp>|<body>
 *                    +SENT|<ref>   +FAIL|<ref>|<reason>
 *                    +STATUS|<csq>|<registered 0/1>   +PONG   +ERR|<text>
 *   Server -> Nano:  ACK|<idx>   SEND|<ref>|<number>|<text>   PING
 *
 * Board: Arduino Nano (ATmega328P). No extra libraries needed.
 */

#include <SoftwareSerial.h>
#include <avr/wdt.h>

// ---------------------------------------------------------------- settings --
#define FW_VERSION        "gw-1.0"
#define GATEWAY_BAUD      57600UL   // USB serial to the server (WATER_GSM_BAUDRATE)
#define MODEM_BAUD        9600UL    // SoftwareSerial is reliable up to 9600
#define PIN_MODEM_RX      2         // Nano receives on D2  (from SIM800L TXD)
#define PIN_MODEM_TX      3         // Nano transmits on D3 (to SIM800L RXD, via divider)
#define PIN_MODEM_RST     4         // SIM800L RST (active low)
#define SIM_SLOTS         30        // SMS storage slots on a typical SIM
#define RESCAN_MS         30000UL   // look for un-ACKed SMS on the SIM this often
#define REFORWARD_MS      60000UL   // re-send an SMS to the server if not ACKed by then
#define STATUS_MS         60000UL   // report signal/registration this often

SoftwareSerial modem(PIN_MODEM_RX, PIN_MODEM_TX);

char hostLine[220];   // line from the server
uint8_t hostLen = 0;
char modemLine[200];  // line from the modem
uint8_t modemLen = 0;

unsigned long forwardedAt[SIM_SLOTS + 1];  // 0 = not forwarded / ACKed
unsigned long lastRescan = 0;
unsigned long lastStatus = 0;
uint8_t modemFailures = 0;

// ------------------------------------------------------------ host output --
void hostSend(const char *a, const char *b = nullptr, const char *c = nullptr) {
  Serial.print(a);
  if (b) { Serial.print('|'); Serial.print(b); }
  if (c) { Serial.print('|'); Serial.print(c); }
  Serial.print('\n');
}

// ---------------------------------------------------------- modem helpers --
// Reads one line from the modem into modemLine (without CR/LF). Returns true
// when a complete, non-empty line is available. Non-blocking.
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

// Waits for a line starting with `expect` (or an error). Lines that aren't
// the answer — e.g. +CMTI notifications — are handed to handleModemUrc().
// Returns true on `expect`.
bool waitFor(const char *expect, unsigned long timeoutMs) {
  unsigned long start = millis();
  while (millis() - start < timeoutMs) {
    wdt_reset();
    if (modemReadLine()) {
      if (strncmp(modemLine, expect, strlen(expect)) == 0) return true;
      if (strcmp(modemLine, "ERROR") == 0 || strncmp(modemLine, "+CMS ERROR", 10) == 0 ||
          strncmp(modemLine, "+CME ERROR", 10) == 0) return false;
      handleModemUrc();
    }
  }
  return false;
}

void flushModem() {
  unsigned long start = millis();
  while (millis() - start < 100) {
    while (modem.available()) modem.read();
  }
  modemLen = 0;
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
  pinMode(PIN_MODEM_RST, INPUT);   // let the module's pull-up hold RST high
  for (uint8_t i = 0; i < 10; i++) { wdt_reset(); delay(500); }   // boot time
}

bool initModem() {
  for (uint8_t attempt = 0; attempt < 10; attempt++) {   // also syncs autobaud
    flushModem();
    if (at("AT", "OK", 1000)) break;
    if (attempt == 9) return false;
  }
  at("ATE0");                         // no command echo
  at("AT+CMGF=1");                    // text-mode SMS
  at("AT+CSCS=\"GSM\"");
  at("AT+CPMS=\"SM\",\"SM\",\"SM\"", "OK", 5000);   // store SMS on the SIM
  at("AT+CNMI=2,1,0,0,0");           // new SMS -> "+CMTI: "SM",<idx>"
  at("AT+CSDH=0");                    // short CMGR headers
  return true;
}

// ------------------------------------------------------------ SMS receive --
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

// Reads SMS `idx` from the SIM and forwards it to the server.
void forwardSms(uint8_t idx) {
  if (idx < 1 || idx > SIM_SLOTS) return;

  char cmd[16];
  snprintf(cmd, sizeof(cmd), "AT+CMGR=%u", idx);
  modem.println(cmd);
  // Header: +CMGR: "REC UNREAD","+639171234567","","26/10/03,14:58:30+32"
  if (!waitFor("+CMGR:", 5000)) return;   // empty slot answers just OK

  char sender[24] = "", stamp[24] = "";
  quotedField(modemLine, 1, sender, sizeof(sender));
  quotedField(modemLine, 3, stamp, sizeof(stamp));

  // Body: every line until OK, joined with spaces; '|' is our separator.
  char body[170] = "";
  uint8_t bodyLen = 0;
  unsigned long start = millis();
  while (millis() - start < 3000) {
    wdt_reset();
    if (!modemReadLine()) continue;
    if (strcmp(modemLine, "OK") == 0) break;
    for (char *c = modemLine; *c && bodyLen < sizeof(body) - 2; c++) {
      body[bodyLen++] = (*c == '|') ? '/' : *c;
    }
    body[bodyLen++] = ' ';
    body[bodyLen] = '\0';
  }
  while (bodyLen > 0 && body[bodyLen - 1] == ' ') body[--bodyLen] = '\0';

  char idxText[4];
  snprintf(idxText, sizeof(idxText), "%u", idx);
  Serial.print(F("+SMS|")); Serial.print(idxText);
  Serial.print('|');        Serial.print(sender);
  Serial.print('|');        Serial.print(stamp);
  Serial.print('|');        Serial.print(body);
  Serial.print('\n');
  forwardedAt[idx] = millis() | 1;   // never 0 while pending
}

// Finds every SMS on the SIM and forwards the ones not ACKed recently.
void rescanSim() {
  bool present[SIM_SLOTS + 1] = {false};
  modem.println(F("AT+CMGL=\"ALL\",1"));   // ,1 = don't mark as read
  unsigned long start = millis();
  while (millis() - start < 8000) {
    wdt_reset();
    if (!modemReadLine()) continue;
    if (strcmp(modemLine, "OK") == 0 || strcmp(modemLine, "ERROR") == 0) break;
    if (strncmp(modemLine, "+CMGL:", 6) == 0) {
      int idx = atoi(modemLine + 6);
      if (idx >= 1 && idx <= SIM_SLOTS) present[idx] = true;
    }
    // body lines are skipped here; forwardSms() reads them with AT+CMGR
  }
  for (uint8_t idx = 1; idx <= SIM_SLOTS; idx++) {
    if (!present[idx]) { forwardedAt[idx] = 0; continue; }
    if (forwardedAt[idx] == 0 || millis() - forwardedAt[idx] > REFORWARD_MS) forwardSms(idx);
  }
}

// Unsolicited modem lines, e.g. '+CMTI: "SM",3' for a new SMS.
void handleModemUrc() {
  if (strncmp(modemLine, "+CMTI:", 6) == 0) {
    const char *comma = strchr(modemLine, ',');
    if (comma) forwardSms((uint8_t)atoi(comma + 1));
  }
}

// --------------------------------------------------------------- SMS send --
void sendSms(const char *ref, const char *number, const char *text) {
  char cmd[40];
  snprintf(cmd, sizeof(cmd), "AT+CMGS=\"%s\"", number);
  modem.println(cmd);

  // The modem answers with a bare '>' prompt (no line ending).
  unsigned long start = millis();
  bool prompt = false;
  while (millis() - start < 5000 && !prompt) {
    wdt_reset();
    while (modem.available()) {
      if (modem.read() == '>') { prompt = true; break; }
    }
  }
  if (!prompt) {
    modem.write(27);   // ESC cancels a half-started send
    hostSend("+FAIL", ref, "no prompt");
    return;
  }

  modem.print(text);
  modem.write(26);     // Ctrl-Z sends
  if (waitFor("+CMGS:", 60000)) {
    waitFor("OK", 2000);
    hostSend("+SENT", ref);
  } else {
    hostSend("+FAIL", ref, "network did not accept the SMS");
  }
}

// ---------------------------------------------------------- server input --
void handleHostLine(char *line) {
  if (strcmp(line, "PING") == 0) {
    hostSend("+PONG");
  } else if (strncmp(line, "ACK|", 4) == 0) {
    int idx = atoi(line + 4);
    if (idx >= 1 && idx <= SIM_SLOTS) {
      char cmd[16];
      snprintf(cmd, sizeof(cmd), "AT+CMGD=%d", idx);
      at(cmd, "OK", 5000);
      forwardedAt[idx] = 0;
    }
  } else if (strncmp(line, "SEND|", 5) == 0) {
    // SEND|<ref>|<number>|<text>
    char *ref = line + 5;
    char *number = strchr(ref, '|');
    if (!number) return;
    *number++ = '\0';
    char *text = strchr(number, '|');
    if (!text) { hostSend("+FAIL", ref, "bad SEND line"); return; }
    *text++ = '\0';
    sendSms(ref, number, text);
  }
}

void readHost() {
  while (Serial.available()) {
    char ch = Serial.read();
    if (ch == '\r') continue;
    if (ch == '\n') {
      hostLine[hostLen] = '\0';
      if (hostLen > 0) handleHostLine(hostLine);
      hostLen = 0;
    } else if (hostLen < sizeof(hostLine) - 1) {
      hostLine[hostLen++] = ch;
    }
  }
}

// ----------------------------------------------------------------- status --
void reportStatus() {
  char csq[6] = "?";
  modem.println(F("AT+CSQ"));                   // +CSQ: 18,0
  if (waitFor("+CSQ:", 2000)) {
    int v = atoi(modemLine + 6);
    snprintf(csq, sizeof(csq), "%d", v);
    waitFor("OK", 1000);
  }
  bool registered = false;
  modem.println(F("AT+CREG?"));                 // +CREG: 0,1  (1 = home, 5 = roaming)
  if (waitFor("+CREG:", 2000)) {
    const char *comma = strchr(modemLine, ',');
    int stat = comma ? atoi(comma + 1) : 0;
    registered = (stat == 1 || stat == 5);
    waitFor("OK", 1000);
  }

  bool alive = at("AT", "OK", 2000);
  modemFailures = alive ? 0 : modemFailures + 1;
  if (modemFailures >= 3) {
    hostSend("+ERR", "modem not responding, resetting it");
    resetModem();
    initModem();
    modemFailures = 0;
  }
  hostSend("+STATUS", csq, registered ? "1" : "0");
}

// -------------------------------------------------------------- main loop --
void setup() {
  wdt_disable();
  Serial.begin(GATEWAY_BAUD);
  modem.begin(MODEM_BAUD);
  wdt_enable(WDTO_8S);   // reboot the Nano if anything ever hangs

  if (!initModem()) {
    hostSend("+ERR", "modem not responding at boot, resetting it");
    resetModem();
    if (!initModem()) hostSend("+ERR", "modem still not responding - check power and wiring");
  }
  memset(forwardedAt, 0, sizeof(forwardedAt));
  hostSend("+READY", FW_VERSION);
  reportStatus();
  rescanSim();            // anything that arrived while we were off
  lastRescan = lastStatus = millis();
}

void loop() {
  wdt_reset();
  readHost();
  if (modemReadLine()) handleModemUrc();

  if (millis() - lastRescan >= RESCAN_MS) {
    lastRescan = millis();
    rescanSim();
  }
  if (millis() - lastStatus >= STATUS_MS) {
    lastStatus = millis();
    reportStatus();
  }
}
