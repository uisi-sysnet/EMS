# EMS Gateway

EMS Gateway is an environmental monitoring stack for a Raspberry Pi or Debian-based Linux server. It ingests air quality and seismic telemetry, tracks water level stations, stores readings in PostgreSQL/TimescaleDB, exposes a FastAPI REST API, and includes a Laravel dashboard for operations, live station mapping, CCTV, station management, logs, and maintenance.

**Current version: 10.3.2**

| Branch     | Contents                                                        |
| ---------- | --------------------------------------------------------------- |
| `main`     | Latest release (what `update.sh` installs by default)           |
| `version10` | Version 10.x line; fixes for version 10 gateways land here first |
| `version9` | Previous version line (9.x)                                     |

Releases are tagged (`v10.0.0`, `v10.1.0`, `v10.2.0`, `v10.3.0`, `v10.3.1`, `v10.3.2`, ...).

## What It Runs

```text
Air quality station(s)  ->  HJ212 TCP + Modbus TCP  ->  air_quality_ingest.py
Seismic station(s)      ->  MQTT + optional SMS     ->  seismic_mqtt.py
Water level sensor(s)   ->  SMS -> GSM gateway Nano ->  water_level_gsm.py
CCTV cameras            ->  ONVIF + RTSP            ->  MediaMTX (WebRTC to the browser)

air_quality_ingest.py   ->  IOT_aq_sensor_data
water_level_gsm.py      ->  IOT_water_level
seismic_mqtt.py         ->  IOT_seismic_sensor_data + IOT_sms_telemetry
api_server.py           ->  REST API over the stored data (IOT_api)
All services            ->  IOT_service_logs
Laravel Dashboard       ->  Browser UI: dashboard, CityWatch map, CCTV, stations,
                            water level stations, config, keys, logs, maintenance

Shared services:
PostgreSQL + TimescaleDB, Mosquitto MQTT, MediaMTX, nginx + PHP-FPM, systemd
```

## What's New in Version 10.3

- **Uplink Sentinel reporting:** the EMS can send every sensor's status (air quality per measurement, seismic, water level, cameras) to Uplink Sentinel on a schedule and when a status changes, with retries that never affect the EMS. Off by default; `php artisan sentinel:test` checks the link. See [Uplink Sentinel Reporting](#uplink-sentinel-reporting).
- **10.3.2 — Telegram fix:** digests and alerts stopped being sent when the scheduler couldn't write the dashboard's cache (it now carries on without it). A digest missed at its set time is now sent up to 2 hours later instead of being skipped for the day, and Telegram send failures appear on the Logs page and in the bell.
- **10.3.1 — Settings › Sentinel page:** enter the Sentinel IP address, port and key, turn reporting on or off, and use **Test Connection** to send one report and see Sentinel's reply. The key is stored encrypted. The page shows the last report's time and result.

## What's New in Version 10.2

### Logs and Audit Log

- **One Logs page with two tabs.** **Logs** shows messages from the services and device events, filterable by category (**System**, **Device**, **Security**), level, service and message text. **Audit Log** records every change made in the dashboard: who, when, from which IP address, what, and whether it worked. Failed sign-ins are recorded. Passwords and keys are never stored, only the names of the fields changed. Both tabs export to CSV. See [Logs and Audit Log](#logs-and-audit-log).
- **Device events:** stations, cameras and lead sensors going offline and coming back online are logged every minute.
- **Notifications only when needed:** the bell, the menu badge and the "new" count only include service errors and device problems such as a station going offline. API requests and routine messages are still listed but never count as unread.
- **Logs no longer grow forever:** old logs are deleted every hour (30 days for Logs and API Logs, 365 days for the Audit Log, adjustable). Every API request used to be logged twice, and routine messages were logged on every reading; both now stay out of the database. New indexes keep the log pages and the menu badge fast.

### CCTV

- **Live view works on installed gateways:** the installer now sets up the stream server (MediaMTX) as `ems-mediamtx.service`, including the right build for a Raspberry Pi, and routes the browser's stream requests to it. Run `sudo ./install_mediamtx.sh` on a gateway installed before 10.2.
- **Cameras register even without ONVIF:** a camera whose ONVIF (PTZ) handshake fails still gets its live view; the inventory card shows a PTZ warning instead.
- **Live camera status:** a camera is online when it accepts a connection on its stream or ONVIF port, instead of a status saved at its last refresh.
- **Self-repair:** the stream server forgets cameras when it restarts; the dashboard re-registers them within a minute.
- When the live view can't start, it now says why (stream server down, route missing, no stream yet, or the camera's own error).

