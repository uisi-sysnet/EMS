# config.py
import os
from dotenv import load_dotenv

# Load the .env file once
load_dotenv()

# 1. Database Credentials
DB_HOST = os.getenv("DB_HOST", "localhost")
DB_NAME = os.getenv("DB_NAME", "monitoring_db")
DB_USER = os.getenv("DB_USER", "postgres")
DB_PASS = os.getenv("DB_PASSWORD", "secret")
DB_PORT = os.getenv("DB_PORT", "5432")

# 2. API Configs
API_HOST = os.getenv("API_HOST", "0.0.0.0")
API_PORT = int(os.getenv("API_PORT", "8000"))

# 3. Air Quality Specific Configs
AQ_INTERVAL = int(os.getenv("AQ_SENSOR_POLL_INTERVAL_SECS", "60"))
AQ_ALERT_THRESHOLD = float(os.getenv("AQ_HIGH_PM25_THRESHOLD", "35.0"))

# 4. Seismic Specific Configs
SEISMIC_STATION = os.getenv("SEISMIC_STATION_ID", "UNKNOWN_STATION")
SEISMIC_THRESHOLD = float(os.getenv("SEISMIC_TRIGGER_THRESHOLD_G", "0.02"))