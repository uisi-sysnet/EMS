#!/usr/bin/env python3
"""
Seismic Ingestion Service
Subscribes to MQTT station telemetry and writes it to the
`seismic_sensor_data` TimescaleDB database. No API code lives here —
see api_server.py.
"""

import os
import json
import logging
import threading
import time
from datetime import datetime, timezone
from pathlib import Path
import paho.mqtt.client as mqtt
import psycopg
from psycopg import sql
from dotenv import load_dotenv

try:
    from sim800l import SIM800L, SIM800LError
except ImportError:
    # pyserial (or sim800l.py itself) isn't available — SMS ingestion is
    # disabled automatically; MQTT ingestion is unaffected either way.
    SIM800L = None
    SIM800LError = Exception

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

# ---- Database config (SEISMIC_ prefixed to share one .env with the other services) ----
DB_HOST = os.getenv("SEISMIC_DB_HOST", "localhost")
DB_PORT = os.getenv("SEISMIC_DB_PORT", "5432")
DB_USER = os.getenv("SEISMIC_DB_USER", "seismic_user")
DB_PASSWORD = os.getenv("SEISMIC_DB_PASSWORD")
DB_NAME = os.getenv("SEISMIC_DB_NAME", "seismic_sensor_data")

# ---- MQTT config ----
MQTT_BROKER_HOST = os.getenv("MQTT_BROKER_HOST", "localhost")
MQTT_BROKER_PORT = int(os.getenv("MQTT_BROKER_PORT", 1883))
MQTT_TIMEOUT_SEC = int(os.getenv("MQTT_TIMEOUT_SEC", 60))
MQTT_TOPIC = os.getenv("MQTT_TOPIC", "seismic/stations/+/telemetry")
MQTT_USER = os.getenv("MQTT_USER")
MQTT_PASSWORD = os.getenv("MQTT_PASSWORD")

# ---- SMS config (SIM800L second ingestion channel) ----
# See sim800l.py's module docstring for wiring notes. This only opens a
# serial device path — SIM800_SERIAL_PORT tells it which one; it never
# addresses GPIO pins directly.
SMS_INGESTION_ENABLED = os.getenv("SMS_INGESTION_ENABLED", "true").strip().lower() == "true"
SIM800_SERIAL_PORT = os.getenv("SIM800_SERIAL_PORT", "/dev/serial0")
SIM800_BAUDRATE = int(os.getenv("SIM800_BAUDRATE", 9600))
# Full-inbox safety-net sweep interval, in case a +CMTI notification is ever
# missed (e.g. this service wasn't running when an SMS arrived).
SMS_POLL_INTERVAL_SEC = int(os.getenv("SMS_POLL_INTERVAL_SEC", 30))
# Optional comma-separated allowlist of sender phone numbers (e.g. as shown
# by the modem, typically E.164 like +639171234567). Blank = accept from any
# sender (messages are still validated by format tag + checksum either way).
SMS_ALLOWED_SENDERS = {s.strip() for s in os.getenv("SMS_ALLOWED_SENDERS", "").split(",") if s.strip()}

BASE_CONN_STRING = f"host={DB_HOST} user={DB_USER} password={DB_PASSWORD} port={DB_PORT}"
APP_DB_CONN_STRING = f"{BASE_CONN_STRING} dbname={DB_NAME}"

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

# ---- Database-backed logging ----
# Logs are mirrored into the `service_logs` table in the shared air_quality
# database (same one air_quality_ingest.py and api_server.py use), tagged
# service='seismic_mqtt', so all three services' logs live in one queryable
# place instead of scattered log files. Console output (captured by
# systemd's journal when run as a service) remains the fallback.
DB_LOG_ENABLED = os.getenv("DB_LOG_ENABLED", "true").strip().lower() == "true"
DB_LOG_TABLE = os.getenv("DB_LOG_TABLE", "service_logs")
LOG_DB_HOST = os.getenv("AQ_DB_HOST", DB_HOST)
LOG_DB_PORT = os.getenv("AQ_DB_PORT", DB_PORT)
LOG_DB_NAME = os.getenv("AQ_DB_NAME", "air_quality")
LOG_DB_USER = os.getenv("AQ_DB_USER", DB_USER)
LOG_DB_PASSWORD = os.getenv("AQ_DB_PASSWORD", DB_PASSWORD)

