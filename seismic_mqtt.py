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
from pathlib import Path
import paho.mqtt.client as mqtt
import psycopg
from psycopg import sql
from dotenv import load_dotenv

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

BASE_CONN_STRING = f"host={DB_HOST} user={DB_USER} password={DB_PASSWORD} port={DB_PORT}"
APP_DB_CONN_STRING = f"{BASE_CONN_STRING} dbname={DB_NAME}"

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")


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
                    "peis": "INT"
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

        logging.info("Storage clustering systems finalized and running cleanly.")
    except Exception as e:
        logging.critical(f"Storage architecture sync failed: {e}")
        raise


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
        if len(topic_parts) >= 4:
            extracted_station_id = topic_parts[2]
        else:
            extracted_station_id = "UNKNOWN"

        payload_raw = msg.payload.decode('utf-8')
        data = json.loads(payload_raw)

        loc = data.get('location', {})
        measurements = data.get('measurements', {})
        acc = measurements.get('acceleration', {})
        vel = measurements.get('velocity', {})
        disp = measurements.get('displacement', {})

        station_identity = data.get('station_id', extracted_station_id)
        timestamp_value = data.get('timestamp')

        if not timestamp_value:
            logging.error("Payload rejected: 'timestamp' key is missing from JSON.")
            return

        peis_raw = data.get('peis')
        peis_value = int(float(peis_raw)) if peis_raw is not None else None

        query = """
            INSERT INTO station_metrics (
                time, station_id, station_name,
                latitude, longitude, elevation_m,
                acc_x, acc_y, acc_z,
                vel_x, vel_y, vel_z,
                disp_x, disp_y, disp_z,
                pga, peis
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s);
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
            peis_value
        )

        with psycopg.connect(APP_DB_CONN_STRING) as conn:
            with conn.cursor() as cur:
                cur.execute(query, params)
                conn.commit()
                logging.info(f"SUCCESS: Ingested telemetry to TimescaleDB for: {station_identity}")

    except json.JSONDecodeError:
        logging.error("Payload failed parsing: Received message is not valid JSON.")
    except Exception as e:
        logging.error(f"Database insertion failed: {e}")


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

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.on_connect = on_connect
    client.on_message = on_message

    if MQTT_USER and MQTT_PASSWORD:
        client.username_pw_set(MQTT_USER, MQTT_PASSWORD)

    client.connect(MQTT_BROKER_HOST, MQTT_BROKER_PORT, MQTT_TIMEOUT_SEC)
    client.loop_forever()


if __name__ == "__main__":
    main()