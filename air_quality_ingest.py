#!/usr/bin/env python3
"""
Air Quality Ingestion Service
Protocol: HJ212 (TCP) & Modbus TCP (lead sensor)
Responsibility: receive station telemetry, parse it, write it to the
`air_quality` TimescaleDB database. No API code lives here — see api_server.py.
"""

import json
import logging
import os
import queue
import re
import socket
import threading
import time
from datetime import datetime, timezone, timedelta
from pathlib import Path
from typing import Optional

import psycopg2
from psycopg2 import pool, sql
from psycopg2.extensions import ISOLATION_LEVEL_AUTOCOMMIT
from psycopg2.extras import execute_values
from pymodbus.client import ModbusTcpClient
from pymodbus.framer import FramerType
from dotenv import load_dotenv

# ==========================================================
# CONFIG (from shared .env)
# ==========================================================
SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

SERVER_HOST = os.getenv("AQ_SERVER_HOST", "0.0.0.0")
SERVER_PORT = int(os.getenv("AQ_SERVER_PORT", 1935))
BUFFER_SIZE = 4096
MAX_CONNECTIONS = 20
VERIFY_CHECKSUM = False
SUPPORTED_CN = ["2011", "9014"]
LEAD_POLL_INTERVAL = int(os.getenv("AQ_LEAD_POLL_INTERVAL", 30))

# How old the latest sensor_data row for a station is allowed to be before a
# Modbus lead reading is attached to it. Without this, a station whose
# HJ212 telemetry has gone quiet (but whose lead analyzer is still
# reachable over Modbus) would keep having fresh lead values silently
# stitched onto an increasingly stale row — data for the wrong point in
# time, tagged with the right station_mn but the wrong data_time.
AQ_LEAD_MAX_ROW_AGE_SEC = int(os.getenv("AQ_LEAD_MAX_ROW_AGE_SEC", 600))

# How far a sensor's self-reported DataTime is allowed to drift from this
# server's (NTP-synced) clock before we treat it as bogus and fall back to
# server time. Override via .env if your sensors are expected to run ahead/
# behind by more than an hour under normal operation.
MAX_SENSOR_CLOCK_DRIFT = timedelta(hours=int(os.getenv("AQ_MAX_SENSOR_CLOCK_DRIFT_HOURS", 1)))

# ---- Active clock correction (HJ212 §6.6.5, CN=1012 设置现场机时间) ----
# Off by default: this actively pushes a "set your clock" command to the
# physical station over its live TCP connection, so it should be turned on
# deliberately after confirming your station firmware implements CN=1012 as
# the standard specifies (implementations vary by vendor).
AQ_TIME_SYNC_ENABLED = os.getenv("AQ_TIME_SYNC_ENABLED", "false").strip().lower() == "true"
# System code (ST) to use in outbound command frames — HJ212 Table 5.
# 22 = 空气质量监测 (ambient air quality monitoring). If your stations are
# regulatory emission-source monitors rather than ambient monitors, your
# vendor may expect 31 (大气环境污染源) instead — check your station's manual.
AQ_HJ212_ST = os.getenv("AQ_HJ212_ST", "22")
# Access password (PW field) the station expects on commands sent to it.
# This is a per-device credential configured on the station itself — it is
# NOT necessarily the same as anything in this .env already. Ask your
# station vendor/installer for it if you don't have it on hand.
AQ_HJ212_PW = os.getenv("AQ_HJ212_PW", "123456")
# Minimum time between time-sync attempts for the same station, so a
# persistently drifting sensor doesn't get flooded with correction commands.
AQ_TIME_SYNC_COOLDOWN_MIN = int(os.getenv("AQ_TIME_SYNC_COOLDOWN_MIN", 60))

