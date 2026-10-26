#!/usr/bin/env python3
"""
water_level_gsm.py
Bridge between the GSM gateway (Arduino Nano + SIM800L on USB serial,
firmware: firmware/gsm_gateway/gsm_gateway.ino) and the water level database
(IOT_water_level).

    Water level sensor --SMS--> gateway Nano --USB serial--> this service --> IOT_water_level
                       <--SMS-- (interval setting)            <-- dashboard: station interval

WHAT IT DOES
------------
1. Readings: every SMS the Nano forwards is matched to a station by the
   sender's SIM number (stations.sim_number), parsed, and saved to
   sensor_data. Every SMS is also logged in gsm_messages, parsed or not.
   The Nano keeps each SMS on its SIM until this service has saved it and
   replies ACK, so nothing is lost while the server is down.
2. Interval settings: when a station's report_interval_minutes (set in the
   dashboard) differs from the interval the sensor last reported using
   (applied_interval_minutes), the setting is texted to the sensor. The
   sensor confirms with WLACK and includes its interval in every reading;
   either one marks the setting as applied. Unconfirmed settings are resent
   every WATER_GSM_RESEND_MINUTES.

SMS FORMAT (sensor firmware: firmware/water_level_sensor/water_level_sensor.ino)
--------------------------------------------------------------------------------
  Reading  (sensor -> gateway):  WL1,<station_mn>,<seq>,<distance_cm>,<battery_mV>,<temp_tenths_C|NA>,<interval_min>
           e.g. WL1,WLS001,42,153,3912,281,15
  Ack      (sensor -> gateway):  WLACK,<station_mn>,<interval_min>
  Power-on (sensor -> gateway):  WLON,<station_mn>,<interval_min>   (once after each power-up)
  SMS test (gateway -> sensor):  WLPING                             (dashboard Test SMS button)
  Test reply (sensor -> gateway): WLPONG,<station_mn>,<interval_min>,<distance_cm>,<battery_mV>
  Setting  (gateway -> sensor):  WLCFG,<interval_min>

  distance_cm is the ultrasonic distance from the sensor down to the water.
  Water level = station installation_height (m, sensor height above the
  reference point) - distance. Without an installation height only the
  distance is stored.

SERIAL PROTOCOL (Nano <-> this service, one line each, '|' separated)
---------------------------------------------------------------------
  Nano -> server:  +READY|<fw>              modem initialised
                   +SMS|<idx>|<sender>|<timestamp>|<body>
                   +SENT|<ref>  /  +FAIL|<ref>|<reason>
                   +STATUS|<csq>|<registered 0/1>
                   +PONG  /  +ERR|<text>
  Server -> Nano:  ACK|<idx>                delete SMS <idx> from the SIM
                   SEND|<ref>|<number>|<text>
                   PING

CONFIGURATION (scripts/.env)
----------------------------
  WATER_GSM_ENABLED=true
  WATER_GSM_SERIAL_PORT=/dev/ttyUSB0      prefer /dev/serial/by-id/... (stable across reboots)
  WATER_GSM_BAUDRATE=57600                must match GATEWAY_BAUD in the Nano firmware
  WATER_GSM_RESEND_MINUTES=30             resend an unconfirmed interval after this long
  WATER_LAN_POLL_SEC=60                   read stations with an RS485-to-LAN converter (their IP /
                                          Port / Modbus address in the dashboard) this often; 0 = off
plus the shared SYSTEM_DB_* settings and WATER_LEVEL_DB_NAME.

The service user needs access to the serial device (member of the
'dialout' group); install_services.sh adds it.
"""

import logging
import os
import re
import signal
import sys
import threading
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

import psycopg2
import psycopg2.extras
import serial
from log_throttle import throttled
from dotenv import load_dotenv

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