if DB_LOG_ENABLED and LOG_DB_PASSWORD:
    from db_logging import attach_db_logging
    _log_dsn = f"host={LOG_DB_HOST} port={LOG_DB_PORT} dbname={LOG_DB_NAME} user={LOG_DB_USER} password={LOG_DB_PASSWORD}"
    attach_db_logging(logging.getLogger(), _log_dsn, service_name="seismic_mqtt", table=DB_LOG_TABLE)


def initialize_database():
    """Validates data cluster health, generates databases, schemas, and maps TimescaleDB hypertables."""
    try:
        with psycopg.connect(f"{BASE_CONN_STRING} dbname=postgres", autocommit=True) as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT 1 FROM pg_database WHERE datname = %s;", (DB_NAME,))
                if not cur.fetchone():
                    logging.info(f"Target cluster '{DB_NAME}' missing. Initializing new storage block...")
                    cur.execute(sql.SQL("CREATE DATABASE {}").format(sql.Identifier(DB_NAME)))
                else:
                    logging.info(f"Target cluster '{DB_NAME}' verified operational.")
    except Exception as e:
        logging.critical(f"Database structural setup failure: {e}")
        raise

    try:
        with psycopg.connect(APP_DB_CONN_STRING, autocommit=True) as conn:
            with conn.cursor() as cur:
                cur.execute("CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;")

                cur.execute("""
                    CREATE TABLE IF NOT EXISTS station_metrics (
                        time TIMESTAMPTZ NOT NULL,
                        station_id VARCHAR(50) NOT NULL
                    );
                """)

                cur.execute("""
                    SELECT 1 FROM _timescaledb_catalog.hypertable
                    WHERE table_name = 'station_metrics';
                """)
                if not cur.fetchone():
                    logging.info("Optimizing data table via TimescaleDB hypertable engine partitioning...")
                    cur.execute("SELECT create_hypertable('station_metrics', by_range('time'), if_not_exists => TRUE);")

                required_columns = {
                    "station_name": "VARCHAR(100)",
                    "latitude": "DOUBLE PRECISION",
                    "longitude": "DOUBLE PRECISION",
                    "elevation_m": "REAL",
                    "acc_x": "REAL", "acc_y": "REAL", "acc_z": "REAL",
                    "vel_x": "REAL", "vel_y": "REAL", "vel_z": "REAL",
                    "disp_x": "REAL", "disp_y": "REAL", "disp_z": "REAL",
                    "pga": "REAL",
                    "peis": "INT",
                    "source": "VARCHAR(10)",
                }

                for col_name, col_type in required_columns.items():
                    cur.execute("""
                        SELECT 1 FROM information_schema.columns
                        WHERE table_name='station_metrics' AND column_name=%s;
                    """, (col_name,))

                    if not cur.fetchone():
                        logging.info(f"Injecting structural migration update column: '{col_name}'")
                        alter_query = sql.SQL("ALTER TABLE station_metrics ADD COLUMN {} {}").format(
                            sql.Identifier(col_name),
                            sql.SQL(col_type)
                        )
                        cur.execute(alter_query)

                # Raw SMS log — every message received via the SIM800L channel is
                # stored here regardless of whether it parsed successfully, so
                # nothing from the modem is ever silently lost. Successfully
                # parsed messages ALSO land in station_metrics (source='sms') via
                # insert_station_metrics(), same as MQTT readings.
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS sms_messages (
                        id SERIAL PRIMARY KEY,
                        received_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                        sender VARCHAR(32),
                        modem_timestamp VARCHAR(32),
                        raw_body TEXT,
                        parsed_ok BOOLEAN NOT NULL DEFAULT FALSE,
                        parse_error TEXT,
                        station_id VARCHAR(50)
                    );
                """)
                cur.execute("""
                    CREATE INDEX IF NOT EXISTS idx_sms_messages_received_at
                    ON sms_messages (received_at DESC);
                """)

        logging.info("Storage clustering systems finalized and running cleanly.")
    except Exception as e:
        logging.critical(f"Storage architecture sync failed: {e}")
        raise


# ---- Persistent data connection ----
# paho-mqtt's loop_forever() drives on_connect/on_message from a single
# network thread, so a single reused connection (guarded by a lock, just in
# case) is safe and avoids paying a fresh TCP-connect + auth handshake for
# every incoming telemetry message — meaningful savings on a Raspberry Pi's
# more limited CPU/network budget compared to Postgres running on a bigger box.
_data_conn = None
_data_conn_lock = threading.Lock()


def get_data_connection():
    global _data_conn
    with _data_conn_lock:
        if _data_conn is None or _data_conn.closed:
            _data_conn = psycopg.connect(APP_DB_CONN_STRING)
        return _data_conn


def reset_data_connection():
    """Called after a failed insert so the next message reconnects instead
    of reusing a connection that's in an unknown/broken state."""
    global _data_conn
    with _data_conn_lock:
        if _data_conn is not None:
            try:
                _data_conn.close()
            except Exception:
                pass
        _data_conn = None