### Fixes

- The dashboard's scheduled tasks (Telegram alerts and daily digest, device events, log cleanup) never ran on installed gateways. `deploy.sh` and `update.sh` now install the scheduler cron job.
- `deploy.sh` no longer stops while writing the web server config (the web terminal section broke it).

## What's New in Version 10.1

Water level stations now appear everywhere the other sensors do:

- **Dashboard:** a **Water Level Station Status** card (online / idle / offline) next to the other status cards, a **Water Level – Total per Station** chart, and a **Water Level Stations** table with the latest level reading. The system status banner counts water level stations too. Everything refreshes every 20 seconds.
- **CityWatch:** water level stations get their own pin and a **Water Level** filter. A station's card shows the latest level and when it was recorded, the linked camera, and network status from a ping of the station's lead IP.
- **Readings table:** water level readings are stored in `IOT_water_level.sensor_data` (`station_mn`, `water_level` in meters, `battery_voltage`, `temperature`, `recorded_at`). A station is online when it has a reading in the last 2 minutes, idle up to 3 minutes, otherwise offline, the same rule as the other sensors.
- The station delete confirmation now reports how many readings a water level station has.

- **GSM reporting:** water level sensors can report by SMS, with no internet needed. Each sensor (Arduino Nano + SIM800L + ultrasonic sensor) texts its readings to a GSM gateway (another Nano + SIM800L) plugged into the server by USB. The new `water_level_gsm.py` service saves them. Firmware for both devices and a setup guide are in [`firmware/`](firmware/README.md).
- **Reporting interval from the dashboard:** each water level station has a SIM number and a reporting interval. Changing the interval texts it to the sensor, and the station list shows when the sensor has applied it (Pending → Sent → Applied).
- **Status follows the interval:** a water level station is online when it has reported within its interval plus 2 minutes, and idle until it misses a second interval.
- Stations need either an IP address or a SIM number; GSM stations don't need an IP. The station list's Data Status column now shows each station's reading count.

## What's New in Version 10

### CityWatch — live station map

A new **CityWatch** tab (`/citywatch`) shows every station on a satellite map:

- One pin per station that has latitude/longitude, colored by status: **green** = everything online, **amber** = partly working, **red** = nothing reporting.
- **Hover** a pin for a status card, **click** to pin it open. Each card shows:
  - **Sensors** — online if the station sent data in the last 2 minutes, idle up to 3 minutes, otherwise offline (the same rules as the dashboard).
  - **Camera** — the CCTV camera whose location matches the station name.
  - **Network** — whether the station's lead IP answers a ping. Seismic stations report over MQTT/SMS, so their network status follows their data.
- Summary panels (stations, sensors, cameras, network), a **Needs attention** list that flies to a station, filters (All / Air Quality / Seismic / CCTV), full screen, and a 20-second live refresh.
- **Camera live view with PTZ:** in a station's pinned card, **View camera** opens a pop-up with the live stream. PTZ cameras get a pan/tilt/zoom pad (hold to move, release to stop), a speed slider, and keyboard control (arrow keys, `+` / `−`). Stations with several cameras get tabs. Live view and PTZ are available to admins and super admins.

Satellite imagery needs internet access. Without it, the pins and status still work on a plain background.

### Water Level stations

A new **Stations › Water Level** inventory (`/inventory/water-level-stations`) manages water level stations, with installation height and automatic elevation lookup from latitude/longitude. They are stored in their own database, `IOT_water_level`. Since 10.1 they also appear on the dashboard and on CityWatch.

### One-step installation