DB_HOST = os.getenv("SYSTEM_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("SYSTEM_DB_PORT", 5432))
DB_USER = os.getenv("SYSTEM_DB_USER")
DB_PASSWORD = os.getenv("SYSTEM_DB_PASSWORD")
DB_NAME = os.getenv("WATER_LEVEL_DB_NAME", "IOT_water_level")
LOG_DB_NAME = os.getenv("LOG_DB_NAME", "IOT_service_logs")

GSM_ENABLED = os.getenv("WATER_GSM_ENABLED", "false").strip().lower() in ("1", "true", "yes", "on", "enabled")
SERIAL_PORT = os.getenv("WATER_GSM_SERIAL_PORT", "/dev/ttyUSB0")
BAUDRATE = int(os.getenv("WATER_GSM_BAUDRATE", 57600))
RESEND_MINUTES = int(os.getenv("WATER_GSM_RESEND_MINUTES", 30))
# LAN path: stations with an RS485-to-LAN converter (its IP/port/Modbus
# address are the station's IP / Port / Slave in the dashboard) are read over
# Modbus RTU-over-TCP this often. 0 = off.
LAN_POLL_SEC = int(os.getenv("WATER_LAN_POLL_SEC", 60))
LAN_DEFAULT_PORT = 8899
LAN_STALE_SEC = 300        # station's own measurement older than this is not stored

SEND_CHECK_SEC = 15        # how often to look for settings to send
PING_SEC = 60              # liveness check of the Nano
NANO_SILENT_SEC = 180      # reconnect if the Nano says nothing for this long
FAIL_RETRY_MINUTES = 5     # retry a failed SMS send after this long

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger("water_level_gsm")
# pymodbus logs an ERROR for every failed LAN poll; the root logger is saved to
# service_logs, so an offline converter would add one a minute. Our own
# throttled warning in poll_lan_station() reports it instead.
logging.getLogger("pymodbus").setLevel(logging.CRITICAL)

# Log categories for the dashboard's Logs page (stored in service_logs.category
# by db_logging.py). Messages without one are "system".
DEVICE = {"category": "device"}
SECURITY = {"category": "security"}

if DB_PASSWORD:
    from db_logging import attach_db_logging
    _log_dsn = f"host={DB_HOST} port={DB_PORT} dbname={LOG_DB_NAME} user={DB_USER} password={DB_PASSWORD}"
    attach_db_logging(logging.getLogger(), _log_dsn, service_name="water_level_gsm", table="service_logs")

_running = True


def _stop(signum, _frame):
    global _running
    logger.info(f"Signal {signum} received, stopping.")
    _running = False


# ----------------------------------------------------------------------
# Parsing (pure functions — no I/O, easy to test)
# ----------------------------------------------------------------------
class ParseError(ValueError):
    pass


def normalize_number(number: str) -> str:
    """'+63 917-123-4567', '09171234567', '639171234567' -> '+639171234567'.
    Same rules as WaterLevelStationController::prepareSimNumber()."""
    n = re.sub(r"[\s\-().]", "", number or "")
    if re.fullmatch(r"0(9\d{9})", n):
        return "+63" + n[1:]
    if re.fullmatch(r"63\d{10}", n):
        return "+" + n
    return n


def parse_modem_timestamp(ts: str, now: datetime | None = None) -> datetime:
    """SIM800 SMS timestamp 'yy/MM/dd,hh:mm:ss±zz' (zz = quarter hours) ->
    aware UTC datetime. Falls back to now when missing or implausible
    (wrong network clock: more than 10 min in the future or 7 days old)."""
    now = now or datetime.now(timezone.utc)
    m = re.fullmatch(r"(\d{2})/(\d{2})/(\d{2}),(\d{2}):(\d{2}):(\d{2})([+-])(\d{1,2})", (ts or "").strip())
    if not m:
        return now
    yy, mo, dd, hh, mi, ss, sign, quarters = m.groups()
    try:
        offset = timedelta(minutes=15 * int(quarters)) * (1 if sign == "+" else -1)
        local = datetime(2000 + int(yy), int(mo), int(dd), int(hh), int(mi), int(ss), tzinfo=timezone(offset))
    except ValueError:
        return now
    sent = local.astimezone(timezone.utc)
    if sent > now + timedelta(minutes=10) or sent < now - timedelta(days=7):
        return now
    return sent


def _int(value: str, field: str, lo: int, hi: int) -> int:
    try:
        n = int(value)
    except (TypeError, ValueError):
        raise ParseError(f"{field} is not a number: {value!r}")
    if not lo <= n <= hi:
        raise ParseError(f"{field} out of range ({lo}-{hi}): {n}")
    return n


def parse_sensor_sms(body: str) -> dict:
    """Parses a sensor SMS body. Returns {'type': 'reading'|'ack', ...}."""
    text = (body or "").strip()
    parts = [p.strip() for p in text.split(",")]
    kind = parts[0].upper() if parts else ""

    if kind == "WL1":
        if len(parts) != 7:
            raise ParseError(f"WL1 needs 7 fields, got {len(parts)}")
        _, station_mn, seq, distance_cm, battery_mv, temp, interval = parts
        if not station_mn:
            raise ParseError("missing station_mn")
        temperature = None
        if temp.upper() != "NA":
            temperature = _int(temp, "temperature", -400, 1000) / 10.0
        # 0 = the sensor got no echo. Stored as "no reading": as 0 m it would
        # make water_level equal the installation height, a false flood.
        distance = _int(distance_cm, "distance_cm", 0, 10000)
        return {
            "type": "reading",
            "station_mn": station_mn,
            "seq": _int(seq, "seq", 0, 2**31 - 1),
            "distance_m": distance / 100.0 if distance > 0 else None,
            "battery_v": _int(battery_mv, "battery_mV", 0, 20000) / 1000.0,
            "temperature": temperature,
            "interval": _int(interval, "interval", 1, 1440),
        }

    if kind == "WLPONG":   # answer to the dashboard's SMS test (WLPING)
        if len(parts) != 5:
            raise ParseError(f"WLPONG needs 5 fields, got {len(parts)}")
        _, station_mn, interval, distance_cm, battery_mv = parts
        distance = _int(distance_cm, "distance_cm", 0, 10000)
        return {
            "type": "pong",
            "station_mn": station_mn,
            "interval": _int(interval, "interval", 1, 1440),
            "distance_m": distance / 100.0 if distance > 0 else None,
            "battery_v": _int(battery_mv, "battery_mV", 0, 20000) / 1000.0,
        }

    if kind in ("WLACK", "WLON"):
        if len(parts) != 3:
            raise ParseError(f"{kind} needs 3 fields, got {len(parts)}")
        return {"type": "ack" if kind == "WLACK" else "power_on",
                "station_mn": parts[1], "interval": _int(parts[2], "interval", 1, 1440)}

    raise ParseError(f"unknown message type {kind!r}")


def pong_summary(msg: dict) -> str:
    distance = f"distance {msg['distance_m']:.2f} m" if msg["distance_m"] is not None else "no echo from the sensor"
    return f"Station {msg['station_mn']}: {distance}, battery {msg['battery_v']:.2f} V, interval {msg['interval']} min."


def water_level_from(installation_height, distance_m):
    if installation_height is None or distance_m is None:
        return None
    return round(float(installation_height) - distance_m, 3)


# ----------------------------------------------------------------------
# Database
# ----------------------------------------------------------------------
class Store:
    def __init__(self):
        self.conn = None

    def connect(self):
        if self.conn is not None and not self.conn.closed:
            return
        self.conn = psycopg2.connect(host=DB_HOST, port=DB_PORT, dbname=DB_NAME, user=DB_USER, password=DB_PASSWORD)
        self.conn.autocommit = False
        logger.info(f"Connected to database '{DB_NAME}'.")

    def close(self):
        if self.conn is not None:
            self.conn.close()
            self.conn = None

    def _cur(self):
        self.connect()
        return self.conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)

    def station_by_sim(self, sender: str):
        with self._cur() as cur:
            cur.execute("""
                SELECT id, station_mn, enabled, installation_height, report_interval_minutes, applied_interval_minutes
                FROM stations
                WHERE sim_number = %s AND deleted_at IS NULL
            """, (sender,))
            return cur.fetchone()

    def lan_stations(self):
        """Enabled stations with an RS485-to-LAN converter address."""
        with self._cur() as cur:
            cur.execute("""
                SELECT station_mn, installation_height, lead_ip, lead_port, lead_slave
                FROM stations
                WHERE deleted_at IS NULL AND enabled AND COALESCE(lead_ip, '') <> ''
                ORDER BY station_mn
            """)
            rows = cur.fetchall()
        self.conn.commit()
        return rows

    def insert_lan_reading(self, station_mn, r):
        with self._cur() as cur:
            cur.execute("""
                INSERT INTO sensor_data (station_mn, water_level, distance, battery_voltage, temperature, seq, recorded_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """, (station_mn, r["water_level"], r["distance_m"], r["battery_v"], r["temperature"], r["seq"], r["recorded_at"]))
        self.conn.commit()

    def log_message(self, cur, sender, modem_ts, body, ok, error, station_mn):
        cur.execute("""
            INSERT INTO gsm_messages (sender, modem_timestamp, raw_body, parsed_ok, parse_error, station_mn)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (sender, modem_ts, body, ok, error, station_mn))

    def handle_sms(self, sender_raw: str, modem_ts: str, body: str) -> None:
        """Parses and stores one SMS in a single transaction. Never raises for
        bad content (it's logged in gsm_messages instead); raises only on DB
        failure, so the caller can withhold the ACK and retry later."""
        sender = normalize_number(sender_raw)
        station = None
        error = None
        msg = None

        try:
            station = self.station_by_sim(sender)
            if station is None:
                raise ParseError(f"no station has SIM number {sender}")
            msg = parse_sensor_sms(body)
            if msg["station_mn"] != station["station_mn"]:
                raise ParseError(f"message is for station {msg['station_mn']} but {sender} belongs to {station['station_mn']}")
        except ParseError as e:
            error = str(e)

        with self._cur() as cur:
            try:
                station_mn = station["station_mn"] if station else None
                self.log_message(cur, sender, modem_ts, body, error is None, error, station_mn)

                if error is None:
                    if msg["type"] == "reading":
                        recorded_at = parse_modem_timestamp(modem_ts)
                        cur.execute("""
                            INSERT INTO sensor_data (station_mn, water_level, distance, battery_voltage, temperature, seq, recorded_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """, (
                            station_mn,
                            water_level_from(station["installation_height"], msg["distance_m"]),
                            msg["distance_m"], msg["battery_v"], msg["temperature"], msg["seq"], recorded_at,
                        ))
                    elif msg["type"] == "pong":
                        cur.execute(
                            "UPDATE stations SET sms_test_replied_at = NOW(), sms_test_result = %s WHERE id = %s",
                            (pong_summary(msg), station["id"]))
                    self._record_applied_interval(cur, station, msg["interval"])
                self.conn.commit()
            except Exception:
                self.conn.rollback()
                raise

        if error:
            logger.warning(f"SMS from {sender} not stored as a reading: {error} | body={body!r}", extra=DEVICE)
        elif msg["type"] == "reading":
            if msg["distance_m"] is None:
                logger.warning(f"Reading from {station['station_mn']}: the sensor got no echo (check the sensor and its power), battery {msg['battery_v']} V, seq {msg['seq']}", extra=DEVICE)
            else:
                # DEBUG: every SMS is already kept in gsm_messages.
                logger.debug(f"Reading from {station['station_mn']}: distance {msg['distance_m']} m, battery {msg['battery_v']} V, seq {msg['seq']}", extra=DEVICE)
        elif msg["type"] == "pong":
            logger.info(f"{station['station_mn']} answered the SMS test: {pong_summary(msg)}", extra=DEVICE)
        elif msg["type"] == "power_on":
            logger.info(f"{station['station_mn']} powered on (interval {msg['interval']} min)", extra=DEVICE)
        else:
            logger.info(f"{station['station_mn']} confirmed interval {msg['interval']} min", extra=DEVICE)

    def _record_applied_interval(self, cur, station, interval: int):
        """The sensor reports its interval in every message. Record it, and
        stamp interval_applied_at when it now matches the dashboard setting."""
        if station["applied_interval_minutes"] == interval:
            return
        cur.execute("""
            UPDATE stations
            SET applied_interval_minutes = %s,
                interval_applied_at = CASE WHEN report_interval_minutes = %s THEN NOW() ELSE interval_applied_at END
            WHERE id = %s
        """, (interval, interval, station["id"]))

    def settings_to_send(self):
        """Stations whose dashboard interval isn't confirmed yet and that are
        due for a (re)send."""
        with self._cur() as cur:
            cur.execute("""
                SELECT id, station_mn, sim_number, report_interval_minutes
                FROM stations
                WHERE deleted_at IS NULL
                  AND enabled
                  AND sim_number IS NOT NULL
                  AND applied_interval_minutes IS DISTINCT FROM report_interval_minutes
                  AND (interval_sent_at IS NULL OR interval_sent_at < NOW() - make_interval(mins => %s))
                ORDER BY id
            """, (RESEND_MINUTES,))
            rows = cur.fetchall()
        self.conn.commit()
        return rows

    def tests_to_send(self):
        """Stations with a dashboard SMS test (Test SMS button) not sent yet."""
        with self._cur() as cur:
            cur.execute("""
                SELECT id, station_mn, sim_number
                FROM stations
                WHERE deleted_at IS NULL
                  AND sim_number IS NOT NULL
                  AND sms_test_requested_at > NOW() - INTERVAL '10 minutes'   -- don't send a stale test after an outage
                  AND sms_test_sent_at IS NULL
                ORDER BY id
            """)
            rows = cur.fetchall()
        self.conn.commit()
        return rows

    def set_test_status(self, station_id, result, sent=False):
        """Updates the SMS test status shown on the dashboard (sent=True also stamps the send)."""
        with self._cur() as cur:
            cur.execute(
                f"UPDATE stations SET sms_test_result = %s{', sms_test_sent_at = NOW()' if sent else ''} WHERE id = %s",
                (result, station_id))
        self.conn.commit()

    def mark_sent(self, station_id, retry_in_minutes=None):
        """Records a send. With retry_in_minutes (failed send), backdates
        interval_sent_at so settings_to_send() picks it up again sooner."""
        with self._cur() as cur:
            if retry_in_minutes is None:
                cur.execute("UPDATE stations SET interval_sent_at = NOW() WHERE id = %s", (station_id,))
            else:
                cur.execute(
                    "UPDATE stations SET interval_sent_at = NOW() - make_interval(mins => %s) WHERE id = %s",
                    (max(0, RESEND_MINUTES - retry_in_minutes), station_id),
                )
        self.conn.commit()


# ----------------------------------------------------------------------
# Gateway (Nano) link
# ----------------------------------------------------------------------
class Gateway:
    def __init__(self, store: Store, port_factory=None):
        self.store = store
        self.port_factory = port_factory or (lambda: serial.Serial(SERIAL_PORT, BAUDRATE, timeout=1))
        self.port = None
        self.pending_sends = {}          # ref -> ("interval" | "test", station id)
        self.last_status = None          # (registered, weak signal) last logged
        self.next_ref = 1
        self.last_heard = 0.0
        self.last_ping = 0.0
        self.last_send_check = 0.0

    # -- serial helpers --
    def open(self):
        self.port = self.port_factory()
        logger.info(f"Opened GSM gateway on {SERIAL_PORT} at {BAUDRATE} baud; waiting for the Nano.")
        # Opening the port resets most Nanos (DTR); give the bootloader and
        # modem init time, then ask for a status line.
        self.last_heard = time.monotonic()
        self.pending_sends.clear()

    def close(self):
        if self.port is not None:
            try:
                self.port.close()
            except Exception:
                pass
            self.port = None

    def write(self, line: str):
        self.port.write((line + "\n").encode("ascii", errors="replace"))
        self.port.flush()

    # -- incoming --
    def handle_line(self, line: str):
        line = line.strip()
        if not line:
            return
        self.last_heard = time.monotonic()

        if line.startswith("+SMS|"):
            parts = line.split("|", 4)
            if len(parts) != 5:
                logger.warning(f"Malformed SMS line from gateway: {line!r}", extra=DEVICE)
                return
            _, idx, sender, modem_ts, body = parts
            try:
                self.store.handle_sms(sender, modem_ts, body)
            except psycopg2.Error as e:
                # Keep the SMS on the SIM (no ACK); the Nano re-forwards it.
                logger.error(f"Database error, SMS {idx} kept on the SIM for retry: {e}")
                self.store.close()
                return
            self.write(f"ACK|{idx}")

        elif line.startswith("+SENT|") or line.startswith("+FAIL|"):
            parts = line.split("|", 2)
            ref = parts[1] if len(parts) > 1 else ""
            kind, station_id = self.pending_sends.pop(ref, (None, None))
            if station_id is None:
                return
            if kind == "test":
                if line.startswith("+SENT|"):
                    self.store.set_test_status(station_id, "SMS sent to the station; waiting for its reply.")
                else:
                    reason = parts[2] if len(parts) > 2 else "unknown"
                    self.store.set_test_status(station_id, f"Gateway could not send the test SMS: {reason}.")
                    logger.warning(f"SMS test to station id {station_id} not sent: {reason}", extra=DEVICE)
            elif line.startswith("+SENT|"):
                logger.info(f"Interval setting delivered to the network (station id {station_id}).", extra=DEVICE)
            else:
                reason = parts[2] if len(parts) > 2 else "unknown"
                logger.warning(f"Sending interval setting failed (station id {station_id}): {reason}; retrying in {FAIL_RETRY_MINUTES} min.", extra=DEVICE)
                self.store.mark_sent(station_id, retry_in_minutes=FAIL_RETRY_MINUTES)

        elif line.startswith("+READY"):
            logger.info(f"GSM gateway ready ({line}).", extra=DEVICE)
            self.pending_sends.clear()
            self.last_status = None

        elif line.startswith("+STATUS|"):
            # Sent every minute: log only when registration or weak/OK signal
            # changes, not 1440 identical lines a day.
            parts = line.split("|")
            csq = parts[1] if len(parts) > 1 else "?"
            reg = parts[2] if len(parts) > 2 else "?"
            weak = not csq.isdigit() or int(csq) < 10 or int(csq) == 99
            state = (reg == "1", weak)
            if state != self.last_status:
                self.last_status = state
                level = logging.INFO if reg == "1" and not weak else logging.WARNING
                logger.log(level, f"GSM gateway status: signal CSQ {csq}/31{' (weak)' if weak else ''}, network {'registered' if reg == '1' else 'NOT registered'}", extra=DEVICE)

        elif line.startswith("+ERR|"):
            logger.warning(f"GSM gateway: {line[5:]}", extra=DEVICE)

        elif line == "+PONG":
            pass

        else:
            logger.debug(f"Gateway: {line}")

    # -- outgoing --
    def _new_ref(self, kind, station_id):
        ref = str(self.next_ref)
        self.next_ref = self.next_ref % 99999 + 1
        self.pending_sends[ref] = (kind, station_id)
        return ref

    def send_due_settings(self):
        busy = set(self.pending_sends.values())
        for row in self.store.settings_to_send():
            if ("interval", row["id"]) in busy:
                continue
            ref = self._new_ref("interval", row["id"])
            text = f"WLCFG,{row['report_interval_minutes']}"
            self.store.mark_sent(row["id"])
            self.write(f"SEND|{ref}|{row['sim_number']}|{text}")
            logger.info(f"Sending interval {row['report_interval_minutes']} min to {row['station_mn']} ({row['sim_number']}).", extra=DEVICE)
        for row in self.store.tests_to_send():
            ref = self._new_ref("test", row["id"])
            self.store.set_test_status(row["id"], "Test SMS handed to the gateway; sending…", sent=True)
            self.write(f"SEND|{ref}|{row['sim_number']}|WLPING")
            logger.info(f"Sending SMS test to {row['station_mn']} ({row['sim_number']}).", extra=DEVICE)

    def tick(self):
        now = time.monotonic()
        if now - self.last_send_check >= SEND_CHECK_SEC:
            self.last_send_check = now
            self.send_due_settings()
        if now - self.last_ping >= PING_SEC:
            self.last_ping = now
            self.write("PING")
        if now - self.last_heard >= NANO_SILENT_SEC:
            raise ConnectionError(f"no response from the GSM gateway for {NANO_SILENT_SEC}s")

    def run_once(self):
        raw = self.port.readline()
        if raw:
            self.handle_line(raw.decode("ascii", errors="replace"))
        self.tick()


# ----------------------------------------------------------------------
# LAN path: station Nano -> RS485 -> RS485-to-LAN converter -> Modbus
# ----------------------------------------------------------------------
def decode_station_registers(regs, installation_height, now):
    """The station sketch's 6 input registers (see water_level_sensor.ino) ->
    a reading, or None when the station hasn't measured yet or its value is stale."""
    distance_cm, battery_mv, temp_raw, _interval, seq, age_s = regs[:6]
    if age_s == 0xFFFF or age_s > LAN_STALE_SEC:
        return None
    distance_m = distance_cm / 100.0 if distance_cm > 0 else None   # 0 = no echo
    temp = temp_raw - 0x10000 if temp_raw >= 0x8000 else temp_raw
    return {
        "distance_m": distance_m,
        "water_level": water_level_from(installation_height, distance_m),
        "battery_v": battery_mv / 1000.0,
        "temperature": None if temp == -32768 else temp / 10.0,
        "seq": seq,
        "recorded_at": now - timedelta(seconds=age_s),
    }


def poll_lan_station(store, station, client_factory=None):
    from pymodbus.client import ModbusTcpClient
    from pymodbus.framer import FramerType

    mn, ip = station["station_mn"], station["lead_ip"]
    port, slave = station["lead_port"] or LAN_DEFAULT_PORT, station["lead_slave"] or 1
    # Converters in transparent mode pass Modbus RTU frames through TCP
    # (same as the air quality lead sensors).
    client = (client_factory or ModbusTcpClient)(host=ip, port=port, framer=FramerType.RTU, timeout=3)
    try:
        if not client.connect():
            raise ConnectionError(f"could not connect to {ip}:{port}")
        import inspect
        unit = {"device_id" if "device_id" in inspect.signature(client.read_input_registers).parameters else "slave": slave}
        rr = client.read_input_registers(0, count=6, **unit)
        if rr.isError():
            raise IOError(f"Modbus error reply from address {slave}: {rr}")
        reading = decode_station_registers(rr.registers, station["installation_height"], datetime.now(timezone.utc))
        if reading is None:
            if throttled(f"lan-stale:{mn}"):
                logger.warning(f"LAN reading from {mn}: the station has no fresh measurement (sensor problem?).", extra=DEVICE)
            return
        store.insert_lan_reading(mn, reading)
        logger.debug(f"LAN reading from {mn}: distance {reading['distance_m']} m, battery {reading['battery_v']} V")
    except psycopg2.Error:
        raise
    except Exception as e:
        if throttled(f"lan:{mn}:{type(e).__name__}"):
            logger.warning(f"LAN reading from {mn} ({ip}:{port}, Modbus address {slave}) failed: {e}. Repeats are not logged for 30 minutes.", extra=DEVICE)
    finally:
        client.close()


def lan_loop():
    store = Store()
    while _running:
        try:
            for station in store.lan_stations():
                poll_lan_station(store, station)
        except psycopg2.Error as e:
            logger.error(f"LAN polling database problem: {e}. Retrying.")
            store.close()
        for _ in range(LAN_POLL_SEC):
            if not _running:
                break
            time.sleep(1)
    store.close()


def main():
    signal.signal(signal.SIGTERM, _stop)
    signal.signal(signal.SIGINT, _stop)

    if LAN_POLL_SEC > 0:
        threading.Thread(target=lan_loop, daemon=True, name="LanPoll").start()

    if not GSM_ENABLED:
        logger.info("WATER_GSM_ENABLED is not true in scripts/.env — GSM gateway disabled (LAN polling still runs).")
        # Stay alive (systemd Restart=on-failure would otherwise loop);
        # enabling it later only needs a service restart.
        while _running:
            time.sleep(5)
        return 0

    store = Store()
    gateway = Gateway(store)

    while _running:
        try:
            if gateway.port is None:
                gateway.open()
            gateway.run_once()
        except (serial.SerialException, OSError, ConnectionError) as e:
            # Retried every 10s: log each distinct problem once per 30 min, not 8640 times a day.
            if throttled(f"gsm-link:{e}"):
                logger.error(f"GSM gateway link problem: {e}. Reconnecting every 10s; repeats are not logged for 30 minutes.", extra=DEVICE)
            gateway.close()
            time.sleep(10)
        except psycopg2.Error as e:
            logger.error(f"Database problem: {e}. Retrying in 10s.")
            store.close()
            time.sleep(10)

    gateway.close()
    store.close()
    return 0


def _self_check():
    """python3 water_level_gsm.py --check"""
    now = datetime(2026, 10, 20, 1, 0, tzinfo=timezone.utc)
    assert parse_sensor_sms("WLON,WLS001,3") == {"type": "power_on", "station_mn": "WLS001", "interval": 3}
    assert parse_sensor_sms("WLACK,WLS001,3")["type"] == "ack"
    pong = parse_sensor_sms("WLPONG,WLS001,3,161,8750")
    assert pong == {"type": "pong", "station_mn": "WLS001", "interval": 3, "distance_m": 1.61, "battery_v": 8.75}
    assert pong_summary(pong) == "Station WLS001: distance 1.61 m, battery 8.75 V, interval 3 min."
    assert "no echo" in pong_summary(parse_sensor_sms("WLPONG,WLS001,3,0,8750"))
    r = decode_station_registers([315, 3912, 281, 15, 77, 12], 5.5, now)
    assert r["distance_m"] == 3.15 and r["water_level"] == 2.35 and r["battery_v"] == 3.912
    assert r["temperature"] == 28.1 and r["seq"] == 77 and r["recorded_at"] == now - timedelta(seconds=12)
    r = decode_station_registers([0, 3700, 0x10000 - 45, 15, 1, 5], 5.5, now)
    assert r["distance_m"] is None and r["water_level"] is None, "no echo -> no water level"
    assert r["temperature"] == -4.5
    assert decode_station_registers([300, 3700, 0x8000, 15, 1, 5], None, now)["temperature"] is None
    assert decode_station_registers([300, 3700, 0, 15, 1, 5], None, now)["water_level"] is None   # no height set
    assert decode_station_registers([300, 3700, 0, 15, 1, 0xFFFF], 5.5, now) is None, "not measured yet"
    assert decode_station_registers([300, 3700, 0, 15, 1, LAN_STALE_SEC + 1], 5.5, now) is None, "stale"
    print("water_level_gsm LAN decoding OK")


if __name__ == "__main__":
    if "--check" in sys.argv:
        _self_check()
        sys.exit(0)
    sys.exit(main())
