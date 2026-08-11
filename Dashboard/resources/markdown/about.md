# EMS Gateway

EMS Gateway is an environmental monitoring stack for a Raspberry Pi or Debian-based Linux server. It ingests air quality and seismic telemetry, stores readings in PostgreSQL/TimescaleDB, exposes a FastAPI REST API, and includes a Laravel dashboard for operations, station management, logs, and maintenance.

## What It Runs

```text
Air quality station(s)  ->  HJ212 TCP + Modbus TCP  ->  air_quality_ingest.py
Seismic station(s)      ->  MQTT + optional SMS     ->  seismic_mqtt.py

air_quality_ingest.py   ->  IOT_aq_sensor_data
seismic_mqtt.py         ->  IOT_seismic_sensor_data + IOT_sms_telemetry
api_server.py           ->  REST API over the stored data
Laravel Dashboard       ->  Browser UI for data, config, keys, logs, and maintenance

Shared services:
PostgreSQL + TimescaleDB, Mosquitto MQTT, systemd, optional nginx/php-fpm
```

## Repository Layout

```text
.
|-- scripts/
|   |-- air_quality_ingest.py     # HJ212 TCP listener + Modbus lead polling
|   |-- seismic_mqtt.py           # MQTT telemetry + optional SIM800L SMS ingestion
|   |-- api_server.py             # FastAPI REST API
|   |-- import_stations.py        # Bulk import/update air quality station registry
|   |-- stations.json             # Example/default station registry
|   |-- sim800l.py                # SIM800L helper
|   `-- .env.EMS.scripts          # Sample Python service environment file
|-- Dashboard/                    # Laravel 12 dashboard
|-- template/                     # systemd service templates
|-- install.sh                    # Interactive gateway setup wrapper
|-- deploy.sh                     # Installs packages, DBs, Python deps, MQTT, dashboard
|-- install_services.sh           # Installs/enables EMS systemd units
|-- update.sh                     # Pulls code, reprovisions, restarts services
|-- uninstall_services.sh         # Removes EMS systemd units only
|-- check_requirements.sh         # Checks Python/PostgreSQL/MQTT/time sync
`-- requirements.txt              # Python dependencies
```

## Requirements

- Raspberry Pi OS Lite / Bookworm 64-bit, Ubuntu, or another Debian-based system with `apt` and `systemd`
- Python 3
- PostgreSQL 16 and TimescaleDB
- Mosquitto MQTT broker
- PHP 8.2+, Composer, Node.js, and npm for the Laravel dashboard
- Network access from sensors/stations to the gateway

TimescaleDB official packages are available for `amd64` and `arm64`. A 64-bit Raspberry Pi OS image is strongly recommended.

## Quick Start

On the target Linux gateway:

```bash
git clone https://github.com/uisi-sysnet/EMS.git
cd EMS
chmod +x *.sh
sudo ./install.sh
sudo ./install_services.sh
./check_requirements.sh
```

`install.sh` prepares the gateway and calls into the deployment workflow. It can configure Raspberry Pi network mode, create or use `scripts/.env`, install dependencies, and prepare the dashboard.

If this is a fresh checkout and `scripts/.env` does not exist, copy the sample first:

```bash
cp scripts/.env.EMS.scripts scripts/.env
nano scripts/.env
sudo ./deploy.sh
```

Set real database, MQTT, and API key values before starting services.

## Configuration

There are two environment files:

- `scripts/.env` is used by `air_quality_ingest.py`, `seismic_mqtt.py`, `api_server.py`, and the dashboard environment editor.
- `Dashboard/.env` is used by Laravel.

Important Python service settings:

| Setting | Purpose |
| --- | --- |
| `SYSTEM_DB_HOST`, `SYSTEM_DB_PORT`, `SYSTEM_DB_USER`, `SYSTEM_DB_PASSWORD` | Shared PostgreSQL connection |
| `AQ_DB_NAME` | Air quality database, default `IOT_aq_sensor_data` |
| `SEISMIC_DB_NAME` | Seismic database, default `IOT_seismic_sensor_data` |
| `SMS_DB_NAME` | Raw SMS database, default `IOT_sms_telemetry` |
| `API_DB_NAME` | API keys and allowlist database, default `IOT_api` |
| `LOG_DB_NAME` | Centralized service logs database, default `IOT_service_logs` |
| `AQ_SERVER_HOST`, `AQ_SERVER_PORT` | HJ212 TCP listener bind address and port |
| `MQTT_BROKER_HOST`, `MQTT_BROKER_PORT`, `MQTT_TOPIC` | Seismic MQTT source |
| `SMS_INGESTION_ENABLED`, `SIM800_SERIAL_PORT`, `SIM800_BAUDRATE` | Optional SIM800L SMS ingestion |
| `API_BIND_HOST`, `API_PORT`, `API_KEYS` | FastAPI bind address, port, and initial tokens |

`API_KEYS` uses this format:

```text
token:owner_label,another_token:another_owner
```

The API server migrates environment API keys into its database-backed key table. The Laravel dashboard can then manage API keys and allowed client networks.

## Services

`install_services.sh` installs these systemd units:

- `ems-air-quality.service`
- `ems-seismic.service`
- `ems-api.service`
- `ems.target`

Useful commands:

