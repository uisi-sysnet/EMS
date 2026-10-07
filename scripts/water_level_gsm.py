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
plus the shared SYSTEM_DB_* settings and WATER_LEVEL_DB_NAME.

The service user needs access to the serial device (member of the
'dialout' group); install_services.sh adds it.
"""

import logging
import os
import re
import signal
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path

import psycopg2
import psycopg2.extras
import serial
from dotenv import load_dotenv

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

DB_HOST = os.getenv("SYSTEM_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("SYSTEM_DB_PORT", 5432))
DB_USER = os.getenv("SYSTEM_DB_USER")
DB_PASSWORD = os.getenv("SYSTEM_DB_PASSWORD")
DB_NAME = os.getenv("WATER_LEVEL_DB_NAME", "IOT_water_level")
LOG_DB_NAME = os.getenv("LOG_DB_NAME", "IOT_service_logs")

GSM_ENABLED = os.getenv("WATER_GSM_ENABLED", "false").strip().lower() in ("1", "true", "yes", "on")
SERIAL_PORT = os.getenv("WATER_GSM_SERIAL_PORT", "/dev/ttyUSB0")
BAUDRATE = int(os.getenv("WATER_GSM_BAUDRATE", 57600))
RESEND_MINUTES = int(os.getenv("WATER_GSM_RESEND_MINUTES", 30))

SEND_CHECK_SEC = 15        # how often to look for settings to send
PING_SEC = 60              # liveness check of the Nano
NANO_SILENT_SEC = 180      # reconnect if the Nano says nothing for this long
FAIL_RETRY_MINUTES = 5     # retry a failed SMS send after this long

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
logger = logging.getLogger("water_level_gsm")

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
        return {
            "type": "reading",
            "station_mn": station_mn,
            "seq": _int(seq, "seq", 0, 2**31 - 1),
            "distance_m": _int(distance_cm, "distance_cm", 0, 10000) / 100.0,
            "battery_v": _int(battery_mv, "battery_mV", 0, 20000) / 1000.0,
            "temperature": temperature,
            "interval": _int(interval, "interval", 1, 1440),
        }

    if kind == "WLACK":
        if len(parts) != 3:
            raise ParseError(f"WLACK needs 3 fields, got {len(parts)}")
        return {"type": "ack", "station_mn": parts[1], "interval": _int(parts[2], "interval", 1, 1440)}

    raise ParseError(f"unknown message type {kind!r}")


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
                    self._record_applied_interval(cur, station, msg["interval"])
                self.conn.commit()
            except Exception:
                self.conn.rollback()
                raise

        if error:
            logger.warning(f"SMS from {sender} not stored as a reading: {error} | body={body!r}", extra=DEVICE)
        elif msg["type"] == "reading":
            logger.info(f"Reading from {station['station_mn']}: distance {msg['distance_m']} m, battery {msg['battery_v']} V, seq {msg['seq']}", extra=DEVICE)
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
        self.pending_sends = {}          # ref -> station id
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
            station_id = self.pending_sends.pop(ref, None)
            if station_id is None:
                return
            if line.startswith("+SENT|"):
                logger.info(f"Interval setting delivered to the network (station id {station_id}).", extra=DEVICE)
            else:
                reason = parts[2] if len(parts) > 2 else "unknown"
                logger.warning(f"Sending interval setting failed (station id {station_id}): {reason}; retrying in {FAIL_RETRY_MINUTES} min.", extra=DEVICE)
                self.store.mark_sent(station_id, retry_in_minutes=FAIL_RETRY_MINUTES)

        elif line.startswith("+READY"):
            logger.info(f"GSM gateway ready ({line}).", extra=DEVICE)
            self.pending_sends.clear()

        elif line.startswith("+STATUS|"):
            parts = line.split("|")
            csq = parts[1] if len(parts) > 1 else "?"
            reg = parts[2] if len(parts) > 2 else "?"
            level = logging.INFO if reg == "1" else logging.WARNING
            logger.log(level, f"GSM gateway status: signal CSQ {csq}/31, network {'registered' if reg == '1' else 'NOT registered'}", extra=DEVICE)

        elif line.startswith("+ERR|"):
            logger.warning(f"GSM gateway: {line[5:]}", extra=DEVICE)

        elif line == "+PONG":
            pass

        else:
            logger.debug(f"Gateway: {line}")

    # -- outgoing --
    def send_due_settings(self):
        busy = set(self.pending_sends.values())
        for row in self.store.settings_to_send():
            if row["id"] in busy:
                continue
            ref = str(self.next_ref)
            self.next_ref = self.next_ref % 99999 + 1
            text = f"WLCFG,{row['report_interval_minutes']}"
            self.pending_sends[ref] = row["id"]
            self.store.mark_sent(row["id"])
            self.write(f"SEND|{ref}|{row['sim_number']}|{text}")
            logger.info(f"Sending interval {row['report_interval_minutes']} min to {row['station_mn']} ({row['sim_number']}).", extra=DEVICE)

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


def main():
    signal.signal(signal.SIGTERM, _stop)
    signal.signal(signal.SIGINT, _stop)

    if not GSM_ENABLED:
        logger.info("WATER_GSM_ENABLED is not true in scripts/.env — GSM gateway disabled. Sleeping.")
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
            logger.error(f"GSM gateway link problem: {e}. Reconnecting in 10s.", extra=DEVICE)
            gateway.close()
            time.sleep(10)
        except psycopg2.Error as e:
            logger.error(f"Database problem: {e}. Retrying in 10s.")
            store.close()
            time.sleep(10)

    gateway.close()
    store.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
