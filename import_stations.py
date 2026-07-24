#!/usr/bin/env python3
"""
import_stations.py — one-off / repeatable CLI tool to load station
registry entries from a JSON file (same format as the old stations.json)
into the `stations` table in the air quality database.

You do NOT need this for normal operation: air_quality_ingest.py reads its
station list from the database itself, and will auto-import stations.json
on its very first run if the `stations` table is empty. Use this script
when you want to:
  - bulk-add/update stations from a JSON file after the table already has
    rows (the automatic import only ever fires once, on an empty table)
  - re-apply a JSON file after editing it, without touching SQL by hand
  - migrate stations into a freshly rebuilt database

Usage:
    python3 import_stations.py                     # imports ./stations.json
    python3 import_stations.py /path/to/file.json   # imports a specific file
    python3 import_stations.py --dry-run            # preview only, no writes
"""

import argparse
import json
import os
import sys
from pathlib import Path

import psycopg2
from dotenv import load_dotenv

SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

DB_HOST = os.getenv("SYSTEM_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("SYSTEM_DB_PORT", 5432))
DB_NAME = os.getenv("AQ_DB_NAME", "air_quality")
DB_USER = os.getenv("SYSTEM_DB_USER", "aq_user")
DB_PASSWORD = os.getenv("SYSTEM_DB_PASSWORD")

# Keep in sync with the `stations` table schema in air_quality_ingest.py.
REQUIRED_KEYS = {"station_name", "enabled", "latitude", "longitude", "lead_ip", "lead_port", "lead_slave"}


def load_json(path: Path) -> dict:
    with open(path, "r", encoding="utf-8") as f:
        stations = json.load(f)
    valid = {}
    for mn, info in stations.items():
        missing = REQUIRED_KEYS - info.keys()
        if missing:
            print(f"  SKIP {mn}: missing {', '.join(sorted(missing))}")
            continue
        valid[mn] = info
    return valid


def main():
    parser = argparse.ArgumentParser(description="Import/update stations from a JSON file into the database.")
    parser.add_argument(
        "json_file", nargs="?", default=str(SCRIPT_DIR / "stations.json"),
        help="Path to the stations JSON file (default: stations.json next to this script).",
    )
    parser.add_argument("--dry-run", action="store_true", help="Show what would be imported without writing to the database.")
    args = parser.parse_args()

    json_path = Path(args.json_file)
    if not json_path.exists():
        print(f"ERROR: {json_path} not found.")
        sys.exit(1)

    if not DB_PASSWORD:
        print("ERROR: AQ_DB_PASSWORD not set — check .env.")
        sys.exit(1)

    stations = load_json(json_path)
    if not stations:
        print("Nothing valid to import.")
        sys.exit(0)

    print(f"Found {len(stations)} valid station(s) in {json_path.name}:")
    for mn, info in stations.items():
        print(f"  {mn}: {info.get('station_name')} (enabled={info.get('enabled')})")

    if args.dry_run:
        print("\n--dry-run: no changes written.")
        return

    conn = psycopg2.connect(host=DB_HOST, port=DB_PORT, dbname=DB_NAME, user=DB_USER, password=DB_PASSWORD)
    try:
        cur = conn.cursor()
        # Self-contained: creates the table if this is run before
        # air_quality_ingest.py has ever started. Schema must stay in sync
        # with create_tables() in air_quality_ingest.py.
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

        for mn, info in stations.items():
            cur.execute("""
                INSERT INTO stations (station_mn, station_name, enabled, latitude, longitude, lead_ip, lead_port, lead_slave)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                ON CONFLICT (station_mn) DO UPDATE SET
                    station_name = EXCLUDED.station_name,
                    enabled = EXCLUDED.enabled,
                    latitude = EXCLUDED.latitude,
                    longitude = EXCLUDED.longitude,
                    lead_ip = EXCLUDED.lead_ip,
                    lead_port = EXCLUDED.lead_port,
                    lead_slave = EXCLUDED.lead_slave,
                    updated_at = CURRENT_TIMESTAMP;
            """, (mn, info["station_name"], info["enabled"], info["latitude"], info["longitude"],
                  info["lead_ip"], info["lead_port"], info["lead_slave"]))
        conn.commit()
        print(f"\nImported/updated {len(stations)} station(s) into '{DB_NAME}'.")
        print(
            "Note: air_quality_ingest.py picks up station changes automatically within "
            "AQ_STATIONS_REFRESH_INTERVAL_SEC (default 300s) — or restart the service to apply immediately."
        )
    except Exception as e:
        conn.rollback()
        print(f"ERROR: {e}")
        sys.exit(1)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
