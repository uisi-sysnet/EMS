#!/usr/bin/env python3
"""
Monitoring API Server
Serves REST endpoints for both the Air Quality and Seismic monitoring systems.
This process does NOT ingest data itself — it only reads what
air_quality_ingest.py and seismic_mqtt.py have already written.
"""

import logging
import os
import threading
import time
from datetime import datetime, timezone
from zoneinfo import ZoneInfo
from pathlib import Path

import psycopg2
from psycopg2 import pool
from psycopg2.extras import RealDictCursor

import uvicorn
from fastapi import FastAPI, Depends, HTTPException, Security, Request, Query, Path as ApiPath
from fastapi.security import APIKeyHeader
from dotenv import load_dotenv
from pydantic import BaseModel, Field
from typing import Any, List, Optional, Dict

# ==========================================================
# STANDARD VALIDATION ERROR SCHEMA (shown on every endpoint's docs)
# ==========================================================
class ValidationErrorDetail(BaseModel):
    loc: List[Any]
    msg: str
    type: str
    input: Optional[Any] = None
    ctx: Optional[Dict[str, Any]] = None

class HTTPValidationError(BaseModel):
    detail: List[ValidationErrorDetail]

VALIDATION_RESPONSES: Dict[int, Any] = {
    422: {"model": HTTPValidationError, "description": "Validation Error"},
}


# ==========================================================
# STANDARD ERROR SCHEMAS FOR AUTH / NOT FOUND / SERVER ERRORS
# These match what FastAPI's HTTPException actually returns:
# a plain {"detail": "<message>"} string, not the validation list shape above.
# ==========================================================
class HTTPError(BaseModel):
    detail: str

AUTH_RESPONSES: Dict[int, Any] = {
    403: {
        "model": HTTPError,
        "description": "Missing or invalid API key",
        "content": {
            "application/json": {
                "example": {"detail": "Unauthorized request: Invalid API Token"}
            }
        },
    },
}

NOT_FOUND_RESPONSES: Dict[int, Any] = {
    404: {
        "model": HTTPError,
        "description": "Station not found",
        "content": {
            "application/json": {
                "example": {"detail": "Station Identifier not found in database records."}
            }
        },
    },
}

SERVER_ERROR_RESPONSES: Dict[int, Any] = {
    500: {
        "model": HTTPError,
        "description": "Internal server error (e.g. database unreachable)",
        "content": {
            "application/json": {
                "example": {"detail": "Internal Server Error"}
            }
        },
    },
}

# Combined response sets, ready to drop into any @app.get(..., responses=...)
AUTHED_RESPONSES: Dict[int, Any] = {**VALIDATION_RESPONSES, **AUTH_RESPONSES, **SERVER_ERROR_RESPONSES}
AUTHED_LOOKUP_RESPONSES: Dict[int, Any] = {**AUTHED_RESPONSES, **NOT_FOUND_RESPONSES}


# ==========================================================
# SUCCESS RESPONSE SCHEMAS (200) — shown as "Example Value" / "Schema"
# in the docs for every endpoint, instead of a generic "string".
# ==========================================================

# ---- Air Quality ----
class AQLocation(BaseModel):
    latitude: Optional[float] = Field(None, examples=[14.5995])
    longitude: Optional[float] = Field(None, examples=[120.9842])

class AQWeather(BaseModel):
    temperature: Optional[float] = Field(None, examples=[29.4])
    humidity: Optional[float] = Field(None, examples=[68.2])
    pressure: Optional[float] = Field(None, examples=[101.3])
    rain: Optional[float] = Field(None, examples=[0.0])
    wind_speed: Optional[float] = Field(None, examples=[2.1])
    wind_direction: Optional[float] = Field(None, examples=[180.0])
    noise: Optional[float] = Field(None, examples=[62.5])

class AQPollutants(BaseModel):
    pm2_5: Optional[float] = Field(None, examples=[18.3])
    pm10: Optional[float] = Field(None, examples=[32.7])
    tsp: Optional[float] = Field(None, examples=[45.1])
    co: Optional[float] = Field(None, examples=[0.6])
    so2: Optional[float] = Field(None, examples=[5.2])
    no2: Optional[float] = Field(None, examples=[21.4])
    o3: Optional[float] = Field(None, examples=[38.9])
    pb: Optional[float] = Field(None, examples=[0.02])
    pb_temp: Optional[float] = Field(None, examples=[27.8])

class AQStation(BaseModel):
    station_mn: str = Field(..., examples=["4101025U122041"])
    friendly_name: Optional[str] = Field(None, examples=["AQM001"])
    location: AQLocation
    status: str = Field(..., examples=["online"], description="'online' if last reading was under 15 minutes ago, else 'offline'.")
    last_update: Optional[str] = Field(None, examples=["2026-07-10T09:15:32.123Z"])
    weather: AQWeather
    pollutants: AQPollutants

class AQStationsLatestResponse(BaseModel):
    timestamp: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    total_stations: int = Field(..., examples=[2])
    stations: List[AQStation]

class AQStationLatestResponse(BaseModel):
    timestamp: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    station: AQStation

