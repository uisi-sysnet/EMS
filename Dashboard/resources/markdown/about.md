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