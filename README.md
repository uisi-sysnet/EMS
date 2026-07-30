# IoT Environmental Monitoring Gateway (EMS)

A Raspberry Pi 4B / Raspberry Pi OS Lite gateway that ingests **air quality**
(HJ212 over TCP + Modbus lead sensor) and **seismic** (MQTT, with SMS as a
fallback channel) station telemetry, stores it in PostgreSQL/TimescaleDB, and
serves it over a REST API.

```
Air quality stations ──TCP(HJ212)──┐
Lead analyzer (Modbus TCP) ────────┼──► air_quality_ingest.py ──► IOT_aq_sensor_data (TimescaleDB)
                                    │
Seismic stations ──MQTT────────────┤
Seismic stations ──SMS (SIM800L)───┼──► seismic_mqtt.py ────────► IOT_seismic_sensor_data (TimescaleDB)
                                    │                        └──► IOT_sms_telemetry (raw SMS log)
                                    │
                                    └──► api_server.py ──────────► REST API (reads from the DBs above)

All three services log to ──────────► IOT_service_logs (shared)
```

---

## 1. Repository layout

| File | Role |
|---|---|
| `install.sh` | **Start here.** Interactive setup wizard — network mode, static IP, DB/MQTT location + credentials, SMS on/off — then hands off to `deploy.sh`. (Its internal header comment still calls itself `configure.sh`; the file itself is `install.sh`.) |
| `deploy.sh` | Installs everything: Python, PostgreSQL + TimescaleDB, Mosquitto, ntpsec, Python dependencies. Idempotent — safe to re-run directly on its own too. |
| `install_services.sh` | Renders the `*.template` files into real systemd units, installs them, enables them on boot, and starts them. |
| `uninstall_services.sh` | Stops, disables, and removes the systemd units installed above (does **not** touch data, `.env`, or installed packages). |
| `update.sh` | Pulls the latest code from git and restarts the systemd services — see [§6](#6-updatesh--pulling-updates). |
| `check_requirements.sh` | Post-install health check: Python packages, PostgreSQL, MQTT, and NTP — with a live credential test against `.env` and remediation steps on failure. |
| `.env` / `_env` | Shared runtime config for all three Python services (`_env` is the template; `.env` is your actual, git-ignored copy). |
| `ems-air-quality_service.template`, `ems-seismic_service.template`, `ems-api_service.template` | systemd unit templates (`__EMS_DIR__` / `__EMS_USER__` placeholders filled in by `install_services.sh`). |
| `ems.target` | Groups the three services so they can be started/stopped/restarted together. |
| `air_quality_ingest.py` | Air quality ingestion service — see [§7](#7-air_quality_ingestpy--air-quality-service). |
| `seismic_mqtt.py` | Seismic ingestion service (MQTT + SMS) — see [§9](#9-seismic_mqttpy--seismic-service). |
| `sim800l.py` | Minimal AT-command driver for the SIM800L GSM module, used by `seismic_mqtt.py`. |
| `api_server.py` *(referenced, not covered here)* | REST API server — built on **FastAPI + uvicorn** (per `requirements.txt`). Configured via `API_PORT`/`API_KEYS` in `.env`; see the file itself for its endpoints. |
| `db_logging.py` *(referenced)* | Shared helper that mirrors each service's log records into the `service_logs` table. Required by all three services when `DB_LOG_ENABLED=true` (the default). |
| `import_stations.py` | CLI tool to (re-)load `stations.json`-format files into the `stations` table — see [§8](#8-stationsjson--import_stationspy). |
| `stations.json` | Static registry of air-quality stations, keyed by station MN — see [§8](#8-stationsjson--import_stationspy). |
| `requirements.txt` | Python dependencies installed by `deploy.sh` and checked by `check_requirements.sh` — see [§13](#13-python-dependencies-requirementstxt). |

---

## 2. Quick start

Everything must live in **one folder** on the Pi.

### Clone the repository

For a new Raspberry Pi or a fresh installation, clone the **`version3`** branch:

```bash
git clone -b version3 https://github.com/uisi-sysnet/EMS.git
cd EMS
```

Verify that you are on the correct branch:

```bash
git branch --show-current
```

Expected output:

```text
version3
```

You can also check the configured remote:

```bash
git remote -v
```

### First-time installation

After cloning the repository:

```bash
# 1. Run the setup wizard
sudo ./install.sh          # wizard: network, static IP, DB/MQTT, SMS
                            #  -> writes .env, then automatically runs deploy.sh

# 2. Register the always-on systemd services
sudo ./install_services.sh # starts + enables ems.target on boot

# 3. Confirm everything is actually working
./check_requirements.sh
```

### Update an existing installation

The recommended way to update an installed EMS gateway is:

```bash
cd /path/to/EMS
sudo ./update.sh
```

`update.sh` pulls the latest code from git and restarts the EMS systemd services.

Before updating, it is a good idea to check whether you have local code changes:

```bash
git status
```

If the working tree is clean, you can update normally:

```bash
sudo ./update.sh
```

If you have local changes that you want to keep, commit or stash them before updating. For example:

```bash
git stash
sudo ./update.sh
git stash pop
```

### Manual Git update

If you need to update the repository manually instead of using `update.sh`:

```bash
cd /path/to/EMS

git fetch origin
git checkout version3
git pull --ff-only origin version3
```

Then restart the EMS services:

```bash
sudo systemctl restart ems.target
```

Check the current branch and latest commit:

```bash
git branch --show-current
git log -1 --oneline
```

> **Important:** `.env` is your local runtime configuration and is git-ignored, so updating the repository does not replace your database credentials, MQTT credentials, or other local environment settings.

To stop and remove the systemd services (without touching data):

```bash
sudo ./uninstall_services.sh
```

### Running the wizard vs. running `deploy.sh` directly
- `sudo ./install.sh` — first-time setup, or whenever you want to change
  network mode, static IP, or credentials. Ends by calling `deploy.sh`.
- `sudo ./deploy.sh` — re-run any time on its own (e.g. after editing
  `requirements.txt`) to reinstall/update packages without touching network
  or `.env` config.

---

## 3. `install.sh` — setup wizard

Run once with `sudo ./install.sh`. Creates `.env` from `_env` if it doesn't
exist yet, then walks through:

1. **Network mode**
   - *Standalone* — turns `wlan0` into a WiFi Access Point (via
     NetworkManager's `nmcli`, `ipv4.method shared`) so you can connect
     directly to the Pi's own WiFi and SSH in; also enables the `ssh`
     service. Asks for an AP SSID and password (auto-generates one if left
     blank).
   - *Stay as-is* — no WiFi/AP changes.
2. **Wired (`eth0`) static IP** — always asked. Defaults: `192.168.1.10/24`,
   gateway/DNS `192.168.1.1`. Warns before applying, since changing `eth0`'s
   IP will drop an active SSH session over that interface.
3. **Database + MQTT location** — local (`127.0.0.1` / `localhost`, default)
   or a remote server address, written to `SYSTEM_DB_HOST` /
   `MQTT_BROKER_HOST`.
4. **Database + MQTT credentials** — prompts default to whatever is already
   in `.env`; press Enter to keep them.
5. **SMS ingestion** — enable/disable (default: disabled), written to
   `SMS_INGESTION_ENABLED`.

Assumes **Raspberry Pi OS Bookworm+** (NetworkManager-based networking). On
an older `dhcpcd`-based image, the AP/static-IP steps won't apply — configure
those manually instead.

---

## 4. `deploy.sh` — package installer

Installs, in order:

1. **Base tools** — `python3`, `pip3`, `build-essential`, `libpq-dev`, etc.
2. **PostgreSQL 16** (override with `PG_VERSION=15 sudo -E ./deploy.sh`) +
   **TimescaleDB**, tuned for the Pi's available RAM via `timescaledb-tune`.
3. **Mosquitto** MQTT broker, with password-file authentication configured
   for `MQTT_USER`/`MQTT_PASSWORD` from `.env` (anonymous access disabled).
4. **ntpsec**, opened up so other hosts on any network can query this Pi for
   time too (not just sync locally).
5. A **PostgreSQL role** (`SYSTEM_DB_USER`) with `CREATEDB`, shared by both
   the air-quality and seismic databases (each service creates its own
   database/tables on first run).
6. **UART enablement for the SIM800L**, if `raspi-config` is present — frees
   the primary UART (GPIO14/15) from the serial console, and disables
   onboard Bluetooth's claim on it (needed for stable baud timing).
7. **Python dependencies** from `requirements.txt`, installed system-wide
   (no venv), using `--break-system-packages --ignore-installed`.

Auto-detects Ubuntu vs. Raspberry Pi OS and 32-bit vs. 64-bit, warning if
TimescaleDB (arm64/amd64 only) won't be available on a 32-bit image.
Idempotent — safe to re-run.

At the end it prints next steps for importing `stations.json` and either
running the three services directly or via `install_services.sh`.

---

## 5. `install_services.sh` / `uninstall_services.sh` — systemd management

**`install_services.sh`** (`sudo ./install_services.sh`):
- Auto-detects the project directory and the service-running user (prefers
  whoever invoked `sudo`, so services aren't accidentally owned by `root`).
- Renders `ems-air-quality_service.template`, `ems-seismic_service.template`,
  and `ems-api_service.template` into real unit files (substituting
  `__EMS_DIR__`/`__EMS_USER__`), copies `ems.target`, reloads systemd,
  enables everything on boot, and starts `ems.target` (which starts all
  three services, since they each have `PartOf=ems.target`).
- Warns (doesn't fail) if `air_quality_ingest.py`, `seismic_mqtt.py`,
  `api_server.py`, `db_logging.py`, or `sim800l.py` are missing from the
  project folder.

Each service (`Restart=on-failure`, `RestartSec=5`) logs to the systemd
journal:

```bash
sudo systemctl status ems.target                # overview of all three
sudo systemctl restart ems.target                # restart everything together
sudo journalctl -u ems-air-quality.service -f    # live logs, one service
sudo journalctl -u ems-seismic.service -f
sudo journalctl -u ems-api.service -f
```

**`uninstall_services.sh`** (`sudo ./uninstall_services.sh [-y]`):
Stops, disables, and deletes the four unit files above, then
`systemctl daemon-reload`. Asks for confirmation unless run with `-y`.
Leaves the Python code, `.env`, database contents, and Mosquitto config
untouched — it only undoes what `install_services.sh` did.

---

## 7. `check_requirements.sh` — health check

Run any time after `deploy.sh` (no `sudo` needed unless your user can't read
`.env`):

```bash
./check_requirements.sh
```

Checks, in order, printing a `->` remediation line under anything that fails
rather than just a pass/fail line:

1. **Python packages** — every package in `requirements.txt` is actually
   importable (`importlib.metadata`), by name (not just "pip said so").
2. **PostgreSQL** — `psql` installed, `postgresql` service active, and a
   **live login** using `SYSTEM_DB_HOST/PORT/USER/PASSWORD` from `.env`.
3. **MQTT (Mosquitto)** — `mosquitto_pub` installed, `mosquitto` service
   active, and a **live publish** to a throwaway topic using
   `MQTT_BROKER_HOST/PORT/USER/PASSWORD` from `.env`.
4. **NTP** — client (`ntpq`/`chronyc`) installed, a time-sync service
   active, whether the clock is actually reported synced, and the current
   **date, time, and timezone**, plus `ntpq -p` peer status.

Exit codes: `0` all passed, `1` something failed/missing, `2` a hard
precondition (`python3` or `requirements.txt` itself) is absent.

---

## 8. `air_quality_ingest.py` — air quality service

Two independent data paths feeding the same `sensor_data` hypertable in the
`IOT_aq_sensor_data` database:

- **HJ212 over TCP** (`AQ_SERVER_HOST:AQ_SERVER_PORT`, default `0.0.0.0:1935`)
  — a raw TCP server that frames/CRC-checks/parses HJ212 `##LLLL...####`
  packets from air-quality stations (`CN=2011` data frames are ACKed with
  `CN=9014`), extracts pollutant/weather readings via the `CP=&&...&&` block,
  and queues them for a background batch-insert worker (flushes on
  `AQ_BATCH_MAX_SIZE` readings or `AQ_BATCH_MAX_INTERVAL_SEC` seconds,
  whichever comes first — one multi-row `INSERT` + one commit per flush
  instead of one per reading).
- **Modbus TCP** — polls each station's registered lead analyzer
  (`lead_ip`/`lead_port`/`lead_slave` in the `stations` table) every
  `AQ_LEAD_POLL_INTERVAL` seconds and stitches the lead + lead-temperature
  values onto that station's most recent `sensor_data` row (only if it's
  younger than `AQ_LEAD_MAX_ROW_AGE_SEC`, so a stale row never gets a
  reading tagged with the wrong timestamp).

Other behavior:
- **Station registry** lives in the `stations` table (`enabled`, location,
  Modbus lead config), auto-migrated from `stations.json` on first run, and
  reloaded from the DB every `AQ_STATIONS_REFRESH_INTERVAL_SEC` seconds — so
  edits via `import_stations.py` or direct SQL apply without a restart.
- **Optional active clock correction** — if `AQ_TIME_SYNC_ENABLED=true`,
  sends an HJ212 `CN=1012` command telling a station to set its clock to
  this server's (NTP-synced) time, with a per-station cooldown
  (`AQ_TIME_SYNC_COOLDOWN_MIN`). Off by default since not all vendor
  firmware implements `CN=1012` per spec.
- Refuses to start (`_validate_config`) if `SYSTEM_DB_PASSWORD` isn't loaded
  from `.env`, with a specific message pointing at likely causes.

---

## 9. `stations.json` & `import_stations.py`

`stations.json` is the air-quality station registry, keyed by station MN
(the HJ212 `MN` field), one entry per station:

```json
{
  "4101025U122041": {
    "station_name": "AQM001",
    "enabled": true,
    "latitude": 14.5995,
    "longitude": 120.9842,
    "lead_ip": "192.168.55.11",
    "lead_port": 8899,
    "lead_slave": 1
  }
}
```

All seven fields (`station_name`, `enabled`, `latitude`, `longitude`,
`lead_ip`, `lead_port`, `lead_slave`) are required per entry — an entry
missing any of them is skipped (printed, not fatal) rather than imported
with nulls. `lead_ip`/`lead_port`/`lead_slave` are the Modbus TCP address of
that station's lead analyzer (see [§7](#7-air_quality_ingestpy--air-quality-service)).

You normally don't need to run `import_stations.py` yourself:
`air_quality_ingest.py` auto-imports `stations.json` on its very first run,
but **only** if the `stations` table is still empty — that auto-import
never fires again after that. Use `import_stations.py` directly when you
need to:

```bash
python3 import_stations.py                    # imports ./stations.json
python3 import_stations.py /path/to/file.json  # imports a specific file
python3 import_stations.py --dry-run           # preview only, no writes
```

- Upserts by station MN (`ON CONFLICT ... DO UPDATE`) — safe to re-run after
  editing the file, existing stations are updated in place rather than
  duplicated.
- Self-contained: creates the `stations` table itself if run before
  `air_quality_ingest.py` has ever started (schema kept in sync with
  `create_tables()` there).
- Reads the same `SYSTEM_DB_*` / `AQ_DB_NAME` variables from `.env` as
  `air_quality_ingest.py`.
- Changes are picked up by the running service within
  `AQ_STATIONS_REFRESH_INTERVAL_SEC` (default 300s), or immediately after a
  restart.

---

## 10. `seismic_mqtt.py` — seismic service

Two independent ingestion channels feeding the same `station_metrics`
hypertable in `IOT_seismic_sensor_data` (each row tagged `source='mqtt'` or
`source='sms'`):

- **MQTT** — subscribes to `MQTT_TOPIC` (default
  `seismic/stations/+/telemetry`) on the broker at
  `MQTT_BROKER_HOST:MQTT_BROKER_PORT`. Expects a JSON payload with
  `station_id`, `timestamp`, `location{}`, and
  `measurements{acceleration,velocity,displacement}` (`x`/`y`/`z` each),
  plus `pga` and `peis`. If `station_id` is missing it's inferred from the
  topic (`seismic/stations/<id>/telemetry`).
- **SMS (SIM800L)**, enabled via `SMS_INGESTION_ENABLED` — a second,
  MQTT-independent channel for stations with cellular but no data/WiFi
  coverage, or as a fallback when MQTT is down. Uses a custom compact
  format, **`SEISMSG1`**, designed to fit in one 160-char GSM segment:

  ```
  SEISMSG1,<station_id>,<epoch_ts>,<lat>,<lon>,<elev_m>,<acc_x>,<acc_y>,<acc_z>,<vel_x>,<vel_y>,<vel_z>,<disp_x>,<disp_y>,<disp_z>,<pga>,<peis>,<checksum>
  ```
  - `lat`/`lon`/`elev_m` are optional (leave blank).
  - `checksum` (2-digit hex, sum of ASCII codes mod 256) is optional but
    recommended — catches messages corrupted by a weak GSM signal.
  - Every SMS received is stored in `IOT_sms_telemetry` regardless of
    whether it parses (with `parsed_ok`/`parse_error`), so nothing is
    silently dropped.
  - A connectivity test is built in: texting the module's SIM number the
    word `PING` (configurable, `SMS_TEST_COMMAND`) gets an immediate `OK`
    reply (`SMS_TEST_REPLY`) — confirms the modem/SIM/signal chain without
    needing a full telemetry payload.
  - `SMS_ALLOWED_SENDERS` (comma-separated, E.164 numbers) optionally
    restricts which senders are processed; blank accepts any sender.

See `sim800l.py`'s module docstring for the physical wiring (GPIO14/15 →
SIM800L RXD/TXD, separate regulated ~4V power supply — **do not** power it
from the Pi's 3V3/5V rail, it can pull ~2A in bursts) and for testing the
module with a serial terminal before wiring it into the service.

---


## 11. Seismic SMS protocol (`SEISMSG1`)

The EMS seismic ingestion service supports a compact SMS telemetry format named **`SEISMSG1`**. It is designed for seismic stations that can transmit telemetry through a GSM/SIM800L modem when MQTT or IP connectivity is unavailable.

The format is intentionally compact so a complete telemetry record can fit within a standard GSM SMS message.

### Message format

```text
SEISMSG1,<station_id>,<epoch_ts>,<lat>,<lon>,<elev_m>,<acc_x>,<acc_y>,<acc_z>,<vel_x>,<vel_y>,<vel_z>,<disp_x>,<disp_y>,<disp_z>,<pga>,<peis>,<checksum>
```

### Field definitions

| # | Field | Description |
|---:|---|---|
| 1 | `SEISMSG1` | Literal protocol identifier. An SMS that does not begin with this tag is archived but ignored by the seismic telemetry parser. This allows normal carrier, administrative, or maintenance SMS messages to coexist safely with telemetry. |
| 2 | `station_id` | Station identifier. This should match the station ID used by the MQTT telemetry channel. |
| 3 | `epoch_ts` | Unix timestamp in UTC, in seconds, generated by the station. |
| 4 | `lat` | Latitude in decimal degrees. Optional. Leave blank when unavailable. |
| 5 | `lon` | Longitude in decimal degrees. Optional. Leave blank when unavailable. |
| 6 | `elev_m` | Elevation above sea level in meters. Optional. Leave blank when unavailable. |
| 7 | `acc_x` | X-axis acceleration. |
| 8 | `acc_y` | Y-axis acceleration. |
| 9 | `acc_z` | Z-axis acceleration. |
| 10 | `vel_x` | X-axis velocity. |
| 11 | `vel_y` | Y-axis velocity. |
| 12 | `vel_z` | Z-axis velocity. |
| 13 | `disp_x` | X-axis displacement. |
| 14 | `disp_y` | Y-axis displacement. |
| 15 | `disp_z` | Z-axis displacement. |
| 16 | `pga` | Peak Ground Acceleration (PGA). |
| 17 | `peis` | Earthquake intensity code, represented as an integer. |
| 18 | `checksum` | Optional but recommended. A two-digit uppercase hexadecimal checksum calculated from the ASCII values of every character before the checksum field, including the comma immediately preceding the checksum, modulo 256. This helps detect truncated or corrupted SMS messages caused by poor GSM signal conditions. |

### Example: telemetry with location and checksum

```text
SEISMSG1,STN-004,1721818530,14.5995,120.9842,15.2,0.012,-0.008,0.021,0.5,0.3,0.6,1.2,0.9,1.5,0.045,2,3F
```

### Example: telemetry without location or checksum

When location information is unavailable, leave the corresponding fields empty by using consecutive commas:

```text
SEISMSG1,STN-004,1721818530,,,,0.012,-0.008,0.021,0.5,0.3,0.6,1.2,0.9,1.5,0.045,2
```

The checksum field may also be omitted.

### Checksum calculation

The checksum is optional, but it is recommended for deployments where SMS delivery may be affected by weak or unstable GSM signals.

The checksum is calculated as follows:

1. Take the complete message content up to and including the comma immediately before the checksum.
2. Convert each character to its ASCII value.
3. Add all ASCII values together.
4. Take the result modulo `256`.
5. Format the result as a **two-digit uppercase hexadecimal** value.

The checksum covers everything before the checksum value, including the comma immediately before it.

### Processing behavior

- SMS messages beginning with **`SEISMSG1`** are passed to the seismic telemetry parser.
- Successfully parsed telemetry is stored in the `station_metrics` table with `source = 'sms'`.
- Messages that begin with `SEISMSG1` but fail validation or parsing are still archived in the `sms_messages` table together with their parsing status and error information for troubleshooting.
- Messages that do **not** begin with `SEISMSG1` are archived in `sms_messages` but ignored by the seismic telemetry parser.
- This allows the SIM800L to receive both seismic telemetry and ordinary SMS messages without allowing non-telemetry messages to interfere with the seismic ingestion service.

### Recommended station-side behavior

For reliable operation:

- Use the same `station_id` in both MQTT and SMS telemetry.
- Generate `epoch_ts` in UTC.
- Include latitude, longitude, and elevation whenever available.
- Include the checksum whenever practical, especially in areas with weak GSM coverage.
- Keep numeric formatting compact to minimize SMS length.
- Send one telemetry record per SMS unless the station implementation explicitly supports a larger batching format.

---
## 12. Key environment variables (`.env`)

| Variable | Default | Used by |
|---|---|---|
| `SYSTEM_DB_HOST` / `SYSTEM_DB_PORT` | `127.0.0.1` / `5432` | all three services |
| `SYSTEM_DB_USER` / `SYSTEM_DB_PASSWORD` | — | all three services |
| `AQ_DB_NAME` / `SEISMIC_DB_NAME` / `SMS_DB_NAME` / `LOG_DB_NAME` | `IOT_aq_sensor_data` / `IOT_seismic_sensor_data` / `IOT_sms_telemetry` / `IOT_service_logs` | air quality / seismic / seismic (SMS) / all three |
| `AQ_SERVER_HOST` / `AQ_SERVER_PORT` | `0.0.0.0` / `1935` | air quality (HJ212 TCP) |
| `AQ_LEAD_POLL_INTERVAL` | `30` | air quality (Modbus lead) |
| `MQTT_BROKER_HOST` / `MQTT_BROKER_PORT` | `localhost` / `1883` | seismic (MQTT), Mosquitto auth |
| `MQTT_USER` / `MQTT_PASSWORD` | — | seismic (MQTT), Mosquitto auth |
| `SMS_INGESTION_ENABLED` | `false` | seismic (SMS) |
| `SIM800_SERIAL_PORT` / `SIM800_BAUDRATE` | `/dev/serial0` / `9600` | `sim800l.py` |
| `SMS_ALLOWED_SENDERS` | *(blank = any)* | seismic (SMS) |
| `DB_LOG_ENABLED` | `true` | all three (via `db_logging.py`) |
| `API_PORT` / `API_KEYS` | `8000` / — | `api_server.py` |

Run `sudo ./install.sh` to set the connection/credential fields
interactively rather than hand-editing `.env`.

---

## 13. Python dependencies (`requirements.txt`)

Installed system-wide (no venv) by `deploy.sh`, verified by
`check_requirements.sh`:

| Package | Used by |
|---|---|
| `python-dotenv>=1.0.0` | shared — loads `.env` in all four scripts |
| `psycopg2-binary>=2.9.9` | `air_quality_ingest.py`, `import_stations.py` |
| `pymodbus>=3.6.0` | `air_quality_ingest.py` (Modbus lead sensor) |
| `paho-mqtt>=2.1.0` | `seismic_mqtt.py` (MQTT client) |
| `psycopg>=3.1.0` | `seismic_mqtt.py` (note: psycopg **3**, a different DB driver than air quality's psycopg2) |
| `pyserial>=3.5` | `sim800l.py` (SIM800L AT-command SMS channel) |
| `fastapi>=0.110.0` / `uvicorn>=0.29.0` | `api_server.py` |

On Raspberry Pi OS, pip is normally already pointed at
[piwheels.org](https://www.piwheels.org/) (prebuilt ARM wheels) via
`/etc/pip.conf`, so these install quickly without compiling; if a package
has no piwheels build yet, `deploy.sh`'s `build-essential`/`libpq-dev`/
`python3-dev` install lets pip fall back to compiling from source instead
(slower, but works).

---

## 14. Typical workflow after a config change

| Change | What to run |
|---|---|
| Network mode, static IP, DB/MQTT location, credentials, SMS on/off | `sudo ./install.sh` |
| `requirements.txt` updated | `sudo ./deploy.sh` |
| `.env` edited by hand | `sudo systemctl restart ems.target` |
| `stations.json` edited | `python3 import_stations.py` (add `--dry-run` to preview first) |
| Verify everything is healthy | `./check_requirements.sh` |
| Stop running as always-on services | `sudo ./uninstall_services.sh` |