class AQAnalyticsRow(BaseModel):
    station_mn: str = Field(..., examples=["4101025U122041"])
    station_name: Optional[str] = Field(None, examples=["AQM001"])
    temperature: Optional[float] = Field(None, examples=[28.9])
    humidity: Optional[float] = Field(None, examples=[70.1])
    air_pressure: Optional[float] = Field(None, examples=[101.2])
    rain: Optional[float] = Field(None, examples=[0.4])
    wind_speed: Optional[float] = Field(None, examples=[2.3])
    wind_direction: Optional[float] = Field(None, examples=[175.0])
    noise: Optional[float] = Field(None, examples=[60.8])
    pm25: Optional[float] = Field(None, examples=[19.1])
    pm10: Optional[float] = Field(None, examples=[33.4])
    tsp: Optional[float] = Field(None, examples=[46.0])
    carbon_monoxide: Optional[float] = Field(None, examples=[0.58])
    sulfur_dioxide: Optional[float] = Field(None, examples=[5.0])
    nitrogen_dioxide: Optional[float] = Field(None, examples=[20.7])
    ozone: Optional[float] = Field(None, examples=[37.2])
    lead: Optional[float] = Field(None, examples=[0.019])
    lead_temperature: Optional[float] = Field(None, examples=[27.5])

class AQAnalytics1dResponse(BaseModel):
    range: str = Field(..., examples=["24_hours_aggregated_average"])
    timestamp: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    results: List[AQAnalyticsRow]

class AQDailyAnalyticsRow(AQAnalyticsRow):
    summary_date: Optional[str] = Field(None, examples=["2026-07-09"])

class AQAnalyticsDailyResponse(BaseModel):
    range: str = Field(..., examples=["7_days_daily_averages"])
    results: List[AQDailyAnalyticsRow]

class AQStationInfo(BaseModel):
    station_mn: str = Field(..., examples=["4101025U122041"])
    station_name: Optional[str] = Field(None, examples=["AQM001"])
    latitude: Optional[float] = Field(None, examples=[14.5995])
    longitude: Optional[float] = Field(None, examples=[120.9842])
    updated_at: Optional[str] = Field(None, examples=["2026-07-10T08:00:00.000Z"])

class AQStationsListResponse(BaseModel):
    total_registered: int = Field(..., examples=[2])
    stations: List[AQStationInfo]


# ---- Seismic ----
class SeismicLocation(BaseModel):
    latitude: Optional[float] = Field(None, examples=[14.5995])
    longitude: Optional[float] = Field(None, examples=[120.9842])
    elevation_m: Optional[float] = Field(None, examples=[12.5])

class SeismicVector(BaseModel):
    x: Optional[float] = Field(None, examples=[0.0021])
    y: Optional[float] = Field(None, examples=[-0.0013])
    z: Optional[float] = Field(None, examples=[0.0042])

class SeismicStation(BaseModel):
    station_id: str = Field(..., examples=["STN-001"])
    friendly_name: Optional[str] = Field(None, examples=["Pacific Ridge Station"])
    location: SeismicLocation
    status: str = Field(..., examples=["online"], description="'online' if last reading was under 5 minutes ago, else 'offline'.")
    last_update: Optional[str] = Field(None, examples=["2026-07-10T09:15:32.123Z"])
    acceleration: SeismicVector
    velocity: SeismicVector
    displacement: SeismicVector
    pga: Optional[float] = Field(None, examples=[0.031])
    peis: Optional[int] = Field(None, examples=[2])

class SeismicStationsLatestResponse(BaseModel):
    timestamp: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    total_stations: int = Field(..., examples=[1])
    stations: List[SeismicStation]

class SeismicStationLatestResponse(BaseModel):
    timestamp: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    station: SeismicStation

class SeismicReading(BaseModel):
    time: str = Field(..., examples=["2026-07-10T09:00:00.000Z"])
    acc_x: Optional[float] = Field(None, examples=[0.0021])
    acc_y: Optional[float] = Field(None, examples=[-0.0013])
    acc_z: Optional[float] = Field(None, examples=[0.0042])
    vel_x: Optional[float] = Field(None, examples=[0.0008])
    vel_y: Optional[float] = Field(None, examples=[-0.0004])
    vel_z: Optional[float] = Field(None, examples=[0.0011])
    disp_x: Optional[float] = Field(None, examples=[0.0002])
    disp_y: Optional[float] = Field(None, examples=[-0.0001])
    disp_z: Optional[float] = Field(None, examples=[0.0003])
    pga: Optional[float] = Field(None, examples=[0.031])
    peis: Optional[int] = Field(None, examples=[2])

class SeismicHistoryResponse(BaseModel):
    station_id: str = Field(..., examples=["STN-001"])
    hours: int = Field(..., examples=[1])
    readings: List[SeismicReading]

class SeismicEvent(BaseModel):
    time: str = Field(..., examples=["2026-07-10T09:00:00.000Z"])
    station_id: str = Field(..., examples=["STN-001"])
    station_name: Optional[str] = Field(None, examples=["Pacific Ridge Station"])
    latitude: Optional[float] = Field(None, examples=[14.5995])
    longitude: Optional[float] = Field(None, examples=[120.9842])
    pga: Optional[float] = Field(None, examples=[0.058])
    peis: Optional[int] = Field(None, examples=[3])

