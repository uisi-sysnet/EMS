# EMS IoT Gateway — Raspberry Pi Deployment

Three always-on services on one Raspberry Pi (Raspberry Pi OS / Raspbian,
64-bit strongly recommended):

| Service                | File                     | Ingests via                          | Database              |
|-------------------------|--------------------------|---------------------------------------|------------------------|
| Air Quality Ingestion   | `air_quality_ingest.py`  | TCP/HJ212 from AQ sensors             | `air_quality` (Timescale) |
| Seismic Ingestion       | `seismic_mqtt.py`        | MQTT **and** SMS (SIM800L)            | `seismic_sensor_data` (Timescale) |
| Monitoring API          | `api_server.py`          | HTTP (FastAPI)                        | reads both databases   |

All three log into a shared `service_logs` table in `air_quality` instead of
local files (better for SD card longevity), and are supervised by systemd
via `ems.target` so they start on boot and restart on failure.

## Files in this package

```
air_quality_ingest.py          Air quality TCP/HJ212 ingestion service
seismic_mqtt.py                Seismic ingestion — MQTT + SMS (SIM800L)
sim800l.py                     AT-command driver for the SIM800L modem
api_server.py                  FastAPI monitoring/read API
db_logging.py                  Shared Postgres logging handler (all 3 services)
import_stations.py             CLI: (re-)apply stations.json to the database

deploy.sh                      One-time OS/DB/broker setup (run with sudo)
install_services.sh            Installs + starts the systemd units (run with sudo)
ems.target                     systemd target grouping all 3 services
ems-air-quality_service.template
ems-seismic_service.template
ems-api_service.template       systemd unit templates (rendered by install_services.sh)

requirements.txt               Python dependencies
_env                            Environment template — rename to .env and fill in
stations.json                  Air-quality station registry (one-time DB import)
```

## Deploy from scratch

1. Copy this whole folder onto the Pi (e.g. `/home/pi/ems/`).
2. `mv _env .env` and fill in real passwords/API keys.
3. `sudo ./deploy.sh`
   - Installs Python, PostgreSQL 16, TimescaleDB, Mosquitto, ntpsec
   - Creates DB roles, configures Mosquitto auth, installs Python deps
   - Detects Raspberry Pi OS vs Ubuntu and adjusts (see below)
   - Enables the Pi's UART for the SIM800L and disables the serial console
4. **Reboot** (`sudo reboot`) — required for the UART change to take effect.
5. `sudo ./install_services.sh`
   - Renders the systemd units, enables + starts `ems.target`
6. Check it's alive:
   ```
   sudo systemctl status ems.target
   sudo journalctl -u ems-seismic.service -f
   ```

## Raspberry Pi–specific behavior in deploy.sh

- Detects CPU architecture; **warns clearly if you're on 32-bit Raspberry Pi
  OS (armhf)** — TimescaleDB has no official armhf packages, so 64-bit
  Raspberry Pi OS is required for the seismic/AQ hypertables to work.
- Uses `--no-install-recommends` everywhere to keep the SD card footprint down.
- Installs `build-essential`/`libpq-dev`/`python3-dev` as a fallback in case
  a Python package has no prebuilt ARM wheel on piwheels.org.
- Warns if RAM + swap look too small for package builds, with the
  `dphys-swapfile` fix.
- Configures the Pi's UART (`raspi-config nonint do_serial_cons/do_serial_hw`)
  so the SIM800L can use it for AT commands instead of a login console.
- Systemd unit templates set `PYTHONUNBUFFERED=1` and include a commented-out
  `MemoryMax=` you can enable to cap RAM per service on a constrained Pi.

## Station registry (air quality)

`air_quality_ingest.py` reads its station list from the database, not from
`stations.json` directly. On first run, if the `stations` table is empty and
`stations.json` exists, it's imported automatically (one time only). After
that, the database is the source of truth — the service refreshes its
in-memory copy from the DB every `AQ_STATIONS_REFRESH_INTERVAL_SEC` (default
300s). To (re-)apply a JSON file later:
```
python3 import_stations.py [path/to/stations.json]   # add --dry-run to preview
```

## Seismic: two ingestion channels

`seismic_mqtt.py` runs both, independently, at the same time:

- **MQTT** (existing) — subscribes to `MQTT_TOPIC`, expects JSON payloads.
- **SMS** (new) — a background thread drives a SIM800L over UART
  (`SIM800_SERIAL_PORT`, default `/dev/serial0`) and parses incoming SMS in
  the `SEISMSG1` format (documented in the file header of `seismic_mqtt.py`):
  ```
  SEISMSG1,<station_id>,<epoch_ts>,<lat>,<lon>,<elev_m>,<acc_x>,<acc_y>,<acc_z>,<vel_x>,<vel_y>,<vel_z>,<disp_x>,<disp_y>,<disp_z>,<pga>,<peis>,<checksum>
  ```
  Location fields and the checksum are optional. Every SMS received — parsed
  or not — is stored in the `sms_messages` table (sender, raw body, parse
  status/error), and successfully parsed readings land in the same
  `station_metrics` table MQTT uses, tagged `source = 'sms'` (vs `'mqtt'`)
  so you can tell which channel each row came from with a plain SQL query:
  ```sql
  SELECT time, station_id, source, pga, peis FROM station_metrics ORDER BY time DESC LIMIT 20;
  ```

**Wiring**: the driver talks to a serial device path, not GPIO pins
directly. Default assumes the Pi's hardware UART — GPIO14/GPIO15 (physical
header pins 8/10) — wired to the SIM800L's RXD/TXD, with the module powered
from its own ~4V supply (not the Pi's 3V3/5V rail). If your wiring differs,
just change `SIM800_SERIAL_PORT` in `.env`.

**Disabling SMS ingestion**: set `SMS_INGESTION_ENABLED=false` in `.env`, or
just don't install `pyserial` — either way MQTT ingestion is unaffected.

## Centralized logging

All three services mirror their logs into `service_logs` in the `air_quality`
database (`service` column identifies which one), in addition to console
output (captured by systemd's journal). Query it directly:
```sql
SELECT created_at, service, level, message
FROM service_logs
WHERE created_at > NOW() - INTERVAL '1 hour'
ORDER BY created_at DESC;
```
Also queryable via `GET /api/system/logs` on the monitoring API (filter by
`service`, `level`, `hours`). Disable with `DB_LOG_ENABLED=false` in `.env`.

## Known caveats / things to verify with real hardware

- **SIM800L AT response parsing** (`sim800l.py`) was written against the
  documented SIMCom AT command set. Some clone modules/firmware format
  `+CMGL`/`+CMGR` responses slightly differently — if messages aren't being
  read correctly, first confirm the module responds to plain `AT` over a
  terminal (`screen /dev/serial0 9600`), then adjust `_CMGL_HEADER_RE` in
  `sim800l.py` if needed.
- **SEISMSG1 format** is a new design (you didn't have an existing SMS format)
  — if your sensor firmware sends something different, update
  `parse_seismic_sms()` in `seismic_mqtt.py` to match, or have the firmware
  emit this format.
- `config.py` from the original upload isn't imported by any of the three
  services (each reads its own env vars directly) — it's not part of this
  deployable set; leave it out or delete it.