def insert_station_metrics(data: dict, source: str):
    """Shared insert path for both ingestion channels. `data` uses the same
    shape as the MQTT JSON payload: station_id, timestamp, location{},
    measurements{acceleration{},velocity{},displacement{}}, pga, peis.
    `source` is 'mqtt' or 'sms', recorded per-row for traceability."""
    loc = data.get('location', {}) or {}
    measurements = data.get('measurements', {}) or {}
    acc = measurements.get('acceleration', {}) or {}
    vel = measurements.get('velocity', {}) or {}
    disp = measurements.get('displacement', {}) or {}

    station_identity = data.get('station_id')
    timestamp_value = data.get('timestamp')
    if not station_identity:
        raise ValueError("station_id is required")
    if not timestamp_value:
        raise ValueError("timestamp is required")

    peis_raw = data.get('peis')
    peis_value = int(float(peis_raw)) if peis_raw is not None else None

    query = """
        INSERT INTO station_metrics (
            time, station_id, station_name,
            latitude, longitude, elevation_m,
            acc_x, acc_y, acc_z,
            vel_x, vel_y, vel_z,
            disp_x, disp_y, disp_z,
            pga, peis, source
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s);
    """
    params = (
        timestamp_value,
        station_identity,
        data.get('station_name'),
        loc.get('latitude'),
        loc.get('longitude'),
        loc.get('elevation_m'),
        acc.get('x'), acc.get('y'), acc.get('z'),
        vel.get('x'), vel.get('y'), vel.get('z'),
        disp.get('x'), disp.get('y'), disp.get('z'),
        data.get('pga'),
        peis_value,
        source,
    )

    try:
        conn = get_data_connection()
        with conn.cursor() as cur:
            cur.execute(query, params)
        conn.commit()
    except Exception as e:
        reset_data_connection()
        raise RuntimeError(f"station_metrics insert failed: {e}") from e


def on_connect(client, userdata, flags, rc, properties=None):
    if rc == 0:
        logging.info(f"Successfully authenticated and connected to MQTT Broker. Subscribing to: {MQTT_TOPIC}")
        client.subscribe(MQTT_TOPIC)
    else:
        logging.error(f"MQTT Session rejected by server broker. Status code error: {rc}")