class SeismicEventsResponse(BaseModel):
    min_peis: int = Field(..., examples=[1])
    hours: int = Field(..., examples=[24])
    total_events: int = Field(..., examples=[1])
    events: List[SeismicEvent]


# ---- System ----
class SubsystemStatus(BaseModel):
    air_quality_db_pool: str = Field(..., examples=["initialized"])
    seismic_db_pool: str = Field(..., examples=["initialized"])

class SystemStatusResponse(BaseModel):
    status: str = Field(..., examples=["operational"])
    timestamp: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    subsystems: SubsystemStatus

class ServiceLogEntry(BaseModel):
    created_at: str = Field(..., examples=["2026-07-10T09:16:00.000Z"])
    service: str = Field(..., examples=["air_quality_ingest"])
    level: str = Field(..., examples=["INFO"])
    logger_name: Optional[str] = Field(None, examples=["air_quality_ingest"])
    thread_name: Optional[str] = Field(None, examples=["MainThread"])
    message: str = Field(..., examples=["Ingested air quality reading for station 4101025U122041"])

class ServiceLogsResponse(BaseModel):
    total: int = Field(..., examples=[42])
    logs: List[ServiceLogEntry]


# ==========================================================
# CONFIG
# ==========================================================
SCRIPT_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=SCRIPT_DIR / ".env")

API_PORT = int(os.getenv("API_PORT", 8000))

# Parse "token:label,token:label" into a dict
def _parse_api_keys(raw: str):
    keys = {}
    for pair in raw.split(","):
        if ":" in pair:
            token, label = pair.split(":", 1)
            keys[token.strip()] = label.strip()
    return keys

AUTHORIZED_KEYS = _parse_api_keys(os.getenv("API_KEYS", ""))

AQ_DB = dict(
    host=os.getenv("AQ_DB_HOST", "127.0.0.1"),
    port=int(os.getenv("AQ_DB_PORT", 5432)),
    dbname=os.getenv("AQ_DB_NAME", "air_quality"),
    user=os.getenv("AQ_DB_USER", "aq_user"),
    password=os.getenv("AQ_DB_PASSWORD"),
)

SEISMIC_DB = dict(
    host=os.getenv("SEISMIC_DB_HOST", "localhost"),
    port=int(os.getenv("SEISMIC_DB_PORT", 5432)),
    dbname=os.getenv("SEISMIC_DB_NAME", "seismic_sensor_data"),
    user=os.getenv("SEISMIC_DB_USER", "seismic_user"),
    password=os.getenv("SEISMIC_DB_PASSWORD"),
)

# Connection pool sizing — kept modest by default since air_quality_ingest.py
# and seismic_mqtt.py may all be running on the same low-memory Raspberry Pi.
AQ_DB_POOL_MIN = int(os.getenv("AQ_DB_POOL_MIN", 2))
AQ_DB_POOL_MAX = int(os.getenv("AQ_DB_POOL_MAX", 10))
SEISMIC_DB_POOL_MIN = int(os.getenv("SEISMIC_DB_POOL_MIN", 2))
SEISMIC_DB_POOL_MAX = int(os.getenv("SEISMIC_DB_POOL_MAX", 10))

logger = logging.getLogger("monitoring_api")
logger.setLevel(logging.INFO)
formatter = logging.Formatter("%(asctime)s [%(levelname)s] %(threadName)s: %(message)s")
console_handler = logging.StreamHandler()
console_handler.setFormatter(formatter)
logger.addHandler(console_handler)

# ---- Database-backed logging ----
# Logs are mirrored into the `service_logs` table in the air_quality database
# (shared with air_quality_ingest.py and seismic_mqtt.py, tagged
# service='api_server') instead of a local log file — queryable via SQL or
# GET /api/system/logs below. Console output remains, captured by systemd's
# journal when this runs as a service.
DB_LOG_ENABLED = os.getenv("DB_LOG_ENABLED", "true").strip().lower() == "true"
DB_LOG_TABLE = os.getenv("DB_LOG_TABLE", "service_logs")
if DB_LOG_ENABLED and AQ_DB.get("password"):
    from db_logging import attach_db_logging
    _log_dsn = (
        f"host={AQ_DB['host']} port={AQ_DB['port']} dbname={AQ_DB['dbname']} "
        f"user={AQ_DB['user']} password={AQ_DB['password']}"
    )
    attach_db_logging(logger, _log_dsn, service_name="api_server", table=DB_LOG_TABLE)


def format_api_datetime(dt: datetime) -> str:
    if not dt:
        return None
    
    manila_tz = ZoneInfo("Asia/Manila")

    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=manila_tz)
    else:
        dt = dt.astimezone(manila_tz)
    return dt.strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "+08:00"


# ==========================================================
# CONNECTION POOLS (one per database)
# ==========================================================
_aq_pool = None
_seismic_pool = None
_pool_lock = threading.Lock()


