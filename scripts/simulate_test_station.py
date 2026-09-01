#!/usr/bin/env python3
"""
TEMPORARY TEST-DATA SIMULATOR — not part of the real ingest pipeline.

Station 4101025U122007 has no physical sensor deployed yet. To test the
pipeline/dashboard end-to-end, this script mirrors station 4101025U122011's
latest PM2.5 / PM10 / TSP / NO2 readings into .007, with independent ±5%
random jitter applied to each value so they're not byte-identical.

Every row this script writes is tagged data_source='synthetic_test' and
source_mn='<the station it was copied from>' so it is never mistaken for a
real reading if someone queries `sensor_data` directly, in a report, or in
the dashboard. DELETE THIS SCRIPT (and stop the ± its rows) once .007 has
a real sensor reporting.

Run from the same directory as air_quality_ingest.py (reuses its .env).
"""

import logging
import os
import random
import time
from datetime import datetime, timezone
from pathlib import Path

import psycopg2
from dotenv import load_dotenv

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

DB_HOST = os.getenv("SYSTEM_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("SYSTEM_DB_PORT", 5432))
DB_NAME = os.getenv("AQ_DB_NAME", "IOT_aq_sensor_data")
DB_USER = os.getenv("SYSTEM_DB_USER", "aq_user")
DB_PASSWORD = os.getenv("SYSTEM_DB_PASSWORD")

SOURCE_MN = "4101025U122011"
TARGET_MN = "4101025U122007"
JITTER_FRACTION = 0.05          # ±5%
POLL_INTERVAL_SEC = int(os.getenv("TEST_SIM_INTERVAL_SEC", 60))
# How stale .011's latest row can be before we skip a cycle instead of
# reusing an old value under a fresh timestamp.
MAX_SOURCE_AGE_SEC = int(os.getenv("TEST_SIM_MAX_SOURCE_AGE_SEC", 600))

COLUMNS = ["pm25", "pm10", "tsp", "nitrogen_dioxide"]

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger("simulate_test_station")


def get_conn():
    return psycopg2.connect(
        host=DB_HOST, port=DB_PORT, dbname=DB_NAME, user=DB_USER, password=DB_PASSWORD
    )


def ensure_tagging_column(conn):
    """Idempotent: adds data_source / source_mn if this is the first time
    this script has run against the DB. Leaves all existing rows alone
    except defaulting their data_source to 'live' (never NULL, so a plain
    query can always filter WHERE data_source = 'synthetic_test' safely)."""
    with conn.cursor() as cur:
        cur.execute("""
            ALTER TABLE sensor_data
                ADD COLUMN IF NOT EXISTS data_source VARCHAR(32) NOT NULL DEFAULT 'live',
                ADD COLUMN IF NOT EXISTS source_mn VARCHAR(32);
        """)
    conn.commit()


def fetch_latest_source_row(conn):
    with conn.cursor() as cur:
        cur.execute(f"""
            SELECT data_time, {', '.join(COLUMNS)}
            FROM sensor_data
            WHERE station_mn = %s
            ORDER BY data_time DESC
            LIMIT 1;
        """, (SOURCE_MN,))
        return cur.fetchone()


def jitter(value):
    if value is None:
        return None
    factor = 1 + random.uniform(-JITTER_FRACTION, JITTER_FRACTION)
    return round(value * factor, 2)


def insert_synthetic_row(conn, values):
    with conn.cursor() as cur:
        cur.execute(f"""
            INSERT INTO sensor_data
                (station_mn, data_time, {', '.join(COLUMNS)}, data_source, source_mn)
            VALUES
                (%s, %s, {', '.join(['%s'] * len(COLUMNS))}, 'synthetic_test', %s);
        """, (TARGET_MN, datetime.now(timezone.utc), *values, SOURCE_MN))
    conn.commit()


def run_once(conn):
    row = fetch_latest_source_row(conn)
    if not row:
        logger.warning(f"No data yet for source station {SOURCE_MN} — skipping this cycle.")
        return

    data_time, *raw_values = row
    age_sec = (datetime.now(timezone.utc) - data_time.replace(tzinfo=timezone.utc)).total_seconds()
    if age_sec > MAX_SOURCE_AGE_SEC:
        logger.warning(
            f"Latest {SOURCE_MN} row is {age_sec:.0f}s old (max {MAX_SOURCE_AGE_SEC}s) — skipping "
            f"this cycle rather than mirroring a stale reading under a fresh timestamp."
        )
        return

    jittered = [jitter(v) for v in raw_values]
    insert_synthetic_row(conn, jittered)
    logger.info(
        f"Inserted synthetic row for {TARGET_MN} from {SOURCE_MN}: "
        + ", ".join(f"{c}={v}" for c, v in zip(COLUMNS, jittered))
    )


def main():
    if not DB_PASSWORD:
        logger.critical("SYSTEM_DB_PASSWORD not set — check .env in this script's folder.")
        return

    conn = get_conn()
    try:
        ensure_tagging_column(conn)
        logger.info(
            f"Starting test simulator: {TARGET_MN} <- {SOURCE_MN} ± {JITTER_FRACTION*100:.0f}%, "
            f"every {POLL_INTERVAL_SEC}s. Rows are tagged data_source='synthetic_test'."
        )
        while True:
            try:
                run_once(conn)
            except Exception:
                logger.exception("Cycle failed — will retry next interval.")
                conn.rollback()
            time.sleep(POLL_INTERVAL_SEC)
    finally:
        conn.close()


if __name__ == "__main__":
    main()