def on_message(client, userdata, msg):
    """Processes network telemetry queues, parses topics dynamically, and saves them to the hypertable."""
    try:
        logging.info(f"Incoming message caught on topic: {msg.topic}")

        topic_parts = msg.topic.split('/')
        extracted_station_id = topic_parts[2] if len(topic_parts) >= 4 else "UNKNOWN"

        payload_raw = msg.payload.decode('utf-8')
        data = json.loads(payload_raw)

        if not data.get('station_id'):
            data['station_id'] = extracted_station_id

        if not data.get('timestamp'):
            logging.error("Payload rejected: 'timestamp' key is missing from JSON.")
            return

        insert_station_metrics(data, source="mqtt")
        logging.info(f"SUCCESS: Ingested telemetry to TimescaleDB for: {data['station_id']}")

    except json.JSONDecodeError:
        logging.error("Payload failed parsing: Received message is not valid JSON.")
    except Exception as e:
        logging.error(f"Database insertion failed: {e}")


# ============================================================
# SMS ingestion (SIM800L)
# ============================================================
#
# SMS PAYLOAD FORMAT — "SEISMSG1"
# --------------------------------
# A single comma-separated line, designed to fit in one 160-character GSM-7
# SMS segment and carry the same fields as the MQTT JSON payload:
#
#   SEISMSG1,<station_id>,<epoch_ts>,<lat>,<lon>,<elev_m>,<acc_x>,<acc_y>,<acc_z>,<vel_x>,<vel_y>,<vel_z>,<disp_x>,<disp_y>,<disp_z>,<pga>,<peis>,<checksum>
#
# Fields:
#   1. "SEISMSG1"    — literal format/version tag. Any SMS that doesn't
#                      start with this is ignored as telemetry (but still
#                      logged to sms_messages) — this is what lets the
#                      SIM card also receive normal texts (e.g. from a
#                      carrier) without them being mistaken for readings.
#   2. station_id    — matches the station identifier used on the MQTT side.
#   3. epoch_ts       — Unix timestamp, seconds, UTC (station's own clock).
#   4-6. lat,lon,elev_m — station location. OPTIONAL — leave blank
#                      (consecutive commas) if the station doesn't send
#                      location every message; it'll be stored as NULL.
#   7-9. acc_x,acc_y,acc_z    — acceleration
#   10-12. vel_x,vel_y,vel_z  — velocity
#   13-15. disp_x,disp_y,disp_z — displacement
#   16. pga           — peak ground acceleration
#   17. peis          — intensity code (integer)
#   18. checksum      — OPTIONAL but recommended: 2-digit uppercase hex of
#                      (sum of ASCII codes of every character before the
#                      checksum's own comma, including that comma) mod 256.
#                      Catches messages truncated/corrupted by a weak GSM
#                      signal. If omitted, the message is processed without
#                      an integrity check.
#
# Example (with location, with checksum):
#   SEISMSG1,STN-004,1721818530,14.5995,120.9842,15.2,0.012,-0.008,0.021,0.5,0.3,0.6,1.2,0.9,1.5,0.045,2,3F
#
# Example (no location, no checksum — shorter, for weak-signal areas):
#   SEISMSG1,STN-004,1721818530,,,,0.012,-0.008,0.021,0.5,0.3,0.6,1.2,0.9,1.5,0.045,2

SMS_FORMAT_TAG = "SEISMSG1"


def _sms_checksum(payload_prefix: str) -> str:
    return f"{sum(ord(c) for c in payload_prefix) % 256:02X}"