def _validate_config():
    env_path = SCRIPT_DIR / ".env"
    missing = []
    if not AQ_DB.get("password"):
        missing.append("AQ_DB_PASSWORD")
    if not SEISMIC_DB.get("password"):
        missing.append("SEISMIC_DB_PASSWORD")
    if not AUTHORIZED_KEYS:
        missing.append("API_KEYS")

    if missing:
        print("=" * 70)
        print("CONFIG ERROR: could not load required values from .env")
        print(f"Expected .env at: {env_path}")
        print(f"Found file there: {env_path.exists()}")
        print(f"Missing/empty vars: {', '.join(missing)}")
        print("Common causes on Windows:")
        print("  - file got saved as '.env.txt' instead of '.env'")
        print("  - .env is not in the same folder as this script")
        print("  - .env was saved as UTF-16 instead of UTF-8")
        print("=" * 70)
        raise SystemExit(1)


def initialize_pools():
    global _aq_pool, _seismic_pool
    _validate_config()
    with _pool_lock:
        if _aq_pool is None:
            _aq_pool = pool.ThreadedConnectionPool(minconn=AQ_DB_POOL_MIN, maxconn=AQ_DB_POOL_MAX, **AQ_DB)
            logger.info("Air quality DB pool ready.")
        if _seismic_pool is None:
            _seismic_pool = pool.ThreadedConnectionPool(minconn=SEISMIC_DB_POOL_MIN, maxconn=SEISMIC_DB_POOL_MAX, **SEISMIC_DB)
            logger.info("Seismic DB pool ready.")

        # Housekeeping table for API request logs, kept in the air quality DB
        conn = _aq_pool.getconn()
        try:
            cur = conn.cursor()
            cur.execute("""
                CREATE TABLE IF NOT EXISTS api_request_logs (
                    client_ip INET,
                    method VARCHAR(10),
                    path TEXT,
                    status_code INT,
                    duration_ms DOUBLE PRECISION,
                    api_key_owner VARCHAR(100),
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            """)
            cur.execute("SELECT create_hypertable('api_request_logs', 'created_at', if_not_exists => TRUE, migrate_data => TRUE);")
            cur.execute("CREATE INDEX IF NOT EXISTS idx_api_logs_composite ON api_request_logs(path, created_at DESC);")
            conn.commit()
        except Exception as e:
            conn.rollback()
            logger.exception(f"API log table setup failed: {e}")
        finally:
            _aq_pool.putconn(conn)


def get_aq_conn():
    return _aq_pool.getconn()


def release_aq_conn(conn):
    if conn:
        try:
            _aq_pool.putconn(conn)
        except Exception:
            conn.close()


def get_seismic_conn():
    return _seismic_pool.getconn()


def release_seismic_conn(conn):
    if conn:
        try:
            _seismic_pool.putconn(conn)
        except Exception:
            conn.close()