DB_HOST = os.getenv("SYSTEM_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("SYSTEM_DB_PORT", 5432))
DB_NAME = os.getenv("AQ_DB_NAME", "IOT_aq_sensor_data")
DB_USER = os.getenv("SYSTEM_DB_USER", "aq_user")
DB_PASSWORD = os.getenv("SYSTEM_DB_PASSWORD")

# Connection pool sizing — kept modest by default since this, seismic_mqtt.py,
# and api_server.py may all be running on the same low-memory Raspberry Pi.
DB_POOL_MIN = int(os.getenv("SYSTEM_DB_POOL_MIN", 2))
DB_POOL_MAX = int(os.getenv("SYSTEM_DB_POOL_MAX", 10))

# ---- Batched sensor-data writes ----
# A station reading used to be a single INSERT + a single commit, executed
# synchronously on whichever thread received that station's TCP frame. Fine
# at low volume, but every extra station/poll rate adds a full round-trip +
# WAL fsync per reading. Instead, parsed readings are dropped on an in-memory
# queue (cheap, non-blocking for the TCP handler threads) and a single
# background thread (see batch_insert_worker) flushes them to Postgres as one
# multi-row INSERT + one commit, whenever AQ_BATCH_MAX_SIZE readings have
# queued up OR AQ_BATCH_MAX_INTERVAL_SEC seconds have passed since the first
# unflushed reading — whichever comes first, so worst-case latency is bounded
# even when traffic is light.
AQ_BATCH_MAX_SIZE = int(os.getenv("AQ_BATCH_MAX_SIZE", 100))
AQ_BATCH_MAX_INTERVAL_SEC = float(os.getenv("AQ_BATCH_MAX_INTERVAL_SEC", 2))

# Minimum time between "reading discarded" log entries for the *same*
# station, so a chatty unregistered/disabled station doesn't flood the
# system log (service_logs table) with a repeat warning on every frame.
AQ_STATION_REJECT_LOG_COOLDOWN_MIN = int(os.getenv("AQ_STATION_REJECT_LOG_COOLDOWN_MIN", 15))

# How often the in-memory station registry is reloaded from the database, so
# stations added/edited/disabled in the DB (e.g. via import_stations.py or
# direct SQL) are picked up without restarting this service.
AQ_STATIONS_REFRESH_INTERVAL_SEC = int(os.getenv("AQ_STATIONS_REFRESH_INTERVAL_SEC", 300))

# ---- Database-backed logging ----
# All log records are mirrored into the `service_logs` table in the
# separate IOT_service_logs database (shared with seismic_mqtt.py and
# api_server.py, each tagging its own rows via the `service` column) so
# logs are centrally queryable via SQL or the /api/system/logs endpoint,
# instead of living only in per-host log files or mixed into sensor data.
# Console output is kept and captured by systemd's journal.
DB_LOG_ENABLED = os.getenv("DB_LOG_ENABLED", "true").strip().lower() == "true"
DB_LOG_TABLE = os.getenv("DB_LOG_TABLE", "service_logs")
LOG_DB_NAME = os.getenv("LOG_DB_NAME", "IOT_service_logs")

# Registered station records used to live only in stations.json. The database
# `stations` table is now the source of truth: this service loads/refreshes
# its working station list from the DB. stations.json is only ever consulted
# once, automatically, to seed an empty `stations` table on a brand-new
# deployment (see migrate_stations_from_json_if_needed()) — after that it's
# not read again by this script. Use import_stations.py to (re-)apply a JSON
# file to the database at any time.
STATIONS_FILE = SCRIPT_DIR / "stations.json"
REQUIRED_STATION_KEYS = {"station_name", "enabled", "latitude", "longitude", "lead_ip", "lead_port", "lead_slave"}

STATIONS = {}
_stations_lock = threading.RLock()

logger = logging.getLogger("air_quality_ingest")
logger.setLevel(logging.INFO)
log_formatter = logging.Formatter("%(asctime)s [%(levelname)s] %(threadName)s: %(message)s")

console_handler = logging.StreamHandler()
console_handler.setFormatter(log_formatter)
logger.addHandler(console_handler)

if DB_LOG_ENABLED and DB_PASSWORD:
    from Fles.db_logging import attach_db_logging
    try:
        _bootstrap_conn = psycopg2.connect(host=DB_HOST, port=DB_PORT, database="postgres", user=DB_USER, password=DB_PASSWORD)
        _bootstrap_conn.set_isolation_level(ISOLATION_LEVEL_AUTOCOMMIT)
        _bootstrap_cur = _bootstrap_conn.cursor()
        _bootstrap_cur.execute("SELECT 1 FROM pg_database WHERE datname = %s", (LOG_DB_NAME,))
        if not _bootstrap_cur.fetchone():
            _bootstrap_cur.execute(f'CREATE DATABASE "{LOG_DB_NAME}"')
            logger.info(f"Database '{LOG_DB_NAME}' created successfully.")
        _bootstrap_cur.close()
        _bootstrap_conn.close()
    except Exception as e:
        logger.error(f"Could not ensure '{LOG_DB_NAME}' exists ahead of DB logging: {e}")

    _log_dsn = f"host={DB_HOST} port={DB_PORT} dbname={LOG_DB_NAME} user={DB_USER} password={DB_PASSWORD}"
    attach_db_logging(logger, _log_dsn, service_name="air_quality_ingest", table=DB_LOG_TABLE)


# ==========================================================
# SENSOR DEFINITIONS
# ==========================================================
SENSORS = {
    "a34004": {"name": "PM2.5", "column": "pm25", "unit": "µg/m³"},
    "a34002": {"name": "PM10", "column": "pm10", "unit": "µg/m³"},
    "a34001": {"name": "TSP", "column": "tsp", "unit": "µg/m³"},
    "a05024": {"name": "Ozone", "column": "ozone", "unit": "µg/m³"},
    "a21005": {"name": "Carbon Monoxide", "column": "carbon_monoxide", "unit": "mg/m³"},
    "a21026": {"name": "Sulfur Dioxide", "column": "sulfur_dioxide", "unit": "µg/m³"},
    "a21004": {"name": "Nitrogen Dioxide", "column": "nitrogen_dioxide", "unit": "µg/m³"},
    "a01001": {"name": "Temperature", "column": "temperature", "unit": "°C"},
    "a01002": {"name": "Humidity", "column": "humidity", "unit": "%"},
    "a06001": {"name": "Rain", "column": "rain", "unit": "mm"},
    "LA":     {"name": "Noise", "column": "noise", "unit": "dB"},
    "a01007": {"name": "Wind Speed", "column": "wind_speed", "unit": "m/s"},
    "a01008": {"name": "Wind Direction", "column": "wind_direction", "unit": "°"},
    "a01006": {"name": "Air Pressure", "column": "air_pressure", "unit": "kPa"},
}


# ==========================================================
# DATABASE LAYER
# ==========================================================
_connection_pool = None
_pool_lock = threading.Lock()

# Parsed readings waiting to be flushed to sensor_data by batch_insert_worker.
_sensor_data_queue = queue.Queue()

# Fixed column order for the batched INSERT. Every queued row uses exactly
# these keys (missing sensors are simply None) so a whole batch can be
# written with one execute_values() call regardless of which sensors each
# individual station reported.
_ROW_COLUMNS = ["station_mn", "ip_address", "data_time"] + [s["column"] for s in SENSORS.values()]

# Per-station cooldown so repeated readings from an unregistered/disabled
# station log a warning periodically instead of on every single frame.
_last_station_reject_warn = {}
_station_reject_warn_lock = threading.Lock()


def _log_station_reading_rejected(mn, ip_address, reason):
    now_mono = time.monotonic()
    with _station_reject_warn_lock:
        last = _last_station_reject_warn.get(mn, 0)
        if now_mono - last < AQ_STATION_REJECT_LOG_COOLDOWN_MIN * 60:
            return
        _last_station_reject_warn[mn] = now_mono
    logger.warning(f"Station {mn} (IP {ip_address}) {reason} — reading discarded, not saved.")


def create_database_if_not_exists():
    conn = None
    try:
        conn = psycopg2.connect(host=DB_HOST, port=DB_PORT, database="postgres", user=DB_USER, password=DB_PASSWORD)
        conn.set_isolation_level(ISOLATION_LEVEL_AUTOCOMMIT)
        cur = conn.cursor()
        cur.execute("SELECT 1 FROM pg_database WHERE datname = %s", (DB_NAME,))
        if not cur.fetchone():
            cur.execute(f'CREATE DATABASE "{DB_NAME}"')
            logger.info(f"Database '{DB_NAME}' created successfully.")
        cur.close()
        return True
    except Exception as e:
        logger.exception(f"Unable to create database: {e}")
        return False
    finally:
        if conn:
            conn.close()


def initialize_database():
    global _connection_pool
    with _pool_lock:
        if _connection_pool is not None:
            return True
        if not create_database_if_not_exists():
            return False
        try:
            _connection_pool = pool.ThreadedConnectionPool(
                minconn=DB_POOL_MIN, maxconn=DB_POOL_MAX,
                host=DB_HOST, port=DB_PORT, database=DB_NAME, user=DB_USER, password=DB_PASSWORD
            )
            create_tables()
            return True
        except Exception as e:
            logger.exception(f"Database initialization failed: {e}")
            return False


def get_connection():
    if _connection_pool is None:
        raise RuntimeError("Database pool is not initialized.")
    return _connection_pool.getconn()


def release_connection(conn):
    if conn:
        try:
            _connection_pool.putconn(conn)
        except Exception:
            conn.close()


def _load_stations_json_for_migration() -> dict:
    """Best-effort read of stations.json for the one-time DB migration.
    Unlike the old load_stations(), this never raises SystemExit — an empty
    or invalid file is fine, the DB is the real config now."""
    if not STATIONS_FILE.exists():
        return {}
    try:
        with open(STATIONS_FILE, "r", encoding="utf-8") as f:
            stations = json.load(f)
    except (json.JSONDecodeError, OSError) as e:
        logger.error(f"Could not read {STATIONS_FILE.name} for migration: {e}")
        return {}

    valid = {}
    for mn, info in stations.items():
        missing = REQUIRED_STATION_KEYS - info.keys()
        if missing:
            logger.warning(
                f"Skipping station '{mn}' in {STATIONS_FILE.name} during migration — "
                f"missing: {', '.join(sorted(missing))}"
            )
            continue
        valid[mn] = info
    return valid


def migrate_stations_from_json_if_needed(cur, conn):
    """One-time bootstrap: if the `stations` table is empty and stations.json
    is present, import it. Only fires on an empty table, so it never
    overwrites stations that were added/edited through the database
    afterwards. Re-run/re-apply a JSON file at any time with
    import_stations.py instead."""
    cur.execute("SELECT COUNT(*) FROM stations;")
    existing_count = cur.fetchone()[0]
    if existing_count > 0:
        return
    if not STATIONS_FILE.exists():
        return

    json_stations = _load_stations_json_for_migration()
    if not json_stations:
        return

    logger.info(f"'stations' table is empty — importing {len(json_stations)} station(s) from {STATIONS_FILE.name} (one-time).")
    for mn, info in json_stations.items():
        cur.execute("""
            INSERT INTO stations (station_mn, station_name, enabled, latitude, longitude, lead_ip, lead_port, lead_slave)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            ON CONFLICT (station_mn) DO NOTHING;
        """, (mn, info.get("station_name", mn), info.get("enabled", True), info.get("latitude"),
              info.get("longitude"), info.get("lead_ip"), info.get("lead_port"), info.get("lead_slave")))
    conn.commit()
    logger.info(
        f"Migrated {len(json_stations)} station(s) into the database. The database is now the "
        f"source of truth — {STATIONS_FILE.name} won't be read again automatically. It's safe to "
        f"archive it; use import_stations.py if you want to (re-)apply a JSON file later."
    )


def load_stations_from_db() -> dict:
    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()
        cur.execute("""
            SELECT station_mn, station_name, enabled, latitude, longitude, lead_ip, lead_port, lead_slave
            FROM stations;
        """)
        rows = cur.fetchall()
        stations = {}
        for mn, name, enabled, lat, lon, lead_ip, lead_port, lead_slave in rows:
            stations[mn] = {
                "station_name": name,
                "enabled": enabled,
                "latitude": lat,
                "longitude": lon,
                "lead_ip": lead_ip,
                "lead_port": lead_port,
                "lead_slave": lead_slave,
            }
        return stations
    finally:
        if conn:
            release_connection(conn)


def refresh_stations(initial=False):
    global STATIONS
    try:
        stations = load_stations_from_db()
        with _stations_lock:
            STATIONS = stations
        if initial:
            logger.info(f"Loaded {len(stations)} station(s) from the database.")
        else:
            logger.info(f"Station registry refreshed from database ({len(stations)} station(s)).")
    except Exception as e:
        logger.error(f"Failed to refresh station registry from database: {e}")


def get_stations() -> dict:
    """Thread-safe snapshot of the current station registry."""
    with _stations_lock:
        return dict(STATIONS)


def get_station(mn) -> Optional[dict]:
    """Thread-safe lookup of a single station's config. Cheaper than
    get_stations() when only one station is needed (e.g. once per incoming
    HJ212 reading) since it doesn't copy the whole registry."""
    with _stations_lock:
        return STATIONS.get(mn)


def stations_refresh_loop():
    while True:
        time.sleep(AQ_STATIONS_REFRESH_INTERVAL_SEC)
        refresh_stations()


def create_tables():
    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()

        cur.execute("CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;")

        cur.execute("""
        CREATE TABLE IF NOT EXISTS stations (
            station_mn VARCHAR(32) PRIMARY KEY,
            station_name VARCHAR(100),
            enabled BOOLEAN NOT NULL DEFAULT TRUE,
            latitude DOUBLE PRECISION,
            longitude DOUBLE PRECISION,
            lead_ip VARCHAR(64),
            lead_port INTEGER,
            lead_slave INTEGER,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        """)

        # Migration path for deployments that already have a `stations` table
        # from before enabled/lead_ip/lead_port/lead_slave lived in the DB.
        required_station_columns = {
            "enabled": "BOOLEAN NOT NULL DEFAULT TRUE",
            "lead_ip": "VARCHAR(64)",
            "lead_port": "INTEGER",
            "lead_slave": "INTEGER",
        }
        for col_name, col_type in required_station_columns.items():
            cur.execute("""
                SELECT 1 FROM information_schema.columns
                WHERE table_name='stations' AND column_name=%s;
            """, (col_name,))
            if not cur.fetchone():
                logger.info(f"Migrating 'stations' table: adding column '{col_name}'")
                cur.execute(sql.SQL("ALTER TABLE stations ADD COLUMN {} {}").format(
                    sql.Identifier(col_name), sql.SQL(col_type)
                ))

        migrate_stations_from_json_if_needed(cur, conn)

        cur.execute("""
        CREATE TABLE IF NOT EXISTS sensor_data (
            station_mn VARCHAR(32) NOT NULL REFERENCES stations(station_mn),
            ip_address INET,
            data_time TIMESTAMP NOT NULL,
            pm25 DOUBLE PRECISION, pm10 DOUBLE PRECISION, tsp DOUBLE PRECISION,
            ozone DOUBLE PRECISION, carbon_monoxide DOUBLE PRECISION,
            sulfur_dioxide DOUBLE PRECISION, nitrogen_dioxide DOUBLE PRECISION,
            temperature DOUBLE PRECISION, humidity DOUBLE PRECISION,
            rain DOUBLE PRECISION, wind_speed DOUBLE PRECISION,
            wind_direction DOUBLE PRECISION, air_pressure DOUBLE PRECISION,
            noise DOUBLE PRECISION, lead DOUBLE PRECISION,
            lead_temperature DOUBLE PRECISION,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        """)

        cur.execute("""
        SELECT create_hypertable('sensor_data', 'data_time', if_not_exists => TRUE, migrate_data => TRUE);
        """)

        cur.execute("CREATE INDEX IF NOT EXISTS idx_station_time ON sensor_data(station_mn, data_time DESC);")

        conn.commit()
        logger.info("Air quality tables/hypertables verified.")
    except Exception as e:
        if conn:
            conn.rollback()
        logger.exception(f"Table creation/Hypertable setup failed: {e}")
    finally:
        if conn:
            release_connection(conn)


def insert_sensor_data(data, ip_address, station_conn=None):
    """Parses one station reading and enqueues it for batch_insert_worker.
    Deliberately does no DB I/O itself — this runs on the TCP handler thread
    for whichever station just sent a frame, so it needs to stay fast and
    non-blocking regardless of how busy the batch writer/DB currently are."""
    cp = data.get("CP", {})
    mn = data.get("MN")

    # Only save data for stations that are registered AND enabled in the DB.
    # Besides being the correct behavior, this also protects batch writes:
    # since a whole batch is written as one multi-row INSERT, a row from an
    # unrecognized station would fail the sensor_data foreign key and roll
    # back every other valid reading sitting in that same batch.
    station = get_station(mn)
    if station is None:
        _log_station_reading_rejected(mn, ip_address, "is not in the registered station list")
        return
    if not station.get("enabled", True):
        _log_station_reading_rejected(mn, ip_address, "is registered but disabled")
        return

    data_time = datetime.now(timezone.utc)  # trusted default: this server's NTP-synced clock

    if "DataTime" in cp:
        try:
            naive_dt = datetime.strptime(cp["DataTime"], "%Y%m%d%H%M%S")
            philippines_tz = timezone(timedelta(hours=8))
            localized_pht_dt = naive_dt.replace(tzinfo=philippines_tz)
            candidate_time = localized_pht_dt.astimezone(timezone.utc)

            # Sensors have their own onboard clock (usually backed by a
            # coin-cell RTC battery) that's independent of this server's
            # NTP-synced time. If that battery dies or the unit loses
            # power, the sensor's clock resets to some default/epoch and
            # every DataTime it reports afterward is garbage — silently
            # mis-dating readings by months or years if we trust it as-is.
            # Guard against that: only accept the sensor's timestamp if
            # it's within MAX_SENSOR_CLOCK_DRIFT of our own clock; otherwise
            # fall back to server time and flag it so the field team knows
            # station `mn` needs its RTC/battery checked.
            drift_secs = abs((candidate_time - data_time).total_seconds())
            if drift_secs <= MAX_SENSOR_CLOCK_DRIFT.total_seconds():
                data_time = candidate_time
            else:
                logger.warning(
                    f"Station {mn}: sensor-reported DataTime '{cp['DataTime']}' is "
                    f"{drift_secs / 3600:.1f}h off from server time — looks like the "
                    f"sensor's clock reset (dead RTC battery / power loss). Using "
                    f"server time for this reading instead; station needs a hardware check."
                )
                if AQ_TIME_SYNC_ENABLED and station_conn is not None:
                    request_sensor_time_sync(station_conn, mn)
        except ValueError:
            logger.warning(f"Station {mn}: unparseable DataTime '{cp.get('DataTime')}' — using server time.")

    row = {"station_mn": mn, "ip_address": ip_address, "data_time": data_time}
    for code, sensor in SENSORS.items():
        if code not in cp:
            continue
        sensor_data = cp[code]
        row[sensor["column"]] = sensor_data.get("Rtd") or sensor_data.get("Avg") or sensor_data.get("Value")

    _sensor_data_queue.put(row)


def _flush_batch(rows):
    """Writes a batch of parsed readings as one multi-row INSERT + one
    commit. Runs only on the batch_insert_worker thread."""
    if not rows:
        return
    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()
        values = [tuple(row.get(col) for col in _ROW_COLUMNS) for row in rows]
        query = f"INSERT INTO sensor_data ({', '.join(_ROW_COLUMNS)}) VALUES %s"
        execute_values(cur, query, values)
        conn.commit()
        logger.info(f"Ingested {len(rows)} air quality reading(s) in one batch.")
    except Exception as e:
        if conn:
            conn.rollback()
        logger.error(f"Error inserting batch of {len(rows)} sensor reading(s): {e}")
    finally:
        if conn:
            release_connection(conn)


def batch_insert_worker():
    """Drains _sensor_data_queue and flushes to the DB whenever
    AQ_BATCH_MAX_SIZE readings have accumulated or AQ_BATCH_MAX_INTERVAL_SEC
    seconds have elapsed since the first reading in the current batch,
    whichever happens first. Runs for the lifetime of the process."""
    batch = []
    deadline = None
    while True:
        timeout = max(0.0, deadline - time.monotonic()) if deadline is not None else None
        try:
            row = _sensor_data_queue.get(timeout=timeout)
            batch.append(row)
            if deadline is None:
                deadline = time.monotonic() + AQ_BATCH_MAX_INTERVAL_SEC
        except queue.Empty:
            pass  # nothing new arrived before the deadline — flush what we have

        if batch and (len(batch) >= AQ_BATCH_MAX_SIZE or (deadline is not None and time.monotonic() >= deadline)):
            _flush_batch(batch)
            batch = []
            deadline = None


def start_batch_insert_worker():
    threading.Thread(target=batch_insert_worker, daemon=True, name="SensorDataBatchWriter").start()


def update_lead_value(mn, ip, lead, temperature):
    """Attaches a Modbus lead reading to station `mn`'s most recent
    sensor_data row — but only if that row is recent enough (see
    AQ_LEAD_MAX_ROW_AGE_SEC), and only for `mn` (never any other station,
    regardless of what else might share that lead IP/port). `ip` is the
    lead_ip this station is assigned in the `stations` table — passed
    through purely so success/failure logs are traceable to a specific
    station+IP pairing rather than just an MN."""
    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()
        cutoff = datetime.now(timezone.utc) - timedelta(seconds=AQ_LEAD_MAX_ROW_AGE_SEC)
        cur.execute("""
            UPDATE sensor_data SET lead = %s, lead_temperature = %s
            WHERE station_mn = %s AND data_time = (
                SELECT MAX(data_time) FROM sensor_data
                WHERE station_mn = %s AND data_time >= %s
            )
            """, (lead, temperature, mn, mn, cutoff))
        conn.commit()
        if cur.rowcount == 0:
            logger.warning(
                f"Station {mn} (lead IP {ip}): read a Modbus lead value but found no "
                f"sensor_data row from the last {AQ_LEAD_MAX_ROW_AGE_SEC}s to attach it to "
                f"— station's HJ212 telemetry may have gone quiet. Lead reading discarded."
            )
        else:
            logger.info(f"Station {mn} (lead IP {ip}): synced lead={lead}, lead_temperature={temperature}.")
    except Exception as e:
        if conn:
            conn.rollback()
        logger.error(f"Station {mn} (lead IP {ip}): error updating Modbus lead values: {e}")
    finally:
        if conn:
            release_connection(conn)


# ==========================================================
# HJ212 PROTOCOL & PARSER
# ==========================================================
def crc16(data: str) -> str:
    crc = 0xFFFF
    for b in data.encode("ascii"):
        crc ^= b
        for _ in range(8):
            if crc & 0x0001:
                crc >>= 1
                crc ^= 0xA001
            else:
                crc >>= 1
    return f"{crc:04X}"


def verify_crc(frame: str) -> bool:
    try:
        return frame[-4:].upper() == crc16(frame[6:-4])
    except Exception:
        return False


def get_field(frame: str, field: str) -> str:
    match = re.search(rf"{field}=([^;]+)", frame)
    return match.group(1) if match else ""


def extract_frames(buffer: str):
    frames = []
    while True:
        start = buffer.find("##")
        if start == -1 or len(buffer) < start + 6:
            break
        try:
            length = int(buffer[start + 2:start + 6])
        except ValueError:
            buffer = buffer[start + 2:]
            continue
        total_length = 2 + 4 + length + 4
        if len(buffer) < start + total_length:
            break
        frames.append(buffer[start:start + total_length])
        buffer = buffer[start + total_length:]
    return frames, buffer


def build_ack(frame: str) -> str:
    body = f"QN={get_field(frame, 'QN')};ST=91;CN=9014;PW={get_field(frame, 'PW')};MN={get_field(frame, 'MN')};Flag=4;CP=&&QnRtn=1;ExeRtn=1&&"
    return f"##{len(body):04d}{body}{crc16(body)}\r\n"


# ---- Outbound command: CN=1012 设置现场机时间 (HJ212 §6.6.5 Table 9,示例 C.3) ----
# This is the monitoring center (us) actively telling a station to correct
# its clock. Response comes back on the same connection as CN=9011
# (request ack) then CN=9012 (execution result) — see _pending_time_syncs.
_pending_time_syncs = {}
_pending_time_syncs_lock = threading.Lock()
_last_time_sync_attempt = {}  # mn -> monotonic time of last attempt, for cooldown


def build_time_sync_command(mn: str) -> tuple:
    """Builds a CN=1012 request frame that sets the station's clock to this
    server's current (NTP-synced) local time. No PolId in CP means the
    command targets the field machine's overall clock, not one instrument.
    Returns (qn, frame_string)."""
    qn = datetime.now().strftime("%Y%m%d%H%M%S") + f"{int(time.time() * 1000) % 1000:03d}"
    philippines_tz = timezone(timedelta(hours=8))
    system_time_str = datetime.now(philippines_tz).strftime("%Y%m%d%H%M%S")
    body = (
        f"QN={qn};ST={AQ_HJ212_ST};CN=1012;PW={AQ_HJ212_PW};MN={mn};Flag=5;"
        f"CP=&&SystemTime={system_time_str}&&"
    )
    frame = f"##{len(body):04d}{body}{crc16(body)}\r\n"
    return qn, frame


def request_sensor_time_sync(conn, mn):
    """Sends a CN=1012 time-correction command to the station over its live
    connection, subject to a per-station cooldown so a persistently drifting
    sensor doesn't get flooded with commands."""
    now_mono = time.monotonic()
    last = _last_time_sync_attempt.get(mn, 0)
    if now_mono - last < AQ_TIME_SYNC_COOLDOWN_MIN * 60:
        return
    _last_time_sync_attempt[mn] = now_mono

    qn, frame = build_time_sync_command(mn)
    try:
        conn.sendall(frame.encode())
        with _pending_time_syncs_lock:
            _pending_time_syncs[qn] = {"mn": mn, "sent_at": now_mono}
        logger.info(f"Station {mn}: sent HJ212 CN=1012 time-sync command to correct its clock.")
    except Exception as e:
        logger.error(f"Station {mn}: failed to send time-sync command: {e}")


def handle_command_response(frame: str):
    """Handles CN=9011/9012 responses to our own outbound commands (currently
    just the CN=1012 time-sync). Not a data frame, so it's routed separately
    from process_frame() in handle_client()."""
    cn = get_field(frame, "CN")
    qn = get_field(frame, "QN")
    with _pending_time_syncs_lock:
        pending = _pending_time_syncs.get(qn)
    if not pending:
        return  # not one of ours (or already resolved)

    mn = pending["mn"]
    if cn == "9011":
        qnrtn = get_field(frame, "QnRtn")
        if qnrtn != "1":
            logger.warning(f"Station {mn}: time-sync command rejected by station (QnRtn={qnrtn}).")
            with _pending_time_syncs_lock:
                _pending_time_syncs.pop(qn, None)
        # QnRtn==1: station accepted the request, wait for CN=9012 execution result.
    elif cn == "9012":
        exertn = get_field(frame, "ExeRtn")
        if exertn == "1":
            logger.info(f"Station {mn}: sensor clock corrected successfully via HJ212 time-sync.")
        else:
            logger.warning(
                f"Station {mn}: time-sync execution failed (ExeRtn={exertn}). "
                f"If this keeps failing, the station's RTC/battery likely needs physical service."
            )
        with _pending_time_syncs_lock:
            _pending_time_syncs.pop(qn, None)


def parse_cp(cp_data: str):
    result = {}
    if not cp_data:
        return result
    for section in cp_data.split(";"):
        if not section:
            continue
        if section.startswith("DataTime="):
            result["DataTime"] = section.split("=", 1)[1]
            continue
        # Format: <SensorName>-Rtd=<val>,Avg=<val>,... or similar HJ212 key-value groupings
        if "-" in section:
            name, rest = section.split("-", 1)
            fields = {}
            for kv in rest.split(","):
                if "=" in kv:
                    k, v = kv.split("=", 1)
                    try:
                        fields[k] = float(v)
                    except ValueError:
                        fields[k] = v
            result[name] = fields
    return result


def parse_frame(frame: str):
    fields = {"QN": get_field(frame, "QN"), "CN": get_field(frame, "CN"), "MN": get_field(frame, "MN")}
    cp_match = re.search(r"CP=&&(.*)&&", frame)
    return {"Length": frame[2:6], "QN": fields.get("QN"), "CN": fields.get("CN"), "MN": fields.get("MN"),
            "CP": parse_cp(cp_match.group(1) if cp_match else "")}


def process_frame(frame, ip_address, conn=None):
    data = parse_frame(frame)
    if data:
        insert_sensor_data(data, ip_address, station_conn=conn)


# ==========================================================
# MODBUS LEAD SENSOR SERVICE
# ==========================================================
def poll_station(mn, station):
    ip, port, slave = station["lead_ip"], station["lead_port"], station["lead_slave"]
    client = ModbusTcpClient(host=ip, port=port, framer=FramerType.RTU, timeout=3)
    if not client.connect():
        logger.error(f"[MODBUS] Station {mn} (IP {ip}): could not connect.")
        return
    try:
        rr = None
        try:
            rr = client.read_holding_registers(address=0, count=10, slave=slave)
        except TypeError:
            try:
                rr = client.read_holding_registers(address=0, count=10, device_id=slave)
            except TypeError:
                rr = client.read_holding_registers(address=0, count=10, unit=slave)

        if rr and not rr.isError():
            update_lead_value(mn, ip, rr.registers[2] / 10.0, rr.registers[1] / 10.0)
    except Exception as e:
        logger.error(f"[MODBUS] Station {mn} (IP {ip}): {e}")
    finally:
        client.close()


def lead_service():
    while True:
        for mn, station in get_stations().items():
            if station["enabled"]:
                poll_station(mn, station)
        time.sleep(LEAD_POLL_INTERVAL)


def start_lead_service():
    threading.Thread(target=lead_service, daemon=True, name="ModbusLeadService").start()


# ==========================================================
# TCP SERVER
# ==========================================================
def handle_client(conn, addr):
    ip_address = addr[0]
    buffer = ""
    while True:
        try:
            data = conn.recv(BUFFER_SIZE)
            if not data:
                break
            buffer += data.decode(errors="ignore")
            frames, buffer = extract_frames(buffer)

            for frame in frames:
                if VERIFY_CHECKSUM and not verify_crc(frame):
                    continue
                cn = get_field(frame, "CN")
                if cn == "2011":
                    process_frame(frame, ip_address, conn)
                elif cn in ("9011", "9012"):
                    # Station's response to a command WE sent it (e.g. our
                    # CN=1012 time-sync request) — not something to ack.
                    handle_command_response(frame)
                if cn in SUPPORTED_CN:
                    conn.sendall(build_ack(frame).encode())
        except Exception:
            break
    conn.close()


def start_tcp_server():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((SERVER_HOST, SERVER_PORT))
    server.listen(MAX_CONNECTIONS)
    logger.info(f"Air quality TCP server running on {SERVER_HOST}:{SERVER_PORT}")
    while True:
        conn, addr = server.accept()
        conn.settimeout(60)
        threading.Thread(target=handle_client, args=(conn, addr), daemon=True).start()


def _validate_config():
    env_path = SCRIPT_DIR / ".env"
    if not DB_PASSWORD:
        logger.critical("=" * 70)
        logger.critical("CONFIG ERROR: SYSTEM_DB_PASSWORD not loaded from .env")
        logger.critical(f"Expected .env at: {env_path} (exists: {env_path.exists()})")
        logger.critical("Check: file isn't secretly named '.env.txt', is in this script's folder, and is saved as UTF-8.")
        logger.critical("=" * 70)
        raise SystemExit(1)


def main():
    _validate_config()
    if not initialize_database():
        logger.critical("Database initialization failed. Halting.")
        return

    refresh_stations(initial=True)
    threading.Thread(target=stations_refresh_loop, daemon=True, name="StationsRefresh").start()

    start_batch_insert_worker()
    start_lead_service()
    start_tcp_server()  # blocks the main thread


if __name__ == "__main__":
    main()