```bash
sudo systemctl status ems.target
sudo systemctl restart ems.target
sudo systemctl stop ems.target

sudo systemctl status ems-air-quality.service
sudo systemctl status ems-seismic.service
sudo systemctl status ems-api.service

sudo journalctl -u ems-air-quality.service -f
sudo journalctl -u ems-seismic.service -f
sudo journalctl -u ems-api.service -f
```

Remove only the EMS service registration:

```bash
sudo ./uninstall_services.sh
```

This does not delete the repository, `.env` files, PostgreSQL data, or Mosquitto configuration.

## Dashboard

For local dashboard development:

```bash
cd Dashboard
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

For production deployment on the gateway, use `sudo ./deploy.sh` or `sudo ./update.sh`. The deploy/update scripts install dashboard dependencies, run migrations, build Vite assets, cache Laravel config/routes/views, and fix permissions for `storage/`, `bootstrap/cache/`, and `scripts/.env`.

Dashboard routes include:

- `/login` and `/register`
- `/` for the main dashboard
- `/dashboard/data` and `/dashboard/report`
- `/stations` and `/seismic-stations`
- `/env-editor`, `/env/mqtt-editor`, and `/api-editor`
- `/logs`, `/api-logs`, and `/recent-logs`
- `/network` and `/maintenance`
- `/maintenance/services` and `/maintenance/terminal`

## REST API

The API is served by `scripts/api_server.py`.

Default base URL:

```text
http://127.0.0.1:8000
```

If `API_PORT` is not set by deployment, `api_server.py` defaults to `8443`; the sample environment uses `8000`.

Authentication:

```http
X-API-Key: your-token
```

Health check, no API key required:

```text
GET /api/system/status
```

Main API endpoints:

| Endpoint | Description |
| --- | --- |
| `GET /api/air-quality/stations/latest` | Latest reading for every air quality station |
| `GET /api/air-quality/stations/{station_mn}/latest` | Latest reading for one air quality station |
| `GET /api/air-quality/analytics/1d` | 24-hour average readings |
| `GET /api/air-quality/analytics/7d` | 7-day daily averages |
| `GET /api/air-quality/analytics/30d` | 30-day daily averages |
| `GET /api/air-quality/stations` | Registered air quality station list |
| `GET /api/seismic/stations/latest` | Latest seismic reading for every station |
| `GET /api/seismic/stations/{station_id}/latest` | Latest seismic reading for one station |
| `GET /api/seismic/graph/latest` | Latest graph payload for every seismic station |
| `GET /api/seismic/stations/{station_id}/graph/latest` | Latest graph payload for one seismic station |
| `GET /api/seismic/stations/{station_id}/history?hours=1` | Raw seismic history, 1 to 24 hours |
| `GET /api/seismic/events?min_peis=1&hours=24` | PEIS-filtered seismic events |
| `GET /api/system/logs` | Centralized service logs |

Interactive API docs are available from FastAPI at:

```text
/docs
/redoc
```

Example:

```bash
curl -H "X-API-Key: your-token" \
  http://127.0.0.1:8000/api/air-quality/stations/latest
```

## Station Registry

Air quality stations are stored in the `stations` table. `air_quality_ingest.py` can auto-import `scripts/stations.json` on first run if the table is empty.

To import or update stations manually:

```bash
cd scripts
python3 import_stations.py --dry-run
python3 import_stations.py
python3 import_stations.py /path/to/stations.json
```

The ingestion service refreshes station metadata periodically using `AQ_STATIONS_REFRESH_INTERVAL_SEC`, or immediately after a service restart.

## Updating

After an initial deployment:

```bash
sudo ./update.sh
```

By default, the update script pulls branch `version5`. Override it when needed:

```bash
GIT_BRANCH=main sudo -E ./update.sh
```

The update flow stops services, pulls code, reinstalls Python dependencies, reprovisions Laravel, rebuilds assets, fixes permissions, then restarts nginx and `ems.target`.

## Troubleshooting

Run the built-in checks first:

```bash
./check_requirements.sh
```

Check service status and logs:

```bash
sudo systemctl status ems.target
sudo journalctl -u ems-api.service -n 100 --no-pager
sudo journalctl -u ems-air-quality.service -n 100 --no-pager
sudo journalctl -u ems-seismic.service -n 100 --no-pager
```

Common issues:

- `scripts/.env` missing: copy `scripts/.env.EMS.scripts` to `scripts/.env` and edit it.
- API returns `401`: send a valid `X-API-Key` header or add a key through the dashboard.
- API returns `403`: check the allowed client networks in the dashboard API editor.
- PostgreSQL connection fails: verify `SYSTEM_DB_*` values and run `pg_isready`.
- MQTT messages are not arriving: verify broker host, port, topic, username, password, and station publish topic.
- SMS ingestion is not working: confirm `SMS_INGESTION_ENABLED`, serial port, baud rate, modem wiring, and SIM800L power.
- Dashboard cannot edit Python settings: make sure the web server user can read/write `scripts/.env`; `deploy.sh` and `update.sh` normally repair this.

## Development Notes

Python dependencies:

```bash
pip3 install -r requirements.txt
```

Run services manually during development:

```bash
python3 scripts/air_quality_ingest.py
python3 scripts/seismic_mqtt.py
python3 scripts/api_server.py
```

Dashboard tests:

```bash
cd Dashboard
php artisan test
```

Build dashboard assets:

```bash
cd Dashboard
npm run build
```
