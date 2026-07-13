#!/usr/bin/env python3
"""
Air Quality Ingestion Service
Protocol: HJ212 (TCP) & Modbus TCP (lead sensor)
Responsibility: receive station telemetry, parse it, write it to the
`air_quality` TimescaleDB database. No API code lives here — see api_server.py.
"""

import logging
import os
import re
import socket
import threading
import time
from datetime import datetime, timezone, timedelta
from pathlib import Path

import psycopg2
from psycopg2 import pool
from psycopg2.extensions import ISOLATION_LEVEL_AUTOCOMMIT
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

# How far a sensor's self-reported DataTime is allowed to drift from this
# server's (NTP-synced) clock before we treat it as bogus and fall back to
# server time. Override via .env if your sensors are expected to run ahead/
# behind by more than an hour under normal operation.
MAX_SENSOR_CLOCK_DRIFT = timedelta(hours=int(os.getenv("AQ_MAX_SENSOR_CLOCK_DRIFT_HOURS", 1)))

DB_HOST = os.getenv("AQ_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("AQ_DB_PORT", 5432))
DB_NAME = os.getenv("AQ_DB_NAME", "air_quality")
DB_USER = os.getenv("AQ_DB_USER", "aq_user")
DB_PASSWORD = os.getenv("AQ_DB_PASSWORD")

LOG_FILE_NAME = "air_quality_ingest.log"

# Registered station records — structural config, not secrets, so it stays in code.
# Move to a JSON file later if you want to edit stations without touching source.
STATIONS = {
    "4101025U122041": {
        "station_name": "AQM001",
        "enabled": True,
        "latitude": 14.5995,
        "longitude": 120.9842,
        "lead_ip": "192.168.55.11",
        "lead_port": 8899,
        "lead_slave": 1,
    },
    "4101025U122042": {
        "station_name": "AQM002",
        "enabled": True,
        "latitude": 14.6095,
        "longitude": 120.9942,
        "lead_ip": "192.168.55.12",
        "lead_port": 8899,
        "lead_slave": 1,
    },
}

logger = logging.getLogger("air_quality_ingest")
logger.setLevel(logging.INFO)
log_formatter = logging.Formatter("%(asctime)s [%(levelname)s] %(threadName)s: %(message)s")

console_handler = logging.StreamHandler()
console_handler.setFormatter(log_formatter)
logger.addHandler(console_handler)

file_handler = logging.FileHandler(LOG_FILE_NAME, encoding="utf-8")
file_handler.setFormatter(log_formatter)
logger.addHandler(file_handler)


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
                minconn=2, maxconn=20, host=DB_HOST, port=DB_PORT, database=DB_NAME, user=DB_USER, password=DB_PASSWORD
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
            latitude DOUBLE PRECISION,
            longitude DOUBLE PRECISION,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        """)

        for mn, info in STATIONS.items():
            cur.execute("""
                INSERT INTO stations (station_mn, station_name, latitude, longitude)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (station_mn) DO UPDATE
                SET station_name = EXCLUDED.station_name,
                    latitude = EXCLUDED.latitude,
                    longitude = EXCLUDED.longitude,
                    updated_at = CURRENT_TIMESTAMP;
            """, (mn, info.get("station_name", mn), info.get("latitude"), info.get("longitude")))

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


def insert_sensor_data(data, ip_address):
    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()
        cp = data.get("CP", {})
        mn = data.get("MN")

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
            except ValueError:
                logger.warning(f"Station {mn}: unparseable DataTime '{cp.get('DataTime')}' — using server time.")

        values = {"station_mn": mn, "ip_address": ip_address, "data_time": data_time}

        for code, sensor in SENSORS.items():
            if sensor["name"] not in cp:
                continue
            sensor_data = cp[sensor["name"]]
            values[sensor["column"]] = sensor_data.get("Rtd") or sensor_data.get("Avg") or sensor_data.get("Value")

        columns = list(values.keys())
        placeholders = ["%s"] * len(columns)
        query = f"INSERT INTO sensor_data ({', '.join(columns)}) VALUES ({', '.join(placeholders)})"

        cur.execute(query, [values[col] for col in columns])
        conn.commit()
        logger.info(f"Ingested air quality reading for station {mn}")
    except Exception as e:
        if conn:
            conn.rollback()
        logger.error(f"Error inserting sensor data: {e}")
    finally:
        if conn:
            release_connection(conn)


def update_lead_value(mn, lead, temperature):
    conn = None
    try:
        conn = get_connection()
        cur = conn.cursor()
        cur.execute("""
            UPDATE sensor_data SET lead = %s, lead_temperature = %s
            WHERE station_mn = %s AND data_time = (
                SELECT MAX(data_time) FROM sensor_data WHERE station_mn = %s
            )
            """, (lead, temperature, mn, mn))
        conn.commit()
    except Exception as e:
        if conn:
            conn.rollback()
        logger.error(f"Error updating Modbus lead values: {e}")
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


def process_frame(frame, ip_address):
    data = parse_frame(frame)
    if data:
        insert_sensor_data(data, ip_address)


# ==========================================================
# MODBUS LEAD SENSOR SERVICE
# ==========================================================
def poll_station(mn, station):
    ip, port, slave = station["lead_ip"], station["lead_port"], station["lead_slave"]
    client = ModbusTcpClient(host=ip, port=port, framer=FramerType.RTU, timeout=3)
    if not client.connect():
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
            update_lead_value(mn, rr.registers[2] / 10.0, rr.registers[1] / 10.0)
    except Exception as e:
        logger.error(f"[MODBUS] IP {ip}: {e}")
    finally:
        client.close()


def lead_service():
    while True:
        for mn, station in STATIONS.items():
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
                    process_frame(frame, ip_address)
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
        logger.critical("CONFIG ERROR: AQ_DB_PASSWORD not loaded from .env")
        logger.critical(f"Expected .env at: {env_path} (exists: {env_path.exists()})")
        logger.critical("Check: file isn't secretly named '.env.txt', is in this script's folder, and is saved as UTF-8.")
        logger.critical("=" * 70)
        raise SystemExit(1)


def main():
    _validate_config()
    if not initialize_database():
        logger.critical("Database initialization failed. Halting.")
        return

    start_lead_service()
    start_tcp_server()  # blocks the main thread


if __name__ == "__main__":
    main()