def parse_seismic_sms(body: str) -> dict:
    """Parses a SEISMSG1-format SMS body into the same dict shape used by
    the MQTT JSON payload. Raises ValueError with a human-readable reason
    on anything malformed — callers should catch this and store the raw
    message with parsed_ok=False rather than crash the listener."""
    body = (body or "").strip()
    parts = body.split(",")

    if not parts or parts[0].strip() != SMS_FORMAT_TAG:
        raise ValueError(f"Not a {SMS_FORMAT_TAG} message (unrecognized/missing format tag)")

    if len(parts) == 18:
        payload_prefix = ",".join(parts[:-1]) + ","
        expected = _sms_checksum(payload_prefix)
        received = parts[-1].strip().upper()
        if expected != received:
            raise ValueError(f"Checksum mismatch (expected {expected}, got {received}) — message may be corrupted")
        fields = parts[1:-1]
    elif len(parts) == 17:
        fields = parts[1:]  # checksum omitted — accepted, just unverified
    else:
        raise ValueError(f"Expected 17 or 18 comma-separated fields, got {len(parts)}")

    def _f(s):
        s = s.strip()
        return float(s) if s else None

    def _i(s):
        s = s.strip()
        return int(float(s)) if s else None

    (station_id, ts_raw, lat, lon, elev,
     acc_x, acc_y, acc_z, vel_x, vel_y, vel_z,
     disp_x, disp_y, disp_z, pga, peis) = fields

    station_id = station_id.strip()
    if not station_id:
        raise ValueError("Missing station_id")

    ts_raw = ts_raw.strip()
    if not ts_raw:
        raise ValueError("Missing timestamp")
    try:
        timestamp_value = datetime.fromtimestamp(int(ts_raw), tz=timezone.utc).isoformat()
    except (ValueError, OSError):
        raise ValueError(f"Bad timestamp value '{ts_raw}' — expected Unix epoch seconds")

    return {
        "station_id": station_id,
        "timestamp": timestamp_value,
        "location": {"latitude": _f(lat), "longitude": _f(lon), "elevation_m": _f(elev)},
        "measurements": {
            "acceleration": {"x": _f(acc_x), "y": _f(acc_y), "z": _f(acc_z)},
            "velocity": {"x": _f(vel_x), "y": _f(vel_y), "z": _f(vel_z)},
            "displacement": {"x": _f(disp_x), "y": _f(disp_y), "z": _f(disp_z)},
        },
        "pga": _f(pga),
        "peis": _i(peis),
    }


def _store_sms_record(sender, modem_timestamp, raw_body, parsed_ok, parse_error, station_id):
    """Stores every SMS received, regardless of whether it parsed as
    telemetry — this is the "SMS is stored to the database" record."""
    try:
        conn = get_data_connection()
        with conn.cursor() as cur:
            cur.execute("""
                INSERT INTO sms_messages (sender, modem_timestamp, raw_body, parsed_ok, parse_error, station_id)
                VALUES (%s, %s, %s, %s, %s, %s);
            """, (sender, modem_timestamp, raw_body, parsed_ok, parse_error, station_id))
        conn.commit()
    except Exception as e:
        logging.error(f"Failed to store SMS record: {e}")
        reset_data_connection()


def _handle_incoming_sms(modem, index, preloaded=None):
    msg = preloaded or modem.read_message(index)
    if not msg:
        logging.warning(f"SMS index {index} reported but could not be read — skipping.")
        return

    sender = msg.get("sender")
    body = msg.get("body", "")
    modem_ts = msg.get("timestamp")
    logging.info(f"SMS received from {sender}: {body[:60]!r}")

    if SMS_ALLOWED_SENDERS and sender not in SMS_ALLOWED_SENDERS:
        logging.warning(f"SMS from sender '{sender}' not in SMS_ALLOWED_SENDERS — storing but not processing as telemetry.")
        _store_sms_record(sender, modem_ts, body, parsed_ok=False,
                           parse_error="sender not in SMS_ALLOWED_SENDERS", station_id=None)
    else:
        try:
            data = parse_seismic_sms(body)
            insert_station_metrics(data, source="sms")
            _store_sms_record(sender, modem_ts, body, parsed_ok=True, parse_error=None, station_id=data["station_id"])
            logging.info(f"SUCCESS: Ingested SMS telemetry for: {data['station_id']}")
        except Exception as e:
            logging.error(f"Failed to parse/ingest SMS from {sender}: {e}")
            _store_sms_record(sender, modem_ts, body, parsed_ok=False, parse_error=str(e), station_id=None)

    try:
        modem.delete_message(index)
    except Exception as e:
        # SIM800L's on-SIM storage is small (often ~10-15 messages) — if
        # deletes keep failing, the SIM fills up and new SMS start getting
        # rejected by the network, so this is worth surfacing loudly.
        logging.error(f"Failed to delete SMS index {index} from SIM storage: {e}")