def insert_api_log(client_ip, method, path, status_code, duration_ms, api_key_owner):
    conn = None
    try:
        conn = get_aq_conn()
        cur = conn.cursor()
        cur.execute("""
            INSERT INTO api_request_logs (client_ip, method, path, status_code, duration_ms, api_key_owner)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (client_ip, method, path, status_code, duration_ms, api_key_owner))
        conn.commit()
    except Exception as e:
        if conn:
            conn.rollback()
        logger.error(f"Error logging API request: {e}")
    finally:
        release_aq_conn(conn)


# ==========================================================
# FASTAPI APP
# ==========================================================
app = FastAPI(
    title="Environmental Monitoring System (Air Quality + Seismic)",
    version="1.0",
    description="""Read-only REST endpoints backed by the Air Quality and Seismic TimescaleDB databases.""",
)
api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)


def verify_api_key(api_key: str = Security(api_key_header)):
    if api_key not in AUTHORIZED_KEYS:
        raise HTTPException(status_code=403, detail="Unauthorized request: Invalid API Token")
    return api_key


@app.middleware("http")
async def monitor_and_log_api_requests(request: Request, call_next):
    start_time = time.time()
    client_ip = request.client.host if request.client else "Unknown"
    method = request.method
    path = request.url.path
    raw_token = request.headers.get("X-API-Key")
    api_key_owner = AUTHORIZED_KEYS.get(raw_token, "Unauthorized/None")

    response = await call_next(request)

    duration_ms = round((time.time() - start_time) * 1000, 2)
    status_code = response.status_code

    logger.info(f"[API] {client_ip} ({api_key_owner}) -> {method} {path} | Status: {status_code} | {duration_ms}ms")

    threading.Thread(
        target=insert_api_log,
        args=(client_ip, method, path, status_code, duration_ms, api_key_owner),
        daemon=True,
    ).start()

    return response


# ----------------------------------------------------------
# AIR QUALITY ENDPOINTS
# ----------------------------------------------------------
def map_aq_station_row_to_json(row, now):
    status = "offline"
    last_update_str = None
    if row['data_time']:
        # data_time is stored as naive Manila local time (Postgres session
        # TimeZone converts tz-aware UTC values to local on insert into a
        # `timestamp without time zone` column) — do NOT tag it as UTC.
        manila_tz = ZoneInfo("Asia/Manila")
        last_update_manila = row['data_time'].replace(tzinfo=manila_tz)
        last_update_str = format_api_datetime(last_update_manila)
        last_update_utc = last_update_manila.astimezone(timezone.utc)
        if (now - last_update_utc).total_seconds() < 900:
            status = "online"

    return {
        "station_mn": row['station_mn'],
        "friendly_name": row['station_name'],
        "location": {"latitude": row['latitude'], "longitude": row['longitude']},
        "status": status,
        "last_update": last_update_str,
        "weather": {
            "temperature": row['temperature'],
            "humidity": row['humidity'],
            "pressure": row['air_pressure'],
            "rain": row['rain'],
            "wind_speed": row['wind_speed'],
            "wind_direction": row['wind_direction'],
            "noise": row['noise'],
        },
        "pollutants": {
            "pm2_5": row['pm25'],
            "pm10": row['pm10'],
            "tsp": row['tsp'],
            "co": row['carbon_monoxide'],
            "so2": row['sulfur_dioxide'],
            "no2": row['nitrogen_dioxide'],
            "o3": row['ozone'],
            "pb": row['lead'],
            "pb_temp": row['lead_temperature'],
        },
    }


@app.get(
    "/api/air-quality/stations/latest",
    tags=["Air Quality - Live"],
    summary="Latest reading for every air quality station",
    description="Returns the most recent sensor reading for each registered air quality station, including weather and pollutant values.",
    response_model=AQStationsLatestResponse,
    responses=AUTHED_RESPONSES,
)
def aq_latest_all(api_key: str = Depends(verify_api_key)):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("""
            SELECT DISTINCT ON (st.station_mn)
                st.station_mn, st.station_name, st.latitude, st.longitude, s.data_time,
                s.temperature, s.humidity, s.air_pressure, s.rain, s.wind_speed, s.wind_direction, s.noise,
                s.pm25, s.pm10, s.tsp, s.carbon_monoxide, s.sulfur_dioxide,
                s.nitrogen_dioxide, s.ozone, s.lead, s.lead_temperature
            FROM stations st
            LEFT JOIN sensor_data s ON st.station_mn = s.station_mn
            ORDER BY st.station_mn, s.data_time DESC NULLS LAST;
        """)
        rows = cur.fetchall()
        now = datetime.now(timezone.utc)
        stations_list = [map_aq_station_row_to_json(r, now) for r in rows]

        # Top-level "timestamp" = the actual latest saved reading across all
        # stations (max data_time), not the moment this request was handled.
        # data_time is naive Manila local time — tag it as such, don't shift it.
        manila_tz = ZoneInfo("Asia/Manila")
        data_times = [r['data_time'] for r in rows if r['data_time']]
        if data_times:
            latest_manila = max(data_times).replace(tzinfo=manila_tz)
            response_timestamp = format_api_datetime(latest_manila)
        else:
            response_timestamp = format_api_datetime(now)

        return {"timestamp": response_timestamp, "total_stations": len(stations_list), "stations": stations_list}
    except Exception as e:
        logger.error(f"AQ latest error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_aq_conn(conn)


@app.get(
    "/api/air-quality/stations/{station_mn}/latest",
    tags=["Air Quality - Live"],
    summary="Latest reading for one air quality station",
    description="Returns the most recent sensor reading for a single station, identified by its monitoring number (station_mn).",
    response_model=AQStationLatestResponse,
    responses=AUTHED_LOOKUP_RESPONSES,
)
def aq_latest_station(
    station_mn: str = ApiPath(..., description="Station monitoring number, e.g. '4101025U122041'."),
    api_key: str = Depends(verify_api_key),
):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("""
            SELECT st.station_mn, st.station_name, st.latitude, st.longitude, s.data_time,
                   s.temperature, s.humidity, s.air_pressure, s.rain, s.wind_speed, s.wind_direction, s.noise,
                   s.pm25, s.pm10, s.tsp, s.carbon_monoxide, s.sulfur_dioxide,
                   s.nitrogen_dioxide, s.ozone, s.lead, s.lead_temperature
            FROM stations st
            LEFT JOIN sensor_data s ON st.station_mn = s.station_mn
            WHERE st.station_mn = %s
            ORDER BY s.data_time DESC NULLS LAST LIMIT 1;
        """, (station_mn,))
        row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="Station Identifier not found in database records.")
        now = datetime.now(timezone.utc)
        station_json = map_aq_station_row_to_json(row, now)
        response_timestamp = station_json["last_update"] or format_api_datetime(now)
        return {"timestamp": response_timestamp, "station": station_json}
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"AQ single station error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_aq_conn(conn)


def _aq_avg_query(interval: str):
    return f"""
        SELECT st.station_mn, st.station_name,
               ROUND(AVG(s.temperature)::numeric, 2) as temperature,
               ROUND(AVG(s.humidity)::numeric, 2) as humidity,
               ROUND(AVG(s.air_pressure)::numeric, 2) as air_pressure,
               ROUND(AVG(s.rain)::numeric, 2) as rain,
               ROUND(AVG(s.wind_speed)::numeric, 2) as wind_speed,
               ROUND(AVG(s.wind_direction)::numeric, 2) as wind_direction,
               ROUND(AVG(s.noise)::numeric, 2) as noise,
               ROUND(AVG(s.pm25)::numeric, 2) as pm25,
               ROUND(AVG(s.pm10)::numeric, 2) as pm10,
               ROUND(AVG(s.tsp)::numeric, 2) as tsp,
               ROUND(AVG(s.carbon_monoxide)::numeric, 2) as carbon_monoxide,
               ROUND(AVG(s.sulfur_dioxide)::numeric, 2) as sulfur_dioxide,
               ROUND(AVG(s.nitrogen_dioxide)::numeric, 2) as nitrogen_dioxide,
               ROUND(AVG(s.ozone)::numeric, 2) as ozone,
               ROUND(AVG(s.lead)::numeric, 2) as lead,
               ROUND(AVG(s.lead_temperature)::numeric, 2) as lead_temperature
        FROM stations st
        JOIN sensor_data s ON st.station_mn = s.station_mn
        WHERE s.data_time >= NOW() - INTERVAL '{interval}'
        GROUP BY st.station_mn, st.station_name
        ORDER BY st.station_mn;
    """


@app.get(
    "/api/air-quality/analytics/1d",
    tags=["Air Quality - Analytics"],
    summary="24-hour average readings per station",
    description="Returns one averaged row per station, aggregated over the last 24 hours.",
    response_model=AQAnalytics1dResponse,
    responses=AUTHED_RESPONSES,
)
def aq_avg_1d(api_key: str = Depends(verify_api_key)):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute(_aq_avg_query("1 day"))
        return {"range": "24_hours_aggregated_average", "timestamp": format_api_datetime(datetime.now(timezone.utc)), "results": cur.fetchall()}
    except Exception as e:
        logger.error(f"AQ 1d analytics error: {e}")
        raise HTTPException(status_code=500, detail="Internal Analytical Server Error")
    finally:
        release_aq_conn(conn)


def _aq_daily_avg_query(interval: str):
    return f"""
        SELECT st.station_mn, st.station_name,
               time_bucket('1 day', s.data_time) as summary_date,
               ROUND(AVG(s.temperature)::numeric, 2) as temperature,
               ROUND(AVG(s.humidity)::numeric, 2) as humidity,
               ROUND(AVG(s.air_pressure)::numeric, 2) as air_pressure,
               ROUND(AVG(s.rain)::numeric, 2) as rain,
               ROUND(AVG(s.wind_speed)::numeric, 2) as wind_speed,
               ROUND(AVG(s.wind_direction)::numeric, 2) as wind_direction,
               ROUND(AVG(s.noise)::numeric, 2) as noise,
               ROUND(AVG(s.pm25)::numeric, 2) as pm25,
               ROUND(AVG(s.pm10)::numeric, 2) as pm10,
               ROUND(AVG(s.tsp)::numeric, 2) as tsp,
               ROUND(AVG(s.carbon_monoxide)::numeric, 2) as carbon_monoxide,
               ROUND(AVG(s.sulfur_dioxide)::numeric, 2) as sulfur_dioxide,
               ROUND(AVG(s.nitrogen_dioxide)::numeric, 2) as nitrogen_dioxide,
               ROUND(AVG(s.ozone)::numeric, 2) as ozone,
               ROUND(AVG(s.lead)::numeric, 2) as lead,
               ROUND(AVG(s.lead_temperature)::numeric, 2) as lead_temperature
        FROM stations st
        JOIN sensor_data s ON st.station_mn = s.station_mn
        WHERE s.data_time >= NOW() - INTERVAL '{interval}'
        GROUP BY st.station_mn, st.station_name, summary_date
        ORDER BY st.station_mn, summary_date DESC;
    """


@app.get(
    "/api/air-quality/analytics/7d",
    tags=["Air Quality - Analytics"],
    summary="7-day daily average readings per station",
    description="Returns one averaged row per station per day, aggregated over the last 7 days (time-bucketed daily).",
    response_model=AQAnalyticsDailyResponse,
    responses=AUTHED_RESPONSES,
)
def aq_avg_7d(api_key: str = Depends(verify_api_key)):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute(_aq_daily_avg_query("7 days"))
        rows = cur.fetchall()
        for r in rows:
            if r['summary_date']:
                r['summary_date'] = r['summary_date'].strftime("%Y-%m-%d")
        return {"range": "7_days_daily_averages", "results": rows}
    except Exception as e:
        logger.error(f"AQ 7d analytics error: {e}")
        raise HTTPException(status_code=500, detail="Internal Analytical Server Error")
    finally:
        release_aq_conn(conn)


@app.get(
    "/api/air-quality/analytics/30d",
    tags=["Air Quality - Analytics"],
    summary="30-day daily average readings per station",
    description="Returns one averaged row per station per day, aggregated over the last 30 days (time-bucketed daily).",
    response_model=AQAnalyticsDailyResponse,
    responses=AUTHED_RESPONSES,
)
def aq_avg_30d(api_key: str = Depends(verify_api_key)):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute(_aq_daily_avg_query("30 days"))
        rows = cur.fetchall()
        for r in rows:
            if r['summary_date']:
                r['summary_date'] = r['summary_date'].strftime("%Y-%m-%d")
        return {"range": "30_days_daily_averages", "results": rows}
    except Exception as e:
        logger.error(f"AQ 30d analytics error: {e}")
        raise HTTPException(status_code=500, detail="Internal Analytical Server Error")
    finally:
        release_aq_conn(conn)


@app.get(
    "/api/air-quality/stations",
    tags=["Air Quality - Infrastructure"],
    summary="List all registered air quality stations",
    description="Returns the static registry of air quality stations (id, name, location) — not live readings.",
    response_model=AQStationsListResponse,
    responses=AUTHED_RESPONSES,
)
def aq_list_stations(api_key: str = Depends(verify_api_key)):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("SELECT station_mn, station_name, latitude, longitude, updated_at FROM stations ORDER BY station_mn;")
        rows = cur.fetchall()
        for r in rows:
            if r['updated_at']:
                r['updated_at'] = format_api_datetime(r['updated_at'])
        return {"total_registered": len(rows), "stations": rows}
    except Exception as e:
        logger.error(f"AQ stations list error: {e}")
        raise HTTPException(status_code=500, detail="Internal Database Error")
    finally:
        release_aq_conn(conn)


# ----------------------------------------------------------
# SEISMIC ENDPOINTS
# ----------------------------------------------------------
def map_seismic_row_to_json(row, now):
    status = "offline"
    last_update_str = None
    if row['time']:
        last_update_utc = row['time'] if row['time'].tzinfo else row['time'].replace(tzinfo=timezone.utc)
        last_update_str = format_api_datetime(last_update_utc)
        if (now - last_update_utc).total_seconds() < 300:
            status = "online"

    return {
        "station_id": row['station_id'],
        "friendly_name": row['station_name'],
        "location": {
            "latitude": row['latitude'],
            "longitude": row['longitude'],
            "elevation_m": row['elevation_m'],
        },
        "status": status,
        "last_update": last_update_str,
        "acceleration": {"x": row['acc_x'], "y": row['acc_y'], "z": row['acc_z']},
        "velocity": {"x": row['vel_x'], "y": row['vel_y'], "z": row['vel_z']},
        "displacement": {"x": row['disp_x'], "y": row['disp_y'], "z": row['disp_z']},
        "pga": row['pga'],
        "peis": row['peis'],
    }


@app.get(
    "/api/seismic/stations/latest",
    tags=["Seismic - Live"],
    summary="Latest reading for every seismic station",
    description="Returns the most recent acceleration/velocity/displacement reading for each seismic station.",
    response_model=SeismicStationsLatestResponse,
    responses=AUTHED_RESPONSES,
)
def seismic_latest_all(api_key: str = Depends(verify_api_key)):
    conn = get_seismic_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("""
            SELECT DISTINCT ON (station_id)
                station_id, station_name, latitude, longitude, elevation_m, time,
                acc_x, acc_y, acc_z, vel_x, vel_y, vel_z, disp_x, disp_y, disp_z, pga, peis
            FROM station_metrics
            ORDER BY station_id, time DESC;
        """)
        rows = cur.fetchall()
        now = datetime.now(timezone.utc)
        stations_list = [map_seismic_row_to_json(r, now) for r in rows]
        return {"timestamp": format_api_datetime(now), "total_stations": len(stations_list), "stations": stations_list}
    except Exception as e:
        logger.error(f"Seismic latest error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_seismic_conn(conn)


@app.get(
    "/api/seismic/stations/{station_id}/latest",
    tags=["Seismic - Live"],
    summary="Latest reading for one seismic station",
    description="Returns the most recent reading for a single seismic station, identified by its station_id.",
    response_model=SeismicStationLatestResponse,
    responses=AUTHED_LOOKUP_RESPONSES,
)
def seismic_latest_station(
    station_id: str = ApiPath(..., description="Seismic station identifier, e.g. 'STN-001'."),
    api_key: str = Depends(verify_api_key),
):
    conn = get_seismic_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("""
            SELECT station_id, station_name, latitude, longitude, elevation_m, time,
                   acc_x, acc_y, acc_z, vel_x, vel_y, vel_z, disp_x, disp_y, disp_z, pga, peis
            FROM station_metrics
            WHERE station_id = %s
            ORDER BY time DESC LIMIT 1;
        """, (station_id,))
        row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="Station Identifier not found in database records.")
        now = datetime.now(timezone.utc)
        return {"timestamp": format_api_datetime(now), "station": map_seismic_row_to_json(row, now)}
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Seismic single station error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_seismic_conn(conn)


@app.get(
    "/api/seismic/stations/{station_id}/history",
    tags=["Seismic - History"],
    summary="Raw reading history for one seismic station",
    description="Returns every raw telemetry reading for a station within the lookback window, ordered oldest to newest.",
    response_model=SeismicHistoryResponse,
    responses=AUTHED_RESPONSES,
)
def seismic_station_history(
    station_id: str = ApiPath(..., description="Seismic station identifier, e.g. 'STN-001'."),
    hours: int = Query(1, ge=1, le=24, description="Lookback window in hours. Minimum 1, maximum 24."),
    api_key: str = Depends(verify_api_key),
):
    """Raw readings for a station over the last N hours (default 1, max 24)."""
    hours = max(1, min(hours, 24))
    conn = get_seismic_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("""
            SELECT time, acc_x, acc_y, acc_z, vel_x, vel_y, vel_z,
                   disp_x, disp_y, disp_z, pga, peis
            FROM station_metrics
            WHERE station_id = %s AND time >= NOW() - (%s || ' hours')::interval
            ORDER BY time ASC;
        """, (station_id, hours))
        rows = cur.fetchall()
        for r in rows:
            if r['time']:
                r['time'] = format_api_datetime(r['time'])
        return {"station_id": station_id, "hours": hours, "readings": rows}
    except Exception as e:
        logger.error(f"Seismic history error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_seismic_conn(conn)


@app.get(
    "/api/seismic/events",
    tags=["Seismic - Events"],
    summary="Seismic readings flagged as events",
    description="Returns readings where the PEIS intensity value meets or exceeds min_peis, within the lookback window.",
    response_model=SeismicEventsResponse,
    responses=AUTHED_RESPONSES,
)
def seismic_events(
    min_peis: int = Query(1, ge=0, description="Minimum PEIS intensity value to include (readings with peis >= this value)."),
    hours: int = Query(24, ge=1, le=168, description="Lookback window in hours. Minimum 1, maximum 168 (7 days)."),
    api_key: str = Depends(verify_api_key),
):
    """Readings flagged as seismic events (PEIS >= min_peis) within the lookback window."""
    hours = max(1, min(hours, 168))
    conn = get_seismic_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        cur.execute("""
            SELECT time, station_id, station_name, latitude, longitude, pga, peis
            FROM station_metrics
            WHERE peis >= %s AND time >= NOW() - (%s || ' hours')::interval
            ORDER BY time DESC;
        """, (min_peis, hours))
        rows = cur.fetchall()
        for r in rows:
            if r['time']:
                r['time'] = format_api_datetime(r['time'])
        return {"min_peis": min_peis, "hours": hours, "total_events": len(rows), "events": rows}
    except Exception as e:
        logger.error(f"Seismic events error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_seismic_conn(conn)


# ----------------------------------------------------------
# SYSTEM LOGS
# ----------------------------------------------------------
@app.get(
    "/api/system/logs",
    tags=["System"],
    summary="Query centralized service logs",
    description=(
        "Returns log records written by air_quality_ingest, seismic_mqtt, and api_server "
        "into the shared `service_logs` table."
    ),
    response_model=ServiceLogsResponse,
    responses=AUTHED_RESPONSES,
)
def system_logs(
    service: Optional[str] = Query(None, description="Filter to one service: air_quality_ingest, seismic_mqtt, or api_server."),
    level: Optional[str] = Query(None, description="Filter to one log level: INFO, WARNING, ERROR, CRITICAL."),
    hours: int = Query(24, ge=1, le=168, description="Lookback window in hours. Minimum 1, maximum 168 (7 days)."),
    limit: int = Query(200, ge=1, le=1000, description="Max rows to return."),
    api_key: str = Depends(verify_api_key),
):
    conn = get_aq_conn()
    try:
        cur = conn.cursor(cursor_factory=RealDictCursor)
        clauses = ["created_at >= NOW() - (%s || ' hours')::interval"]
        params: list = [hours]
        if service:
            clauses.append("service = %s")
            params.append(service)
        if level:
            clauses.append("level = %s")
            params.append(level.upper())
        where = " AND ".join(clauses)
        params.append(limit)
        cur.execute(f"""
            SELECT created_at, service, level, logger_name, thread_name, message
            FROM service_logs
            WHERE {where}
            ORDER BY created_at DESC
            LIMIT %s;
        """, params)
        rows = cur.fetchall()
        for r in rows:
            if r['created_at']:
                r['created_at'] = format_api_datetime(r['created_at'])
        return {"total": len(rows), "logs": rows}
    except Exception as e:
        logger.error(f"System logs query error: {e}")
        raise HTTPException(status_code=500, detail="Internal Server Error")
    finally:
        release_aq_conn(conn)


# ----------------------------------------------------------
# SYSTEM STATUS
# ----------------------------------------------------------
@app.get(
    "/api/system/status",
    tags=["System"],
    summary="API health check",
    description="Returns overall API status and whether each database connection pool is initialized. No API key required.",
    response_model=SystemStatusResponse,
    responses=VALIDATION_RESPONSES,
)
def system_health_check():
    return {
        "status": "operational",
        "timestamp": format_api_datetime(datetime.now(timezone.utc)),
        "subsystems": {
            "air_quality_db_pool": "initialized" if _aq_pool else "not initialized",
            "seismic_db_pool": "initialized" if _seismic_pool else "not initialized",
        },
    }


if __name__ == "__main__":
    initialize_pools()
    logger.info(f"Monitoring API starting on 0.0.0.0:{API_PORT}")
    uvicorn.run(app, host="0.0.0.0", port=API_PORT, log_level="warning")