`sudo ./install.sh` now does the whole setup. It asks for (or generates) every credential, installs all required packages, applies the credentials, installs the services, and runs the health check. See [Quick Start](#quick-start).

### Security

- Real `.env` files are no longer stored in git. Templates hold placeholders only.
- No hardcoded database passwords: the `postgres` password comes from the installer, the existing install, or is generated.
- Deployed dashboards run with `APP_ENV=production` and `APP_DEBUG=false`, so error pages no longer expose configuration.
- `update.sh` preserves each gateway's `.env` files across updates.

### Fixes

- New gateways no longer fail on the log pages (`column "seen_at" does not exist`) or the API settings page (`relation "allowed_ips" does not exist`).
- `deploy.sh` creates all EMS databases (including SMS, service logs, and water level) with TimescaleDB before the services and migrations need them.
- The dashboard finds `scripts/.env` wherever the project is installed (`EMS_SCRIPTS_ENV`).
- The version shown in the dashboard now comes from the code, so it always matches the release.

## Version History

| Version | Changes |
| ------- | ------- |
| 10.3.2  | Telegram digests/alerts no longer stop when the cache isn't writable; missed digests catch up within 2 hours; Telegram failures shown on the Logs page. |
| 10.3.1  | Settings › Sentinel page: Sentinel IP address, port and key, on/off switch, Test Connection button, last report status. |
| 10.3.0  | Uplink Sentinel status reporting (scheduled and on change, with retry), `sentinel:test` connection test. |
| 10.2.0  | Logs page with Logs and Audit Log tabs; device online/offline events; notifications only for errors and device problems; automatic log cleanup; CCTV live view fixes (MediaMTX installer, ONVIF-independent streams, live camera status). |
| 10.1.0  | Water level stations on the dashboard and CityWatch; GSM (SMS) reporting through an Arduino Nano + SIM800L gateway, with the reporting interval set from the dashboard. |
| 10.0.0  | CityWatch map with camera live view and PTZ; water level stations; one-step installer; security hardening; setup fixes (see above). |
| 9.0.5   | Severity labels for site status in the air quality station table; calibration API plan types. |
| 9.0.4   | Fixed inventories table layout. |
| 9.0.3   | Fixed and enhanced image reports. |
| 9.0.2   | Fixed dashboard auto refresh bugs. |

## Repository Layout

```text
.
|-- scripts/
|   |-- air_quality_ingest.py     # HJ212 TCP listener + Modbus lead polling
|   |-- seismic_mqtt.py           # MQTT telemetry + optional SIM800L SMS ingestion
|   |-- api_server.py             # FastAPI REST API
|   |-- db_logging.py             # Shared service logging to IOT_service_logs
|   |-- import_stations.py        # Bulk import/update air quality station registry
|   |-- stations.json             # Example/default station registry
|   |-- sim800l.py                # SIM800L helper
|   `-- .env.EMS.scripts          # Template for scripts/.env (placeholders only)
|   |-- water_level_gsm.py        # Water level SMS readings via the GSM gateway Nano
|-- firmware/                     # Arduino sketches: GSM gateway + water level sensor
|-- Dashboard/                    # Laravel 12 dashboard
|-- mediamtx/                     # MediaMTX media server (CCTV streaming)
|-- template/                     # systemd service templates
|-- install.sh                    # One-step gateway setup (network, credentials, packages, services)
|-- deploy.sh                     # Installs packages, databases, Python deps, MQTT, dashboard
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

`install.sh` installs all of these. TimescaleDB official packages are available for `amd64` and `arm64`, so a 64-bit Raspberry Pi OS image is strongly recommended.

## Quick Start

On the target Linux gateway:

```bash
git clone https://github.com/uisi-sysnet/EMS.git
cd EMS
chmod +x *.sh
sudo ./install.sh
```

`install.sh` does the whole setup in one run:

1. Configures the Raspberry Pi network (WiFi access point, eth0 DHCP/static). This step is skipped on other systems.
2. Creates `scripts/.env` from `scripts/.env.EMS.scripts` and asks for every credential: database host, port, user and password, the `postgres` superuser password used by the Dashboard, MQTT host, port, user and password, and the database names. Leave a password blank to generate a strong random one. An API key is generated automatically.
3. Runs `deploy.sh`, which installs all required packages (PostgreSQL + TimescaleDB, Mosquitto, Python dependencies, nginx, PHP, Composer, Node.js), creates the databases, and applies the credentials.
4. Installs the systemd services and runs `check_requirements.sh`.

Generated credentials are printed once at the end, so record them. Re-running `sudo ./install.sh` offers the saved values as defaults, which makes it the way to change credentials later.

The real `.env` files are not stored in git. Never commit `scripts/.env` or `Dashboard/.env`.

## Upgrading a Version 9 Gateway

Version 10 stops tracking the `.env` files in git. The **version 9** `update.sh` does not know this, and pulling version 10 with it would delete the gateway's `scripts/.env` and `Dashboard/.env`. Upgrade once with these steps; after that, the new `update.sh` handles it automatically.

```bash
cd /path/to/EMS
sudo cp scripts/.env /root/ems-scripts.env.backup
sudo cp Dashboard/.env /root/ems-dashboard.env.backup
git fetch origin
git checkout main
git pull origin main
sudo cp /root/ems-scripts.env.backup scripts/.env
sudo cp /root/ems-dashboard.env.backup Dashboard/.env
sudo ./update.sh
```

Then:

- Add `WATER_LEVEL_DB_NAME=IOT_water_level` to `scripts/.env` if it is missing, and run `sudo ./deploy.sh` once so the new database is created.
- If `Dashboard/.env` contains an old `APP_VERSION=9.x` line, remove it so the dashboard shows the correct version.
- Change any passwords, API keys, and the `APP_KEY` that were stored in git before version 10 (see [Security Notes](#security-notes)).

## Configuration

There are two environment files:

- `scripts/.env` is used by `air_quality_ingest.py`, `seismic_mqtt.py`, `api_server.py`, and the dashboard (database connections and the environment editor).
- `Dashboard/.env` is used by Laravel. `deploy.sh` rewrites it on every run, keeping the existing `APP_KEY` and `postgres` password.

Important Python service settings:

| Setting                                                                    | Purpose                                                       |
| -------------------------------------------------------------------------- | ------------------------------------------------------------- |
| `SYSTEM_DB_HOST`, `SYSTEM_DB_PORT`, `SYSTEM_DB_USER`, `SYSTEM_DB_PASSWORD` | Shared PostgreSQL connection                                  |
| `AQ_DB_NAME`                                                               | Air quality database, default `IOT_aq_sensor_data`            |
| `SEISMIC_DB_NAME`                                                          | Seismic database, default `IOT_seismic_sensor_data`           |
| `SMS_DB_NAME`                                                              | Raw SMS database, default `IOT_sms_telemetry`                 |
| `API_DB_NAME`                                                              | API keys and allowlist database, default `IOT_api`            |
| `LOG_DB_NAME`                                                              | Centralized service logs database, default `IOT_service_logs` |
| `WATER_LEVEL_DB_NAME`                                                      | Water level stations database, default `IOT_water_level`      |
| `WATER_GSM_ENABLED`, `WATER_GSM_SERIAL_PORT`, `WATER_GSM_BAUDRATE`         | GSM gateway Nano for water level SMS (off by default)         |
| `WATER_GSM_RESEND_MINUTES`                                                 | Resend an unconfirmed interval setting after this long        |
| `AQ_SERVER_HOST`, `AQ_SERVER_PORT`                                         | HJ212 TCP listener bind address and port                      |
| `MQTT_BROKER_HOST`, `MQTT_BROKER_PORT`, `MQTT_TOPIC`                       | Seismic MQTT source                                           |
| `SMS_INGESTION_ENABLED`, `SIM800_SERIAL_PORT`, `SIM800_BAUDRATE`           | Optional SIM800L SMS ingestion                                |
| `API_BIND_HOST`, `API_PORT`, `API_KEYS`                                    | FastAPI bind address, port, and initial tokens                |

Dashboard settings in `Dashboard/.env`:

| Setting           | Purpose                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------- |
| `DB_*`            | Dashboard database (`IOT_api`, as the `postgres` user)                                        |
| `EMS_SCRIPTS_ENV` | Path to `scripts/.env`; written by `deploy.sh`, defaults to `/home/system/EMS/scripts/.env`   |
| `APP_VERSION`     | Optional override of the displayed version. Leave unset so the release version is shown.      |

`API_KEYS` uses this format:

```text
token:owner_label,another_token:another_owner
```

The API server migrates environment API keys into its database-backed key table on first start. The Laravel dashboard then manages API keys and allowed client networks.

## Services

`install_services.sh` installs these systemd units:

- `ems-air-quality.service`
- `ems-seismic.service`
- `ems-api.service`
- `ems-water-level-gsm.service` (idles until `WATER_GSM_ENABLED=true`)
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
sudo journalctl -u ems-water-level-gsm.service -f
```

Existing gateways get the new water level GSM service by running `sudo ./install_services.sh` once after updating.

Remove only the EMS service registration:

```bash
sudo ./uninstall_services.sh
```

This does not delete the repository, `.env` files, PostgreSQL data, or Mosquitto configuration.

## Dashboard

For production deployment on the gateway, use `sudo ./install.sh` (first time) and `sudo ./update.sh` (afterwards). They install dashboard dependencies, run migrations, build Vite assets, cache Laravel config/routes/views, and fix permissions for `storage/`, `bootstrap/cache/`, and `scripts/.env`.

Main pages:

| Page                                | Who          | Purpose                                                        |
| ----------------------------------- | ------------ | -------------------------------------------------------------- |
| `/`                                 | All users    | Dashboard: system health, station status, charts, reports      |
| `/citywatch`                        | All users    | CityWatch map; camera live view and PTZ for admins             |
| `/inventory/stations`               | Admins       | Air quality stations                                           |
| `/inventory/water-level-stations`   | Admins       | Water level stations                                           |
| `/seismic-stations`                 | Admins       | Seismic stations                                               |
| `/inventory/cameras`                | Admins       | CCTV inventory                                                 |
| `/maintenance/cameras/live`         | Admins       | CCTV live view with PTZ                                        |
| `/env-editor`, `/env/mqtt-editor`   | Admins       | Python service and MQTT settings                               |
| `/api-editor`                       | Admins       | API keys and allowed client networks                           |
| `/logs`, `/api-logs`                | Admins       | Service and API request logs                                   |
| `/network`, `/maintenance`          | Admins       | Network configuration and diagnostics (gateway only)           |
| `/maintenance/services`             | Admins       | Service control and web terminal                               |
| `/settings/telegram`                | Admins       | Telegram alerts and daily digest                               |
| `/user`                             | Admins       | User management                                                |

### Local development

```bash
cd Dashboard
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run dev
php artisan serve
```

Set `EMS_SCRIPTS_ENV` in `Dashboard/.env` to your local `scripts/.env`. The local PostgreSQL needs the role from `SYSTEM_DB_USER` and the EMS databases before `php artisan migrate` will succeed; on a gateway, `deploy.sh` and the Python services create them. The network pages use `nmcli` and only work on the Linux gateway.

## Logs and Audit Log

**Maintenance › Logs & Audit** (`/logs`) has two tabs:

- **Logs** — messages from the EMS services and device status changes, with a **category** filter:
  - **System**: services starting, database and broker connections, errors.
  - **Device**: station readings rejected, Modbus lead polling, clock sync, seismic telemetry and SMS, the GSM gateway, and stations, cameras and lead sensors **going offline or coming back online** (checked every minute by `php artisan logs:track-device-status`).
  - **Security**: API requests blocked by the allowed-networks list.
- **Audit Log** — every change made in the dashboard: who, when, from which IP, what (for example "Updated water level station WLS001"), and whether it succeeded. Includes sign-ins, failed sign-in attempts, user and station changes, settings, network changes, service start/stop and web terminal access. Filter by user, category, result and date.

Both tabs export to CSV. The Audit Log stores submitted values only for non-sensitive fields; passwords, keys and other secrets are recorded by field name only. Descriptions and categories are set in `Dashboard/config/audit.php`; an action that isn't listed there is still recorded, under "Other". API request logs stay on their own page (`/api-logs`).

The scheduled tasks (device status tracking, log cleanup, Telegram alerts and daily digest) run from a cron job that `deploy.sh` and `update.sh` install at `/etc/cron.d/ems-dashboard-scheduler`.

Old logs are deleted every hour (`php artisan logs:prune`). By default the Logs tab and API Logs keep 30 days and the Audit Log keeps 365 days. Change this in `Dashboard/.env` with `LOG_RETENTION_DAYS`, `API_LOG_RETENTION_DAYS` and `AUDIT_LOG_RETENTION_DAYS` (0 keeps that log forever). Run `php artisan logs:prune --dry-run` to see how many rows would be deleted.

## Uplink Sentinel Reporting

The dashboard can send every sensor's status to **Uplink Sentinel**, our project-monitoring system. Sentinel only receives; it never calls the EMS. Reporting is off by default.

**What is sent:** one unit per sensor, every time:

- Air quality: one unit per measurement a station reports, ID `<station MN>-<code>` (e.g. `STN01-PM25`, `STN01-CO`, `STN01-TEMP`), with the latest value.
- Seismic stations: `SEIS-<station id>`. Water level stations: `WL-<station MN>`, with the latest level. Cameras: `CAM-<id>`.
- Units are grouped by station (`<MN> · <name>`); a camera goes under the station named in its location.

**Status words:** `online` (data within 2 minutes), `stale` (2-3 minutes), `offline` (the station has stopped sending), `no_data` (the station reports but not this measurement). Water level stations use their own reporting interval. Cameras are `online`, `unreachable`, or `fault` (reachable, but the live view isn't set up).

**When:** every *Send every (minutes)* (default 30), and within about 2 minutes of any status change. On a timeout, connection error, 429 or 5xx it retries after 1, 2, 5, then every 10 minutes, always with a fresh snapshot. On 401/403 it waits 30 minutes. A 400/422 is logged and not retried.

**Enable it** on **Settings › Sentinel** (admins):

1. Enter the **Sentinel IP address** (or hostname) and **port** (default 8090). Reports go to `http://<IP>:<port>/api/ems/status`; tick **Use HTTPS** if Sentinel is served over HTTPS.
2. Paste the **key from Sentinel**. It's stored encrypted and never shown again; leave the field blank later to keep it.
3. Click **Save Settings**, then **Test Connection**. It sends one report and shows Sentinel's reply on the page, even while reporting is off.
4. Tick **Enable reporting** and save. The scheduler cron job (`/etc/cron.d/ems-dashboard-scheduler`) runs `php artisan sentinel:push` every minute. The page shows the time and result of the last report.

From a terminal: `cd Dashboard && sudo -u www-data php artisan sentinel:test` does the same test, and `--dry-run > sample.json` saves the JSON for a curl test:

```bash
curl -X POST http://SENTINEL_HOST:8090/api/ems/status -H "Authorization: Bearer <key>" -H "Content-Type: application/json" --data-binary @sample.json
```

Until the page has been saved with an address and key, the `SENTINEL_EMS_URL`, `SENTINEL_EMS_TOKEN`, `SENTINEL_EMS_ENABLED`, `SENTINEL_EMS_INTERVAL_MINUTES` and `SENTINEL_EMS_SYSTEM` values in `scripts/.env` are used instead.

Every send is logged on the **Logs** page (service `dashboard`, logger `sentinel`): HTTP code, matched / added / changes counts and any warnings. The token is never logged. A wrong token, a disabled link or a rejected report is logged as an error, so it shows in the notification bell.

## REST API

The API is served by `scripts/api_server.py`. On a deployed gateway, nginx serves it at `/api/*` on the same host as the dashboard.

Default local base URL:

```text
http://127.0.0.1:8000
```

If `API_PORT` is not set, `api_server.py` defaults to `8443`; the sample environment uses `8000`.

Authentication:

```http
X-API-Key: your-token
```

Health check, no API key required:

```text
GET /api/system/status
```

Main API endpoints:

| Endpoint                                                 | Description                                    |
| -------------------------------------------------------- | ---------------------------------------------- |
| `GET /api/air-quality/stations/latest`                   | Latest reading for every air quality station   |
| `GET /api/air-quality/stations/{station_mn}/latest`      | Latest reading for one air quality station     |
| `GET /api/air-quality/analytics/1d`                      | 24-hour average readings                       |
| `GET /api/air-quality/analytics/7d`                      | 7-day daily averages                           |
| `GET /api/air-quality/analytics/30d`                     | 30-day daily averages                          |
| `GET /api/air-quality/stations`                          | Registered air quality station list            |
| `GET /api/seismic/stations/latest`                       | Latest seismic reading for every station       |
| `GET /api/seismic/stations/{station_id}/latest`          | Latest seismic reading for one station         |
| `GET /api/seismic/graph/latest`                          | Latest graph payload for every seismic station |
| `GET /api/seismic/stations/{station_id}/graph/latest`    | Latest graph payload for one seismic station   |
| `GET /api/seismic/stations/{station_id}/history?hours=1` | Raw seismic history, 1 to 24 hours             |
| `GET /api/seismic/events?min_peis=1&hours=24`            | PEIS-filtered seismic events                   |
| `GET /api/system/logs`                                   | Centralized service logs                       |

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

To show a station on CityWatch, give it a latitude and longitude. To link a camera to a station, set the camera's location in CCTV inventory to the station's name.

## Updating

After the initial installation:

```bash
sudo ./update.sh
```

`update.sh` pulls the `main` branch by default. To follow a specific version line instead:

```bash
GIT_BRANCH=version10 sudo -E ./update.sh
```

The update flow stops services, backs up the gateway's `.env` files, pulls code, restores the `.env` files, reinstalls Python dependencies, reprovisions Laravel, rebuilds assets, fixes permissions, then restarts nginx and `ems.target`.

Upgrading from version 9? Follow [Upgrading a Version 9 Gateway](#upgrading-a-version-9-gateway) once first.

## Security Notes

- Keep `scripts/.env` and `Dashboard/.env` out of git; they hold database passwords, MQTT credentials, API keys, and the Laravel `APP_KEY`.
- Before version 10, these files and a default `postgres` password were committed to the repository. Treat those values as exposed and change them: the PostgreSQL passwords, the MQTT password, the API tokens, `TERMINAL_SHARED_SECRET`, and `APP_KEY` (`php artisan key:generate`; this logs everyone out).
- Known issue: the login page still accepts built-in default accounts defined in `LoginController`. Plan to replace them with database users created during installation.

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

- `scripts/.env` missing: run `sudo ./install.sh`, which creates it from the template and asks for the credentials.
- `role "iot_user" does not exist` or a database does not exist: run `sudo ./deploy.sh`, which creates the role and all EMS databases.
- API returns `401`: send a valid `X-API-Key` header or add a key through the dashboard.
- API returns `403`: check the allowed client networks in the dashboard API editor.
- PostgreSQL connection fails: verify `SYSTEM_DB_*` values and run `pg_isready`.
- MQTT messages are not arriving: verify broker host, port, topic, username, password, and station publish topic.
- SMS ingestion is not working: confirm `SMS_INGESTION_ENABLED`, serial port, baud rate, modem wiring, and SIM800L power.
- Dashboard cannot edit Python settings: make sure the web server user can read/write `scripts/.env`; `deploy.sh` and `update.sh` normally repair this.
- CityWatch shows no stations: add latitude and longitude to the stations.
- Telegram digests/alerts, device events or Sentinel reports stop: the scheduler must run every minute **as `www-data`**. Check `/etc/cron.d/ems-dashboard-scheduler` exists and contains `* * * * * www-data cd <EMS>/Dashboard && php artisan schedule:run`, and remove any older `schedule:run` line from another user's crontab (`crontab -l`, `sudo crontab -l`), since running it as another user fails on the cache folder. Problems sending to Telegram are listed on the Logs page (logger `telegram`). Test with `sudo -u www-data php artisan telegram:daily-digest --force`.
- Water level stations are always offline: check `sudo journalctl -u ems-water-level-gsm.service`, that `WATER_GSM_ENABLED=true`, and the `gsm_messages` table for rejected SMS. See [`firmware/README.md`](firmware/README.md#troubleshooting) for GSM and hardware issues.
- Camera live view says "Unable to connect": the message now gives the reason. If the stream server is not running or `/cctv-stream/` is not routed to it, run `sudo ./install_mediamtx.sh` (`update.sh` also runs it). Check it with `sudo systemctl status ems-mediamtx` and `sudo journalctl -u ems-mediamtx -n 50`. CityWatch and the Live View page use the same stream.
- PTZ buttons report an error: the camera must be set as PTZ in CCTV inventory and have an ONVIF profile (use Refresh on the camera).

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