def sms_listener_loop():
    """Background thread: initializes the SIM800L, then loops watching for
    unsolicited '+CMTI' new-message notifications, plus a periodic
    full-inbox sweep as a safety net. Runs independently of the MQTT loop
    in main() — an SMS backlog or modem hiccup never blocks MQTT ingestion,
    and vice versa."""
    modem = SIM800L(SIM800_SERIAL_PORT, SIM800_BAUDRATE)

    while True:
        try:
            modem.initialize()
            break
        except Exception as e:
            logging.error(f"SIM800L init failed ({e}) — retrying in 30s. Check wiring/SIM800_SERIAL_PORT/power.")
            time.sleep(30)

    last_sweep = 0.0
    while True:
        try:
            for index in modem.wait_for_notification(timeout=2.0):
                _handle_incoming_sms(modem, index)

            if time.time() - last_sweep > SMS_POLL_INTERVAL_SEC:
                for msg in modem.list_unread_messages():
                    _handle_incoming_sms(modem, msg["index"], preloaded=msg)
                last_sweep = time.time()

        except (SIM800LError, OSError) as e:
            logging.error(f"SMS listener lost the modem ({e}) — reinitializing in 10s.")
            modem.close()
            time.sleep(10)
            try:
                modem.initialize()
            except Exception as e2:
                logging.error(f"SIM800L reinit failed: {e2} — will keep retrying.")
                time.sleep(30)
        except Exception as e:
            # Anything unexpected: log and keep the loop alive rather than
            # letting the whole SMS channel die on one bad message.
            logging.error(f"Unexpected error in SMS listener loop: {e}")
            time.sleep(5)


def _validate_config():
    env_path = SCRIPT_DIR / ".env"
    if not DB_PASSWORD:
        logging.critical("=" * 70)
        logging.critical("CONFIG ERROR: SEISMIC_DB_PASSWORD not loaded from .env")
        logging.critical(f"Expected .env at: {env_path} (exists: {env_path.exists()})")
        logging.critical("Check: file isn't secretly named '.env.txt', is in this script's folder, and is saved as UTF-8.")
        logging.critical("=" * 70)
        raise SystemExit(1)


def main():
    _validate_config()
    initialize_database()

    if SMS_INGESTION_ENABLED:
        if SIM800L is None:
            logging.error(
                "SMS_INGESTION_ENABLED is true but the 'pyserial' package (or sim800l.py) isn't "
                "available — run: pip3 install pyserial --break-system-packages. Continuing with "
                "MQTT ingestion only."
            )
        else:
            threading.Thread(target=sms_listener_loop, daemon=True, name="SMSListener").start()
    else:
        logging.info("SMS_INGESTION_ENABLED is false — running with MQTT ingestion only.")

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.on_connect = on_connect
    client.on_message = on_message

    if MQTT_USER and MQTT_PASSWORD:
        client.username_pw_set(MQTT_USER, MQTT_PASSWORD)

    client.connect(MQTT_BROKER_HOST, MQTT_BROKER_PORT, MQTT_TIMEOUT_SEC)
    client.loop_forever()  # blocks the main thread; SMS listener (if any) runs alongside it


if __name__ == "__main__":
    main()